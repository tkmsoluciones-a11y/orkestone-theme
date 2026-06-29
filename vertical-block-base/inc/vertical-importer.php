<?php
/**
 * Vertical import actions: pages, navigation, media and front page.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apply the front page declared by the active vertical.
 *
 * @return array
 */
function vbb_apply_vertical_front_page() {
	$config        = vbb_get_vertical_config();
	$import_options = isset( $config['importOptions'] ) && is_array( $config['importOptions'] ) ? $config['importOptions'] : array();
	$homepage_key  = isset( $import_options['homepageKey'] ) ? sanitize_key( $import_options['homepageKey'] ) : 'home';
	$set_front     = isset( $import_options['setFrontPage'] ) ? (bool) $import_options['setFrontPage'] : true;

	if ( ! $set_front ) {
		return array(
			'applied' => false,
			'reason'  => 'setFrontPage disabled',
		);
	}

	$page_config = vbb_get_vertical_page( $homepage_key );

	if ( ! is_array( $page_config ) || empty( $page_config['slug'] ) ) {
		return array(
			'applied' => false,
			'reason'  => 'homepageKey not found',
		);
	}

	$page = get_page_by_path( sanitize_title( $page_config['slug'] ), OBJECT, 'page' );

	if ( ! $page ) {
		return array(
			'applied' => false,
			'reason'  => 'page not found',
		);
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $page->ID );

	return array(
		'applied' => true,
		'pageId'  => (int) $page->ID,
		'slug'    => sanitize_title( $page_config['slug'] ),
	);
}

/**
 * Build block markup for a WordPress Navigation entity.
 *
 * Handles both 'custom' (URL-based) and 'post-type' (resolved page ID)
 * navigation link kinds. Items with kind 'post-type' require an 'id'
 * field referencing a published page.
 *
 * @param array $items Menu items with label, kind, url/id fields.
 * @return string
 */
function vbb_build_navigation_markup( $items ) {
	$content = '';

	foreach ( $items as $item ) {
		$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
		$kind  = isset( $item['kind'] ) ? $item['kind'] : 'custom';

		if ( '' === $label ) {
			continue;
		}

		if ( 'post-type' === $kind && isset( $item['id'] ) ) {
			$attrs = array(
				'label' => $label,
				'kind'  => 'post-type',
				'id'    => (int) $item['id'],
			);
		} else {
			$url = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';

			if ( '' === $url ) {
				continue;
			}

			$attrs = array(
				'label' => $label,
				'url'   => $url,
				'kind'  => 'custom',
			);
		}

		$content .= '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->' . "\n";
	}

	return $content;
}

/**
 * Create or update the OrkestOne Theme navigation menu.
 *
 * Two-pass navigation builder: if a page ID map is provided, resolves
 * navigation.primary items with url_slug references to actual published
 * page IDs (kind:post-type). Items without url_slug remain as kind:custom.
 *
 * The menu is always named 'OrkestOne Theme' and tagged with _vbb_source=vertical
 * so the reset orchestrator can find and trash it on vertical switches.
 *
 * @param array $page_id_map Optional. Page slug → ID map from vbb_generate_page_id_map().
 *                           When empty, falls back to custom-only links.
 * @return array{created: bool, updated: bool, navigationId: int, items: int, error?: string, reason?: string}
 */
function vbb_generate_vertical_navigation( $page_id_map = array() ) {
	$config    = vbb_get_vertical_config();
	$raw_items = vbb_array_get( $config, 'navigation.primary', array() );

	if ( ! is_array( $raw_items ) || empty( $raw_items ) ) {
		return array(
			'created' => false,
			'updated' => false,
			'reason'  => 'navigation.primary empty',
		);
	}

	if ( ! post_type_exists( 'wp_navigation' ) ) {
		return array(
			'created' => false,
			'updated' => false,
			'reason'  => 'wp_navigation post type unavailable',
		);
	}

	// Two-pass resolution: resolve url_slug references using the page ID map.
	if ( ! empty( $page_id_map ) ) {
		$items = vbb_resolve_navigation_page_ids( $raw_items, $page_id_map );
	} else {
		// Fall back to custom-only links — use literal URLs as-is.
		$items = array();
		foreach ( $raw_items as $item ) {
			$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
			$url   = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';

			if ( '' !== $label && '' !== $url ) {
				$items[] = array(
					'label' => $label,
					'url'   => $url,
					'kind'  => 'custom',
				);
			}
		}
	}

	if ( empty( $items ) ) {
		return array(
			'created' => false,
			'updated' => false,
			'reason'  => 'navigation.primary empty after resolution',
		);
	}

	$title     = 'OrkestOne Theme';
	$content   = vbb_build_navigation_markup( $items );
	$nav_slug  = sanitize_title( $title );

	// Look for existing OrkestOne Theme nav by slug to avoid duplicates.
	$existing = get_page_by_path( $nav_slug, OBJECT, 'wp_navigation' );

	// Fallback: search by title if slug lookup fails.
	if ( ! $existing ) {
		$existing = get_page_by_title( $title, OBJECT, 'wp_navigation' );
	}

	$post_data = array(
		'post_title'   => $title,
		'post_name'    => $nav_slug,
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_content' => $content,
	);

	if ( $existing ) {
		$post_data['ID'] = (int) $existing->ID;
		$post_id         = wp_update_post( wp_slash( $post_data ), true );
		$action          = 'updated';
	} else {
		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		$action  = 'created';
	}

	if ( is_wp_error( $post_id ) ) {
		return array(
			'created' => false,
			'updated' => false,
			'error'   => $post_id->get_error_message(),
		);
	}

	// Tag with _vbb_source so reset-orchestrator can find it.
	update_post_meta( (int) $post_id, '_vbb_source', 'vertical' );

	return array(
		'created'      => 'created' === $action,
		'updated'      => 'updated' === $action,
		'navigationId' => (int) $post_id,
		'items'        => count( $items ),
	);
}

/**
 * Extract media URLs from the active vertical JSON.
 *
 * @return array
 */
function vbb_get_vertical_media_items() {
	$config = vbb_get_vertical_config();
	$items  = array();
	$paths  = array(
		'graphics.images',
		'graphics.themeAssets',
		'graficos.imagenes',
		'graficos.assetsDelThemeOriginal',
	);

	foreach ( $paths as $path ) {
		$found = vbb_array_get( $config, $path, array() );

		if ( ! is_array( $found ) ) {
			continue;
		}

		foreach ( $found as $item ) {
			if ( empty( $item['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( $item['url'] );

			if ( isset( $items[ $url ] ) ) {
				continue;
			}

			$items[ $url ] = array(
				'url'   => $url,
				'title' => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : basename( wp_parse_url( $url, PHP_URL_PATH ) ),
				'alt'   => isset( $item['alt'] ) ? sanitize_text_field( $item['alt'] ) : '',
				'role'  => isset( $item['role'] ) ? sanitize_key( $item['role'] ) : '',
			);
		}
	}

	return array_values( $items );
}

/**
 * Find an already imported attachment by source URL.
 *
 * @param string $url Source URL.
 * @return int
 */
function vbb_find_attachment_by_source_url( $url ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_vbb_source_url',
					'value' => esc_url_raw( $url ),
				),
			),
		)
	);

	return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
}

/**
 * Import media URLs declared by active vertical into Media Library.
 *
 * @param int $limit Maximum items to try in one execution.
 * @return array
 */
function vbb_import_vertical_media( $limit = 25 ) {
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$summary = array(
		'imported' => array(),
		'skipped'  => array(),
		'errors'   => array(),
	);

	$items = array_slice( vbb_get_vertical_media_items(), 0, max( 1, absint( $limit ) ) );

	foreach ( $items as $item ) {
		$url = $item['url'];

		$existing_id = vbb_find_attachment_by_source_url( $url );

		if ( $existing_id ) {
			$summary['skipped'][] = array(
				'url' => $url,
				'id'  => $existing_id,
			);
			continue;
		}

		$attachment_id = media_sideload_image( $url, 0, $item['title'], 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			$summary['errors'][] = array(
				'url'   => $url,
				'error' => $attachment_id->get_error_message(),
			);
			continue;
		}

		update_post_meta( $attachment_id, '_vbb_source_url', $url );
		update_post_meta( $attachment_id, '_vbb_media_role', $item['role'] );

		if ( '' !== $item['alt'] ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $item['alt'] );
		}

		$summary['imported'][] = array(
			'url' => $url,
			'id'  => (int) $attachment_id,
		);
	}

	return $summary;
}

/**
 * Import media URLs declared by active vertical into Media Library,
 * creating SVG placeholder attachments on failure.
 *
 * Extends the base vbb_import_vertical_media() behavior: when
 * media_sideload_image() fails, it creates a placeholder attachment
 * from vbb_svg_placeholder() and marks it with _vbb_broken=1 and
 * _vbb_broken_url for audit in the import report.
 *
 * @param int   $limit  Maximum items to try in one execution (0 = no limit).
 * @param array $report Reference to the global import report accumulator.
 * @return array Partial summary for this batch.
 */
function vbb_import_vertical_media_with_placeholders( $limit = 25, &$report = array() ) {
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$summary = array(
		'imported' => array(),
		'skipped'  => array(),
		'errors'   => array(),
	);

	$all_items = vbb_get_vertical_media_items();
	$items     = $limit > 0 ? array_slice( $all_items, 0, max( 1, absint( $limit ) ) ) : $all_items;

	foreach ( $items as $item ) {
		$url = $item['url'];

		// Check for previously imported attachment.
		$existing_id = vbb_find_attachment_by_source_url( $url );

		if ( $existing_id ) {
			$summary['skipped'][] = array(
				'url' => $url,
				'id'  => $existing_id,
			);
			continue;
		}

		$attachment_id = media_sideload_image( $url, 0, $item['title'], 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			// Create SVG placeholder attachment on failure.
			$placeholder_id = vbb_create_placeholder_attachment( $url, $item['title'], $attachment_id->get_error_message() );

			if ( is_wp_error( $placeholder_id ) ) {
				$summary['errors'][] = array(
					'url'      => $url,
					'error'    => $attachment_id->get_error_message(),
					'fallback' => $placeholder_id->get_error_message(),
				);

				$report['failed'][] = array(
					'url'    => $url,
					'reason' => $attachment_id->get_error_message(),
				);
			} else {
				$summary['imported'][] = array(
					'url'        => $url,
					'id'         => (int) $placeholder_id,
					'is_placeholder' => true,
				);

				$report['failed'][] = array(
					'url'    => $url,
					'reason' => $attachment_id->get_error_message(),
				);
			}

			continue;
		}

		update_post_meta( $attachment_id, '_vbb_source_url', $url );
		update_post_meta( $attachment_id, '_vbb_media_role', $item['role'] );

		if ( '' !== $item['alt'] ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $item['alt'] );
		}

		$summary['imported'][] = array(
			'url' => $url,
			'id'  => (int) $attachment_id,
		);

		++$report['sideloaded'];
	}

	return $summary;
}

/**
 * Create a placeholder attachment from the SVG data URI.
 *
 * Called when media_sideload_image() fails. Registers a 1×1 transparent
 * SVG as an attachment with _vbb_broken=1 and _vbb_broken_url metadata
 * so the broken URL is traceable in the report.
 *
 * @param string $original_url The URL that failed to sideload.
 * @param string $title        Attachment title.
 * @param string $error_reason Reason from media_sideload_image().
 * @return int|WP_Error Attachment ID on success.
 */
function vbb_create_placeholder_attachment( $original_url, $title = '', $error_reason = '' ) {
	$placeholder_url = vbb_svg_placeholder();

	// Download the inline SVG data URI as a file.
	$tmp_file = download_url( $placeholder_url );

	if ( is_wp_error( $tmp_file ) ) {
		return $tmp_file;
	}

	$title = '' !== $title ? $title : basename( wp_parse_url( $original_url, PHP_URL_PATH ) );

	$file_array = array(
		'name'     => 'vbb-placeholder-' . sanitize_title( $title ) . '.svg',
		'tmp_name' => $tmp_file,
	);

	$attachment_id = media_handle_sideload( $file_array, 0, $title );

	if ( is_wp_error( $attachment_id ) ) {
		// Clean up temp file on error.
		if ( file_exists( $tmp_file ) ) {
			wp_delete_file( $tmp_file );
		}
		return $attachment_id;
	}

	update_post_meta( $attachment_id, '_vbb_source_url', $original_url );
	update_post_meta( $attachment_id, '_vbb_broken', 1 );
	update_post_meta( $attachment_id, '_vbb_broken_url', $original_url );

	if ( '' !== $error_reason ) {
		update_post_meta( $attachment_id, '_vbb_broken_reason', $error_reason );
	}

	return (int) $attachment_id;
}

/**
 * Merge the page ID map into navigation items.
 *
 * Takes navigation.primary items and, for each item with a url_slug
 * field, resolves it to a published page ID using the provided map.
 * Items without url_slug keep their original URL as kind:custom.
 *
 * @param array $items  Navigation items from vertical JSON.
 * @param array $id_map Page slug → ID map from vbb_generate_page_id_map().
 * @return array Updated items with resolved IDs.
 */
function vbb_resolve_navigation_page_ids( $items, $id_map ) {
	if ( ! is_array( $items ) ) {
		return array();
	}

	$resolved = array();

	foreach ( $items as $item ) {
		$entry = array(
			'label' => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
			'url'   => isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '',
		);

		// If url_slug is set and exists in the page map, use the resolved ID.
		if ( isset( $item['url_slug'] ) && '' !== $item['url_slug'] ) {
			$slug = sanitize_title( $item['url_slug'] );

			if ( isset( $id_map[ $slug ] ) ) {
				$entry['url']     = get_permalink( $id_map[ $slug ] );
				$entry['kind']    = 'post-type';
				$entry['id']      = $id_map[ $slug ];
			} else {
				$entry['url']     = '' !== $entry['url'] ? $entry['url'] : '#' . $slug;
				$entry['kind']    = 'custom';
			}
		} else {
			$entry['kind'] = 'custom';
		}

		if ( '' !== $entry['label'] ) {
			$resolved[] = $entry;
		}
	}

	return $resolved;
}

/**
 * Run the common import actions after activating/importing a vertical.
 *
 * @return array
 */
function vbb_import_active_vertical_blueprint() {
	return array(
		'pages'      => vbb_generate_vertical_pages(),
		'navigation' => vbb_generate_vertical_navigation(),
		'frontPage'  => vbb_apply_vertical_front_page(),
	);
}

/**
 * Full vertical import orchestrator.
 *
 * Chains the complete import lifecycle for a vertical JSON:
 *   1. Load the target vertical config.
 *   2. If different from current active, reset (trash) old content.
 *   3. Update active-vertical.json pointer.
 *   4. Import media with SVG placeholder fallback.
 *   5. Generate pages with baked block content.
 *   6. Generate navigation from navigation.primary.
 *   7. Apply front page from importOptions.homepageKey.
 *   8. Return structured report with counts and any errors.
 *
 * @param string $vertical_key Vertical key to import (e.g. 'ecommerce').
 * @return array{
 *   success: bool,
 *   vertical: string,
 *   reset: array|null,
 *   config_updated: bool,
 *   media: array,
 *   pages: array,
 *   navigation: array,
 *   frontPage: array,
 *   report: array
 * }
 */
function vbb_import_vertical_full( $vertical_key ) {
	$vertical_key = sanitize_key( $vertical_key );

	// ---- Assemble the global import report accumulator. ----
	$report = array(
		'sideloaded' => 0,
		'failed'     => array(),
	);

	// ---- 1. Load the target vertical config directly (not via cache). ----
	$config = vbb_load_vertical_by_key( $vertical_key );

	if ( null === $config ) {
		return array(
			'success'  => false,
			'vertical' => $vertical_key,
			'error'    => sprintf(
				'Vertical config not found for key: %s',
				$vertical_key
			),
		);
	}

	// ---- 2. Reset if switching verticals. ----
	$reset_result = null;

	if ( vbb_is_different_vertical( $vertical_key ) ) {
		$old_key      = vbb_get_active_vertical_key();
		$reset_result = vbb_reset_vertical_pages( $old_key );
	}

	// ---- 3. Update active-vertical.json. ----
	$config_updated = vbb_update_active_vertical_config( $vertical_key );

	if ( is_wp_error( $config_updated ) ) {
		return array(
			'success'  => false,
			'vertical' => $vertical_key,
			'error'    => $config_updated->get_error_message(),
			'reset'    => $reset_result,
		);
	}

	// Invalidate the cached config so subsequent calls reload the new active key.
	vbb_invalidate_vertical_cache();

	// ---- 4. Import media with placeholder fallback. ----
	$media_result = vbb_import_vertical_media_with_placeholders( 0, $report );

	// ---- 5. Generate pages with baked block content. ----
	$sections_config = isset( $config['sections'] ) && is_array( $config['sections'] )
		? $config['sections']
		: array();
	$pages_result    = vbb_generate_vertical_pages_from_baked( $config, $sections_config );

	// ---- 6. Two-pass navigation: resolve page IDs, then build nav. ----
	// After pages are created, build the slug→ID map so navigation items
	// with url_slug references resolve to actual published page IDs.
	$page_id_map = vbb_generate_page_id_map();
	$nav_result  = vbb_generate_vertical_navigation( $page_id_map );

	// ---- 7. Configure WooCommerce catalog mode (Store verticals). ----
	$woocommerce_result = vbb_setup_woocommerce_catalog( $config );

	// ---- 8. Apply front page. ----
	$front_result = vbb_apply_vertical_front_page();

	// ---- 9. Assemble and return the structured result. ----
	$report['pages_created']          = count( $pages_result['created'] );
	$report['pages_errors']           = count( $pages_result['errors'] );
	$report['media_sideloaded']       = $report['sideloaded'];
	$report['media_failed']           = count( $report['failed'] );
	$report['woocommerce_configured'] = ! empty( $woocommerce_result['configured'] );
	unset( $report['sideloaded'] );

	return array(
		'success'       => true,
		'vertical'      => $vertical_key,
		'reset'         => $reset_result,
		'configUpdated' => ! is_wp_error( $config_updated ),
		'media'         => $media_result,
		'pages'         => $pages_result,
		'navigation'    => $nav_result,
		'woocommerce'   => $woocommerce_result,
		'frontPage'     => $front_result,
		'report'        => $report,
	);
}

/**
 * Configure WooCommerce for catalog/showcase mode based on vertical config.
 *
 * Detects vertical.woocommerce.mode set to 'catalog' or 'vidriera' and:
 *   - Disables ordering/checkout via woocommerce_catalog_orders option.
 *   - Applies WordPress filters to hide add-to-cart buttons in loop and single.
 *   - Sets the shop page from woocommerce.shop_page if provided.
 *
 * If WooCommerce is not active, the pipeline continues gracefully and a
 * notice string is returned for display in the admin import report.
 *
 * @param array $config Vertical config array containing optional woocommerce section.
 * @return array{
 *   configured: bool,
 *   mode: string,
 *   wc_active: bool,
 *   notice: string,
 *   shop_page: int|null
 * }
 */
function vbb_setup_woocommerce_catalog( $config ) {
	$result = array(
		'configured' => false,
		'mode'       => '',
		'wc_active'  => false,
		'notice'     => '',
		'shop_page'  => null,
	);

	$mode = isset( $config['woocommerce']['mode'] ) ? strtolower( $config['woocommerce']['mode'] ) : '';

	// Only apply catalog mode for store-type verticals.
	if ( ! in_array( $mode, array( 'catalog', 'vidriera' ), true ) ) {
		return $result;
	}

	$result['mode'] = $mode;

	// Verify WooCommerce is active before applying WooCommerce-specific options.
	if ( ! class_exists( 'WooCommerce' ) ) {
		$result['notice'] = sprintf(
			/* translators: %s: configured mode */
			__( 'WooCommerce is not active. Catalog mode "%s" not applied — pipeline continues.', 'vertical-block-base' ),
			$mode
		);
		return $result;
	}

	$result['wc_active'] = true;

	// 1. Disable orders and checkout for catalog mode.
	update_option( 'woocommerce_catalog_orders', 'disabled' );

	// 2. Set shop page if specified in vertical JSON.
	if ( ! empty( $config['woocommerce']['shop_page'] ) ) {
		$shop_slug = sanitize_title( $config['woocommerce']['shop_page'] );
		$shop_page = get_page_by_path( $shop_slug, OBJECT, 'page' );

		if ( $shop_page ) {
			update_option( 'woocommerce_shop_page_id', (int) $shop_page->ID );
			$result['shop_page'] = (int) $shop_page->ID;
		}
	}

	// 3. Apply WordPress filters to hide purchase/cart functionality.
	add_filter( 'woocommerce_is_purchasable', '__return_false' );
	add_filter( 'woocommerce_loop_add_to_cart_link', '__return_empty_string' );
	add_filter( 'woocommerce_single_add_to_cart_button', '__return_empty_string' );
	add_filter( 'woocommerce_single_add_to_cart_text', '__return_empty_string' );
	add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );

	$result['configured'] = true;
	return $result;
}

/**
 * Invalidate cached vertical config so next vbb_get_vertical_config() reloads.
 *
 * @return void
 */
function vbb_invalidate_vertical_cache() {
	// vbb_get_vertical_config() uses a static $config cache.
	// We force a reload by clearing the option value that feeds
	// vbb_get_active_vertical_settings(), then any subsequent call
	// will re-read from the updated file.
	$settings = vbb_get_active_vertical_settings();
	update_option( 'vbb_active_vertical', $settings['active'] );
}

/**
 * Generate pages from a vertical config using the baked content builder.
 *
 * Unlike vbb_generate_vertical_pages(), this function:
 *   - Accepts an explicit config array (not just the active config).
 *   - Uses vbb_build_page_content_from_baked() instead of pattern refs.
 *   - Sets _vbb_vertical meta on each created page.
 *   - Updates existing pages with matching slugs instead of skipping them.
 *
 * @param array $config          Full vertical config.
 * @param array $sections_config Top-level sections config.
 * @return array{
 *   created: array,
 *   updated: array,
 *   errors: string[]
 * }
 */
function vbb_generate_vertical_pages_from_baked( $config, $sections_config = array() ) {
	$summary = array(
		'created' => array(),
		'updated' => array(),
		'errors'  => array(),
	);

	$vertical_key = isset( $config['verticalKey'] ) ? sanitize_key( $config['verticalKey'] ) : '';
	$pages        = isset( $config['pages'] ) && is_array( $config['pages'] ) ? $config['pages'] : array();

	foreach ( $pages as $page ) {
		$title = isset( $page['title'] ) ? sanitize_text_field( $page['title'] ) : '';
		$slug  = isset( $page['slug'] ) ? sanitize_title( $page['slug'] ) : '';

		if ( '' === $title || '' === $slug ) {
			$summary['errors'][] = 'Page skipped — title or slug missing.';
			continue;
		}

		$baked_content = vbb_build_page_content_from_baked( $page, $sections_config );

		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		$post_data = array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $baked_content,
		);

		if ( $existing ) {
			$post_data['ID'] = (int) $existing->ID;
			$post_id         = wp_update_post( wp_slash( $post_data ), true );
			$action          = 'updated';
		} else {
			$post_id = wp_insert_post( wp_slash( $post_data ), true );
			$action  = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			$summary['errors'][] = $slug . ': ' . $post_id->get_error_message();
			continue;
		}

		// Tag with vertical key for future reset.
		if ( '' !== $vertical_key ) {
			update_post_meta( $post_id, '_vbb_vertical', $vertical_key );
		}

		$summary[ $action ][] = array(
			'id'   => (int) $post_id,
			'slug' => $slug,
		);
	}

	return $summary;
}

/**
 * Register extra WP-CLI import commands.
 *
 * @return void
 */
function vbb_register_vertical_importer_wp_cli_commands() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	WP_CLI::add_command(
		'vbb generate-navigation',
		function() {
			WP_CLI::success( wp_json_encode( vbb_generate_vertical_navigation(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
	);

	WP_CLI::add_command(
		'vbb apply-front-page',
		function() {
			WP_CLI::success( wp_json_encode( vbb_apply_vertical_front_page(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
	);

	WP_CLI::add_command(
		'vbb import-media',
		function( $args, $assoc_args ) {
			$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 25;
			WP_CLI::success( wp_json_encode( vbb_import_vertical_media( $limit ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
	);

	WP_CLI::add_command(
		'vbb import-all',
		function() {
			WP_CLI::success( wp_json_encode( vbb_import_active_vertical_blueprint(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}
	);

	WP_CLI::add_command(
		'vbb import-full',
		function( $args, $assoc_args ) {
			$key = isset( $args[0] ) ? sanitize_key( $args[0] ) : vbb_get_active_vertical_key();
			$result = vbb_import_vertical_full( $key );

			if ( empty( $result['success'] ) ) {
				WP_CLI::error( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			} else {
				WP_CLI::success( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			}
		}
	);
}
add_action( 'after_setup_theme', 'vbb_register_vertical_importer_wp_cli_commands' );
