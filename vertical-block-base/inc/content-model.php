<?php
/**
 * Declarative content model helpers.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a content model from vertical config.
 *
 * @param string $model_key Content model key.
 * @return array
 */
function vbb_get_content_model( $model_key ) {
	$models = vbb_get_vertical_value( 'contentModels', array() );

	if ( is_array( $models ) && isset( $models[ $model_key ] ) && is_array( $models[ $model_key ] ) ) {
		return $models[ $model_key ];
	}

	return array();
}

/**
 * Get content model items.
 *
 * @param string $model_key Content model key.
 * @return array
 */
function vbb_get_content_model_items( $model_key ) {
	$model = vbb_get_content_model( $model_key );

	if ( isset( $model['items'] ) && is_array( $model['items'] ) ) {
		return $model['items'];
	}

	return array();
}
