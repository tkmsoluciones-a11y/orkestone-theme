<?php
/**
 * Pro Elite settings storage and sanitization.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VBB_PRO_SETTINGS_OPTION        = 'vbb_pro_settings';
const VBB_PRO_PAGE_SETTINGS_OPTION    = 'vbb_pro_page_settings';
const VBB_PRO_PROFILES_OPTION         = 'vbb_pro_saved_profiles';
const VBB_PRO_ACTIVE_PROFILE_OPTION   = 'vbb_pro_active_profile';
const VBB_PRO_SETTINGS_VERSION_KEY    = 'vbb_pro_settings_version';

/**
 * Get the current settings version (Unix timestamp).
 * Defaults to '0' on fresh install.
 *
 * @return string Version string (timestamp or '0').
 */
function vbb_pro_get_settings_version(): string {
	return (string) get_option( VBB_PRO_SETTINGS_VERSION_KEY, '0' );
}

/**
 * Atomically bump the settings version to the current Unix timestamp.
 * This invalidates all cached page settings globally.
 *
 * @return void
 */
function vbb_pro_increment_settings_version(): void {
	update_option( VBB_PRO_SETTINGS_VERSION_KEY, (string) time(), false );
}

/**
 * Get settings for a specific page, merged with global defaults.
 */
function vbb_pro_get_page_settings( $page_id ) {
	$page_id = (int) $page_id;
	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	$global_settings   = vbb_pro_get_settings();

	if ( ! isset( $all_page_settings[ $page_id ] ) ) {
		return $global_settings;
	}

	// Only extract blocks.*.enabled toggles from page-specific — never full block content
	// (which would be stale and override global updates).
	$page_overrides = $all_page_settings[ $page_id ];
	$safe_overrides = array();

	if ( isset( $page_overrides['sections'] ) && is_array( $page_overrides['sections'] ) ) {
		$safe_overrides['sections'] = $page_overrides['sections'];
	}

	if ( isset( $page_overrides['blocks'] ) && is_array( $page_overrides['blocks'] ) ) {
		$safe_overrides['blocks'] = array();
		foreach ( $page_overrides['blocks'] as $key => $block ) {
			if ( is_array( $block ) && isset( $block['enabled'] ) ) {
				$safe_overrides['blocks'][ $key ] = array( 'enabled' => !!$block['enabled'] );
			}
		}
	}

	return vbb_pro_deep_merge( $global_settings, $safe_overrides );
}

/**
 * Get cached page settings with version-based transient.
 * Falls through to vbb_pro_get_page_settings() on cache miss.
 * Bypasses cache entirely when VBB_PRO_CACHE_DISABLED is truthy.
 *
 * @param int $page_id Page ID.
 * @return array Resolved page settings.
 */
function vbb_pro_get_cached_page_settings( int $page_id ): array {
	if ( defined( 'VBB_PRO_CACHE_DISABLED' ) && VBB_PRO_CACHE_DISABLED ) {
		return vbb_pro_get_page_settings( $page_id );
	}

	$page_id   = $page_id;
	$version   = vbb_pro_get_settings_version();
	$cache_key = 'vbb_page_settings_' . $page_id . '_' . $version;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached && is_array( $cached ) ) {
		if ( defined( 'VBB_PRO_CACHE_DEBUG' ) && VBB_PRO_CACHE_DEBUG ) {
			do_action( 'vbb_pro_cache_log', 'HIT', $cache_key );
		}
		return $cached;
	}

	if ( defined( 'VBB_PRO_CACHE_DEBUG' ) && VBB_PRO_CACHE_DEBUG ) {
		do_action( 'vbb_pro_cache_log', 'MISS', $cache_key );
	}

	$settings = vbb_pro_get_page_settings( $page_id );
	set_transient( $cache_key, $settings, 12 * HOUR_IN_SECONDS );
	return $settings;
}

/**
 * Save settings for a specific page.
 */
function vbb_pro_update_page_settings( $page_id, $settings ) {
	$page_id           = (int) $page_id;
	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	
	$current_page_settings = isset( $all_page_settings[ $page_id ] ) ? $all_page_settings[ $page_id ] : array();
	
	// Strip old block content from existing settings — only keep 'enabled' toggles and 'sections'
	// This prevents stale hero/title/etc data from page-specific from overriding global updates.
	if ( isset( $current_page_settings['blocks'] ) && is_array( $current_page_settings['blocks'] ) ) {
		$clean_blocks = array();
		foreach ( $current_page_settings['blocks'] as $key => $block ) {
			if ( is_array( $block ) && isset( $block['enabled'] ) ) {
				$clean_blocks[ $key ] = array( 'enabled' => !!$block['enabled'] );
			}
		}
		$current_page_settings['blocks'] = $clean_blocks;
	}
	
	// Merge current (cleaned) settings with the new overrides
	$updated_settings = vbb_pro_deep_merge( $current_page_settings, $settings );
	
	$all_page_settings[ $page_id ] = $updated_settings;
	
	update_option( VBB_PRO_PAGE_SETTINGS_OPTION, $all_page_settings, false );
	vbb_pro_increment_settings_version();
	return $updated_settings;
}


/**
 * Get the list of allowed per-block color keys.
 *
 * @return string[] Array of 7 color keys matching the palette.
 */
function vbb_pro_block_color_keys() {
	return array( 'primary', 'secondary', 'accent', 'background', 'surface', 'text', 'mutedText' );
}

function vbb_pro_default_settings() {
	$config = function_exists( 'vbb_get_vertical_config' ) ? vbb_get_vertical_config() : array();
	$brand  = isset( $config['brand'] ) && is_array( $config['brand'] ) ? $config['brand'] : array();

	$light_primary    = $brand['primaryColor'] ?? '#0F1724';
	$light_secondary  = $brand['secondaryColor'] ?? '#C6A163';
	$light_accent     = $brand['accentColor'] ?? '#F4F1EC';
	$light_background = $brand['backgroundColor'] ?? '#FFFFFF';

	// Extract content data from vertical JSON home page for block defaults.
	$vertical_hero_data = array();
	$vertical_cta_final = array();
	$vertical_contact   = array();
	$vertical_sections  = array();

	if ( ! empty( $config['pages'] ) && is_array( $config['pages'] ) ) {
		// Find home page (key === 'home' or first page).
		$home_page = null;
		foreach ( $config['pages'] as $page ) {
			if ( isset( $page['key'] ) && 'home' === $page['key'] ) {
				$home_page = $page;
				break;
			}
		}
		if ( ! $home_page && ! empty( $config['pages'][0] ) ) {
			$home_page = $config['pages'][0];
		}

		if ( is_array( $home_page ) ) {
			// Hero data from home page.
			if ( isset( $home_page['hero'] ) && is_array( $home_page['hero'] ) ) {
				$vertical_hero_data = array(
					'title'       => $home_page['hero']['title'] ?? '',
					'subtitle'    => $home_page['hero']['subtitle'] ?? '',
					'eyebrow'     => $home_page['hero']['eyebrow'] ?? '',
					'primaryCta'  => $home_page['hero']['primaryCta'] ?? '',
					'primaryUrl'  => $home_page['hero']['primaryUrl'] ?? '#',
					'tagline'     => $home_page['hero']['tagline'] ?? $home_page['hero']['subtitle'] ?? '',
				);
			}

			// Other section data from home page (sections that have data at page level).
			foreach ( array( 'servicesGrid', 'benefits', 'process', 'testimonials', 'faq', 'pricing', 'team', 'logoCloud', 'contact', 'stats', 'gallery', 'video', 'newsletter', 'map', 'comparison', 'blog' ) as $section_key ) {
				$section_slug = strtolower( str_replace( array( 'Grid', 'Final', 'Cloud' ), array( '-grid', '-final', '-cloud' ), $section_key ) );
				$section_slug = preg_replace_callback( '/([a-z])([A-Z])/', function( $m ) { return $m[1] . '-' . strtolower( $m[2] ); }, $section_slug );
				if ( isset( $home_page[ $section_slug ] ) && is_array( $home_page[ $section_slug ] ) ) {
					$vertical_sections[ $section_key ] = $home_page[ $section_slug ];
				}
			}
		}
	}

	// CTA Final from vertical config root.
	if ( isset( $config['cta']['final'] ) && is_array( $config['cta']['final'] ) ) {
		$vertical_cta_final = array(
			'text'        => $config['cta']['final']['text'] ?? '',
			'buttonText'  => $config['cta']['final']['buttonText'] ?? '',
			'buttonUrl'   => $config['cta']['final']['buttonUrl'] ?? '#',
			'subtitle'    => $config['cta']['final']['subtitle'] ?? '',
		);
	}

	// Contact from vertical config root.
	if ( isset( $config['contact'] ) && is_array( $config['contact'] ) ) {
		$vertical_contact = array(
			'email' => $config['contact']['email'] ?? '',
			'phone' => $config['contact']['phone'] ?? '',
		);
	}

	// Section headings from vertical config sections.
	foreach ( array( 'benefits', 'process', 'testimonials', 'faq', 'pricing', 'team', 'logoCloud', 'stats', 'gallery', 'video', 'newsletter', 'map', 'comparison', 'blog' ) as $section_key ) {
		if ( isset( $config['sections'][ $section_key ] ) && is_array( $config['sections'][ $section_key ] ) ) {
			$section_data = $config['sections'][ $section_key ];
			$block_key = $section_key;
			// Map section key to block key.
			$block_key_map = array(
				'services-grid' => 'servicesGrid',
				'logoCloud'     => 'logoCloud',
				'pricing'       => 'pricing',
				'team'          => 'team',
				'stats'         => 'stats',
				'gallery'       => 'gallery',
				'video'         => 'video',
				'newsletter'    => 'newsletter',
				'map'           => 'map',
				'comparison'    => 'comparison',
				'blog'          => 'blog',
				'divider'       => 'divider',
			);
			if ( isset( $block_key_map[ $section_key ] ) ) {
				$block_key = $block_key_map[ $section_key ];
			}
			if ( isset( $section_data['heading'] ) ) {
				$vertical_sections[ $block_key ] = array_merge( $vertical_sections[ $block_key ] ?? array(), array( 'heading' => $section_data['heading'] ) );
			}
		}
	}

	$block_keys = array( 'hero', 'heroCentered', 'servicesGrid', 'benefits', 'process', 'testimonials', 'faq', 'contact', 'ctaFinal', 'logoCloud', 'pricing', 'team', 'stats', 'gallery', 'video', 'newsletter', 'map', 'comparison', 'blog', 'divider' );
	$blocks     = array();
	foreach ( $block_keys as $bk ) {
		$block_defaults = array(
			'enabled' => true,
			'style'   => 'A',
			'colors'  => array(),
		);

		// Merge vertical JSON data as defaults.
		if ( 'hero' === $bk ) {
			$block_defaults = array_merge( $block_defaults, $vertical_hero_data );
		} elseif ( 'ctaFinal' === $bk ) {
			$block_defaults = array_merge( $block_defaults, $vertical_cta_final );
		} elseif ( 'contact' === $bk ) {
			$block_defaults = array_merge( $block_defaults, $vertical_contact );
		} elseif ( isset( $vertical_sections[ $bk ] ) ) {
			$block_defaults = array_merge( $block_defaults, $vertical_sections[ $bk ] );
		}

		$blocks[ $bk ] = $block_defaults;
	}

	return array(
		'profileName' => 'Default Pro Elite',
		'colorMode'   => 'light',
		'siteConfig'  => array(
			'type' => 'landing',
		),
		'headerConfig' => array(
			'logoUrl'   => '',
			'siteTitle' => 'Mi Empresa',
			'menuType'  => 'logo-title', // logo-only, logo-title, title-only
		),
		'menuConfig'  => array(
			'type'  => 'standard',
			'style' => 'modern',
		),
		'palettes'    => array(

			'light' => array(
				'primary'    => $light_primary,
				'secondary'  => $light_secondary,
				'accent'     => $light_accent,
				'background' => $light_background,
				'surface'    => '#F7F3ED',
				'text'       => '#172033',
				'mutedText'  => '#667085',
			),
			'dark'  => array(
				'primary'    => '#F4E6C8',
				'secondary'  => $light_secondary,
				'accent'     => '#1E2A3A',
				'background' => '#0F1724',
				'surface'    => '#152033',
				'text'       => '#E7EDF5',
				'mutedText'  => '#A8B3C4',
			),
		),
		'colors'      => array(
			'primary'    => $light_primary,
			'secondary'  => $light_secondary,
			'accent'     => $light_accent,
			'background' => $light_background,
			'text'       => '#172033',
		),
		'typography'  => array(
			'heading' => $brand['fontHeading'] ?? 'Georgia, Times New Roman, serif',
			'body'    => $brand['fontBody'] ?? 'Inter, Arial, sans-serif',
		),
		'layout'      => array(
			'contentWidth' => '1180px',
			'wideWidth'    => '1440px',
			'radius'       => '24px',
			'shadow'       => 'soft',
			'spacingScale' => 'comfortable',
		),
		'blocks'      => $blocks,
		'buttons'     => array(
			'style'     => 'pill',
			'uppercase' => false,
		),
		'menuItems'   => array(),
	);
}

function vbb_pro_deep_merge( $base, $override ) {
	foreach ( $override as $key => $value ) {
		if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
			$base[ $key ] = vbb_pro_deep_merge( $base[ $key ], $value );
		} else {
			$base[ $key ] = $value;
		}
	}
	return $base;
}

function vbb_pro_sanitize_hex( $value, $fallback ) {
	$value = sanitize_hex_color( $value );
	return $value ? $value : $fallback;
}

function vbb_pro_sanitize_size( $value, $fallback ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( preg_match( '/^\d+(\.\d+)?(px|rem|em|%)$/', $value ) ) {
		return $value;
	}
	return $fallback;
}

function vbb_pro_sanitize_settings( $settings ) {
	$defaults = vbb_pro_default_settings();
	$settings = is_array( $settings ) ? vbb_pro_deep_merge( $defaults, $settings ) : $defaults;

	// Backward compatibility: old v0.3.0/v0.3.1 profiles had only colors[].
	if ( ! empty( $settings['colors'] ) && is_array( $settings['colors'] ) ) {
		foreach ( $defaults['colors'] as $key => $fallback ) {
			if ( isset( $settings['colors'][ $key ] ) && empty( $settings['palettes']['light'][ $key ] ) ) {
				$settings['palettes']['light'][ $key ] = $settings['colors'][ $key ];
			}
		}
	}

	$out = $defaults;
	$out['profileName'] = sanitize_text_field( $settings['profileName'] ?? $defaults['profileName'] );
	$out['colorMode']   = in_array( $settings['colorMode'] ?? 'light', array( 'light', 'dark', 'auto' ), true ) ? $settings['colorMode'] : 'light';
	
	$out['siteConfig'] = array(
		'type' => in_array( $settings['siteConfig']['type'] ?? 'landing', array( 'landing', 'multi' ), true ) ? $settings['siteConfig']['type'] : 'landing',
	);
	$out['headerConfig'] = array(
		'logoUrl'   => esc_url_raw( $settings['headerConfig']['logoUrl'] ?? '' ),
		'siteTitle' => sanitize_text_field( $settings['headerConfig']['siteTitle'] ?? $defaults['headerConfig']['siteTitle'] ),
		'menuType'  => in_array( $settings['headerConfig']['menuType'] ?? 'logo-title', array( 'logo-only', 'logo-title', 'title-only' ), true ) ? $settings['headerConfig']['menuType'] : 'logo-title',
	);
	$out['menuConfig'] = array(
		'type'  => in_array( $settings['menuConfig']['type'] ?? 'standard', array( 'standard', 'hamburger', 'sticky' ), true ) ? $settings['menuConfig']['type'] : 'standard',
		'style' => sanitize_text_field( $settings['menuConfig']['style'] ?? $defaults['menuConfig']['style'] ),
	);

	foreach ( array( 'light', 'dark' ) as $mode ) {
		foreach ( $defaults['palettes'][ $mode ] as $key => $fallback ) {
			$out['palettes'][ $mode ][ $key ] = vbb_pro_sanitize_hex( $settings['palettes'][ $mode ][ $key ] ?? $fallback, $fallback );
		}
	}

	// Legacy alias mirrors active/default palette for older code and exports.
	$alias_palette = 'dark' === $out['colorMode'] ? $out['palettes']['dark'] : $out['palettes']['light'];
	foreach ( $defaults['colors'] as $key => $fallback ) {
		$out['colors'][ $key ] = $alias_palette[ $key ] ?? $fallback;
	}

	$out['typography']['heading'] = sanitize_text_field( $settings['typography']['heading'] ?? $defaults['typography']['heading'] );
	$out['typography']['body']    = sanitize_text_field( $settings['typography']['body'] ?? $defaults['typography']['body'] );

	$out['layout']['contentWidth'] = vbb_pro_sanitize_size( $settings['layout']['contentWidth'] ?? $defaults['layout']['contentWidth'], $defaults['layout']['contentWidth'] );
	$out['layout']['wideWidth']    = vbb_pro_sanitize_size( $settings['layout']['wideWidth'] ?? $defaults['layout']['wideWidth'], $defaults['layout']['wideWidth'] );
	$out['layout']['radius']       = vbb_pro_sanitize_size( $settings['layout']['radius'] ?? $defaults['layout']['radius'], $defaults['layout']['radius'] );
	$out['layout']['shadow']       = in_array( $settings['layout']['shadow'] ?? 'soft', array( 'none', 'soft', 'medium', 'strong' ), true ) ? $settings['layout']['shadow'] : 'soft';
	$out['layout']['spacingScale'] = in_array( $settings['layout']['spacingScale'] ?? 'comfortable', array( 'compact', 'comfortable', 'wide' ), true ) ? $settings['layout']['spacingScale'] : 'comfortable';

	// NEW: Blocks as objects instead of booleans
	foreach ( $defaults['blocks'] as $key => $fallback ) {
		$block_val = $settings['blocks'][ $key ] ?? $fallback;
		if ( is_array( $block_val ) ) {
			$out['blocks'][ $key ] = $block_val; // Keep as object if already provided
		} else {
			// Convert boolean to object for consistency
			$out['blocks'][ $key ] = array( 'enabled' => ! empty( $block_val ) );
		}
	}

	// Sanitize per-block colors
	$allowed_color_keys = vbb_pro_block_color_keys();
	foreach ( $out['blocks'] as $bk => &$block ) {
		if ( is_array( $block ) && isset( $block['colors'] ) && is_array( $block['colors'] ) ) {
			$sanitized_colors = array();
			foreach ( $allowed_color_keys as $ckey ) {
				$val = isset( $block['colors'][ $ckey ] ) ? $block['colors'][ $ckey ] : '';
				$sanitized_colors[ $ckey ] = ( '' !== $val ) ? ( sanitize_hex_color( $val ) ?: '' ) : '';
			}
			$block['colors'] = $sanitized_colors;
		} elseif ( is_array( $block ) && ! isset( $block['colors'] ) ) {
			// Backward compat: blocks without colors get empty colors array
			$block['colors'] = array();
		}
	}
	unset( $block );

	// Style validation — only A, B, C allowed
	foreach ( $out['blocks'] as $bk => &$block ) {
		if ( is_array( $block ) ) {
			$style         = isset( $block['style'] ) ? $block['style'] : 'A';
			$block['style'] = in_array( (string) $style, array( 'A', 'B', 'C' ), true ) ? (string) $style : 'A';
		}
	}
	unset( $block );

	$out['buttons']['style']     = in_array( $settings['buttons']['style'] ?? 'pill', array( 'pill', 'rounded', 'square', 'outline' ), true ) ? $settings['buttons']['style'] : 'pill';
	$out['buttons']['uppercase'] = ! empty( $settings['buttons']['uppercase'] );

	// Menu items — recursive sanitization
	if ( isset( $settings['menuItems'] ) && is_array( $settings['menuItems'] ) ) {
		$out['menuItems'] = vbb_pro_sanitize_menu_items( $settings['menuItems'] );
	} else {
		$out['menuItems'] = array();
	}

	return $out;
}

/**
 * Recursively sanitize a menu items array.
 *
 * @param array $items Menu items to sanitize.
 * @return array Sanitized menu items.
 */
function vbb_pro_sanitize_menu_items( $items ) {
	if ( ! is_array( $items ) ) {
		return array();
	}

	$sanitized = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$sanitized_item = array(
			'id'            => sanitize_key( isset( $item['id'] ) ? $item['id'] : 'menu_' . uniqid() ),
			'label'         => sanitize_text_field( isset( $item['label'] ) ? $item['label'] : '' ),
			'type'          => in_array( isset( $item['type'] ) ? $item['type'] : 'custom', array( 'page', 'custom' ), true ) ? $item['type'] : 'custom',
			'url'           => esc_url_raw( isset( $item['url'] ) ? $item['url'] : '' ),
			'targetPageId'  => absint( isset( $item['targetPageId'] ) ? $item['targetPageId'] : 0 ),
			'children'      => array(),
		);

		if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
			$sanitized_item['children'] = vbb_pro_sanitize_menu_items( $item['children'] );
		}

		$sanitized[] = $sanitized_item;
	}

	return $sanitized;
}

/**
 * Build a single wp:navigation-link block from a menu item array.
 *
 * @param array $item Menu item with label, type, url, targetPageId.
 * @return string Block markup.
 */
function vbb_pro_build_nav_block( $item ) {
	if ( empty( $item['label'] ) && empty( $item['url'] ) ) {
		return '';
	}

	$attrs = array(
		'label' => $item['label'] ?? '',
		'url'   => $item['url'] ?? '',
	);

	if ( 'page' === $item['type'] && ! empty( $item['targetPageId'] ) ) {
		$attrs['kind'] = 'page';
		$attrs['id']   = (int) $item['targetPageId'];
	} else {
		$attrs['kind'] = 'custom';
	}

	$has_children = ! empty( $item['children'] );

	if ( $has_children ) {
		$block = '<!-- wp:navigation-link ' . wp_json_encode( $attrs ) . ' -->' . "\n";
		foreach ( $item['children'] as $child ) {
			$child_block = vbb_pro_build_nav_block( $child );
			if ( '' !== $child_block ) {
				$block .= $child_block . "\n";
			}
		}
		$block .= '<!-- /wp:navigation-link -->';
	} else {
		$block = '<!-- wp:navigation-link ' . wp_json_encode( $attrs ) . ' /-->';
	}

	return $block;
}

/**
 * Sync the menuItems array from global settings to the wp_navigation post type.
 *
 * Builds WordPress navigation block markup and upserts the "OrkestOne Primary Navigation"
 * wp_navigation post. Stores the last-sync timestamp in the vbb_last_menu_sync option.
 *
 * @param array $menu_items Array of menu items to sync.
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function vbb_pro_sync_menu_to_wp_navigation( $menu_items ) {
	$menu_items = vbb_pro_sanitize_menu_items( $menu_items );

	// Build wp:navigation block content
	$content = '<!-- wp:navigation {"ref":0} -->' . "\n";
	foreach ( $menu_items as $item ) {
		$block = vbb_pro_build_nav_block( $item );
		if ( '' !== $block ) {
			$content .= $block . "\n";
		}
	}
	$content .= '<!-- /wp:navigation -->';

	$nav_name  = 'OrkestOne Primary Navigation';
	$nav_slug  = 'orkestone-primary-navigation';

	// Look for existing post
	$existing = get_posts(
		array(
			'post_type'      => 'wp_navigation',
			'title'          => $nav_name,
			'posts_per_page' => 1,
			'post_status'    => 'any',
		)
	);

	$nav_id = ! empty( $existing ) ? $existing[0]->ID : 0;

	$post_data = array(
		'post_type'    => 'wp_navigation',
		'post_title'   => $nav_name,
		'post_name'    => $nav_slug,
		'post_status'  => 'publish',
		'post_content' => $content,
	);

	if ( $nav_id > 0 ) {
		$post_data['ID'] = $nav_id;
	}

	$result = wp_insert_post( $post_data, true );

	if ( ! is_wp_error( $result ) ) {
		update_option( 'vbb_last_menu_sync', current_time( 'mysql' ), false );
	}

	return $result;
}

function vbb_pro_get_settings() {
	$stored = get_option( VBB_PRO_SETTINGS_OPTION, array() );
	return vbb_pro_sanitize_settings( $stored );
}

function vbb_pro_update_settings( $settings ) {
	$settings = vbb_pro_sanitize_settings( $settings );
	update_option( VBB_PRO_SETTINGS_OPTION, $settings, false );
	vbb_pro_increment_settings_version();
	return $settings;
}

function vbb_pro_get_profiles() {
	$profiles = get_option( VBB_PRO_PROFILES_OPTION, array() );
	return is_array( $profiles ) ? $profiles : array();
}

function vbb_pro_save_profile( $name, $settings ) {
	$name     = sanitize_text_field( $name );
	$key      = sanitize_title( $name ? $name : 'profile-' . time() );
	$profiles = vbb_pro_get_profiles();
	$profiles[ $key ] = array(
		'name'      => $name ? $name : $key,
		'settings'  => vbb_pro_sanitize_settings( $settings ),
		'updatedAt' => current_time( 'mysql' ),
	);
	update_option( VBB_PRO_PROFILES_OPTION, $profiles, false );
	update_option( VBB_PRO_ACTIVE_PROFILE_OPTION, $key, false );
	return $key;
}

function vbb_pro_apply_profile( $key ) {
	$key      = sanitize_key( $key );
	$profiles = vbb_pro_get_profiles();
	if ( empty( $profiles[ $key ]['settings'] ) ) {
		return false;
	}
	vbb_pro_update_settings( $profiles[ $key ]['settings'] );
	update_option( VBB_PRO_ACTIVE_PROFILE_OPTION, $key, false );
	return true;
}

function vbb_pro_reset_to_vertical() {
	delete_option( VBB_PRO_SETTINGS_OPTION );
	delete_option( VBB_PRO_ACTIVE_PROFILE_OPTION );
	vbb_pro_increment_settings_version();
	return vbb_pro_get_settings();
}

/**
 * Dynamically replace content placeholders with current settings.
 * This ensures Live Preview and Frontend update instantly.
 */
function vbb_pro_replace_dynamic_content( $content ) {
	// Saltar solo en REST API que sirve contenido RAW para edición.
	// El preview del editor (iframe) y frontend SÍ deben reemplazar.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		$route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
		if ( preg_match( '#/wp/v2/(posts|pages|blocks|patterns|templates)/#', $route ) ) {
			return $content;
		}
	}

	$page_id = get_the_ID();
	$settings = vbb_pro_get_page_settings( $page_id );

	// --- DEBUG V2: Diagnose exact setting values ---
	$debug_global   = get_option( VBB_PRO_SETTINGS_OPTION, array() );
	$debug_page_all = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	$debug_page     = isset( $debug_page_all[ $page_id ] ) ? $debug_page_all[ $page_id ] : array();

	$global_eyebrow   = $debug_global['blocks']['hero']['eyebrow'] ?? 'MISSING';
	$page_eyebrow     = $debug_page['blocks']['hero']['eyebrow'] ?? 'NOT_IN_PAGE';
	$merged_eyebrow   = $settings['blocks']['hero']['eyebrow'] ?? 'EMPTY';

	$has_placeholders = false;
	$found = array();
	$placeholders_to_check = array( '{{vbb_hero_title}}', '{{vbb_hero_eyebrow}}', '{{vbb_hero_subtitle}}' );
	foreach ( $placeholders_to_check as $ph ) {
		if ( false !== strpos( $content, $ph ) ) {
			$has_placeholders = true;
			$found[] = $ph;
		}
	}
	$content .= "\n<!-- VBB DEBUG v2: page={$page_id}"
		. ' | global_hero_eyebrow=' . $global_eyebrow
		. ' | page_hero_eyebrow=' . $page_eyebrow
		. ' | merged_hero_eyebrow=' . $merged_eyebrow
		. ' | has_placeholders=' . ( $has_placeholders ? 'YES' : 'NO' )
		. ' | ph_found=' . ( $found ? implode(',', $found) : 'none' )
		. " -->\n";
	// ----------------------------------------------------
	
	// Helper to resolve image URL from image_id (media library) or fallback to image_url
	$hero_image_id  = $settings['blocks']['hero']['image_id'] ?? 0;
	$hero_image_url = $settings['blocks']['hero']['image_url'] ?? '';
	$resolved_hero_image = '';
	if ( $hero_image_id && wp_get_attachment_url( $hero_image_id ) ) {
		$resolved_hero_image = wp_get_attachment_url( $hero_image_id );
	} elseif ( ! empty( $hero_image_url ) ) {
		if ( strpos( $hero_image_url, 'assets/' ) === 0 ) {
			$resolved_hero_image = get_template_directory_uri() . '/' . $hero_image_url;
		} else {
			$resolved_hero_image = esc_url_raw( $hero_image_url );
		}
	}
	
	// Map of placeholder -> setting path
	$map = array(
		// Hero (already present in Phase 1)
		'{{vbb_hero_title}}'          => $settings['blocks']['hero']['title'] ?? '',
		'{{vbb_hero_subtitle}}'       => $settings['blocks']['hero']['subtitle'] ?? '',
		'{{vbb_hero_eyebrow}}'        => $settings['blocks']['hero']['eyebrow'] ?? '',
		'{{vbb_hero_cta_text}}'       => $settings['blocks']['hero']['primaryCta'] ?? '',
		'{{vbb_hero_cta_url}}'        => $settings['blocks']['hero']['primaryUrl'] ?? '#',
		'{{vbb_hero_secondary_cta}}'  => $settings['blocks']['hero']['secondaryCta'] ?? '',
		'{{vbb_hero_secondary_url}}'  => $settings['blocks']['hero']['secondaryUrl'] ?? '#',
		'{{vbb_hero_image_url}}'      => $resolved_hero_image,
		'{{vbb_hero_image_id}}'       => $settings['blocks']['hero']['image_id'] ?? '',

		// Hero Centered
		'{{vbb_hero_centered_title}}'   => $settings['blocks']['hero']['title'] ?? '',
		'{{vbb_hero_centered_tagline}}' => $settings['blocks']['hero']['tagline'] ?? $settings['blocks']['hero']['subtitle'] ?? '',

		// CTA Final
		'{{vbb_cta_final_text}}'              => $settings['blocks']['ctaFinal']['text'] ?? '',
		'{{vbb_cta_final_button_text}}'       => $settings['blocks']['ctaFinal']['buttonText'] ?? '',
		'{{vbb_cta_final_button_url}}'        => $settings['blocks']['ctaFinal']['buttonUrl'] ?? '#',
		'{{vbb_cta_final_subtitle}}'          => $settings['blocks']['ctaFinal']['subtitle'] ?? '',
		'{{vbb_cta_final_secondary_cta}}'     => $settings['blocks']['ctaFinal']['secondaryCta'] ?? '',
		'{{vbb_cta_final_secondary_url}}'     => $settings['blocks']['ctaFinal']['secondaryUrl'] ?? '#',

// Contact
		'{{vbb_contact_email}}'   => $settings['blocks']['contact']['email'] ?? '',
		'{{vbb_contact_phone}}'   => $settings['blocks']['contact']['phone'] ?? '',
		'{{vbb_contact_address}}' => $settings['blocks']['contact']['address'] ?? '',

		// Repeatable section headings
		'{{vbb_services_heading}}'   => $settings['blocks']['servicesGrid']['heading'] ?? '',
		'{{vbb_benefits_heading}}'   => $settings['blocks']['benefits']['heading'] ?? '',
		'{{vbb_testimonials_heading}}' => $settings['blocks']['testimonials']['heading'] ?? '',
		'{{vbb_faq_heading}}'        => $settings['blocks']['faq']['heading'] ?? '',
		'{{vbb_process_heading}}'    => $settings['blocks']['process']['heading'] ?? '',
		'{{vbb_pricing_heading}}'    => $settings['blocks']['pricing']['heading'] ?? '',
		'{{vbb_team_heading}}'       => $settings['blocks']['team']['heading'] ?? '',
		'{{vbb_logo_cloud_heading}}' => $settings['blocks']['logoCloud']['heading'] ?? '',
		'{{vbb_stats_heading}}'      => $settings['blocks']['stats']['heading'] ?? '',
		'{{vbb_gallery_heading}}'    => $settings['blocks']['gallery']['heading'] ?? '',
		'{{vbb_video_heading}}'      => $settings['blocks']['video']['heading'] ?? '',
		'{{vbb_newsletter_heading}}' => $settings['blocks']['newsletter']['heading'] ?? '',
		'{{vbb_map_heading}}'        => $settings['blocks']['map']['heading'] ?? '',
		'{{vbb_comparison_heading}}' => $settings['blocks']['comparison']['heading'] ?? '',
		'{{vbb_blog_heading}}'       => $settings['blocks']['blog']['heading'] ?? '',
		'{{vbb_divider_heading}}'    => $settings['blocks']['divider']['heading'] ?? '',
	);

	foreach ( $map as $placeholder => $value ) {
		$content = str_replace( $placeholder, esc_html( $value ), $content );
	}

	return $content;
}
add_filter( 'the_content', 'vbb_pro_replace_dynamic_content', 99 );

/**
 * Replace placeholders in render_block context (editor iframe preview).
 * The block editor renders blocks via REST, not the_content, so we need this.
 * 
 * IMPORTANT: Only runs in admin/editor context. On front-end, the_content filter
 * handles replacement. Otherwise render_block would replace placeholders DURING
 * do_blocks() (priority 9 on the_content), and in FSE the postId context might
 * be wrong, causing it to replace with empty values BEFORE the_content can fix them.
 */
function vbb_pro_replace_block_content( $block_content, $block ) {
	// Skip on front-end — let the_content filter handle it to avoid context issues in FSE
	if ( ! is_admin() && ! wp_doing_ajax() ) {
		return $block_content;
	}

	// Obtener post ID desde el contexto del bloque (editor) o global
	$post_id = $block['context']['postId'] ?? get_the_ID();
	if ( ! $post_id ) {
		return $block_content;
	}

	$settings = vbb_pro_get_page_settings( $post_id );

	// Resolve hero image URL: image_id (media library) takes priority over image_url
	$hero_image_id  = $settings['blocks']['hero']['image_id'] ?? 0;
	$hero_image_url = $settings['blocks']['hero']['image_url'] ?? '';
	$resolved_hero_image = '';
	if ( $hero_image_id && wp_get_attachment_url( $hero_image_id ) ) {
		$resolved_hero_image = wp_get_attachment_url( $hero_image_id );
	} elseif ( ! empty( $hero_image_url ) ) {
		if ( strpos( $hero_image_url, 'assets/' ) === 0 ) {
			$resolved_hero_image = get_template_directory_uri() . '/' . $hero_image_url;
		} else {
			$resolved_hero_image = esc_url_raw( $hero_image_url );
		}
	}

	$map = array(
		'{{vbb_hero_title}}'           => $settings['blocks']['hero']['title'] ?? '',
		'{{vbb_hero_subtitle}}'        => $settings['blocks']['hero']['subtitle'] ?? '',
		'{{vbb_hero_eyebrow}}'         => $settings['blocks']['hero']['eyebrow'] ?? '',
		'{{vbb_hero_cta_text}}'        => $settings['blocks']['hero']['primaryCta'] ?? '',
		'{{vbb_hero_cta_url}}'         => $settings['blocks']['hero']['primaryUrl'] ?? '#',
		'{{vbb_hero_secondary_cta}}'   => $settings['blocks']['hero']['secondaryCta'] ?? '',
		'{{vbb_hero_secondary_url}}'   => $settings['blocks']['hero']['secondaryUrl'] ?? '#',
		'{{vbb_hero_image_url}}'       => $resolved_hero_image,
		'{{vbb_hero_image_id}}'        => $settings['blocks']['hero']['image_id'] ?? '',
		'{{vbb_hero_centered_title}}'   => $settings['blocks']['hero']['title'] ?? '',
		'{{vbb_hero_centered_tagline}}' => $settings['blocks']['hero']['tagline'] ?? $settings['blocks']['hero']['subtitle'] ?? '',
		'{{vbb_cta_final_text}}'              => $settings['blocks']['ctaFinal']['text'] ?? '',
		'{{vbb_cta_final_button_text}}'       => $settings['blocks']['ctaFinal']['buttonText'] ?? '',
		'{{vbb_cta_final_button_url}}'        => $settings['blocks']['ctaFinal']['buttonUrl'] ?? '#',
		'{{vbb_cta_final_subtitle}}'          => $settings['blocks']['ctaFinal']['subtitle'] ?? '',
		'{{vbb_cta_final_secondary_cta}}'     => $settings['blocks']['ctaFinal']['secondaryCta'] ?? '',
		'{{vbb_cta_final_secondary_url}}'     => $settings['blocks']['ctaFinal']['secondaryUrl'] ?? '#',
		'{{vbb_contact_email}}' => $settings['blocks']['contact']['email'] ?? '',
		'{{vbb_contact_phone}}' => $settings['blocks']['contact']['phone'] ?? '',
		'{{vbb_contact_address}}' => $settings['blocks']['contact']['address'] ?? '',
		'{{vbb_services_heading}}'   => $settings['blocks']['servicesGrid']['heading'] ?? '',
		'{{vbb_benefits_heading}}'   => $settings['blocks']['benefits']['heading'] ?? '',
		'{{vbb_testimonials_heading}}' => $settings['blocks']['testimonials']['heading'] ?? '',
		'{{vbb_faq_heading}}'        => $settings['blocks']['faq']['heading'] ?? '',
		'{{vbb_process_heading}}'    => $settings['blocks']['process']['heading'] ?? '',
		'{{vbb_pricing_heading}}'    => $settings['blocks']['pricing']['heading'] ?? '',
		'{{vbb_team_heading}}'       => $settings['blocks']['team']['heading'] ?? '',
		'{{vbb_logo_cloud_heading}}' => $settings['blocks']['logoCloud']['heading'] ?? '',
		'{{vbb_stats_heading}}'      => $settings['blocks']['stats']['heading'] ?? '',
		'{{vbb_gallery_heading}}'    => $settings['blocks']['gallery']['heading'] ?? '',
		'{{vbb_video_heading}}'      => $settings['blocks']['video']['heading'] ?? '',
		'{{vbb_newsletter_heading}}' => $settings['blocks']['newsletter']['heading'] ?? '',
		'{{vbb_map_heading}}'        => $settings['blocks']['map']['heading'] ?? '',
		'{{vbb_comparison_heading}}' => $settings['blocks']['comparison']['heading'] ?? '',
		'{{vbb_blog_heading}}'       => $settings['blocks']['blog']['heading'] ?? '',
	);

	foreach ( $map as $placeholder => $value ) {
		$block_content = str_replace( $placeholder, esc_html( $value ), $block_content );
	}

	return $block_content;
}
add_filter( 'render_block', 'vbb_pro_replace_block_content', 99, 2 );

/**
 * Regenerate all baked pages to ensure placeholders are present.
 * This is required after updating the Baker logic.
 */
function vbb_pro_regenerate_all_pages() {
	$pages = get_pages();
	$count = 0;
	
	foreach ( $pages as $page ) {
		if ( function_exists( 'vbb_bake_page_content' ) ) {
			vbb_bake_page_content( $page->ID );
			$count++;
		}
	}
	return $count;
}

/**
 * Theme activation hook — backfill all builder pages with the new placeholder system.
 *
 * Checks the vbb_baker_version option. If below 1.0.0, runs full regeneration
 * to ensure all existing pages are baked with {{vbb_*}} placeholders.
 *
 * Hooked to after_switch_theme.
 *
 * @return int Number of pages regenerated.
 */
function vbb_pro_on_theme_activation() {
	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 300 );
	}

	$version = get_option( 'vbb_baker_version', '0' );
	if ( version_compare( $version, '1.0.0', '<' ) ) {
		$count = vbb_pro_regenerate_all_pages();
		update_option( 'vbb_baker_version', '1.0.0', false );

		// Clear token detection flag so admin notice re-scans on next page load.
		delete_option( 'vbb_tokens_detected' );

		return $count;
	}

	return 0;
}
add_action( 'after_switch_theme', 'vbb_pro_on_theme_activation' );

function vbb_pro_get_block_section_map() {
	return array(
		'hero'            => 'hero',
		'hero-centered'   => 'hero',
		'services-grid'   => 'servicesGrid',
		'benefits'        => 'benefits',
		'process'         => 'process',
		'testimonials'    => 'testimonials',
		'faq'             => 'faq',
		'contact-section' => 'contact',
		'cta-final'       => 'ctaFinal',
		'logo-cloud'      => 'logoCloud',
		'pricing-tables'  => 'pricing',
		'team-section'    => 'team',
	);
}

function vbb_pro_is_section_enabled( $section_slug ) {
	$section_slug = sanitize_key( (string) $section_slug );
	$map          = vbb_pro_get_block_section_map();
	
	if ( ! isset( $map[ $section_slug ] ) ) {
		return true;
	}
	
	$settings  = vbb_pro_get_settings();
	$block_key = $map[ $section_slug ];
	
	if ( ! isset( $settings['blocks'] ) || ! is_array( $settings['blocks'] ) ) {
		return true;
	}
	
	if ( ! array_key_exists( $block_key, $settings['blocks'] ) ) {
		return true;
	}
	
	$block_val = $settings['blocks'][ $block_key ];
	
	// If it's the new object format, check the 'enabled' key
	if ( is_array( $block_val ) ) {
		return ! empty( $block_val['enabled'] );
	}
	
	// Fallback for old boolean format
	return ! empty( $block_val );
}


function vbb_pro_filter_sections( $sections, $page_id = 0 ) {
	if ( ! is_array( $sections ) ) {
		return array();
	}
	
	$filtered = array();
	
	// Always use fresh uncached settings to avoid stale transient data.
	// If page_id is provided explicitly, use it; otherwise fallback to get_the_ID()
	if ( ! $page_id ) {
		$page_id = get_the_ID();
	}
	$settings = vbb_pro_get_page_settings( $page_id );
	
	foreach ( $sections as $section ) {
		$section_slug = sanitize_key( (string) $section );
		
		if ( '' === $section_slug ) {
			continue;
		}
		
		// Use the merged settings for this specific page
		$map = vbb_pro_get_block_section_map();
		$block_key = isset( $map[ $section_slug ] ) ? $map[ $section_slug ] : null;
		
		if ( null !== $block_key && isset( $settings['blocks'][ $block_key ] ) ) {
			$block_val = $settings['blocks'][ $block_key ];
			$enabled = is_array( $block_val ) ? ! empty( $block_val['enabled'] ) : ! empty( $block_val );
			if ( ! $enabled ) {
				continue;
			}
		}
		
		$filtered[] = $section_slug;
	}
	
	return $filtered;
}

