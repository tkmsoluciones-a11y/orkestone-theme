<?php
/**
 * Theme setup.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme supports.
 *
 * @return void
 */
function vbb_setup_theme() {
	load_theme_textdomain( 'vertical-block-base', get_template_directory() . '/languages' );

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );

	// Register the primary navigation location for the OrkestOne Theme menu.
	register_nav_menus(
		array(
			'vbb_primary' => __( 'OrkestOne Primary', 'vertical-block-base' ),
		)
	);
}
add_action( 'after_setup_theme', 'vbb_setup_theme' );

/**
 * Optionally filter blog name from active vertical in non-admin contexts.
 *
 * Kept conservative for MVP: no database writes.
 *
 * @param string $value Blog info value.
 * @param string $show Requested field.
 * @return string
 */
function vbb_filter_bloginfo_from_vertical( $value, $show ) {
	if ( is_admin() ) {
		return $value;
	}

	if ( 'name' === $show ) {
		return vbb_get_vertical_value( 'brand.siteName', $value );
	}

	if ( 'description' === $show ) {
		return vbb_get_vertical_value( 'brand.tagline', $value );
	}

	return $value;
}
add_filter( 'bloginfo', 'vbb_filter_bloginfo_from_vertical', 10, 2 );
