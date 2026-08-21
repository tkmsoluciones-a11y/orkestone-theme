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

/**
 * Resolve a vertical image to its best available URL.
 *
 * Preference:
 * 1. Valid attachment ID in Media Library.
 * 2. Original remote URL from JSON.
 * 3. Transparent SVG placeholder.
 *
 * @param int    $image_id   Attachment ID (0 if not available).
 * @param string $remote_url Original URL from JSON.
 * @return string Resolved URL.
 */
function vbb_resolve_image_url( $image_id, $remote_url ) {
	if ( $image_id && wp_get_attachment_url( $image_id ) ) {
		return wp_get_attachment_url( $image_id );
	}

	if ( ! empty( $remote_url ) ) {
		// If it's a local path (starts with assets/), prefix it with the theme directory URI.
		if ( strpos( $remote_url, 'assets/' ) === 0 ) {
			return get_template_directory_uri() . '/' . $remote_url;
		}
		return esc_url_raw( $remote_url );
	}

	return vbb_svg_placeholder();
}

/**
 * Normalize a section type identifier to canonical kebab-case.
 *
 * Vertical JSONs may declare sections in camelCase ("heroStyleD"),
 * snake_case ("hero_style_d") or kebab-case ("hero-style-d"). The baker
 * map and block registry are keyed in kebab-case, so every entry point
 * must canonicalize before lookup. sanitize_key() alone is lossy for
 * camelCase ("heroStyleD" becomes "herostyled"), which breaks matching.
 *
 * @param string $type Raw section type from JSON or stored content.
 * @return string Canonical kebab-case slug.
 */
function vbb_normalize_section_type( $type ) {
	$type  = str_replace( array( '_', ' ' ), '-', trim( (string) $type ) );
	$kebab = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $type ) );
	return sanitize_key( $kebab );
}

/**
 * Return every plausible array-key spelling of a section type.
 *
 * Section data in vertical JSONs may be keyed camelCase ("heroStyleD")
 * while the dispatcher receives the canonical kebab form ("hero-style-d"),
 * or vice versa. Callers looking up per-section data should probe all
 * variants so content resolves regardless of which casing the JSON uses.
 *
 * @param string $type Raw or canonical section type.
 * @return string[] Unique candidate keys.
 */
function vbb_section_type_variants( $type ) {
	$canonical = vbb_normalize_section_type( $type );
	$variants  = array( $canonical );

	// kebab-case → camelCase ("hero-style-d" → "heroStyleD").
	$camel = lcfirst( str_replace( '-', '', ucwords( $canonical, '-' ) ) );
	if ( '' !== $camel ) {
		$variants[] = $camel;
	}

	// Preserve the incoming spelling as-is (may be snake_case or other).
	$raw = trim( (string) $type );
	if ( '' !== $raw ) {
		$variants[] = $raw;
	}

	return array_values( array_unique( $variants ) );
}
