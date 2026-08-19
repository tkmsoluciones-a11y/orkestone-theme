<?php
/**
 * Vertical JSON storage helpers.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the supported vertical JSON schema version.
 *
 * Returns the SemVer string representing the schema version this theme
 * supports. Used by the activation endpoint to check compatibility with
 * Hub-generated configurations (REQ-AH21).
 *
 * Bump this when the vertical JSON schema changes in a breaking way:
 * - Major bump (e.g., 1.0.0 → 2.0.0): Hub configs with higher major are rejected.
 * - Minor/patch bump (e.g., 1.0.0 → 1.1.0): Backward compatible, allowed.
 *
 * @return string SemVer schema version.
 */
function vbb_get_schema_version(): string {
	/**
	 * Filter the supported schema version.
	 *
	 * Allows theme developers or child themes to override the schema version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version The schema version string.
	 */
	return apply_filters( 'vbb_schema_version', '1.0.0' );
}

/**
 * Get writable upload directory for imported vertical JSON files.
 *
 * @return array
 */
function vbb_get_imported_verticals_directory() {
	$uploads = wp_upload_dir();
	$basedir = trailingslashit( $uploads['basedir'] ) . 'vertical-block-base/verticals';
	$baseurl = trailingslashit( $uploads['baseurl'] ) . 'vertical-block-base/verticals';

	return array(
		'path' => $basedir,
		'url'  => $baseurl,
	);
}

/**
 * Return all directories where vertical JSON files may live.
 * Imported verticals are first so they can override bundled examples.
 *
 * @return array
 */
function vbb_get_vertical_directories() {
	$imported = vbb_get_imported_verticals_directory();

	return array(
		'imported' => $imported['path'],
		'theme'    => get_theme_file_path( 'config/verticals' ),
	);
}

/**
 * Find a vertical JSON file by key.
 *
 * @param string $vertical_key Vertical key.
 * @return string|null
 */
function vbb_find_vertical_file_path( $vertical_key ) {
	$vertical_key = sanitize_key( $vertical_key );

	foreach ( vbb_get_vertical_directories() as $directory ) {
		$path = trailingslashit( $directory ) . $vertical_key . '.json';

		if ( file_exists( $path ) && is_readable( $path ) ) {
			return $path;
		}
	}

	return null;
}

/**
 * List available vertical JSON files.
 *
 * @return array
 */
function vbb_list_available_verticals() {
	$verticals = array();

	foreach ( vbb_get_vertical_directories() as $source => $directory ) {
		if ( ! is_dir( $directory ) ) {
			continue;
		}

		$files = glob( trailingslashit( $directory ) . '*.json' );

		if ( ! is_array( $files ) ) {
			continue;
		}

		foreach ( $files as $file ) {
			$key = sanitize_key( basename( $file, '.json' ) );

			if ( isset( $verticals[ $key ] ) ) {
				continue;
			}

			$config = vbb_read_json_file( $file );
			$name   = isset( $config['name'] ) ? sanitize_text_field( $config['name'] ) : $key;

			$verticals[ $key ] = array(
				'key'    => $key,
				'name'   => $name,
				'source' => $source,
				'path'   => $file,
				'valid'  => is_array( $config ) && vbb_validate_vertical_config( $config ),
			);
		}
	}

	ksort( $verticals );

	return $verticals;
}

/**
 * Persist an imported vertical JSON file into uploads.
 *
 * @param array $config Vertical config.
 * @return array|WP_Error
 */
function vbb_save_imported_vertical_config( $config ) {
	if ( ! is_array( $config ) || empty( $config['verticalKey'] ) ) {
		return new WP_Error( 'vbb_missing_vertical_key', __( 'El JSON no contiene verticalKey.', 'vertical-block-base' ) );
	}

	if ( ! vbb_validate_vertical_config( $config ) ) {
		return new WP_Error( 'vbb_invalid_vertical_config', __( 'El JSON no cumple la estructura mínima de una vertical.', 'vertical-block-base' ) );
	}

	$directory = vbb_get_imported_verticals_directory();

	if ( ! wp_mkdir_p( $directory['path'] ) ) {
		return new WP_Error( 'vbb_directory_not_writable', __( 'No se pudo crear la carpeta de verticales importadas en uploads.', 'vertical-block-base' ) );
	}

	$key  = sanitize_key( $config['verticalKey'] );
	$path = trailingslashit( $directory['path'] ) . $key . '.json';
	$json = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( false === $json ) {
		return new WP_Error( 'vbb_json_encode_error', __( 'No se pudo volver a codificar el JSON.', 'vertical-block-base' ) );
	}

	$result = file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	if ( false === $result ) {
		return new WP_Error( 'vbb_write_error', __( 'No se pudo guardar el JSON importado.', 'vertical-block-base' ) );
	}

	return array(
		'key'  => $key,
		'path' => $path,
	);
}
