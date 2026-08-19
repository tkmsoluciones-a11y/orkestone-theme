<?php
/**
 * Lightweight vertical config validator.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate minimum required fields for a vertical config.
 *
 * @param array $config Vertical config.
 * @return bool
 */
function vbb_validate_vertical_config( $config ) {
	if ( ! is_array( $config ) ) {
		return false;
	}

	$required_top_level = array(
		'schemaVersion',
		'verticalKey',
		'name',
		'brand',
		'navigation',
		'pages',
		'contentModels',
	);

	foreach ( $required_top_level as $key ) {
		if ( ! array_key_exists( $key, $config ) ) {
			vbb_log_warning( 'Missing vertical field: ' . $key );
			return false;
		}
	}

	if ( ! is_array( $config['brand'] ) || ! is_array( $config['pages'] ) ) {
		vbb_log_warning( 'Invalid vertical structure for brand or pages.' );
		return false;
	}

	return true;
}
