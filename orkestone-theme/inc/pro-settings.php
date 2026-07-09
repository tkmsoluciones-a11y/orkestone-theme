<?php
/**
 * Pro Elite settings storage and sanitization.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VBB_PRO_SETTINGS_OPTION      = 'vbb_pro_settings';
const VBB_PRO_PAGE_SETTINGS_OPTION  = 'vbb_pro_page_settings';
const VBB_PRO_PROFILES_OPTION       = 'vbb_pro_saved_profiles';
const VBB_PRO_ACTIVE_PROFILE_OPTION = 'vbb_pro_active_profile';

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

	// Merge global with specific page overrides
	return vbb_pro_deep_merge( $global_settings, $all_page_settings[ $page_id ] );
}

/**
 * Save settings for a specific page.
 */
function vbb_pro_update_page_settings( $page_id, $settings ) {
	$page_id           = (int) $page_id;
	$all_page_settings = get_option( VBB_PRO_PAGE_SETTINGS_OPTION, array() );
	
	$current_page_settings = isset( $all_page_settings[ $page_id ] ) ? $all_page_settings[ $page_id ] : array();
	
	// Merge current settings with the new overrides
	$updated_settings = vbb_pro_deep_merge( $current_page_settings, $settings );
	
	$all_page_settings[ $page_id ] = $updated_settings;
	
	update_option( VBB_PRO_PAGE_SETTINGS_OPTION, $all_page_settings, false );
	return $updated_settings;
}


function vbb_pro_default_settings() {
	$config = function_exists( 'vbb_get_vertical_config' ) ? vbb_get_vertical_config() : array();
	$brand  = isset( $config['brand'] ) && is_array( $config['brand'] ) ? $config['brand'] : array();

	$light_primary    = $brand['primaryColor'] ?? '#0F1724';
	$light_secondary  = $brand['secondaryColor'] ?? '#C6A163';
	$light_accent     = $brand['accentColor'] ?? '#F4F1EC';
	$light_background = $brand['backgroundColor'] ?? '#FFFFFF';

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
		'blocks'      => array(
			'hero'         => true,
			'servicesGrid' => true,
			'benefits'     => true,
			'process'      => true,
			'testimonials' => true,
		'faq'          => true,
		'contact'      => true,
		'ctaFinal'     => true,
		'logoCloud'    => true,
		'pricing'      => true,
		'team'         => true,
	),
	'buttons'     => array(

			'style'     => 'pill',
			'uppercase' => false,
		),
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

	$out['buttons']['style']     = in_array( $settings['buttons']['style'] ?? 'pill', array( 'pill', 'rounded', 'square', 'outline' ), true ) ? $settings['buttons']['style'] : 'pill';
	$out['buttons']['uppercase'] = ! empty( $settings['buttons']['uppercase'] );

	return $out;
}

function vbb_pro_get_settings() {
	$stored = get_option( VBB_PRO_SETTINGS_OPTION, array() );
	return vbb_pro_sanitize_settings( $stored );
}

function vbb_pro_update_settings( $settings ) {
	$settings = vbb_pro_sanitize_settings( $settings );
	update_option( VBB_PRO_SETTINGS_OPTION, $settings, false );
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
	return vbb_pro_get_settings();
}

/**
 * Dynamically replace content placeholders with current settings.
 * This ensures Live Preview and Frontend update instantly.
 */
function vbb_pro_replace_dynamic_content( $content ) {
	if ( is_admin() ) return $content;

	$page_id = get_the_ID();
	$settings = vbb_pro_get_page_settings( $page_id );
	
	// Map of placeholder -> setting path
	$map = array(
		// Hero (already present in Phase 1)
		'{{vbb_hero_title}}'    => $settings['blocks']['hero']['title'] ?? '',
		'{{vbb_hero_subtitle}}' => $settings['blocks']['hero']['subtitle'] ?? '',
		'{{vbb_hero_eyebrow}}'  => $settings['blocks']['hero']['eyebrow'] ?? '',
		'{{vbb_hero_cta_text}}' => $settings['blocks']['hero']['primaryCta'] ?? '',
		'{{vbb_hero_cta_url}}'  => $settings['blocks']['hero']['primaryUrl'] ?? '#',

		// Hero Centered
		'{{vbb_hero_centered_title}}'   => $settings['blocks']['hero']['title'] ?? '',
		'{{vbb_hero_centered_tagline}}' => $settings['blocks']['hero']['tagline'] ?? $settings['blocks']['hero']['subtitle'] ?? '',

		// CTA Final
		'{{vbb_cta_final_text}}'        => $settings['blocks']['ctaFinal']['text'] ?? '',
		'{{vbb_cta_final_button_text}}' => $settings['blocks']['ctaFinal']['buttonText'] ?? '',
		'{{vbb_cta_final_button_url}}'  => $settings['blocks']['ctaFinal']['buttonUrl'] ?? '#',

		// Contact
		'{{vbb_contact_email}}' => $settings['blocks']['contact']['email'] ?? '',
		'{{vbb_contact_phone}}' => $settings['blocks']['contact']['phone'] ?? '',

		// Repeatable section headings
		'{{vbb_services_heading}}'   => $settings['blocks']['servicesGrid']['heading'] ?? '',
		'{{vbb_benefits_heading}}'   => $settings['blocks']['benefits']['heading'] ?? '',
		'{{vbb_testimonials_heading}}' => $settings['blocks']['testimonials']['heading'] ?? '',
		'{{vbb_faq_heading}}'        => $settings['blocks']['faq']['heading'] ?? '',
		'{{vbb_process_heading}}'    => $settings['blocks']['process']['heading'] ?? '',
		'{{vbb_pricing_heading}}'    => $settings['blocks']['pricing']['heading'] ?? '',
		'{{vbb_team_heading}}'       => $settings['blocks']['team']['heading'] ?? '',
		'{{vbb_logo_cloud_heading}}' => $settings['blocks']['logoCloud']['heading'] ?? '',
	);

	foreach ( $map as $placeholder => $value ) {
		$content = str_replace( $placeholder, esc_html( $value ), $content );
	}

	return $content;
}
add_filter( 'the_content', 'vbb_pro_replace_dynamic_content', 99 );

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


function vbb_pro_filter_sections( $sections ) {
	if ( ! is_array( $sections ) ) {
		return array();
	}
	
	$filtered = array();
	
	// Get the current page ID to apply specific overrides
	$page_id = get_the_ID();
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

