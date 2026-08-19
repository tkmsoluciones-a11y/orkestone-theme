<?php
/**
 * Configuration CPT — tracks client briefings, budgets, and payment status.
 *
 * Registers the `orke_configuration` custom post type to store each client
 * configuration through its lifecycle (draft → published). Manages post meta
 * for payment tracking, delivery tokens, and vertical JSON data.
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registration and lifecycle of the orke_configuration CPT.
 *
 * Meta fields managed by this class:
 * - _orke_vertical_key         — unique slug/key for the configuration
 * - _orke_payment_id           — Stripe/PayPal session or invoice ID
 * - _orke_payment_status       — pending | completed | manual | paypal-completed
 * - _orke_delivery_token       — UUID activation token for client site
 * - _orke_token_expires_at     — token expiry Unix timestamp (24h from generation)
 * - _orke_token_allowed_origin — client site URL bound to the token
 * - _orke_asset_base_url       — base URL for asset resolution in generated JSON
 * - _orke_client_data          — serialized briefing form data
 * - _orke_budget_amount        — calculated budget total
 * - _orke_vertical_json_blob   — generated vertical JSON string
 */
class Orkestone_Configuration_CPT {

	/**
	 * The CPT slug.
	 */
	const POST_TYPE = 'orke_configuration';

	/**
	 * Register the post type and all meta fields.
	 */
	public static function register(): void {
		self::register_post_type();
		self::register_meta_fields();
	}

	/**
	 * Register the orke_configuration custom post type.
	 */
	private static function register_post_type(): void {
		$labels = array(
			'name'               => _x( 'Configurations', 'post type general name', 'orkestone-agency-hub' ),
			'singular_name'      => _x( 'Configuration', 'post type singular name', 'orkestone-agency-hub' ),
			'add_new'            => __( 'New Configuration', 'orkestone-agency-hub' ),
			'add_new_item'       => __( 'Add New Configuration', 'orkestone-agency-hub' ),
			'edit_item'          => __( 'Edit Configuration', 'orkestone-agency-hub' ),
			'view_item'          => __( 'View Configuration', 'orkestone-agency-hub' ),
			'search_items'       => __( 'Search Configurations', 'orkestone-agency-hub' ),
			'not_found'          => __( 'No configurations found', 'orkestone-agency-hub' ),
			'not_found_in_trash' => __( 'No configurations found in Trash', 'orkestone-agency-hub' ),
			'all_items'          => __( 'All Configurations', 'orkestone-agency-hub' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => true,
			'menu_position'   => 25,
			'menu_icon'       => 'dashicons-admin-site',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'rewrite'         => false,
			'query_var'       => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register all post meta fields for the configuration CPT.
	 *
	 * Each meta field is registered with show_in_rest => true so they are
	 * accessible via the WordPress REST API for the CPT.
	 */
	private static function register_meta_fields(): void {
		$meta_fields = array(
			'_orke_vertical_key'         => array(
				'type'              => 'string',
				'description'       => __( 'Unique vertical key for this configuration', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_title',
			),
			'_orke_payment_id'           => array(
				'type'              => 'string',
				'description'       => __( 'Payment session ID (Stripe/PayPal/manual)', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_orke_payment_status'       => array(
				'type'              => 'string',
				'description'       => __( 'Payment status: pending, completed, manual, paypal-completed', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'pending',
			),
			'_orke_delivery_token'       => array(
				'type'              => 'string',
				'description'       => __( 'Activation token for client site delivery (UUID)', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_orke_token_expires_at'     => array(
				'type'              => 'integer',
				'description'       => __( 'Token expiry Unix timestamp (24h from generation)', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'absint',
			),
			'_orke_token_allowed_origin' => array(
				'type'              => 'string',
				'description'       => __( 'Client site URL the token is bound to for origin validation', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_url',
			),
			'_orke_asset_base_url'       => array(
				'type'              => 'string',
				'description'       => __( 'Base URL for asset URL resolution in generated JSON', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_url',
			),
			'_orke_client_data'          => array(
				'type'              => 'string',
				'description'       => __( 'Serialized client briefing form data', 'orkestone-agency-hub' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize_json_meta' ),
			),
			'_orke_budget_amount'        => array(
				'type'              => 'number',
				'description'       => __( 'Calculated budget total amount', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'floatval',
			),
			'_orke_vertical_json_blob'   => array(
				'type'              => 'string',
				'description'       => __( 'Generated vertical JSON configuration payload', 'orkestone-agency-hub' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize_json_meta' ),
			),
		);

		foreach ( $meta_fields as $meta_key => $meta_args ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => $meta_args['type'],
					'description'       => $meta_args['description'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $meta_args['sanitize_callback'],
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Sanitize a JSON string meta value.
	 *
	 * Decodes the input, re-encodes to ensure valid JSON.
	 * Returns empty string on invalid JSON.
	 *
	 * @param string $value The raw meta value.
	 * @return string Validated JSON string or empty.
	 */
	public static function sanitize_json_meta( $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$decoded = json_decode( $value, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return '';
		}

		return wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
