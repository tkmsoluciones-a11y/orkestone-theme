<?php
/**
 * Vertical Block Base functions and definitions.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VBB_VERSION', '0.4.4' );
define( 'VBB_THEME_DIR', get_template_directory() );
define( 'VBB_THEME_URI', get_template_directory_uri() );

$vertical_block_base_files = array(
	'inc/helpers.php',
	'inc/vertical-validator.php',
	'inc/vertical-storage.php',
	'inc/vertical-loader.php',
	'inc/content-model.php',
	'inc/pattern-registry.php',
	'inc/block-registry.php',
	'inc/block-baker.php',
	'inc/reset-orchestrator.php',
	'inc/page-blueprint.php',
	'inc/vertical-importer.php',
	'inc/setup.php',
	'inc/enqueue.php',
	'inc/admin-verticals.php',
);

foreach ( $vertical_block_base_files as $vertical_block_base_file ) {
	$vertical_block_base_path = VBB_THEME_DIR . '/' . $vertical_block_base_file;

	if ( file_exists( $vertical_block_base_path ) ) {
		require_once $vertical_block_base_path;
	}
}

// Pro Elite modules.
require_once get_template_directory() . '/inc/pro-settings.php';
require_once get_template_directory() . '/inc/pro-presets.php';
require_once get_template_directory() . '/inc/pro-css-vars.php';
require_once get_template_directory() . '/inc/pro-admin.php';
require_once get_template_directory() . '/inc/pro-rest-api.php';
