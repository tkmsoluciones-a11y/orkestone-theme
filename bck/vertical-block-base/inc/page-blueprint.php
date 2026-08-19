<?php
/**
 * Page blueprint generator.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map vertical section keys to pattern slugs.
 *
 * @return array
 */
function vbb_get_section_pattern_map() {
	return array(
		'hero'            => 'vertical-block-base/hero-default',
		'hero-centered'   => 'vertical-block-base/hero-centered',
		'services-grid'   => 'vertical-block-base/services-grid',
		'benefits'        => 'vertical-block-base/benefits',
		'process'         => 'vertical-block-base/process',
		'testimonials'    => 'vertical-block-base/testimonials',
		'faq'             => 'vertical-block-base/faq',
		'contact-section' => 'vertical-block-base/contact-section',
		'cta-final'       => 'vertical-block-base/cta-final',
	);
}

/**
 * Build Gutenberg content using section pattern references.
 *
 * @param array $page Page config.
 * @return string
 */
function vbb_build_page_content_from_blueprint( $page ) {
	$sections = isset( $page['sections'] ) && is_array( $page['sections'] ) ? $page['sections'] : array();

	if ( function_exists( 'vbb_pro_filter_sections' ) ) {
		$sections = vbb_pro_filter_sections( $sections );
	}

	$map      = vbb_get_section_pattern_map();
	$content  = '';

	foreach ( $sections as $section ) {
		if ( isset( $map[ $section ] ) ) {
			$content .= '<!-- wp:pattern {"slug":"' . esc_attr( $map[ $section ] ) . '"} /-->' . "

";
		}
	}

	if ( '' === trim( $content ) ) {
		$content = '<!-- wp:paragraph --><p>' . esc_html__( 'Contenido inicial de la página.', 'vertical-block-base' ) . '</p><!-- /wp:paragraph -->';
	}

	return $content;
}

/**
 * Build baked block content for a page using the section-to-baker pipeline.
 *
 * Replaces pattern-based rendering with real Gutenberg block markup
 * via vbb_bake_section(). Each section in the page config is dispatched
 * to its corresponding baker, which returns <!-- wp:group --> etc.
 *
 * @param array $page     Page config from vertical JSON (must contain 'sections').
 * @param array $sections Top-level sections config from vertical JSON.
 * @return string Gutenberg block markup ready for wp_insert_post().
 */
function vbb_build_page_content_from_baked( $page, $sections = array() ) {
	$raw_sections = isset( $page['sections'] ) && is_array( $page['sections'] )
		? $page['sections']
		: array();

	if ( function_exists( 'vbb_pro_filter_sections' ) ) {
		$raw_sections = vbb_pro_filter_sections( $raw_sections );
	}

	if ( empty( $raw_sections ) ) {
		return '<!-- wp:paragraph --><p>'
			. esc_html__( 'Contenido inicial de la página.', 'vertical-block-base' )
			. '</p><!-- /wp:paragraph -->';
	}

	$content = '';

	foreach ( $raw_sections as $section_type ) {
		$section_type = (string) $section_type;
		$content     .= vbb_bake_section( $section_type, $page, $sections ) . "\n\n";
	}

	return $content;
}

/**
 * Build a map of page slug → page ID for all published pages.
 *
 * Used to resolve navigation items that reference pages by url_slug
 * after page creation. Queries all published 'page' posts and returns
 * an associative array keyed by post_name.
 *
 * @return array<string, int> e.g. ['home' => 42, 'about' => 43].
 */
function vbb_generate_page_id_map() {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$map = array();

	foreach ( $pages as $page_id ) {
		$slug = get_post_field( 'post_name', $page_id );

		if ( is_string( $slug ) && '' !== $slug ) {
			$map[ $slug ] = (int) $page_id;
		}
	}

	return $map;
}

/**
 * Generate pages declared in active vertical config.
 *
 * @return array Summary of generated and skipped pages.
 */
function vbb_generate_vertical_pages() {
	$summary = array(
		'created' => array(),
		'skipped' => array(),
		'errors'  => array(),
	);

	foreach ( vbb_get_vertical_pages() as $page ) {
		$title = isset( $page['title'] ) ? sanitize_text_field( $page['title'] ) : '';
		$slug  = isset( $page['slug'] ) ? sanitize_title( $page['slug'] ) : '';

		if ( '' === $title || '' === $slug ) {
			$summary['errors'][] = 'Page skipped because title or slug is missing.';
			continue;
		}

		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		if ( $existing ) {
			$summary['skipped'][] = $slug;
			continue;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => vbb_build_page_content_from_blueprint( $page ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$summary['errors'][] = $slug . ': ' . $post_id->get_error_message();
			continue;
		}

		$active_key = vbb_get_active_vertical_key();

		if ( '' !== $active_key ) {
			update_post_meta( $post_id, '_vbb_vertical', $active_key );
		}

		$summary['created'][] = array(
			'slug'     => $slug,
			'sections' => function_exists( 'vbb_pro_filter_sections' ) && isset( $page['sections'] ) && is_array( $page['sections'] ) ? vbb_pro_filter_sections( $page['sections'] ) : ( isset( $page['sections'] ) && is_array( $page['sections'] ) ? array_values( $page['sections'] ) : array() ),
		);
	}

	return $summary;
}

/**
 * Register WP-CLI command when available.
 *
 * @return void
 */
function vbb_register_wp_cli_commands() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	WP_CLI::add_command(
		'vbb generate-pages',
		function() {
			$summary = vbb_generate_vertical_pages();
			WP_CLI::success( wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
	);
}
add_action( 'after_setup_theme', 'vbb_register_wp_cli_commands' );
