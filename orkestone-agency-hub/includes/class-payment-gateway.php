<?php
/**
 * Payment Gateway — Stripe Checkout, PayPal IPN, and manual override.
 *
 * Handles payment processing for client configurations:
 * - Creates Stripe Checkout Sessions from budget line items
 * - Processes Stripe webhooks (checkout.session.completed)
 * - Provides a manual "Mark as Paid" override for offline payments
 * - Handles PayPal IPN webhook as an alternative payment method
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payment processing for the Agency Hub.
 *
 * Integrates with Stripe Checkout for card payments and PayPal IPN
 * as an alternative. All payment methods transition the orke_configuration
 * post from draft to published on successful payment.
 *
 * Stripe API is called via wp_remote_post() — no SDK dependency.
 * Stripe requires HTTPS on the Hub site (G7). A warning notice is shown
 * in the admin when `is_ssl()` returns false.
 */
class Orkestone_Payment_Gateway {

	/**
	 * Stripe API base URL.
	 */
	const STRIPE_API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Option key for the Stripe secret key.
	 */
	const STRIPE_SECRET_OPTION = 'orke_stripe_secret_key';

	/**
	 * Option key for the Stripe webhook secret.
	 */
	const STRIPE_WEBHOOK_SECRET_OPTION = 'orke_stripe_webhook_secret';

	/**
	 * Option key for the Stripe publishable key (for front-end use).
	 */
	const STRIPE_PUBLISHABLE_OPTION = 'orke_stripe_publishable_key';

	/**
	 * Register WordPress hooks for the payment gateway.
	 */
	public static function register_hooks(): void {
		// Admin notices for SSL requirement.
		add_action( 'admin_notices', array( __CLASS__, 'ssl_warning_notice' ) );

		// Stripe settings field (for configuring API keys).
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// "Mark as Paid" action on configuration edit screen.
		add_action( 'admin_post_orke_mark_as_paid', array( __CLASS__, 'handle_mark_as_paid' ) );

		// PayPal IPN endpoint is registered via REST API (class-hub-rest-api.php).
	}

	/**
	 * Show a warning notice if the site is not running on HTTPS.
	 *
	 * Stripe Checkout requires HTTPS (G7). This notice reminds the admin
	 * to configure SSL before accepting payments.
	 */
	public static function ssl_warning_notice(): void {
		if ( is_ssl() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'orke' ) === false ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Orkestone Agency Hub — SSL Required', 'orkestone-agency-hub' ); ?></strong>
				<?php esc_html_e( 'Stripe Checkout requires HTTPS. Your site is currently running on HTTP. Payments will not work until you configure an SSL certificate.', 'orkestone-agency-hub' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register plugin settings for Stripe API keys.
	 */
	public static function register_settings(): void {
		register_setting( 'orke_agency_hub_settings', self::STRIPE_SECRET_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'orke_agency_hub_settings', self::STRIPE_WEBHOOK_SECRET_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'orke_agency_hub_settings', self::STRIPE_PUBLISHABLE_OPTION, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
	}

	/**
	 * Get the Stripe secret key from options.
	 *
	 * @return string The Stripe secret key or empty string.
	 */
	private static function get_stripe_secret(): string {
		return get_option( self::STRIPE_SECRET_OPTION, '' );
	}

	/**
	 * Get the Stripe webhook signing secret from options.
	 *
	 * @return string The webhook secret or empty string.
	 */
	private static function get_webhook_secret(): string {
		return get_option( self::STRIPE_WEBHOOK_SECRET_OPTION, '' );
	}

	/**
	 * Create a Stripe Checkout Session for a configuration purchase.
	 *
	 * Maps the budget items to Stripe line items and creates a Checkout
	 * Session via the Stripe API. Returns the session ID and URL for
	 * redirecting the user to Stripe's hosted checkout page (REQ-AH11).
	 *
	 * @param int   $post_id The orke_configuration post ID.
	 * @param array $budget  The itemized budget from Orkestone_Pricing::calculate().
	 *
	 * @return array|WP_Error {
	 *     Session result.
	 *
	 *     @type string $session_id   Stripe Checkout Session ID.
	 *     @type string $url          Stripe hosted checkout URL.
	 * }
	 */
	public static function create_checkout_session( int $post_id, array $budget ) {
		$secret_key = self::get_stripe_secret();
		if ( empty( $secret_key ) ) {
			return new WP_Error(
				'orke_stripe_not_configured',
				__( 'Stripe is not configured. Set your Stripe secret key in Settings → Agency Hub.', 'orkestone-agency-hub' )
			);
		}

		if ( empty( $budget['items'] ) || ! is_array( $budget['items'] ) ) {
			return new WP_Error(
				'orke_invalid_budget',
				__( 'Invalid budget data. Cannot create checkout session.', 'orkestone-agency-hub' )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post || Orkestone_Configuration_CPT::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'orke_invalid_post',
				__( 'Configuration post not found.', 'orkestone-agency-hub' )
			);
		}

		$vertical_key = get_post_meta( $post_id, '_orke_vertical_key', true );

		// Build Stripe line items from budget components.
		$line_items = array();
		foreach ( $budget['items'] as $item ) {
			if ( empty( $item['subtotal'] ) || floatval( $item['subtotal'] ) <= 0 ) {
				continue;
			}

			$label      = isset( $item['label'] ) ? $item['label'] : __( 'Service', 'orkestone-agency-hub' );
			$qty        = max( 1, intval( $item['qty'] ) );
			$unit_price = floatval( $item['unit_price'] );
			$subtotal   = floatval( $item['subtotal'] );

			// Stripe expects amounts in cents.
			$unit_amount_cents = intval( round( $unit_price * 100 ) );

			$line_items[] = array(
				'price_data' => array(
					'currency'     => 'usd',
					'product_data' => array(
						'name' => $label,
					),
					'unit_amount'  => $unit_amount_cents,
				),
				'quantity'   => $qty,
			);
		}

		if ( empty( $line_items ) ) {
			return new WP_Error(
				'orke_no_chargeable_items',
				__( 'No chargeable items found in budget. Total must be greater than zero.', 'orkestone-agency-hub' )
			);
		}

		$total_cents = intval( round( floatval( $budget['total'] ) * 100 ) );

		// Build metadata for post identification on webhook.
		$metadata = array(
			'post_id'      => (string) $post_id,
			'vertical_key' => $vertical_key,
			'type'         => 'orke_configuration',
		);

		$session_data = array(
			'mode'              => 'payment',
			'success_url'       => admin_url( 'post.php?post=' . $post_id . '&action=edit&orke_payment=success' ),
			'cancel_url'        => admin_url( 'post.php?post=' . $post_id . '&action=edit&orke_payment=cancelled' ),
			'line_items'        => $line_items,
			'metadata'          => $metadata,
			'client_reference_id' => (string) $post_id,
		);

		$result = self::stripe_request( 'checkout/sessions', $session_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['id'] ) || empty( $result['url'] ) ) {
			return new WP_Error(
				'orke_stripe_invalid_response',
				__( 'Invalid response from Stripe API.', 'orkestone-agency-hub' )
			);
		}

		// Store the Stripe session ID on the post for tracking.
		update_post_meta( $post_id, '_orke_payment_id', $result['id'] );
		update_post_meta( $post_id, '_orke_payment_status', 'pending' );

		return array(
			'session_id' => $result['id'],
			'url'        => $result['url'],
		);
	}

	/**
	 * Handle Stripe webhook request.
	 *
	 * Verifies the Stripe signature, processes `checkout.session.completed`
	 * events, and transitions the corresponding orke_configuration post
	 * from draft to published (REQ-AH12).
	 *
	 * @param string $payload    The raw webhook request body.
	 * @param string $signature  The Stripe-Signature header value.
	 *
	 * @return array|WP_Error Response with status and message.
	 */
	public static function handle_webhook( string $payload, string $signature ) {
		$webhook_secret = self::get_webhook_secret();

		if ( empty( $webhook_secret ) ) {
			return new WP_Error(
				'orke_webhook_not_configured',
				__( 'Webhook secret is not configured.', 'orkestone-agency-hub' ),
				array( 'status' => 500 )
			);
		}

		// Verify Stripe signature (REQ-AH12).
		$verified = self::verify_stripe_signature( $payload, $signature, $webhook_secret );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$event = json_decode( $payload, true );
		if ( JSON_ERROR_NONE !== json_last_error() || empty( $event['type'] ) ) {
			return new WP_Error(
				'orke_invalid_webhook_payload',
				__( 'Invalid webhook payload.', 'orkestone-agency-hub' ),
				array( 'status' => 400 )
			);
		}

		// Log the event type for debugging.
		error_log( sprintf( 'Orkestone Agency Hub — Stripe webhook received: %s', $event['type'] ) );

		if ( 'checkout.session.completed' === $event['type'] ) {
			return self::process_checkout_completed( $event );
		}

		// Acknowledge other event types silently.
		return array(
			'success' => true,
			'message' => sprintf( 'Event type %s received but not processed.', $event['type'] ),
		);
	}

	/**
	 * Process a checkout.session.completed event.
	 *
	 * Finds the corresponding orke_configuration post by metadata,
	 * transitions it to published if still in draft, and stores payment
	 * meta. Idempotent — if the post is already published, it logs and
	 * returns success without re-processing (Scenario 3, step 10).
	 *
	 * @param array $event The Stripe event array.
	 * @return array|WP_Error Result with status and message.
	 */
	private static function process_checkout_completed( array $event ): array {
		$session = isset( $event['data']['object'] ) ? $event['data']['object'] : array();

		if ( empty( $session['metadata']['post_id'] ) ) {
			return new WP_Error(
				'orke_missing_post_id',
				__( 'Checkout session missing post_id metadata.', 'orkestone-agency-hub' ),
				array( 'status' => 400 )
			);
		}

		$post_id   = intval( $session['metadata']['post_id'] );
		$session_id = isset( $session['id'] ) ? sanitize_text_field( $session['id'] ) : '';

		$post = get_post( $post_id );
		if ( ! $post || Orkestone_Configuration_CPT::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'orke_post_not_found',
				__( 'Configuration post not found for this session.', 'orkestone-agency-hub' ),
				array( 'status' => 404 )
			);
		}

		// Idempotency check: if already published, log and return success (Scenario 3).
		if ( 'publish' === $post->post_status ) {
			$existing_status = get_post_meta( $post_id, '_orke_payment_status', true );
			error_log(
				sprintf(
					'Orkestone Agency Hub — Duplicate webhook for already-paid config %d (status: %s)',
					$post_id,
					$existing_status
				)
			);
			return array(
				'success' => true,
				'message' => 'Configuration already published. Duplicate webhook ignored.',
			);
		}

		// Transition to published.
		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return new WP_Error(
				'orke_publish_failed',
				__( 'Failed to publish configuration post.', 'orkestone-agency-hub' ),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $post_id, '_orke_payment_id', $session_id );
		update_post_meta( $post_id, '_orke_payment_status', 'completed' );

		/**
		 * Fires after a configuration has been paid and published via webhook.
		 * The Orkestone_Delivery class hooks into this for token generation.
		 *
		 * @param int $post_id The configuration post ID.
		 */
		do_action( 'orke_configuration_paid', $post_id );

		return array(
			'success' => true,
			'message' => sprintf( 'Configuration %d published successfully.', $post_id ),
		);
	}

	/**
	 * Handle PayPal IPN (Instant Payment Notification).
	 *
	 * Validates the IPN via PayPal's verification endpoint and processes
	 * completed payments. Sets _orke_payment_status to 'paypal-completed'.
	 *
	 * @param array $ipn_data The raw IPN POST data.
	 * @return array|WP_Error Result with status and message.
	 */
	public static function handle_paypal_ipn( array $ipn_data ) {
		// Verify IPN with PayPal.
		$verified = self::verify_paypal_ipn( $ipn_data );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$payment_status = isset( $ipn_data['payment_status'] ) ? strtolower( $ipn_data['payment_status'] ) : '';
		if ( 'completed' !== $payment_status ) {
			return array(
				'success' => true,
				'message' => sprintf( 'IPN received but payment status is "%s". No action taken.', $payment_status ),
			);
		}

		$custom = isset( $ipn_data['custom'] ) ? $ipn_data['custom'] : '';
		if ( empty( $custom ) ) {
			return new WP_Error(
				'orke_ipn_missing_custom',
				__( 'IPN missing custom field (post_id reference).', 'orkestone-agency-hub' ),
				array( 'status' => 400 )
			);
		}

		// The 'custom' field contains the post_id.
		$post_id = intval( $custom );
		$txn_id  = isset( $ipn_data['txn_id'] ) ? sanitize_text_field( $ipn_data['txn_id'] ) : '';

		$post = get_post( $post_id );
		if ( ! $post || Orkestone_Configuration_CPT::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'orke_post_not_found',
				__( 'Configuration post not found for this IPN.', 'orkestone-agency-hub' ),
				array( 'status' => 404 )
			);
		}

		// Idempotency check.
		if ( 'publish' === $post->post_status ) {
			error_log(
				sprintf(
					'Orkestone Agency Hub — Duplicate PayPal IPN for already-paid config %d',
					$post_id
				)
			);
			return array(
				'success' => true,
				'message' => 'Configuration already published. Duplicate IPN ignored.',
			);
		}

		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return new WP_Error(
				'orke_publish_failed',
				__( 'Failed to publish configuration post.', 'orkestone-agency-hub' ),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $post_id, '_orke_payment_id', $txn_id );
		update_post_meta( $post_id, '_orke_payment_status', 'paypal-completed' );

		/**
		 * Fire token generation hook.
		 *
		 * @param int $post_id The configuration post ID.
		 */
		do_action( 'orke_configuration_paid', $post_id );

		return array(
			'success' => true,
			'message' => sprintf( 'Configuration %d published via PayPal IPN.', $post_id ),
		);
	}

	/**
	 * Verify a PayPal IPN message with PayPal.
	 *
	 * Sends the raw IPN data back to PayPal for verification.
	 * Uses the sandbox URL if the test sandbox option is enabled.
	 *
	 * @param array $ipn_data The IPN POST data.
	 * @return true|WP_Error True if verified, WP_Error on failure.
	 */
	private static function verify_paypal_ipn( array $ipn_data ) {
		$verify_url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
		if ( defined( 'ORKE_PAYPAL_SANDBOX' ) && ORKE_PAYPAL_SANDBOX ) {
			$verify_url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
		}

		$ipn_data['cmd'] = '_notify-validate';

		$response = wp_remote_post(
			$verify_url,
			array(
				'body'    => $ipn_data,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log(
				sprintf(
					'Orkestone Agency Hub — PayPal IPN verification failed: %s',
					$response->get_error_message()
				)
			);
			return new WP_Error(
				'orke_paypal_verification_failed',
				__( 'PayPal IPN verification request failed.', 'orkestone-agency-hub' ),
				array( 'status' => 502 )
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( 'VERIFIED' !== $body ) {
			error_log(
				sprintf(
					'Orkestone Agency Hub — PayPal IPN verification returned: %s',
					$body
				)
			);
			return new WP_Error(
				'orke_paypal_ipn_invalid',
				__( 'PayPal IPN verification failed: not VERIFIED.', 'orkestone-agency-hub' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Verify a Stripe webhook signature.
	 *
	 * Uses the standard Stripe signature verification approach:
	 * extracts the timestamp and signatures from the header,
	 * computes the expected signature using the webhook secret,
	 * and compares. (REQ-AH12)
	 *
	 * @param string $payload        The raw webhook body.
	 * @param string $signature_header The Stripe-Signature header value.
	 * @param string $webhook_secret  The webhook signing secret.
	 * @return true|WP_Error True if verified, WP_Error on failure.
	 */
	private static function verify_stripe_signature( string $payload, string $signature_header, string $webhook_secret ) {
		if ( empty( $signature_header ) ) {
			return new WP_Error(
				'orke_missing_stripe_signature',
				__( 'Missing Stripe signature header.', 'orkestone-agency-hub' ),
				array( 'status' => 401 )
			);
		}

		// Parse the Stripe-Signature header.
		$parts = explode( ',', $signature_header );
		$timestamp = '';
		$signatures = array();

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( strpos( $part, 't=' ) === 0 ) {
				$timestamp = substr( $part, 2 );
			} elseif ( strpos( $part, 'v1=' ) === 0 ) {
				$signatures[] = substr( $part, 3 );
			}
		}

		if ( empty( $timestamp ) || empty( $signatures ) ) {
			return new WP_Error(
				'orke_invalid_stripe_signature_format',
				__( 'Invalid Stripe signature format.', 'orkestone-agency-hub' ),
				array( 'status' => 401 )
			);
		}

		// Compute the expected signature.
		$signed_payload = $timestamp . '.' . $payload;
		$expected = hash_hmac( 'sha256', $signed_payload, $webhook_secret );

		// Compare against all provided signatures.
		$match = false;
		foreach ( $signatures as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				$match = true;
				break;
			}
		}

		if ( ! $match ) {
			return new WP_Error(
				'orke_invalid_stripe_signature',
				__( 'Invalid Stripe webhook signature.', 'orkestone-agency-hub' ),
				array( 'status' => 401 )
			);
		}

		// Optional: check timestamp is within tolerance (5 minutes).
		$timestamp_int = intval( $timestamp );
		if ( time() - $timestamp_int > 300 ) {
			error_log(
				sprintf(
					'Orkestone Agency Hub — Stripe webhook timestamp is older than 5 minutes (%d seconds old)',
					time() - $timestamp_int
				)
			);
			// Still accept it — allow for delayed delivery.
		}

		return true;
	}

	/**
	 * Make a request to the Stripe API.
	 *
	 * @param string $endpoint The API endpoint path (e.g., 'checkout/sessions').
	 * @param array  $data     The request body as an associative array.
	 * @return array|WP_Error Decoded response array or WP_Error on failure.
	 */
	private static function stripe_request( string $endpoint, array $data ) {
		$secret_key = self::get_stripe_secret();
		if ( empty( $secret_key ) ) {
			return new WP_Error(
				'orke_stripe_not_configured',
				__( 'Stripe secret key is not configured.', 'orkestone-agency-hub' )
			);
		}

		$url = trailingslashit( self::STRIPE_API_BASE ) . $endpoint;

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => self::build_form_data( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log(
				sprintf(
					'Orkestone Agency Hub — Stripe API request failed: %s',
					$response->get_error_message()
				)
			);
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : 'Unknown Stripe API error.';
			error_log(
				sprintf(
					'Orkestone Agency Hub — Stripe API error (%d): %s',
					$status_code,
					$error_message
				)
			);
			return new WP_Error(
				'orke_stripe_api_error',
				sprintf(
					/* translators: %s: Stripe API error message */
					__( 'Stripe API error: %s', 'orkestone-agency-hub' ),
					$error_message
				),
				array( 'status' => $status_code )
			);
		}

		return $decoded;
	}

	/**
	 * Build URL-encoded form data from a nested array.
	 *
	 * Stripe's API uses a specific format for nested parameters like
	 * line_items[0][price_data][currency]=usd. This helper builds that.
	 *
	 * @param array $data The data array.
	 * @param string $prefix Optional prefix for recursive building.
	 * @return string URL-encoded form data.
	 */
	private static function build_form_data( array $data, string $prefix = '' ): string {
		$params = array();

		foreach ( $data as $key => $value ) {
			$full_key = $prefix ? $prefix . '[' . $key . ']' : $key;

			if ( is_array( $value ) ) {
				$is_indexed = array_keys( $value ) === range( 0, count( $value ) - 1 );
				if ( $is_indexed ) {
					foreach ( $value as $i => $v ) {
						if ( is_array( $v ) ) {
							$params[] = self::build_form_data( $v, $full_key . '[' . $i . ']' );
						} else {
							$params[] = $full_key . '[' . $i . ']=' . rawurlencode( (string) $v );
						}
					}
				} else {
					$params[] = self::build_form_data( $value, $full_key );
				}
			} else {
				$params[] = $full_key . '=' . rawurlencode( (string) $value );
			}
		}

		return implode( '&', $params );
	}

	/**
	 * Handle the "Mark as Paid" admin action.
	 *
	 * Manually transitions an orke_configuration from draft to published,
	 * storing _orke_payment_status='manual' and tracking who performed
	 * the override (REQ-AH13).
	 */
	public static function handle_mark_as_paid(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'orkestone-agency-hub' ) );
		}

		check_admin_referer( 'orke_mark_as_paid' );

		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
		if ( $post_id < 1 ) {
			wp_die( esc_html__( 'Invalid post ID.', 'orkestone-agency-hub' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || Orkestone_Configuration_CPT::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Configuration not found.', 'orkestone-agency-hub' ) );
		}

		if ( 'publish' === $post->post_status ) {
			// Already published — redirect with a notice.
			wp_safe_redirect(
				add_query_arg(
					array(
						'post'            => $post_id,
						'action'          => 'edit',
						'orke_paid'       => 'already',
					),
					admin_url( 'post.php' )
				)
			);
			exit;
		}

		$user_id = get_current_user_id();

		$updated = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_die( esc_html__( 'Failed to publish configuration.', 'orkestone-agency-hub' ) );
		}

		update_post_meta( $post_id, '_orke_payment_id', 'manual-' . $user_id . '-' . time() );
		update_post_meta( $post_id, '_orke_payment_status', 'manual' );

		/**
		 * Fire token generation hook.
		 *
		 * @param int $post_id The configuration post ID.
		 */
		do_action( 'orke_configuration_paid', $post_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'      => $post_id,
					'action'    => 'edit',
					'orke_paid' => 'manual',
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}
}
