<?php
/**
 * Asset loading.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue front-end assets.
 *
 * @return void
 */
function vbb_enqueue_assets() {
	wp_enqueue_style(
		'vertical-block-base-style',
		get_stylesheet_uri(),
		array(),
		VBB_VERSION
	);

	wp_enqueue_script(
		'vertical-block-base-theme',
		get_theme_file_uri( 'assets/js/theme.js' ),
		array(),
		VBB_VERSION,
		true
	);

	wp_localize_script(
		'vertical-block-base-theme',
		'VerticalBlockBase',
		array(
			'activeVertical' => vbb_get_active_vertical_key(),
			'siteName'       => vbb_get_vertical_value( 'brand.siteName', get_bloginfo( 'name' ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'vbb_enqueue_assets' );
