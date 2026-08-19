<?php
/**
 * Pro Elite settings storage and sanitization.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VBB_PRO_SETTINGS_OPTION = 'vbb_pro_settings';
const VBB_PRO_PROFILES_OPTION = 'vbb_pro_saved_profiles';
const VBB_PRO_ACTIVE_PROFILE_OPTION = 'vbb_pro_active_profile';

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

	foreach ( $defaults['blocks'] as $key => $fallback ) {
		$out['blocks'][ $key ] = ! empty( $settings['blocks'][ $key ] );
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

	return ! empty( $settings['blocks'][ $block_key ] );
}

function vbb_pro_filter_sections( $sections ) {
	if ( ! is_array( $sections ) ) {
		return array();
	}

	$filtered = array();

	foreach ( $sections as $section ) {
		$section_slug = sanitize_key( (string) $section );

		if ( '' === $section_slug ) {
			continue;
		}

		if ( vbb_pro_is_section_enabled( $section_slug ) ) {
			$filtered[] = $section_slug;
		}
	}

	return $filtered;
}
