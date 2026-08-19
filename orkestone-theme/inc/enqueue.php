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

	// Pro Elite frontend styles (scoped CSS variables for all blocks)
	if ( get_option( 'vbb_pro_settings', false ) ) {
		wp_enqueue_style(
			'vbb-pro-frontend',
			get_theme_file_uri( 'assets/css/pro-frontend.css' ),
			array( 'vertical-block-base-style' ),
			VBB_VERSION
		);
	}

	// Scroll effects CSS.
	wp_enqueue_style(
		'vbb-effects',
		get_theme_file_uri( 'assets/css/vbb-effects.css' ),
		array( 'vertical-block-base-style' ),
		VBB_VERSION
	);

	// Scroll effects JS (IntersectionObserver).
	wp_enqueue_script(
		'vbb-effects',
		get_theme_file_uri( 'assets/js/vbb-effects.js' ),
		array(),
		VBB_VERSION,
		true
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

/**
 * Enqueue block editor assets.
 * Provides vertical data context to the Gutenberg editor.
 */
function vbb_enqueue_editor_assets() {
	wp_enqueue_style(
		'vertical-block-base-editor',
		get_theme_file_uri( 'assets/css/editor.css' ),
		array( 'wp-edit-blocks' ),
		VBB_VERSION
	);

	wp_enqueue_script(
		'vertical-block-base-editor',
		get_theme_file_uri( 'assets/js/editor.js' ),
		array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post' ),
		VBB_VERSION,
		true
	);

	wp_localize_script(
		'vertical-block-base-editor',
		'VerticalBlockBaseEditor',
		array(
			'activeVertical' => vbb_get_active_vertical_key(),
			'siteName'       => vbb_get_vertical_value( 'brand.siteName', get_bloginfo( 'name' ) ),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'vbb_enqueue_editor_assets' );
