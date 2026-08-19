<?php
/**
 * Pattern category registration.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register custom block pattern category.
 *
 * @return void
 */
function vbb_register_pattern_categories() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'vertical-block-base',
			array(
				'label' => __( 'Vertical Block Base', 'vertical-block-base' ),
			)
		);
	}
}
add_action( 'init', 'vbb_register_pattern_categories' );
