<?php
/** Pro Elite presets. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vbb_pro_get_builtin_presets() {
	$dir = get_template_directory() . '/config/presets';
	$out = array();
	foreach ( glob( $dir . '/*.json' ) as $file ) {
		$data = json_decode( file_get_contents( $file ), true );
		if ( is_array( $data ) && ! empty( $data['presetKey'] ) ) {
			$out[ sanitize_key( $data['presetKey'] ) ] = $data;
		}
	}
	return $out;
}

function vbb_pro_get_preset_settings( $key ) {
	$key = sanitize_key( $key );
	$presets = vbb_pro_get_builtin_presets();
	return isset( $presets[ $key ]['settings'] ) && is_array( $presets[ $key ]['settings'] ) ? $presets[ $key ]['settings'] : null;
}
