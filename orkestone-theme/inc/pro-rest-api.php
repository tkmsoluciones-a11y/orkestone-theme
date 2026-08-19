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
 * POST /orkestone/v1/pages
 *
 * Creates a new builder page. Initialises per-page settings with the requested
 * sections, bakes the initial content, and sets the vertical meta key.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_create_page( WP_REST_Request $request ) {
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

	$title    = isset( $body['title'] ) ? sanitize_text_field( $body['title'] ) : '';
	$slug     = isset( $body['slug'] ) ? sanitize_title( $body['slug'] ) : '';
	$sections = isset( $body['sections'] ) && is_array( $body['sections'] )
		? array_map( 'sanitize_key', $body['sections'] )
		: array();

	if ( '' === $title ) {
return new WP_REST_Response(
		array(
			'success'  => true,
			'settings' => $settings,
		),
		200
	);
}

/**
 * POST /orkestone/v1/contact
 * Public endpoint to submit contact form.
 */
function vbb_rest_submit_contact_form( WP_REST_Request $request ) {
	$params = $request->get_json_params();

	if ( empty( $params ) || ! is_array( $params ) ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Invalid payload' ),
			400
		);
	}

	// Verify honeypot field (anti-spam)
	if ( ! empty( $params['_hp'] ) ) {
		return new WP_REST_Response(
			array( 'success' => true, 'message' => 'OK' ),
			200
		);
	}

	// reCAPTCHA verification if configured
	$recaptcha_token = isset( $params['recaptcha_token'] ) ? $params['recaptcha_token'] : '';
	$recaptcha_secret = get_option( 'vbb_recaptcha_secret_key', '' );
	if ( $recaptcha_token && $recaptcha_secret ) {
		$verify = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $recaptcha_secret,
					'response' => $recaptcha_token,
				),
				'timeout' => 10,
			)
		);
		if ( ! is_wp_error( $verify ) ) {
			$response = json_decode( wp_remote_retrieve_body( $verify ), true );
			if ( empty( $response['success'] ) || ( isset( $response['score'] ) && $response['score'] < 0.5 ) ) {
				return new WP_REST_Response(
					array( 'success' => false, 'message' => 'reCAPTCHA verification failed' ),
					400
				);
			}
		}
	}

	// Sanitize and validate fields
	$fields = array(
		'name'    => sanitize_text_field( $params['name'] ?? '' ),
		'email'   => sanitize_email( $params['email'] ?? '' ),
		'phone'   => sanitize_text_field( $params['phone'] ?? '' ),
		'message' => sanitize_textarea_field( $params['message'] ?? '' ),
	);

	// Basic validation
	$errors = array();
	if ( empty( $fields['name'] ) ) {
		$errors[] = 'El nombre es obligatorio';
	}
	if ( empty( $fields['email'] ) || ! is_email( $fields['email'] ) ) {
		$errors[] = 'Email inválido';
	}
	if ( empty( $fields['message'] ) ) {
		$errors[] = 'El mensaje es obligatorio';
	}

	if ( ! empty( $errors ) ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => implode( ', ', $errors ) ),
			400
		);
	}

	// Send email notification
	$to      = get_option( 'admin_email' );
	$subject = sprintf( 'Nuevo contacto desde %s', get_bloginfo( 'name' ) );
	$body    = sprintf(
		"Nombre: %s\nEmail: %s\nTeléfono: %s\n\nMensaje:\n%s",
		$fields['name'],
		$fields['email'],
		$fields['phone'],
		$fields['message']
	);
	$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $fields['email'] );

	$sent = wp_mail( $to, $subject, $body, $headers );

	if ( ! $sent ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Error enviando email. Contacte al administrador.' ),
			500
		);
	}

	// Optionally store in DB for reference
	$submission = array(
		'name'    => $fields['name'],
		'email'   => $fields['email'],
		'phone'   => $fields['phone'],
		'message' => $fields['message'],
		'date'    => current_time( 'mysql' ),
		'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
		'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
	);

	// Store in custom table or option (simplified - just append to option)
	$submissions = get_option( 'vbb_contact_submissions', array() );
	$submissions[] = $submission;
	// Keep only last 500
	if ( count( $submissions ) > 500 ) {
		$submissions = array_slice( $submissions, -500 );
	}
	update_option( 'vbb_contact_submissions', $submissions );

	return new WP_REST_Response(
		array(
			'success' => true,
			'message' => 'Mensaje enviado correctamente. Nos pondremos en contacto pronto.',
		),
		200
	);
}

	$post_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => '',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $post_id->get_error_message(),
			),
			500
		);
	}

	// Initialise per-page settings with requested sections.
	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	$all_page_settings[ $post_id ] = array( 'sections' => $sections );
	update_option( VBB_PRO_PAGE_SETTINGS_OPTION, $all_page_settings, false );

	// Set the vertical meta key so this page is recognised as a builder page.
	$active_key = function_exists( 'vbb_get_active_vertical_key' ) ? vbb_get_active_vertical_key() : '';
	if ( '' !== $active_key ) {
		update_post_meta( $post_id, '_vbb_vertical', $active_key );
	}

	// Bake the initial page content.
	if ( function_exists( 'vbb_bake_page_content' ) ) {
		vbb_bake_page_content( $post_id );
	}

	$page = get_post( $post_id );

	return new WP_REST_Response(
		array(
			'success' => true,
			'page'    => array(
				'id'   => $post_id,
				'slug' => $page ? $page->post_name : $slug,
			),
			'settings' => array( 'sections' => $sections ),
		),
		201
	);
}

/**
 * DELETE /orkestone/v1/pages/{page_id}
 *
 * Trashes the page and removes its per-page settings.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_delete_page( WP_REST_Request $request ) {
	$page_id = (int) $request->get_param( 'page_id' );

	if ( $page_id < 1 ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Invalid page ID.', 'vertical-block-base' ),
			),
			400
		);
	}

	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Page not found.', 'vertical-block-base' ),
			),
			404
		);
	}

	wp_trash_post( $page_id );

	// Remove per-page settings for this page.
	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	if ( isset( $all_page_settings[ $page_id ] ) ) {
		unset( $all_page_settings[ $page_id ] );
		update_option( VBB_PRO_PAGE_SETTINGS_OPTION, $all_page_settings, false );
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'page_id' => $page_id,
		),
		200
	);
}

/**
 * POST /orkestone/v1/pages/{page_id}/regenerate
 *
 * Re-bakes the page content by calling vbb_bake_page_content().
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_regenerate_page( WP_REST_Request $request ) {
	$page_id = (int) $request->get_param( 'page_id' );

	if ( $page_id < 1 ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Invalid page ID.', 'vertical-block-base' ),
			),
			400
		);
	}

	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Page not found.', 'vertical-block-base' ),
			),
			404
		);
	}

	if ( function_exists( 'vbb_bake_page_content' ) ) {
		vbb_bake_page_content( $page_id );
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'page_id' => $page_id,
		),
		200
	);
}

/**
 * GET /orkestone/v1/blocks
 *
 * Returns the block registry with all field definitions, defaults, and
 * sanitization rules. Used by the JS admin to dynamically render forms.
 *
 * @return WP_REST_Response
 */
function vbb_rest_get_blocks() {
	if ( ! function_exists( 'vbb_get_block_registry' ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Block registry not available.', 'vertical-block-base' ),
			),
			500
		);
	}

	$registry = vbb_get_block_registry();

	// Return only what the JS admin needs: key, label, icon, fields, defaults.
	$blocks = array();
	foreach ( $registry as $key => $block ) {
		$blocks[ $key ] = array(
			'key'      => $key,
			'label'    => $block['label'] ?? $key,
			'icon'     => $block['icon'] ?? '',
			'fields'   => $block['fields'] ?? array(),
			'defaults' => $block['defaults'] ?? array(),
		);
	}

	return new WP_REST_Response(
		array(
			'success' => true,
			'blocks'  => $blocks,
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
 * GET /orkestone/v1/export
 *
 * Returns a full-site JSON document with global settings, per-page overrides,
 * and active profile. Used by the Command Center Export button.
 *
 * @return WP_REST_Response
 */
function vbb_rest_export_site() {
	$settings = vbb_pro_get_settings();

	// Get raw per-page overrides, filter to published pages only.
	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	$published_ids     = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		)
	);
	$page_overrides = array();
	foreach ( $published_ids as $id ) {
		if ( isset( $all_page_settings[ $id ] ) ) {
			$page_overrides[ (string) $id ] = $all_page_settings[ $id ];
		}
	}

	$data = array(
		'exportedAt'    => current_time( 'mysql' ),
		'schemaVersion' => '1.0.0',
		'theme'         => 'orkestOne',
		'customized'    => true,
		'settings'      => $settings,
		'pageOverrides' => (object) $page_overrides, // force {} when empty
		'activeProfile' => get_option( VBB_PRO_ACTIVE_PROFILE_OPTION, null ),
	);

	return new WP_REST_Response( $data, 200 );
}

/**
 * POST /orkestone/v1/profile
 * Save current settings as a named profile (XHR alternative to form submit).
 */
function vbb_rest_save_profile( WP_REST_Request $request ) {
	$body    = $request->get_json_params();
	$name    = isset( $body['name'] ) ? sanitize_text_field( $body['name'] ) : '';
	$stored  = vbb_pro_get_settings();
	$key     = vbb_pro_save_profile( $name, $stored );

	if ( $key ) {
		return new WP_REST_Response(
			array(
				'success' => true,
				'key'     => $key,
				'name'    => $name ? $name : $key,
				'message' => __( 'Profile saved.', 'vertical-block-base' ),
			),
			200
		);
	}

	return new WP_REST_Response(
		array(
			'success' => false,
			'message' => __( 'Failed to save profile.', 'vertical-block-base' ),
		),
		500
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
		'/export',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'vbb_rest_export_site',
			'permission_callback' => 'vbb_rest_command_center_permission',
		)
	);

	register_rest_route(
		$namespace,
		'/blocks',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'vbb_rest_get_blocks',
			'permission_callback' => 'vbb_rest_command_center_permission',
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
	register_rest_route(
		$namespace,
		'/pages',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'vbb_rest_get_pages',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_create_page',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/pages/(?P<page_id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'vbb_rest_delete_page',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/regenerate-pages',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_regenerate_pages',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/pages/(?P<page_id>\d+)/regenerate',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_regenerate_page',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/profile',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_save_profile',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/vertical-config',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'vbb_rest_get_vertical_config',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/menu',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'vbb_rest_get_menu',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'vbb_rest_update_menu',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/menu/items',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_append_menu_item',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/menu/items/(?P<idx>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'vbb_rest_delete_menu_item',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/vertical-settings/(?P<page_id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'vbb_rest_get_page_settings',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_update_page_settings',
				'permission_callback' => 'vbb_rest_command_center_permission',
			),
		)
	);

	register_rest_route(
		$namespace,
		'/activate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'vbb_rest_activate_config',
			'permission_callback' => 'vbb_rest_command_center_permission',
		)
	);

	// Contact form endpoint (public - no auth required)
	register_rest_route(
		$namespace,
		'/contact',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'vbb_rest_submit_contact_form',
				'permission_callback' => '__return_true',
			),
		)
	);
}
add_action( 'rest_api_init', 'vbb_register_command_center_routes' );

/**
 * GET /orkestone/v1/pages
 * Returns a list of published pages for the selector.
 * Filters by site type (landing vs multi).
 */
function vbb_rest_get_pages() {
	$settings = vbb_pro_get_settings();
	$site_type = isset( $settings['siteConfig']['type'] ) ? $settings['siteConfig']['type'] : 'landing';

	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );

	$pages = get_pages( array( 'status' => 'publish' ) );
	$list  = array();

	foreach ( $pages as $page ) {
		// If landing mode, only show the front page (usually ID 2 or specified as home)
		if ( 'landing' === $site_type ) {
			if ( $page->ID != get_option( 'page_on_front' ) ) {
				continue;
			}
		}

		$has_settings  = isset( $all_page_settings[ $page->ID ] );
		$page_settings = $has_settings ? $all_page_settings[ $page->ID ] : array();
		$sections      = isset( $page_settings['sections'] ) && is_array( $page_settings['sections'] )
			? $page_settings['sections']
			: array();

		$list[] = array(
			'id'          => $page->ID,
			'title'       => $page->post_title,
			'slug'        => $page->post_name,
			'sections'    => $sections,
			'hasSettings' => $has_settings,
		);
	}
	return new WP_REST_Response( array( 'pages' => $list ), 200 );
}

/**
 * POST /orkestone/v1/regenerate-pages
 */
function vbb_rest_regenerate_pages() {
	$count = vbb_pro_regenerate_all_pages();
	return new WP_REST_Response(
		array(
			'success' => true,
			'message' => sprintf( 'Successfully regenerated %d pages.', $count ),
		),
		200
	);
}

/**
 * GET /orkestone/v1/menu
 *
 * Returns the merged menuItems array from global settings.
 *
 * @return WP_REST_Response
 */
function vbb_rest_get_menu() {
	$settings   = vbb_pro_get_settings();
	$menu_items = isset( $settings['menuItems'] ) && is_array( $settings['menuItems'] )
		? $settings['menuItems']
		: array();

	return new WP_REST_Response(
		array(
			'menuItems' => $menu_items,
		),
		200
	);
}

/**
 * PUT /orkestone/v1/menu
 *
 * Replaces all menu items, updates global settings, and syncs to wp_navigation.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_update_menu( WP_REST_Request $request ) {
	$body = $request->get_json_params();

	if ( empty( $body ) || ! isset( $body['menuItems'] ) || ! is_array( $body['menuItems'] ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'menuItems array is required.', 'vertical-block-base' ),
			),
			400
		);
	}

	$menu_items = $body['menuItems'];

	// Save to global settings.
	$settings              = vbb_pro_get_settings();
	$settings['menuItems'] = $menu_items;
	vbb_pro_update_settings( $settings );

	// Sync to wp_navigation post type.
	if ( function_exists( 'vbb_pro_sync_menu_to_wp_navigation' ) ) {
		vbb_pro_sync_menu_to_wp_navigation( $menu_items );
	}

	return new WP_REST_Response(
		array(
			'success'   => true,
			'menuItems' => vbb_pro_sanitize_menu_items( $menu_items ),
		),
		200
	);
}

/**
 * POST /orkestone/v1/menu/items
 *
 * Appends a single menu item to the existing menuItems array.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_append_menu_item( WP_REST_Request $request ) {
	$body = $request->get_json_params();

	if ( empty( $body ) || ! isset( $body['item'] ) || ! is_array( $body['item'] ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'item object is required.', 'vertical-block-base' ),
			),
			400
		);
	}

	$settings              = vbb_pro_get_settings();
	$menu_items            = isset( $settings['menuItems'] ) && is_array( $settings['menuItems'] )
		? $settings['menuItems']
		: array();

	$menu_items[] = $body['item'];

	$settings['menuItems'] = $menu_items;
	vbb_pro_update_settings( $settings );

	if ( function_exists( 'vbb_pro_sync_menu_to_wp_navigation' ) ) {
		vbb_pro_sync_menu_to_wp_navigation( $menu_items );
	}

	return new WP_REST_Response(
		array(
			'success'   => true,
			'menuItems' => vbb_pro_sanitize_menu_items( $menu_items ),
		),
		200
	);
}

/**
 * DELETE /orkestone/v1/menu/items/{idx}
 *
 * Removes a menu item by its numeric index in the root menuItems array.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_delete_menu_item( WP_REST_Request $request ) {
	$idx = (int) $request->get_param( 'idx' );

	$settings   = vbb_pro_get_settings();
	$menu_items = isset( $settings['menuItems'] ) && is_array( $settings['menuItems'] )
		? $settings['menuItems']
		: array();

	if ( ! isset( $menu_items[ $idx ] ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Menu item not found at that index.', 'vertical-block-base' ),
			),
			404
		);
	}

	array_splice( $menu_items, $idx, 1 );

	$settings['menuItems'] = $menu_items;
	vbb_pro_update_settings( $settings );

	if ( function_exists( 'vbb_pro_sync_menu_to_wp_navigation' ) ) {
		vbb_pro_sync_menu_to_wp_navigation( $menu_items );
	}

	return new WP_REST_Response(
		array(
			'success'   => true,
			'menuItems' => vbb_pro_sanitize_menu_items( $menu_items ),
		),
		200
	);
}

/**
 * GET /orkestone/v1/vertical-settings/<page_id>
 */
function vbb_rest_get_page_settings( WP_REST_Request $request ) {
	$page_id = $request->get_param( 'page_id' );
	return new WP_REST_Response(
		array(
			'settings' => vbb_pro_get_page_settings( $page_id ),
		),
		200
	);
}

/**
 * POST /orkestone/v1/activate
 *
 * Activates a client site configuration using a Hub-delivered token.
 *
 * Full flow (REQ-AH20):
 * 1. Validate input: {token: "..."} present and string
 * 2. Fetch Hub URL from get_option('orke_hub_url') or ORKE_HUB_URL constant
 * 3. wp_remote_get("$hub_url/orke-hub/v1/config/{token}", ['timeout' => 30])
 * 4. Validate response: 200 + success:true + config object
 * 5. Run vbb_validate_vertical_config($config) — if invalid, return 400
 * 6. Schema check: compare $config['schemaVersion'] with vbb_get_schema_version()
 * 7. vbb_save_imported_vertical_config($config) — persist to uploads
 * 8. vbb_import_vertical_full($config['verticalKey']) — full import pipeline
 * 9. Return 200 with import report
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function vbb_rest_activate_config( WP_REST_Request $request ) {
	$body = $request->get_json_params();

	// Step 1: Validate input.
	if ( empty( $body ) || ! isset( $body['token'] ) || ! is_string( $body['token'] ) || empty( trim( $body['token'] ) ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'A valid activation token is required.', 'vertical-block-base' ),
			),
			400
		);
	}

	$token = sanitize_text_field( trim( $body['token'] ) );

	// Step 2: Fetch Hub URL.
	$hub_url = defined( 'ORKE_HUB_URL' ) ? ORKE_HUB_URL : get_option( 'orke_hub_url', '' );

	if ( empty( $hub_url ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Agency Hub URL is not configured. Set it in Command Center Settings or define ORKE_HUB_URL in wp-config.php.', 'vertical-block-base' ),
			),
			502
		);
	}

	$hub_url = trailingslashit( untrailingslashit( $hub_url ) );

	// Step 3: Fetch configuration from Hub.
	$config_url = $hub_url . 'orke-hub/v1/config/' . $token;

	$response = wp_remote_get(
		$config_url,
		array(
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message from HTTP request */
					__( 'Hub unreachable: %s', 'vertical-block-base' ),
					$response->get_error_message()
				),
			),
			502
		);
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body_raw    = wp_remote_retrieve_body( $response );
	$data        = json_decode( $body_raw, true );

	if ( 200 !== $status_code || ! is_array( $data ) || empty( $data['success'] ) || ! isset( $data['config'] ) ) {
		$message = __( 'Invalid or expired activation token.', 'vertical-block-base' );

		if ( is_array( $data ) && isset( $data['message'] ) ) {
			$message = $data['message'];
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			400
		);
	}

	$config = $data['config'];

	// Step 5: Validate config structure.
	if ( ! function_exists( 'vbb_validate_vertical_config' ) || ! vbb_validate_vertical_config( $config ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'The configuration received from the Hub is invalid or incomplete.', 'vertical-block-base' ),
			),
			400
		);
	}

	// Step 6: Schema compatibility check (REQ-AH22).
	if ( function_exists( 'vbb_get_schema_version' ) ) {
		$hub_version    = isset( $config['schemaVersion'] ) ? $config['schemaVersion'] : '0.0.0';
		$theme_version  = vbb_get_schema_version();
		$compatibility  = vbb_check_schema_compatibility( $hub_version, $theme_version );

		if ( is_string( $compatibility ) ) {
			// Incompatible — $compatibility is the error message.
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $compatibility,
				),
				409
			);
		}
	}

	// Step 7: Save the config to disk.
	if ( ! function_exists( 'vbb_save_imported_vertical_config' ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Required import functions are not available.', 'vertical-block-base' ),
			),
			500
		);
	}

	$save_result = vbb_save_imported_vertical_config( $config );

	if ( is_wp_error( $save_result ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $save_result->get_error_message(),
			),
			500
		);
	}

	$vertical_key = $save_result['key'];

	// Step 8: Run the full import pipeline.
	if ( ! function_exists( 'vbb_import_vertical_full' ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Import function vbb_import_vertical_full() is not available.', 'vertical-block-base' ),
			),
			500
		);
	}

	$import_report = vbb_import_vertical_full( $vertical_key );

	// Step 9: Return success report.
	return new WP_REST_Response(
		array(
			'success'       => true,
			'message'       => __( 'Configuration activated successfully.', 'vertical-block-base' ),
			'verticalKey'   => $vertical_key,
			'pagesCreated'  => isset( $import_report['pages'] ) ? count( $import_report['pages'] ) : 0,
			'mediaImported' => isset( $import_report['media'] ) ? count( $import_report['media'] ) : 0,
			'report'        => $import_report,
		),
		200
	);
}

/**
 * Check schema version compatibility between Hub config and theme.
 *
 * SemVer comparison logic (REQ-AH22):
 * - Hub major > Theme major: REJECTED (breaking changes assumed)
 * - Hub major == Theme major: ALLOWED (any minor/patch combination)
 * - Hub major < Theme major: ALLOWED (theme is newer)
 * - Pre-1.0 Hub (0.x.y): REJECTED if theme is 1.0.0+
 *
 * @param string $hub_version   Schema version from Hub configuration.
 * @param string $theme_version Schema version supported by theme.
 * @return true|string True if compatible, error message string if not.
 */
function vbb_check_schema_compatibility( string $hub_version, string $theme_version ) {
	$hub_parts   = explode( '.', $hub_version );
	$theme_parts = explode( '.', $theme_version );

	$hub_major   = isset( $hub_parts[0] ) ? (int) $hub_parts[0] : 0;
	$hub_minor   = isset( $hub_parts[1] ) ? (int) $hub_parts[1] : 0;
	$theme_major = isset( $theme_parts[0] ) ? (int) $theme_parts[0] : 0;
	$theme_minor = isset( $theme_parts[1] ) ? (int) $theme_parts[1] : 0;

	// Hub is pre-1.0 and theme is 1.0+ → reject.
	if ( $hub_major < 1 && $theme_major >= 1 ) {
		return sprintf(
			/* translators: %1$s: Hub schema version, %2$s: Theme schema version */
			__( 'Configuration uses an older schema format (v%1$s) that is not compatible with this site (v%2$s). Contact your agency to upgrade.', 'vertical-block-base' ),
			$hub_version,
			$theme_version
		);
	}

	// Hub major > Theme major → reject (breaking).
	if ( $hub_major > $theme_major ) {
		return sprintf(
			/* translators: %1$s: Hub schema version, %2$s: Theme schema version */
			__( 'Configuration requires schema v%1$s but this site supports v%2$s. Contact your agency for an updated configuration.', 'vertical-block-base' ),
			$hub_version,
			$theme_version
		);
	}

	// Compatible in all other cases.
	return true;
}

/**
 * POST /orkestone/v1/vertical-settings/<page_id>
 */
function vbb_rest_update_page_settings( WP_REST_Request $request ) {
	$page_id = $request->get_param( 'page_id' );
	$body    = $request->get_json_params();

	if ( empty( $body ) || ! is_array( $body ) ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Invalid body' ),
			400
		);
	}

	// FIX: Extract the 'settings' array if it's wrapped, otherwise use the body.
	$settings_to_save = isset( $body['settings'] ) && is_array( $body['settings'] ) 
		? $body['settings'] 
		: $body;

	$settings = vbb_pro_update_page_settings( $page_id, $settings_to_save );

	return new WP_REST_Response(
		array(
			'success'  => true,
			'settings' => $settings,
		),
		200
	);
}
