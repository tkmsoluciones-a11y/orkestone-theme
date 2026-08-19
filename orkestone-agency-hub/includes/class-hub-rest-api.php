<?php
/**
 * Hub REST API — public endpoints for token-based configuration delivery.
 *
 * Exposes the following endpoints under the `orke-hub/v1` namespace:
 *
 * - GET  /orke-hub/v1/config/{token}      — Returns vertical JSON for valid tokens
 * - POST /orke-hub/v1/validate-token       — Returns validity without exposing JSON
 * - POST /orke-hub/v1/webhook/stripe       — Receives Stripe webhook events
 * - POST /orke-hub/v1/webhook/paypal       — Receives PayPal IPN messages
 *
 * All config/validate endpoints are unauthenticated (token-based security).
 * Webhook endpoints use signature verification for security.
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the Hub's REST API routes.
 *
 * Security measures implemented (REQ-AH18):
 * - Token expiry validation (24h TTL via post meta)
 * - Rate limiting via transient (10 req/min per IP)
 * - Origin validation against _orke_token_allowed_origin
 * - Revocation checking
 */
class Orkestone_Hub_REST_API {

	/**
	 * REST API namespace.
	 */
	const API_NAMESPACE = 'orke-hub/v1';

	/**
	 * Rate limit window in seconds (1 minute).
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Maximum requests per rate limit window.
	 */
	const RATE_LIMIT_MAX = 10;

	/**
	 * Register REST API routes.
	 */
	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all Hub REST API routes.
	 */
	public static function register_routes(): void {
		// GET /orke-hub/v1/config/{token} — returns vertical JSON (REQ-AH16).
		register_rest_route(
			self::API_NAMESPACE,
			'/config/(?P<token>[a-f0-9\-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_config' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return self::is_valid_uuid( $param );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// POST /orke-hub/v1/validate-token — validity check (REQ-AH17).
		register_rest_route(
			self::API_NAMESPACE,
			'/validate-token',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'validate_token_endpoint' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// POST /orke-hub/v1/webhook/stripe — Stripe webhook receiver.
		register_rest_route(
			self::API_NAMESPACE,
			'/webhook/stripe',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// POST /orke-hub/v1/webhook/paypal — PayPal IPN receiver.
		register_rest_route(
			self::API_NAMESPACE,
			'/webhook/paypal',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_paypal_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// POST /orkestone-agency/v1/receive-briefing — receive briefing data from Theme CC.
		register_rest_route(
			'orkestone-agency/v1',
			'/receive-briefing',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'receive_briefing' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'briefing' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * GET /orke-hub/v1/config/{token}
	 *
	 * Returns the full vertical JSON configuration for a valid, non-revoked,
	 * non-expired token. Performs rate limiting and origin validation.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_config( WP_REST_Request $request ) {
		// Rate limiting check (REQ-AH18).
		$rate_check = self::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$token  = $request->get_param( 'token' );
		$origin = $request->get_header( 'origin' );

		// Validate UUID format (REQ-AH16).
		if ( ! self::is_valid_uuid( $token ) ) {
			return new WP_Error(
				'orke_invalid_token_format',
				__( 'Invalid token format. Expected UUID v4.', 'orkestone-agency-hub' ),
				array( 'status' => 400 )
			);
		}

		// Origin validation.
		if ( ! Orkestone_Delivery::validate_origin( $token, $origin ) ) {
			return new WP_Error(
				'orke_origin_mismatch',
				__( 'Token origin does not match the configured client URL.', 'orkestone-agency-hub' ),
				array( 'status' => 403 )
			);
		}

		// Validate the token (checks expiry, revocation).
		$post_id = Orkestone_Delivery::validate_token( $token );
		if ( ! $post_id ) {
			// Return 404 — do not reveal whether token is invalid, revoked, or expired (REQ-AH16).
			return new WP_Error(
				'orke_token_not_found',
				__( 'Token not found or revoked.', 'orkestone-agency-hub' ),
				array( 'status' => 404 )
			);
		}

		// Get the configuration JSON.
		$config = Orkestone_Delivery::get_config_for_token( $token );
		if ( null === $config ) {
			return new WP_Error(
				'orke_config_not_found',
				__( 'Configuration not found for this token.', 'orkestone-agency-hub' ),
				array( 'status' => 404 )
			);
		}

		/**
		 * Filter the config response before sending to the client.
		 *
		 * @since 1.0.0
		 *
		 * @param array $config  The vertical configuration array.
		 * @param string $token  The activation token.
		 */
		$config = apply_filters( 'orke_hub_config_response', $config, $token );

		return new WP_REST_Response(
			array(
				'success' => true,
				'config'  => $config,
			),
			200
		);
	}

	/**
	 * POST /orke-hub/v1/validate-token
	 *
	 * Returns whether a token is valid and the associated vertical key,
	 * WITHOUT exposing the configuration JSON (REQ-AH17).
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response
	 */
	public static function validate_token_endpoint( WP_REST_Request $request ) {
		// Rate limiting check.
		$rate_check = self::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$token = $request->get_param( 'token' );

		$post_id = Orkestone_Delivery::validate_token( $token );

		if ( ! $post_id ) {
			return new WP_REST_Response(
				array(
					'valid' => false,
				),
				200
			);
		}

		$vertical_key = get_post_meta( $post_id, '_orke_vertical_key', true );

		return new WP_REST_Response(
			array(
				'valid'       => true,
				'verticalKey' => $vertical_key ?: '',
			),
			200
		);
	}

	/**
	 * POST /orke-hub/v1/webhook/stripe
	 *
	 * Receives Stripe webhook events. Passes the payload and signature
	 * to Orkestone_Payment_Gateway::handle_webhook() for processing.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_stripe_webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );

		if ( empty( $signature ) ) {
			return new WP_Error(
				'orke_missing_signature',
				__( 'Missing Stripe signature.', 'orkestone-agency-hub' ),
				array( 'status' => 401 )
			);
		}

		$result = Orkestone_Payment_Gateway::handle_webhook( $payload, $signature );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data()['status'] ?? 500;
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /orke-hub/v1/webhook/paypal
	 *
	 * Receives PayPal IPN messages. Passes the POST data to
	 * Orkestone_Payment_Gateway::handle_paypal_ipn() for processing.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_paypal_webhook( WP_REST_Request $request ) {
		$ipn_data = $request->get_params();

		$result = Orkestone_Payment_Gateway::handle_paypal_ipn( $ipn_data );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data()['status'] ?? 500;
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => $status )
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Check rate limit for the current IP.
	 *
	 * Uses a transient keyed by client IP to enforce 10 requests per minute
	 * (REQ-AH18). Returns WP_Error with 429 status if rate limit exceeded.
	 *
	 * @return true|WP_Error True if under limit, WP_Error if exceeded.
	 */
	private static function check_rate_limit() {
		$ip = self::get_client_ip();

		if ( empty( $ip ) ) {
			return true; // Can't rate-limit without an IP.
		}

		$transient_key = 'orke_rate_limit_' . md5( $ip );
		$data          = get_transient( $transient_key );

		if ( false === $data ) {
			// First request in the window.
			set_transient( $transient_key, 1, self::RATE_LIMIT_WINDOW );
			return true;
		}

		$count = intval( $data );
		if ( $count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error(
				'orke_rate_limit_exceeded',
				__( 'Too many requests. Please try again in a minute.', 'orkestone-agency-hub' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $transient_key, $count + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Get the client IP address from request headers.
	 *
	 * Respects proxy headers for sites behind Cloudflare or similar.
	 *
	 * @return string Client IP or empty string.
	 */
	private static function get_client_ip(): string {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// If multiple IPs (X-Forwarded-For), take the first.
				$ips = explode( ',', $ip );
				return trim( $ips[0] );
			}
		}

		return '';
	}

	/**
	 * Validate that a string is a UUID v4.
	 *
	 * UUID v4 format: 8-4-4-4-12 hex digits with dashes.
	 *
	 * @param string $uuid The string to validate.
	 * @return bool True if valid UUID v4.
	 */
	private static function is_valid_uuid( string $uuid ): bool {
		if ( empty( $uuid ) ) {
			return false;
		}

		// Match UUID v4 pattern: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx.
		return (bool) preg_match(
			'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',
			$uuid
		);
	}

	/**
	 * POST /orkestone-agency/v1/receive-briefing
	 *
	 * Receives client briefing data from the Theme's Command Center,
	 * creates an orke_configuration draft post, and returns the config ID.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function receive_briefing( WP_REST_Request $request ) {
		$briefing = $request->get_param( 'briefing' );

		if ( empty( $briefing ) || ! is_array( $briefing ) ) {
			return new WP_Error(
				'orke_missing_briefing_data',
				__( 'No briefing data received.', 'orkestone-agency-hub' ),
				array( 'status' => 400 )
			);
		}

		// Extract the site name for the post title.
		$site_name = isset( $briefing['siteName'] ) ? sanitize_text_field( $briefing['siteName'] ) : '';
		if ( empty( $site_name ) ) {
			$site_name = __( 'Client Briefing', 'orkestone-agency-hub' );
		}

		// Build a vertical-like config from the briefing data.
		$config = array(
			'schemaVersion' => '1.0.0',
			'verticalKey'   => 'briefing-' . sanitize_title( $site_name ),
			'name'          => $site_name,
			'brand'         => array(
				'siteName'  => $site_name,
				'tagline'   => isset( $briefing['tagline'] ) ? sanitize_text_field( $briefing['tagline'] ) : '',
				'colors'    => array(
					'primary'   => isset( $briefing['primaryColor'] ) ? sanitize_hex_color( $briefing['primaryColor'] ) : '#1a365d',
					'secondary' => isset( $briefing['secondaryColor'] ) ? sanitize_hex_color( $briefing['secondaryColor'] ) : '#e2e8f0',
					'accent'    => isset( $briefing['accentColor'] ) ? sanitize_hex_color( $briefing['accentColor'] ) : '#3b82f6',
				),
				'fonts'     => array(
					'heading' => isset( $briefing['headingFont'] ) ? sanitize_text_field( $briefing['headingFont'] ) : 'Inter',
					'body'    => isset( $briefing['bodyFont'] ) ? sanitize_text_field( $briefing['bodyFont'] ) : 'Inter',
				),
			),
			'pages'         => array(),
			'contentModels' => array(),
			'seoDefaults'   => array(),
		);

		// Map pages from briefing.
		if ( isset( $briefing['pages'] ) && is_array( $briefing['pages'] ) ) {
			foreach ( $briefing['pages'] as $page_title ) {
				$page_title = sanitize_text_field( $page_title );
				if ( ! empty( $page_title ) ) {
					$config['pages'][] = array(
						'title'       => $page_title,
						'slug'        => sanitize_title( $page_title ),
						'sections'    => array( 'hero' ),
					);
				}
			}
		}

		// Create the orke_configuration post.
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'orke_configuration',
				'post_status'  => 'draft',
				'post_title'   => $site_name,
				'post_content' => wp_json_encode( $config, JSON_PRETTY_PRINT ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'orke_config_creation_failed',
				__( 'Failed to create configuration from briefing.', 'orkestone-agency-hub' ),
				array( 'status' => 500 )
			);
		}

		// Store briefing data as meta.
		update_post_meta( $post_id, '_orke_briefing_source', 'theme-cc' );
		update_post_meta( $post_id, '_orke_client_data', wp_json_encode( $briefing ) );
		update_post_meta( $post_id, '_orke_vertical_key', $config['verticalKey'] );
		update_post_meta( $post_id, '_orke_vertical_json_blob', wp_json_encode( $config ) );

		return new WP_REST_Response(
			array(
				'success'   => true,
				'id'        => $post_id,
				'config_id' => $post_id,
				'message'   => __( 'Briefing received and configuration created.', 'orkestone-agency-hub' ),
			),
			201
		);
	}
}
