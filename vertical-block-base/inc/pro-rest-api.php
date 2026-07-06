<?php
/**
 * Command Center REST API — orkestone/v1 endpoints.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /orkestone/v1/vertical-settings
 *
 * Returns the full Pro Elite settings object.
 *
 * @return WP_REST_Response
 */
function vbb_rest_get_settings() {
	$settings = vbb_pro_get_settings();
	return new WP_REST_Response(
		array(
			'settings' => $settings,
		),
		200
	);
}

/**
 * POST /orkestone/v1/vertical-settings
 *
 * Accepts a full or partial settings object, sanitises via vbb_pro_sanitize_settings(),
 * persists via vbb_pro_update_settings(), and returns the merged result.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_update_settings( WP_REST_Request $request ) {
	$body = $request->get_json_params();

	if ( empty( $body ) || ! is_array( $body ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Invalid or empty request body.', 'vertical-block-base' ),
			),
			400
		);
	}

	$settings = isset( $body['settings'] ) && is_array( $body['settings'] )
		? $body['settings']
		: $body;

	$settings = vbb_pro_update_settings( $settings );

	return new WP_REST_Response(
		array(
			'success'  => true,
			'settings' => $settings,
		),
		200
	);
}

/**
 * GET /orkestone/v1/vertical-config
 *
 * Returns the active vertical configuration for preview context.
 *
 * @return WP_REST_Response
 */
function vbb_rest_get_vertical_config() {
	$config = function_exists( 'vbb_get_vertical_config' ) ? vbb_get_vertical_config() : array();

	return new WP_REST_Response(
		array(
			'config' => $config,
		),
		200
	);
}

/**
 * Common permission callback for all Command Center REST routes.
 *
 * @return bool|WP_Error
 */
function vbb_rest_command_center_permission() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access this endpoint.', 'vertical-block-base' ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Register orkestone/v1 REST routes.
 *
 * @return void
 */
function vbb_register_command_center_routes() {
	$namespace = 'orkestone/v1';

	register_rest_route(
		$namespace,
		'/vertical-settings',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'vbb_rest_get_settings',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_update_settings',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/vertical-config',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'vbb_rest_get_vertical_config',
			'permission_callback' => 'vbb_rest_command_center_permission',
		)
	);
}
add_action( 'rest_api_init', 'vbb_register_command_center_routes' );
