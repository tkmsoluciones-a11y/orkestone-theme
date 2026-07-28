<?php
/**
 * Block Registry — canonical definitions for all 19 section block types.
 *
 * Single source of truth for field structure, baker maps, defaults, and
 * sanitization. Both PHP bakers and JS admin consume this.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the complete block registry.
 *
 * @return array<string, array> Keyed by block key.
 */
function vbb_get_block_registry() {
	$blocks = array();

	// -- Standard repeatable blocks (item-based) --

	$blocks['servicesGrid'] = array(
		'label'      => 'Services Grid',
		'icon'       => 'dashicons-grid-view',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Services',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'ctaText', 'label' => 'CTA Text', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'ctaUrl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '' ),
				),
			),
		),
	);

	$blocks['benefits'] = array(
		'label'      => 'Benefits',
		'icon'       => 'dashicons-yes-alt',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Benefits',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['process'] = array(
		'label'      => 'Process',
		'icon'       => 'dashicons-editor-ol',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Steps',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'number', 'label' => 'Step Number', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
				),
			),
		),
	);

	$blocks['testimonials'] = array(
		'label'      => 'Testimonials',
		'icon'       => 'dashicons-testimonial',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Testimonials',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'author', 'label' => 'Author Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'role', 'label' => 'Role / Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'avatar', 'label' => 'Avatar Image', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'rating', 'label' => 'Rating (1-5)', 'type' => 'number', 'default' => 5 ),
				),
			),
		),
	);

	$blocks['faq'] = array(
		'label'      => 'FAQ',
		'icon'       => 'dashicons-editor-help',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'FAQ Items',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'question', 'label' => 'Question', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['pricing'] = array(
		'label'      => 'Pricing',
		'icon'       => 'dashicons-tag',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Pricing Plans',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'name', 'label' => 'Plan Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'price', 'label' => 'Price', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'period', 'label' => 'Period', 'type' => 'text', 'default' => '/month' ),
					array( 'key' => 'features', 'label' => 'Features (one per line)', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'ctaText', 'label' => 'CTA Text', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'ctaUrl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'featured', 'label' => 'Featured Plan', 'type' => 'checkbox', 'default' => false ),
				),
			),
		),
	);

	$blocks['team'] = array(
		'label'      => 'Team',
		'icon'       => 'dashicons-groups',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Team Members',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'role', 'label' => 'Role', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'bio', 'label' => 'Bio', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'image', 'label' => 'Photo', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'linkedin', 'label' => 'LinkedIn URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'twitter', 'label' => 'Twitter/X URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'github', 'label' => 'GitHub URL', 'type' => 'url', 'default' => '' ),
				),
			),
		),
	);

	$blocks['logoCloud'] = array(
		'label'      => 'Logo Cloud',
		'icon'       => 'dashicons-building',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Logos',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'name', 'label' => 'Company Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'logo', 'label' => 'Logo Image', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'url', 'label' => 'Link URL', 'type' => 'url', 'default' => '' ),
				),
			),
		),
	);

	$blocks['stats'] = array(
		'label'      => 'Stats',
		'icon'       => 'dashicons-chart-bar',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Stats',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'value', 'label' => 'Value', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['gallery'] = array(
		'label'      => 'Gallery',
		'icon'       => 'dashicons-format-gallery',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Gallery Items',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'image', 'label' => 'Image', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'category', 'label' => 'Category', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'url', 'label' => 'Link URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	// -- Simple blocks (no repeatable items) --

	$blocks['hero'] = array(
		'label'      => 'Hero',
		'icon'       => 'dashicons-welcome-widgets-menus',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => '' ),
			array( 'key' => 'eyebrow', 'label' => 'Eyebrow Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryCta', 'label' => 'Primary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryUrl', 'label' => 'Primary CTA URL', 'type' => 'url', 'default' => '#' ),
			array( 'key' => 'secondaryCta', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'secondaryUrl', 'label' => 'Secondary CTA URL', 'type' => 'url', 'default' => '' ),
			array( 'key' => 'image', 'label' => 'Background Image', 'type' => 'image', 'default' => '' ),
		),
	);

	$blocks['heroCentered'] = array(
		'label'      => 'Hero Centered',
		'icon'       => 'dashicons-align-center',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => '' ),
			array( 'key' => 'eyebrow', 'label' => 'Eyebrow Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryCta', 'label' => 'Primary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryUrl', 'label' => 'Primary CTA URL', 'type' => 'url', 'default' => '#' ),
			array( 'key' => 'secondaryCta', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'secondaryUrl', 'label' => 'Secondary CTA URL', 'type' => 'url', 'default' => '' ),
		),
	);

	$blocks['ctaFinal'] = array(
		'label'      => 'CTA Final',
		'icon'       => 'dashicons-megaphone',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'text', 'label' => 'Main Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'buttonText', 'label' => 'Button Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'url', 'default' => '#' ),
			array( 'key' => 'secondaryCta', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'secondaryUrl', 'label' => 'Secondary CTA URL', 'type' => 'url', 'default' => '' ),
		),
	);

	$blocks['divider'] = array(
		'label'      => 'Divider',
		'icon'       => 'dashicons-minus',
		'styles'     => array( 'A' ),
		'effects'    => array( 'none' ),
		'hasColors'  => false,
		'fields'     => array(
			array( 'key' => 'type', 'label' => 'Type', 'type' => 'select', 'default' => 'line', 'options' => array( 'line' => 'Line', 'space' => 'Space', 'wave' => 'Wave', 'dots' => 'Dots' ) ),
			array( 'key' => 'color', 'label' => 'Color', 'type' => 'color', 'default' => '' ),
			array( 'key' => 'thickness', 'label' => 'Thickness (px)', 'type' => 'number', 'default' => 2 ),
			array( 'key' => 'margin', 'label' => 'Vertical Margin (px)', 'type' => 'number', 'default' => 40 ),
		),
	);

	// -- Custom blocks (special rendering, no generic renderer) --

	$blocks['contact'] = array(
		'label'      => 'Contact',
		'icon'       => 'dashicons-email',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'email', 'label' => 'Email', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'address', 'label' => 'Address', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'formEndpoint', 'label' => 'Form Endpoint', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'recaptcha', 'label' => 'reCAPTCHA', 'type' => 'select', 'default' => 'none', 'options' => array( 'none' => 'Sin reCAPTCHA', 'v2' => 'reCAPTCHA v2', 'v3' => 'reCAPTCHA v3' ) ),
			array( 'key' => 'recaptchaKey', 'label' => 'reCAPTCHA Site Key', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'recaptchaSecret', 'label' => 'reCAPTCHA Secret Key', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'formFields',
				'label'       => 'Form Fields',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'type', 'label' => 'Field Type', 'type' => 'select', 'default' => 'text', 'options' => array( 'text' => 'Text', 'email' => 'Email', 'tel' => 'Tel', 'url' => 'URL', 'number' => 'Number', 'textarea' => 'Textarea', 'select' => 'Select', 'checkbox' => 'Checkbox' ) ),
					array( 'key' => 'name', 'label' => 'Field Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'label', 'label' => 'Field Label', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'placeholder', 'label' => 'Placeholder', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'required', 'label' => 'Required', 'type' => 'checkbox', 'default' => false ),
					array( 'key' => 'options', 'label' => 'Options (JSON for select)', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['video'] = array(
		'label'      => 'Video',
		'icon'       => 'dashicons-video-alt3',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'video_url', 'label' => 'Video URL', 'type' => 'url', 'default' => '' ),
			array( 'key' => 'video_type', 'label' => 'Video Type', 'type' => 'select', 'default' => 'youtube', 'options' => array( 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'mp4' => 'MP4 (self-hosted)' ) ),
			array( 'key' => 'poster', 'label' => 'Poster Image', 'type' => 'image', 'default' => '' ),
			array( 'key' => 'cta_text', 'label' => 'CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'default' => '' ),
		),
	);

	$blocks['newsletter'] = array(
		'label'      => 'Newsletter',
		'icon'       => 'dashicons-email-alt',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
			array( 'key' => 'placeholder', 'label' => 'Email Placeholder', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'button_text', 'label' => 'Button Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'default' => 'custom', 'options' => array( 'custom' => 'Custom Endpoint', 'mailchimp' => 'Mailchimp', 'convertkit' => 'ConvertKit' ) ),
			array( 'key' => 'listId', 'label' => 'List ID', 'type' => 'text', 'default' => '' ),
		),
	);

	$blocks['map'] = array(
		'label'      => 'Map',
		'icon'       => 'dashicons-location',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'address', 'label' => 'Address', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'lat', 'label' => 'Latitude', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'lng', 'label' => 'Longitude', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'zoom', 'label' => 'Zoom Level', 'type' => 'number', 'default' => 15 ),
			array( 'key' => 'map_type', 'label' => 'Map Type', 'type' => 'select', 'default' => 'roadmap', 'options' => array( 'roadmap' => 'Roadmap', 'satellite' => 'Satellite', 'hybrid' => 'Hybrid', 'terrain' => 'Terrain' ) ),
			array( 'key' => 'marker_title', 'label' => 'Marker Title', 'type' => 'text', 'default' => '' ),
		),
	);

	$blocks['comparison'] = array(
		'label'      => 'Comparison Table',
		'icon'       => 'dashicons-table-col-after',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'rows',
				'label'       => 'Comparison Rows',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'feature', 'label' => 'Feature', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'plan1', 'label' => 'Plan 1', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'plan2', 'label' => 'Plan 2', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'plan3', 'label' => 'Plan 3', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'highlight', 'label' => 'Highlight Row', 'type' => 'checkbox', 'default' => false ),
				),
			),
		),
	);

	$blocks['blog'] = array(
		'label'      => 'Blog Posts',
		'icon'       => 'dashicons-admin-post',
		'styles'     => array( 'A' ),
		'effects'    => array( 'none' ),
		'hasColors'  => false,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'category', 'label' => 'Category Slug', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'limit', 'label' => 'Post Limit', 'type' => 'number', 'default' => 6 ),
			array( 'key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'default' => 'grid', 'options' => array( 'grid' => 'Grid', 'list' => 'List', 'masonry' => 'Masonry' ) ),
			array( 'key' => 'showExcerpt', 'label' => 'Show Excerpt', 'type' => 'checkbox', 'default' => true ),
			array( 'key' => 'showDate', 'label' => 'Show Date', 'type' => 'checkbox', 'default' => true ),
			array( 'key' => 'showAuthor', 'label' => 'Show Author', 'type' => 'checkbox', 'default' => true ),
		),
	);

	return $blocks;
}

/**
 * Get a single block definition by key.
 *
 * @param string $key Block key.
 * @return array|null Block definition or null if not found.
 */
function vbb_get_block_def( $key ) {
	$registry = vbb_get_block_registry();
	return isset( $registry[ $key ] ) ? $registry[ $key ] : null;
}

/**
 * Generate the baker function map from the registry.
 * Replaces the hardcoded map in block-baker.php.
 *
 * @return array<string, string> Block key => baker function name.
 */
function vbb_get_baker_map() {
	$registry = vbb_get_block_registry();
	$map      = array();

	// Key mapping: registry key → baker key (kebab-case for baker dispatcher).
	$key_to_baker_key = array(
		'hero'            => 'hero',
		'heroCentered'    => 'hero-centered',
		'servicesGrid'    => 'services-grid',
		'benefits'        => 'benefits',
		'process'         => 'process',
		'testimonials'    => 'testimonials',
		'faq'             => 'faq',
		'contact'         => 'contact-section',
		'ctaFinal'        => 'cta-final',
		'logoCloud'       => 'logoCloud',
		'pricing'         => 'pricing',
		'team'            => 'team',
		'stats'           => 'stats',
		'gallery'         => 'gallery',
		'video'           => 'video',
		'newsletter'      => 'newsletter',
		'map'             => 'map',
		'comparison'      => 'comparison',
		'blog'            => 'blog',
		'divider'         => 'divider',
	);

	// Baker function names by block key.
	$baker_functions = array(
		'hero'          => 'vbb_bake_hero',
		'heroCentered'  => 'vbb_bake_hero_centered',
		'servicesGrid'  => 'vbb_bake_services_grid',
		'benefits'      => 'vbb_bake_benefits',
		'process'       => 'vbb_bake_process',
		'testimonials'  => 'vbb_bake_testimonials',
		'faq'           => 'vbb_bake_faq',
		'contact'       => 'vbb_bake_contact_section',
		'ctaFinal'      => 'vbb_bake_cta_final',
		'logoCloud'     => 'vbb_bake_logo_cloud',
		'pricing'       => 'vbb_bake_pricing_tables',
		'team'          => 'vbb_bake_team_section',
		'stats'         => 'vbb_bake_stats',
		'gallery'       => 'vbb_bake_gallery',
		'video'         => 'vbb_bake_video',
		'newsletter'    => 'vbb_bake_newsletter',
		'map'           => 'vbb_bake_map',
		'comparison'    => 'vbb_bake_comparison',
		'blog'          => 'vbb_bake_blog',
		'divider'       => 'vbb_bake_divider',
	);

	foreach ( $registry as $key => $def ) {
		if ( isset( $key_to_baker_key[ $key ] ) && isset( $baker_functions[ $key ] ) ) {
			$map[ $key_to_baker_key[ $key ] ] = $baker_functions[ $key ];
		}
	}

	return $map;
}

/**
 * Get default values for all blocks from the registry.
 *
 * @return array<string, array> Block key => default data array.
 */
function vbb_get_block_defaults() {
	$registry = vbb_get_block_registry();
	$defaults = array();

	foreach ( $registry as $key => $def ) {
		$block_defaults = array(
			'enabled' => true,
			'style'   => $def['styles'][0] ?? 'A',
			'colors'  => array(),
			'effect'  => 'none',
		);

		foreach ( $def['fields'] as $field ) {
			$block_defaults[ $field['key'] ] = $field['default'];
		}

		$defaults[ $key ] = $block_defaults;
	}

	return $defaults;
}

/**
 * Sanitize block data against the registry schema.
 *
 * @param string $key  Block key.
 * @param array  $data Raw block data.
 * @return array Sanitized block data.
 */
function vbb_sanitize_block_data( $key, $data ) {
	$def = vbb_get_block_def( $key );
	if ( ! $def ) {
		return $data;
	}

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$sanitized = array();

	foreach ( $def['fields'] as $field ) {
		$fkey    = $field['key'];
		$ftype   = $field['type'];
		$default = $field['default'];

		$value = isset( $data[ $fkey ] ) ? $data[ $fkey ] : $default;

		switch ( $ftype ) {
			case 'text':
			case 'select':
				$sanitized[ $fkey ] = sanitize_text_field( (string) $value );
				break;
			case 'textarea':
				$sanitized[ $fkey ] = sanitize_textarea_field( (string) $value );
				break;
			case 'number':
				$sanitized[ $fkey ] = intval( $value );
				break;
			case 'url':
				$sanitized[ $fkey ] = esc_url_raw( (string) $value );
				break;
			case 'color':
				$sanitized[ $fkey ] = sanitize_hex_color( (string) $value ) ?: '';
				break;
			case 'checkbox':
				$sanitized[ $fkey ] = ! empty( $value );
				break;
			case 'image':
				if ( is_array( $value ) ) {
					$sanitized[ $fkey ] = array(
						'id'  => isset( $value['id'] ) ? intval( $value['id'] ) : 0,
						'url' => isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '',
					);
				} else {
					$sanitized[ $fkey ] = esc_url_raw( (string) $value );
				}
				break;
			case 'repeatable':
				if ( is_array( $value ) && isset( $field['item_fields'] ) ) {
					$sanitized_items = array();
					foreach ( $value as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$sanitized_item = array();
						foreach ( $field['item_fields'] as $ifield ) {
							$ivalue = isset( $item[ $ifield['key'] ] ) ? $item[ $ifield['key'] ] : $ifield['default'];
							switch ( $ifield['type'] ) {
								case 'text':
								case 'select':
									$sanitized_item[ $ifield['key'] ] = sanitize_text_field( (string) $ivalue );
									break;
								case 'textarea':
									$sanitized_item[ $ifield['key'] ] = sanitize_textarea_field( (string) $ivalue );
									break;
								case 'number':
									$sanitized_item[ $ifield['key'] ] = intval( $ivalue );
									break;
								case 'url':
									$sanitized_item[ $ifield['key'] ] = esc_url_raw( (string) $ivalue );
									break;
								case 'checkbox':
									$sanitized_item[ $ifield['key'] ] = ! empty( $ivalue );
									break;
								case 'image':
									$sanitized_item[ $ifield['key'] ] = esc_url_raw( (string) $ivalue );
									break;
								default:
									$sanitized_item[ $ifield['key'] ] = sanitize_text_field( (string) $ivalue );
							}
						}
						$sanitized_items[] = $sanitized_item;
					}
					$sanitized[ $fkey ] = $sanitized_items;
				} else {
					$sanitized[ $fkey ] = array();
				}
				break;
			default:
				$sanitized[ $fkey ] = sanitize_text_field( (string) $value );
		}
	}

	// Preserve non-field keys (enabled, style, colors, effect).
	foreach ( array( 'enabled', 'style', 'colors', 'effect' ) as $meta_key ) {
		if ( isset( $data[ $meta_key ] ) ) {
			$sanitized[ $meta_key ] = $data[ $meta_key ];
		}
	}

	// Ensure style is valid.
	if ( isset( $sanitized['style'] ) && ! in_array( $sanitized['style'], $def['styles'], true ) ) {
		$sanitized['style'] = $def['styles'][0];
	}

	return $sanitized;
}
