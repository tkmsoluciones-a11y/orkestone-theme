<?php
/**
 * Generic helpers for Vertical Block Base.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write controlled warnings without breaking the site.
 *
 * @param string $message Warning message.
 * @return void
 */
function vbb_log_warning( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[Vertical Block Base] ' . sanitize_text_field( (string) $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * Read and decode a JSON file.
 *
 * @param string $path Absolute path.
 * @return array|null
 */
function vbb_read_json_file( $path ) {
	if ( ! is_readable( $path ) ) {
		vbb_log_warning( 'JSON file not readable: ' . $path );
		return null;
	}

	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( false === $raw ) {
		vbb_log_warning( 'JSON file could not be read: ' . $path );
		return null;
	}

	$data = json_decode( $raw, true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
		vbb_log_warning( 'Invalid JSON file: ' . $path . ' — ' . json_last_error_msg() );
		return null;
	}

	return $data;
}

/**
 * Safely read a nested value from an array using dot notation.
 *
 * @param array  $array Source array.
 * @param string $path Dot path.
 * @param mixed  $default Default value.
 * @return mixed
 */
function vbb_array_get( $array, $path, $default = null ) {
	if ( ! is_array( $array ) || '' === $path ) {
		return $default;
	}

	$segments = explode( '.', $path );
	$current  = $array;

	foreach ( $segments as $segment ) {
		if ( is_array( $current ) && array_key_exists( $segment, $current ) ) {
			$current = $current[ $segment ];
			continue;
		}

		return $default;
	}

	return $current;
}

/**
 * Escape a vertical text value.
 *
 * @param mixed $value Value to escape.
 * @return string
 */
function vbb_esc_text( $value ) {
	return esc_html( is_scalar( $value ) ? (string) $value : '' );
}

/**
 * Escape a vertical URL value.
 *
 * @param mixed $value Value to escape.
 * @return string
 */
function vbb_esc_url_value( $value ) {
	return esc_url( is_scalar( $value ) ? (string) $value : '' );
}

/**
 * Convert an arbitrary text string to a safe CSS class suffix.
 *
 * @param string $value Input value.
 * @return string
 */
function vbb_slugify( $value ) {
	return sanitize_title( (string) $value );
}

/**
 * Return a 1×1 transparent SVG data URI for use as placeholder image.
 *
 * @return string
 */
function vbb_svg_placeholder() {
	return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1' height='1'/%3E";
}
