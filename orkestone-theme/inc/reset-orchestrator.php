<?php
/**
 * Reset orchestrator for vertical import lifecycle.
 *
 * Manages the destructive phase of a vertical switch: trashes pages,
 * navigation menus, and other VBB-generated content from a previous
 * vertical, then updates the active-vertical.json pointer.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trash all posts tagged with a given vertical key.
 *
 * Queries pages by _vbb_vertical meta and wp_navigation posts by
 * _vbb_source meta. Uses wp_trash_post() only — never wp_delete_post() —
 * so content remains recoverable for 30 days.
 *
 * @param string $vertical_key Vertical key to match (e.g. 'law-firm').
 * @return array{
 *   pages_trashed: int,
 *   navigation_trashed: int,
 *   errors: string[]
 * }
 */
function vbb_reset_vertical_pages( $vertical_key ) {
	$vertical_key = sanitize_key( $vertical_key );

	$report = array(
		'pages_trashed'      => 0,
		'navigation_trashed' => 0,
		'errors'             => array(),
	);

	if ( '' === $vertical_key ) {
		return $report;
	}

	// 1. Trash all pages with matching _vbb_vertical meta.
	$page_query = new WP_Query(
		array(
			'post_type'              => 'page',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_vbb_vertical',
					'value' => $vertical_key,
				),
			),
		)
	);

	foreach ( $page_query->posts as $post_id ) {
		$result = wp_trash_post( $post_id );

		if ( $result ) {
			++$report['pages_trashed'];
		} else {
			$report['errors'][] = sprintf(
				'Failed to trash page %d with _vbb_vertical=%s',
				(int) $post_id,
				$vertical_key
			);
		}
	}

	// 2. Trash wp_navigation posts with _vbb_source vertical marker.
	$nav_query = new WP_Query(
		array(
			'post_type'              => 'wp_navigation',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_vbb_source',
					'value' => 'vertical',
				),
			),
		)
	);

	foreach ( $nav_query->posts as $post_id ) {
		$result = wp_trash_post( $post_id );

		if ( $result ) {
			++$report['navigation_trashed'];
		} else {
			$report['errors'][] = sprintf(
				'Failed to trash navigation %d with _vbb_source',
				(int) $post_id
			);
		}
	}

	return $report;
}

/**
 * Compare a candidate vertical key against the currently active one.
 *
 * @param string $new_key Proposed new vertical key.
 * @return bool True if the new key differs from the current active key.
 */
function vbb_is_different_vertical( $new_key ) {
	return sanitize_key( $new_key ) !== vbb_get_active_vertical_key();
}

/**
 * Overwrite config/active-vertical.json with a new vertical key.
 *
 * Writes the full config structure (active + fallback) preserving the
 * expected file format. Does NOT change the in-memory cached config —
 * callers should clear caches or reload as needed.
 *
 * @param string $new_key New vertical key to set as active.
 * @return array{
 *   active: string,
 *   fallback: string,
 *   path: string
 * }|WP_Error
 */
function vbb_update_active_vertical_config( $new_key ) {
	$new_key = sanitize_key( $new_key );

	if ( '' === $new_key ) {
		return new WP_Error(
			'vbb_empty_key',
			__( 'Vertical key cannot be empty.', 'vertical-block-base' )
		);
	}

	$path = get_theme_file_path( 'config/active-vertical.json' );

	$config = array(
		'active'   => $new_key,
		'fallback' => 'default',
	);

	$json = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $json ) {
		return new WP_Error(
			'vbb_json_encode_error',
			__( 'Failed to encode active-vertical config.', 'vertical-block-base' )
		);
	}

	$written = file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	if ( false === $written ) {
		return new WP_Error(
			'vbb_config_write_failed',
			__( 'Could not write to config/active-vertical.json.', 'vertical-block-base' )
		);
	}

	return array(
		'active'   => $new_key,
		'fallback' => 'default',
		'path'     => $path,
	);
}
