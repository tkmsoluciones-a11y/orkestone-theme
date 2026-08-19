<?php
/**
 * Vertical JSON loader.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get active vertical settings file.
 *
 * @return array
 */
function vbb_get_active_vertical_settings() {
	$option_active = get_option( 'vbb_active_vertical', '' );
	
	// Priority: DB option first, then fallback to theme config file.
	$active_key = ( '' !== $option_active ) ? sanitize_key( $option_active ) : 'default';
	
	$settings_path = get_theme_file_path( 'config/active-vertical.json' );
	$settings      = vbb_read_json_file( $settings_path );
	$fallback       = ( is_array( $settings ) && ! empty( $settings['fallback'] ) ) ? sanitize_key( $settings['fallback'] ) : 'default';

	return array(
		'active'   => $active_key,
		'fallback' => $fallback,
	);
}

/**
 * Get the active vertical key.
 *
 * @return string
 */
function vbb_get_active_vertical_key() {
	$settings = vbb_get_active_vertical_settings();

	return $settings['active'];
}

/**
 * Load a vertical by key.
 *
 * @param string $vertical_key Vertical key.
 * @return array|null
 */
function vbb_load_vertical_by_key( $vertical_key ) {
	$vertical_key = sanitize_key( $vertical_key );
	$path         = vbb_find_vertical_file_path( $vertical_key );

	if ( ! $path ) {
		return null;
	}

	$config = vbb_read_json_file( $path );

	if ( is_array( $config ) && vbb_validate_vertical_config( $config ) ) {
		return $config;
	}

	return null;
}

/**
 * Get active vertical config with safe fallback.
 *
 * @return array
 */
function vbb_get_vertical_config() {
	static $config = null;

	if ( null !== $config ) {
		return $config;
	}

	$settings = vbb_get_active_vertical_settings();
	$config   = vbb_load_vertical_by_key( $settings['active'] );

	if ( ! is_array( $config ) ) {
		vbb_log_warning( 'Using fallback vertical: ' . $settings['fallback'] );
		$config = vbb_load_vertical_by_key( $settings['fallback'] );
	}

	if ( ! is_array( $config ) ) {
		$config = array(
			'schemaVersion' => '1.0.0',
			'verticalKey'   => 'runtime-fallback',
			'name'          => 'Runtime Fallback',
			'brand'         => array(
				'siteName' => get_bloginfo( 'name' ),
				'tagline'  => get_bloginfo( 'description' ),
			),
			'navigation'    => array(
				'primary' => array(),
			),
			'pages'         => array(),
			'contentModels' => array(),
		);
	}

	return $config;
}

/**
 * Get a nested value from active vertical config.
 *
 * @param string $path Dot notation path.
 * @param mixed  $default Default value.
 * @return mixed
 */
function vbb_get_vertical_value( $path, $default = null ) {
	return vbb_array_get( vbb_get_vertical_config(), $path, $default );
}

/**
 * Get declared vertical pages.
 *
 * @return array
 */
function vbb_get_vertical_pages() {
	$pages = vbb_get_vertical_value( 'pages', array() );

	return is_array( $pages ) ? $pages : array();
}

/**
 * Get sections for a declared page key.
 *
 * @param string $page_key Page key.
 * @return array
 */
function vbb_get_vertical_sections( $page_key ) {
	foreach ( vbb_get_vertical_pages() as $page ) {
		if ( isset( $page['key'] ) && $page_key === $page['key'] ) {
			return isset( $page['sections'] ) && is_array( $page['sections'] ) ? $page['sections'] : array();
		}
	}

	return array();
}

/**
 * Find a page config by key.
 *
 * @param string $page_key Page key.
 * @return array|null
 */
function vbb_get_vertical_page( $page_key ) {
	foreach ( vbb_get_vertical_pages() as $page ) {
		if ( isset( $page['key'] ) && $page_key === $page['key'] ) {
			return $page;
		}
	}

	return null;
}
