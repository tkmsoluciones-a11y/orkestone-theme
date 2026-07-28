<?php
/**
 * Block Baker — Static Gutenberg markup generator from vertical JSON sections.
 *
 * Transforms section data from vertical JSON into real Gutenberg block markup
 * (<!-- wp:group --> etc.) suitable for direct storage in wp_posts.post_content.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Main dispatcher: route a section type to the appropriate baker.
 *
 * @param string $type     Section type key (e.g. 'hero', 'benefits').
 * @param array  $page     Page-level config array (may contain per-section data).
 * @param array  $sections Top-level sections config from vertical JSON.
 * @return string Gutenberg block markup.
 */
function vbb_bake_section( $type, $page, $sections ) {
	$type = (string) $type;

	// Merge data: sections-level first (base defaults), then page-level (overrides).
	$data = array_merge(
		isset( $sections[ $type ] ) && is_array( $sections[ $type ] ) ? $sections[ $type ] : array(),
		isset( $page[ $type ] ) && is_array( $page[ $type ] ) ? $page[ $type ] : array()
	);

	$map = vbb_get_baker_map();

	if ( isset( $map[ $type ] ) && function_exists( $map[ $type ] ) ) {
		return call_user_func( $map[ $type ], $data );
	}

	// Fallback for unknown section types.
	return vbb_bake_unknown( $type );
}

/**
 * Fallback: emit a readable paragraph for unknown section types.
 *
 * @param string $type Unknown section type.
 * @return string
 */
function vbb_bake_unknown( $type ) {
	$section_class = 'vbb-section vbb-section-' . sanitize_html_class( str_replace( '_', '-', $type ) );
	return '<!-- wp:group {"className":"' . $section_class . '","layout":{"type":"constrained"}} -->'
		. "\n" . '<div class="wp-block-group ' . $section_class . '">'
		. "\n\t" . '<!-- wp:paragraph -->'
		. "\n\t" . '<p>' . esc_html( sprintf( 'Unknown: %s', $type ) ) . '</p>'
		. "\n\t" . '<!-- /wp:paragraph -->'
		. "\n" . '</div>'
		. "\n" . '<!-- /wp:group -->';
}

/**
 * Render a Gutenberg button block with specified appearance style.
 *
 * @param string $text  Button label (may contain {{vbb_*}} placeholder).
 * @param string $url   Button URL (may contain {{vbb_*}} placeholder).
 * @param string $style Button appearance: 'primary'|'secondary'|'outline'.
 * @return string Gutenberg button block markup.
 */
function vbb_render_cta_button( $text, $url, $style = 'primary' ) {
	if ( '' === $text || '' === $url ) {
		return '';
	}

	$bg_color = 'primary' === $style ? 'primary' : ( 'secondary' === $style ? 'secondary' : '' );
	$text_clr = 'primary' === $style ? 'base' : ( 'secondary' === $style ? 'contrast' : 'primary' );
	$class    = 'outline' === $style ? 'is-style-outline' : '';

	$attrs = array();
	if ( $bg_color ) {
		$attrs['backgroundColor'] = $bg_color;
	}
	if ( $text_clr ) {
		$attrs['textColor'] = $text_clr;
	}
	$attrs_json = ! empty( $attrs ) ? ' ' . wp_json_encode( $attrs ) : '';

	$output  = '<!-- wp:button' . $attrs_json . ' -->';
	$output .= "\n" . '<div class="wp-block-button' . ( $class ? ' ' . $class : '' ) . '">';
	$output .= '<a class="wp-block-button__link wp-element-button';
	if ( $bg_color ) {
		$output .= ' has-' . $bg_color . '-background-color has-background';
	}
	if ( $text_clr ) {
		$output .= ' has-' . $text_clr . '-color has-text-color';
	}
	$output .= '" href="' . $url . '">' . $text . '</a>';
	$output .= '</div>';
	$output .= "\n" . '<!-- /wp:button -->';

	return $output;
}

/**
 * Render a Gutenberg heading block.
 *
 * @param string $text  Heading text (may contain {{vbb_*}} placeholder).
 * @param int    $level Heading level (1-6, default 2).
 * @param string $align Text alignment: 'left'|'center'|'right'.
 * @return string Gutenberg heading block markup.
 */
function vbb_render_heading_block( $text, $level = 2, $align = 'left' ) {
	if ( '' === $text ) {
		return '';
	}

	$level    = max( 1, min( 6, (int) $level ) );
	$align_cl = 'center' === $align ? ' has-text-align-center' : ( 'right' === $align ? ' has-text-align-right' : '' );

	$output  = '<!-- wp:heading {"level":' . $level . ',"textAlign":"' . $align . '"} -->';
	$output .= "\n" . '<h' . $level . ' class="wp-block-heading' . $align_cl . '">' . $text . '</h' . $level . '>';
	$output .= "\n" . '<!-- /wp:heading -->';

	return $output;
}

/**
 * Bake a Hero section with eyebrow, title, subtitle, and CTA button.
 *
 * Supports three visual styles dispatched by $data['style'] (A, B, C).
 * Style A: Two-column layout (image left, content right).
 * Style B: Centered single column with background overlay.
 * Style C: Full-bleed background image with left-aligned content.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_hero( $data ) {
	$effect    = isset( $data['effect'] ) ? sanitize_key( $data['effect'] ) : 'fade';
	$style     = isset( $data['style'] ) ? $data['style'] : 'A';

	// Image: use placeholder — dynamic replacement will resolve image_id → attachment URL or fallback to image_url
	$image_url_placeholder = '{{vbb_hero_image_url}}';

	switch ( $style ) {
		case 'B':
			// Style B: Centered single column with background overlay
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero vbb-style-b vbb-effect-' . $effect . '","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-hero vbb-style-b vbb-effect-' . $effect . ' has-accent-background-color has-background">';
			$output .= "\n\t" . '<div class="vbb-hero-bg-overlay"></div>';
			$output .= "\n\t" . '<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"720px"}} -->';
			$output .= "\n\t" . '<div class="wp-block-group alignwide">';
			$output .= "\n\t\t" . '<!-- wp:paragraph {"align":"center","className":"vbb-eyebrow"} -->';
			$output .= "\n\t\t" . '<p class="has-text-align-center vbb-eyebrow">{{vbb_hero_eyebrow}}</p>';
			$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
			$output .= "\n\t\t" . '<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"x-large"} -->';
			$output .= "\n\t\t" . '<h1 class="wp-block-heading has-text-align-center has-x-large-font-size">{{vbb_hero_title}}</h1>';
			$output .= "\n\t\t" . '<!-- /wp:heading -->';
			$output .= "\n\t\t" . '<!-- wp:paragraph {"align":"center","fontSize":"large"} -->';
			$output .= "\n\t\t" . '<p class="has-text-align-center has-large-font-size">{{vbb_hero_subtitle}}</p>';
			$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
			$output .= "\n\t\t" . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->';
			$output .= "\n\t\t" . '<div class="wp-block-buttons">';
			$output .= "\n\t\t\t" . vbb_render_cta_button( '{{vbb_hero_cta_text}}', '{{vbb_hero_cta_url}}', 'primary' );
			// Secondary CTA (outline style)
			$output .= "\n\t\t\t" . vbb_render_cta_button( '{{vbb_hero_secondary_cta}}', '{{vbb_hero_secondary_url}}', 'outline' );
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:buttons -->';
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:group -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;

		case 'C':
			// Style C: Full-bleed background image with left-aligned content
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero vbb-style-c vbb-effect-' . $effect . '","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-hero vbb-style-c vbb-effect-' . $effect . '" style="min-height:70vh;">';
			$output .= "\n\t" . '<div class="vbb-hero-bg-image" style="background-image:url(\'' . $image_url_placeholder . '\')"></div>';
			$output .= "\n\t" . '<div class="vbb-hero-overlay"></div>';
			$output .= "\n\t" . '<!-- wp:columns {"align":"wide","verticalAlignment":"center"} -->';
			$output .= "\n\t" . '<div class="wp-block-columns alignwide">';
			$output .= "\n\t\t" . '<!-- wp:column {"width":"60%"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column" style="flex-basis:60%;z-index:2;">';
			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"className":"vbb-eyebrow"} -->';
			$output .= "\n\t\t\t" . '<p class="vbb-eyebrow">{{vbb_hero_eyebrow}}</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
			$output .= "\n\t\t\t" . '<!-- wp:heading {"level":1,"fontSize":"xx-large"} -->';
			$output .= "\n\t\t\t" . '<h1 class="wp-block-heading has-xx-large-font-size">{{vbb_hero_title}}</h1>';
			$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"fontSize":"large"} -->';
			$output .= "\n\t\t\t" . '<p class="has-large-font-size">{{vbb_hero_subtitle}}</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
			$output .= "\n\t\t\t" . '<!-- wp:buttons -->';
			$output .= "\n\t\t\t" . '<div class="wp-block-buttons">';
			$output .= "\n\t\t\t\t" . vbb_render_cta_button( '{{vbb_hero_cta_text}}', '{{vbb_hero_cta_url}}', 'outline' );
			if ( '{{vbb_hero_secondary_cta}}' !== '' && '{{vbb_hero_secondary_url}}' !== '' ) {
				$output .= "\n\t\t\t\t" . vbb_render_cta_button( '{{vbb_hero_secondary_cta}}', '{{vbb_hero_secondary_url}}', 'secondary' );
			}
			$output .= "\n\t\t\t" . '</div>';
			$output .= "\n\t\t\t" . '<!-- /wp:buttons -->';
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t\t" . '<!-- wp:column {"width":"40%"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column" style="flex-basis:40%;"></div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:columns -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;

		case 'A':
		default:
			// Style A: Two-column layout (image left, content right) — current default
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero vbb-style-a vbb-effect-' . $effect . '","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-hero vbb-style-a vbb-effect-' . $effect . ' has-accent-background-color has-background">';
			$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
			$output .= "\n\t" . '<div class="wp-block-columns alignwide">';
			$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column">';

			$output .= "\n\t\t\t" . '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->';
			$output .= "\n\t\t\t" . '<figure class="wp-block-image size-large"><img src="' . $image_url_placeholder . '" alt="Hero Image" /></figure>';
			$output .= "\n\t\t\t" . '<!-- /wp:image -->';

			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column">';

			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","className":"vbb-eyebrow"} -->';
			$output .= "\n\t\t\t" . '<p class="has-text-align-center vbb-eyebrow">{{vbb_hero_eyebrow}}</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';

			$output .= "\n\t\t\t" . '<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"x-large"} -->';
			$output .= "\n\t\t\t" . '<h1 class="wp-block-heading has-text-align-center has-x-large-font-size">{{vbb_hero_title}}</h1>';
			$output .= "\n\t\t\t" . '<!-- /wp:heading -->';

			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","fontSize":"large"} -->';
			$output .= "\n\t\t\t" . '<p class="has-text-align-center has-large-font-size">{{vbb_hero_subtitle}}</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';

			$output .= "\n\t\t\t" . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->';
			$output .= "\n\t\t\t" . '<div class="wp-block-buttons">';
			$output .= "\n\t\t\t\t" . vbb_render_cta_button( '{{vbb_hero_cta_text}}', '{{vbb_hero_cta_url}}', 'primary' );
			if ( '{{vbb_hero_secondary_cta}}' !== '' && '{{vbb_hero_secondary_url}}' !== '' ) {
				$output .= "\n\t\t\t\t" . vbb_render_cta_button( '{{vbb_hero_secondary_cta}}', '{{vbb_hero_secondary_url}}', 'secondary' );
			}
			$output .= "\n\t\t\t" . '</div>';
			$output .= "\n\t\t\t" . '<!-- /wp:buttons -->';

			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:columns -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;
	}

	return $output;
}


/**
 * Bake a centered Hero section for interior pages.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_hero_centered( $data ) {
	$title   = '{{vbb_hero_centered_title}}';
	$tagline = '{{vbb_hero_centered_tagline}}';

	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero-centered","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-hero-centered has-accent-background-color has-background">';
	$output .= "\n\t" . '<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"860px"}} -->';
	$output .= "\n\t" . '<div class="wp-block-group alignwide">';

	if ( '' !== $title ) {
		$output .= "\n\t\t" . '<!-- wp:heading {"textAlign":"center","level":1} -->';
		$output .= "\n\t\t" . '<h1 class="wp-block-heading has-text-align-center">' . $title . '</h1>';
		$output .= "\n\t\t" . '<!-- /wp:heading -->';
	}

	if ( '' !== $tagline ) {
		$output .= "\n\t\t" . '<!-- wp:paragraph {"align":"center","fontSize":"large"} -->';
		$output .= "\n\t\t" . '<p class="has-text-align-center has-large-font-size">' . $tagline . '</p>';
		$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
	}

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:group -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}

/**
 * Bake a Services Grid section with columns per service item.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_services_grid( $data ) {
	$heading = '{{vbb_services_heading}}';
	$items   = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

	// Default items when no data provided.
	if ( empty( $items ) ) {
		$items = array(
			array(
				'title'   => __( 'Servicio principal', 'vertical-block-base' ),
				'summary' => __( 'Describe aquí el primer servicio de la vertical.', 'vertical-block-base' ),
				'ctaText' => __( 'Ver más', 'vertical-block-base' ),
				'ctaUrl'  => '#',
			),
			array(
				'title'   => __( 'Servicio estratégico', 'vertical-block-base' ),
				'summary' => __( 'Describe aquí un segundo servicio relevante.', 'vertical-block-base' ),
				'ctaText' => __( 'Ver más', 'vertical-block-base' ),
				'ctaUrl'  => '#',
			),
			array(
				'title'   => __( 'Servicio especializado', 'vertical-block-base' ),
				'summary' => __( 'Describe aquí una solución especializada.', 'vertical-block-base' ),
				'ctaText' => __( 'Ver más', 'vertical-block-base' ),
				'ctaUrl'  => '#',
			),
		);
	}

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-services-grid","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-services-grid">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
	$output .= "\n\t" . '<div class="wp-block-columns alignwide">';

	foreach ( array_slice( $items, 0, 6 ) as $item ) {
		$item_title = isset( $item['title'] ) ? vbb_esc_text( $item['title'] ) : '';
		$summary    = isset( $item['summary'] ) ? vbb_esc_text( $item['summary'] ) : '';
		$cta_text   = isset( $item['ctaText'] ) ? vbb_esc_text( $item['ctaText'] ) : '';
		$cta_url    = isset( $item['ctaUrl'] ) ? vbb_esc_url_value( $item['ctaUrl'] ) : '#';

		$output .= "\n\t\t" . '<!-- wp:column -->';
		$output .= "\n\t\t" . '<div class="wp-block-column">';
		$output .= "\n\t\t\t" . '<!-- wp:group {"className":"vbb-card","layout":{"type":"constrained"}} -->';
		$output .= "\n\t\t\t" . '<div class="wp-block-group vbb-card">';

		if ( '' !== $item_title ) {
			$output .= "\n\t\t\t\t" . '<!-- wp:heading {"level":3} -->';
			$output .= "\n\t\t\t\t" . '<h3 class="wp-block-heading">' . $item_title . '</h3>';
			$output .= "\n\t\t\t\t" . '<!-- /wp:heading -->';
		}

		if ( '' !== $summary ) {
			$output .= "\n\t\t\t\t" . '<!-- wp:paragraph -->';
			$output .= "\n\t\t\t\t" . '<p>' . $summary . '</p>';
			$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
		}

		if ( '' !== $cta_text ) {
			$output .= "\n\t\t\t\t" . '<!-- wp:paragraph -->';
			$output .= "\n\t\t\t\t" . '<p><a href="' . $cta_url . '">' . $cta_text . '</a></p>';
			$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
		}

		$output .= "\n\t\t\t" . '</div>';
		$output .= "\n\t\t\t" . '<!-- /wp:group -->';
		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}

/**
 * Bake a Benefits section with columns per benefit.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_benefits( $data ) {
	$heading = '{{vbb_benefits_heading}}';
	$items   = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

	if ( empty( $items ) ) {
		$items = array(
			array(
				'icon'        => 'layout',
				'title'       => __( 'Arquitectura reusable por vertical.', 'vertical-block-base' ),
				'description' => __( 'Estructura base que se adapta a cualquier modelo de negocio sin rehacer el theme.', 'vertical-block-base' ),
			),
			array(
				'icon'        => 'edit',
				'title'       => __( 'Edición visual con Gutenberg.', 'vertical-block-base' ),
				'description' => __( 'Cambia textos, imágenes y estilos desde el editor de bloques nativo.', 'vertical-block-base' ),
			),
			array(
				'icon'        => 'database',
				'title'       => __( 'Contenido inicial controlado por JSON.', 'vertical-block-base' ),
				'description' => __( 'Todos los textos e imágenes vienen de config/verticals/*.json versionable en Git.', 'vertical-block-base' ),
			),
		);
	}

	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-benefits","backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-benefits has-base-color has-primary-background-color has-text-color has-background">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","textColor":"base"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
	$output .= "\n\t" . '<div class="wp-block-columns alignwide">';

	foreach ( array_slice( $items, 0, 6 ) as $item ) {
		$icon        = isset( $item['icon'] ) ? vbb_esc_text( $item['icon'] ) : '';
		$title       = isset( $item['title'] ) ? vbb_esc_text( $item['title'] ) : '';
		$description = isset( $item['description'] ) ? vbb_esc_text( $item['description'] ) : '';

		// Backward compat: si es string o tiene 'text', usar como description
		if ( $icon === '' && $title === '' && $description === '' ) {
			if ( is_string( $item ) ) {
				$description = vbb_esc_text( $item );
			} elseif ( is_array( $item ) && isset( $item['text'] ) ) {
				$description = vbb_esc_text( $item['text'] );
			}
		}

		$output .= "\n\t\t" . '<!-- wp:column -->';
		$output .= "\n\t\t" . '<div class="wp-block-column">';
		
		// Icon wrapper
		if ( $icon !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","className":"vbb-benefit-icon"} -->';
			$output .= "\n\t\t\t" . '<p class="has-text-align-center vbb-benefit-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" style="font-size:2.5rem;height:2.5rem;width:2.5rem;"></span></p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		}
		
		if ( $title !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:heading {"level":4,"align":"center"} -->';
			$output .= "\n\t\t\t" . '<h4 class="wp-block-heading has-text-align-center">' . $title . '</h4>';
			$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
		}
		
		if ( $description !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center"} -->';
			$output .= "\n\t\t\t" . '<p class="has-text-align-center">' . $description . '</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		}

		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}

/**
 * Bake a Process section with columns per step.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_process( $data ) {
	$heading = '{{vbb_process_heading}}';
	$steps   = isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : array();

	if ( empty( $steps ) ) {
		$steps = array(
			array(
				'title'       => __( '1. Diagnóstico', 'vertical-block-base' ),
				'description' => __( 'Entendemos el caso, negocio o necesidad de la vertical.', 'vertical-block-base' ),
			),
			array(
				'title'       => __( '2. Estrategia', 'vertical-block-base' ),
				'description' => __( 'Definimos páginas, secciones, mensajes y llamados a la acción.', 'vertical-block-base' ),
			),
			array(
				'title'       => __( '3. Publicación', 'vertical-block-base' ),
				'description' => __( 'Activamos la vertical y ajustamos el sitio desde el editor.', 'vertical-block-base' ),
			),
		);
	}

		$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-process","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-process">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns -->';
	$output .= "\n\t" . '<div class="wp-block-columns">';

	foreach ( $steps as $step ) {
		$step_title   = isset( $step['title'] ) ? vbb_esc_text( $step['title'] ) : '';
		$description  = isset( $step['description'] ) ? vbb_esc_text( $step['description'] ) : '';

		$output .= "\n\t\t" . '<!-- wp:column -->';
		$output .= "\n\t\t" . '<div class="wp-block-column">';

		if ( '' !== $step_title ) {
			$output .= "\n\t\t\t" . '<!-- wp:heading {"level":3} -->';
			$output .= "\n\t\t\t" . '<h3 class="wp-block-heading">' . $step_title . '</h3>';
			$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
		}

		if ( '' !== $description ) {
			$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
			$output .= "\n\t\t\t" . '<p>' . $description . '</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		}

		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}
	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}

/**
 * Bake a Testimonials section with quote blocks.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_testimonials( $data ) {
	$heading = '{{vbb_testimonials_heading}}';
	$items   = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	$style   = isset( $data['style'] ) ? $data['style'] : 'A';

	if ( empty( $items ) ) {
		$items = array(
			array(
				'quote'  => __( 'Una experiencia clara, profesional y enfocada en resolver.', 'vertical-block-base' ),
				'author' => __( 'Cliente destacado', 'vertical-block-base' ),
			),
		);
	}

	switch ( $style ) {
		case 'B':
			// Style B: Three-column grid with avatar + rating cards
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-testimonials vbb-style-b","backgroundColor":"background","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-testimonials vbb-style-b has-background-background-color has-background">';
			$output .= "\n\t" . vbb_render_heading_block( $heading, 2, 'center' );
			$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
			$output .= "\n\t" . '<div class="wp-block-columns alignwide">';
			foreach ( array_slice( $items, 0, 3 ) as $item ) {
				$quote  = isset( $item['quote'] ) ? vbb_esc_text( $item['quote'] ) : '';
				$author = isset( $item['author'] ) ? vbb_esc_text( $item['author'] ) : '';
				$avatar = isset( $item['avatar'] ) ? $item['avatar'] : '';
				$rating = isset( $item['rating'] ) ? (int) $item['rating'] : 0;

				$output .= "\n\t\t" . '<!-- wp:column -->';
				$output .= "\n\t\t" . '<div class="wp-block-column">';
				$output .= "\n\t\t\t" . '<!-- wp:group {"style":{"spacing":{"padding":"20px"},"border":{"radius":"12px"}},"backgroundColor":"surface","className":"vbb-testimonial-card"} -->';
				$output .= "\n\t\t\t" . '<div class="wp-block-group vbb-testimonial-card has-surface-background-color has-background" style="border-radius:12px;padding:20px;">';
				if ( '' !== $avatar ) {
					$output .= "\n\t\t\t\t" . '<!-- wp:image {"width":48,"height":48,"sizeSlug":"thumbnail","className":"vbb-testimonial-avatar"} -->';
					$output .= "\n\t\t\t\t" . '<figure class="wp-block-image size-thumbnail is-resized vbb-testimonial-avatar"><img src="' . $avatar . '" alt="" style="width:48px;height:48px;border-radius:999px;"/></figure>';
					$output .= "\n\t\t\t\t" . '<!-- /wp:image -->';
				}
				if ( $rating > 0 ) {
					$output .= "\n\t\t\t\t" . '<!-- wp:paragraph {"className":"vbb-testimonial-stars"} -->';
					$output .= "\n\t\t\t\t" . '<p class="vbb-testimonial-stars">' . str_repeat( '&#9733;', $rating ) . '</p>';
					$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
				}
				if ( '' !== $quote ) {
					$output .= "\n\t\t\t\t" . '<!-- wp:paragraph -->';
					$output .= "\n\t\t\t\t" . '<p>' . $quote . '</p>';
					$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
				}
				if ( '' !== $author ) {
					$output .= "\n\t\t\t\t" . '<!-- wp:paragraph {"fontSize":"small"} -->';
					$output .= "\n\t\t\t\t" . '<p class="has-small-font-size"><strong>' . $author . '</strong></p>';
					$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
				}
				$output .= "\n\t\t\t" . '</div>';
				$output .= "\n\t\t\t" . '<!-- /wp:group -->';
				$output .= "\n\t\t" . '</div>';
				$output .= "\n\t\t" . '<!-- /wp:column -->';
			}
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:columns -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;

		case 'C':
			// Style C: Featured + supporting (large featured + smaller supporting)
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-testimonials vbb-style-c","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-testimonials vbb-style-c">';
			$output .= "\n\t" . vbb_render_heading_block( $heading, 2, 'center' );
			$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
			$output .= "\n\t" . '<div class="wp-block-columns alignwide">';
			$output .= "\n\t\t" . '<!-- wp:column {"width":"60%"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column" style="flex-basis:60%;">';
			// Featured item (first)
			$featured = ! empty( $items[0] ) ? $items[0] : array();
			$f_quote  = isset( $featured['quote'] ) ? vbb_esc_text( $featured['quote'] ) : '';
			$f_author = isset( $featured['author'] ) ? vbb_esc_text( $featured['author'] ) : '';
			$f_avatar = isset( $featured['avatar'] ) ? $featured['avatar'] : '';
			$output .= "\n\t\t\t" . '<!-- wp:group {"style":{"spacing":{"padding":"20px"},"border":{"radius":"12px"}},"backgroundColor":"accent","className":"vbb-testimonial-featured"} -->';
			$output .= "\n\t\t\t" . '<div class="wp-block-group vbb-testimonial-featured has-accent-background-color has-background" style="border-radius:12px;padding:20px;">';
			if ( '' !== $f_avatar ) {
				$output .= "\n\t\t\t\t" . '<!-- wp:image {"width":64,"height":64,"sizeSlug":"thumbnail","className":"vbb-testimonial-avatar"} -->';
				$output .= "\n\t\t\t\t" . '<figure class="wp-block-image size-thumbnail is-resized vbb-testimonial-avatar"><img src="' . $f_avatar . '" alt="" style="width:64px;height:64px;border-radius:999px;"/></figure>';
				$output .= "\n\t\t\t\t" . '<!-- /wp:image -->';
			}
			if ( '' !== $f_quote ) {
				$output .= "\n\t\t\t\t" . '<!-- wp:paragraph {"fontSize":"large"} -->';
				$output .= "\n\t\t\t\t" . '<p class="has-large-font-size">' . $f_quote . '</p>';
				$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
			}
			if ( '' !== $f_author ) {
				$output .= "\n\t\t\t\t" . '<!-- wp:paragraph -->';
				$output .= "\n\t\t\t\t" . '<p><strong>' . $f_author . '</strong></p>';
				$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
			}
			$output .= "\n\t\t\t" . '</div>';
			$output .= "\n\t\t\t" . '<!-- /wp:group -->';
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t\t" . '<!-- wp:column {"width":"40%"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column" style="flex-basis:40%;">';
			// Supporting items (remaining)
			$supporting = array_slice( $items, 1, 2 );
			foreach ( $supporting as $s_item ) {
				$s_quote  = isset( $s_item['quote'] ) ? vbb_esc_text( $s_item['quote'] ) : '';
				$s_author = isset( $s_item['author'] ) ? vbb_esc_text( $s_item['author'] ) : '';
				$output .= "\n\t\t\t" . '<!-- wp:group {"style":{"spacing":{"padding":"16px"},"border":{"radius":"8px"}},"backgroundColor":"surface"} -->';
				$output .= "\n\t\t\t" . '<div class="wp-block-group has-surface-background-color has-background" style="border-radius:8px;padding:16px;">';
				if ( '' !== $s_quote ) {
					$output .= "\n\t\t\t\t" . '<!-- wp:paragraph -->';
					$output .= "\n\t\t\t\t" . '<p>' . $s_quote . '</p>';
					$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
				}
				if ( '' !== $s_author ) {
					$output .= "\n\t\t\t\t" . '<!-- wp:paragraph {"fontSize":"small"} -->';
					$output .= "\n\t\t\t\t" . '<p class="has-small-font-size"><strong>' . $s_author . '</strong></p>';
					$output .= "\n\t\t\t\t" . '<!-- /wp:paragraph -->';
				}
				$output .= "\n\t\t\t" . '</div>';
				$output .= "\n\t\t\t" . '<!-- /wp:group -->';
			}
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:columns -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;

		case 'A':
		default:
			// Style A: Stacked quote blocks on accent background (current default)
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-testimonials vbb-style-a","backgroundColor":"accent","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-testimonials vbb-style-a has-accent-background-color has-background">';
			$output .= "\n\t" . vbb_render_heading_block( $heading, 2, 'center' );

			foreach ( array_slice( $items, 0, 3 ) as $item ) {
				$quote  = isset( $item['quote'] ) ? vbb_esc_text( $item['quote'] ) : '';
				$author = isset( $item['author'] ) ? vbb_esc_text( $item['author'] ) : '';

				$output .= "\n\t" . '<!-- wp:quote {"align":"center"} -->';
				$output .= "\n\t" . '<blockquote class="wp-block-quote has-text-align-center">';

				if ( '' !== $quote ) {
					$output .= "\n\t\t" . '<!-- wp:paragraph -->';
					$output .= "\n\t\t" . '<p>' . $quote . '</p>';
					$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
				}

				if ( '' !== $author ) {
					$output .= "\n\t\t" . '<cite>' . $author . '</cite>';
				}

				$output .= "\n\t" . '</blockquote>';
				$output .= "\n\t" . '<!-- /wp:quote -->';
			}

			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;
	}

	return $output;
}

/**
 * Bake an FAQ section with details blocks.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_faq( $data ) {
	$heading = '{{vbb_faq_heading}}';
	$items   = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

	if ( empty( $items ) ) {
		$items = array(
			array(
				'question' => __( '¿Puedo cambiar la vertical?', 'vertical-block-base' ),
				'answer'   => __( 'Sí. Edita config/active-vertical.json y apunta a otra configuración dentro de config/verticals.', 'vertical-block-base' ),
			),
			array(
				'question' => __( '¿Esto reemplaza theme.json?', 'vertical-block-base' ),
				'answer'   => __( 'No. El JSON de vertical complementa a theme.json, pero no lo reemplaza.', 'vertical-block-base' ),
			),
		);
	}

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-faq","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-faq">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';

	foreach ( $items as $item ) {
		$question = isset( $item['question'] ) ? vbb_esc_text( $item['question'] ) : '';
		$answer   = isset( $item['answer'] ) ? vbb_esc_text( $item['answer'] ) : '';

		if ( '' === $question ) {
			continue;
		}

		$output .= "\n\t" . '<!-- wp:details -->';
		$output .= "\n\t" . '<details class="wp-block-details">';
		$output .= "\n\t\t" . '<summary>' . $question . '</summary>';
		$output .= "\n\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t" . '<p>' . ( '' !== $answer ? $answer : '&nbsp;' ) . '</p>';
		$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
		$output .= "\n\t" . '</details>';
		$output .= "\n\t" . '<!-- /wp:details -->';
	}

	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}

/**
 * Bake a Contact section with email, phone, and a functional form.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_contact_section( $data ) {
	$heading       = isset( $data['heading'] ) ? vbb_esc_text( $data['heading'] ) : __( 'Contacto', 'vertical-block-base' );
	$email         = '{{vbb_contact_email}}';
	$phone         = '{{vbb_contact_phone}}';
	$address       = '{{vbb_contact_address}}';
	$form_endpoint = isset( $data['formEndpoint'] ) ? esc_url_raw( $data['formEndpoint'] ) : admin_url( 'admin-ajax.php?action=vbb_contact_form' );
	$form_fields   = isset( $data['formFields'] ) && is_array( $data['formFields'] ) ? $data['formFields'] : array();
	$recaptcha     = isset( $data['recaptcha'] ) ? $data['recaptcha'] : 'none'; // none, v2, v3
	$recaptcha_key = isset( $data['recaptchaKey'] ) ? esc_attr( $data['recaptchaKey'] ) : '';

	// Default fields if none configured
	if ( empty( $form_fields ) ) {
		$form_fields = array(
			array( 'type' => 'text', 'name' => 'name', 'label' => __( 'Nombre', 'vertical-block-base' ), 'required' => true ),
			array( 'type' => 'email', 'name' => 'email', 'label' => __( 'Email', 'vertical-block-base' ), 'required' => true ),
			array( 'type' => 'textarea', 'name' => 'message', 'label' => __( 'Mensaje', 'vertical-block-base' ), 'required' => true ),
		);
	}

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-contact-section","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-contact-section">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';

	// Contact info column + form column
	$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
	$output .= "\n\t" . '<div class="wp-block-columns alignwide">';

	// Column 1: Contact info
	$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center"} -->';
	$output .= "\n\t\t" . '<div class="wp-block-column">';

	if ( '' !== $address ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p><strong>' . esc_html__( 'Dirección:', 'vertical-block-base' ) . '</strong> ' . esc_html( $address ) . '</p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	if ( '' !== $email ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p><strong>' . esc_html__( 'Email:', 'vertical-block-base' ) . '</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	if ( '' !== $phone ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p><strong>' . esc_html__( 'Teléfono:', 'vertical-block-base' ) . '</strong> ' . esc_html( $phone ) . '</p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	if ( '' === $email && '' === $phone && '' === $address ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p>' . esc_html__( 'Configura tu información de contacto en el Command Center.', 'vertical-block-base' ) . '</p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	$output .= "\n\t\t" . '</div>';
	$output .= "\n\t\t" . '<!-- /wp:column -->';

	// Column 2: Form
	$output .= "\n\t\t" . '<!-- wp:column -->';
	$output .= "\n\t\t" . '<div class="wp-block-column">';

	$output .= "\n\t\t\t" . '<!-- wp:html -->';
	$output .= "\n\t\t\t" . '<form class="vbb-contact-form" action="' . esc_attr( $form_endpoint ) . '" method="POST" data-endpoint="' . esc_attr( $form_endpoint ) . '">';
	$output .= "\n\t\t\t" . wp_nonce_field( 'vbb_contact_form', 'vbb_contact_nonce', true, false );

	foreach ( $form_fields as $field ) {
		$type       = isset( $field['type'] ) ? esc_attr( $field['type'] ) : 'text';
		$name       = isset( $field['name'] ) ? esc_attr( $field['name'] ) : '';
		$label      = isset( $field['label'] ) ? esc_html( $field['label'] ) : '';
		$required   = isset( $field['required'] ) && $field['required'] ? ' required' : '';
		$placeholder = isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '';
		$options     = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();

		if ( empty( $name ) ) continue;

		$output .= "\n\t\t\t\t" . '<div class="vbb-form-field vbb-form-field-' . esc_attr( $type ) . '" style="margin-bottom:1rem;">';

		if ( $type === 'select' && ! empty( $options ) ) {
			$output .= "\n\t\t\t\t\t" . '<label for="vbb-contact-' . $name . '">' . $label . ( $required ? ' *' : '' ) . '</label>';
			$output .= "\n\t\t\t\t\t" . '<select id="vbb-contact-' . $name . '" name="' . $name . '"' . $required . '>';
			$output .= "\n\t\t\t\t\t\t" . '<option value="">' . esc_html__( 'Selecciona...', 'vertical-block-base' ) . '</option>';
			foreach ( $options as $opt ) {
				$opt_val = isset( $opt['value'] ) ? esc_attr( $opt['value'] ) : '';
				$opt_lbl = isset( $opt['label'] ) ? esc_html( $opt['label'] ) : $opt_val;
				$output .= "\n\t\t\t\t\t\t" . '<option value="' . $opt_val . '">' . $opt_lbl . '</option>';
			}
			$output .= "\n\t\t\t\t\t" . '</select>';
		} elseif ( $type === 'textarea' ) {
			$output .= "\n\t\t\t\t\t" . '<label for="vbb-contact-' . $name . '">' . $label . ( $required ? ' *' : '' ) . '</label>';
			$output .= "\n\t\t\t\t\t" . '<textarea id="vbb-contact-' . $name . '" name="' . $name . '"' . $required . ' placeholder="' . $placeholder . '" style="width:100%;min-height:120px;"></textarea>';
		} elseif ( $type === 'checkbox' ) {
			$output .= "\n\t\t\t\t\t" . '<label style="display:flex;align-items:center;gap:0.5rem;">';
			$output .= "\n\t\t\t\t\t\t" . '<input type="checkbox" id="vbb-contact-' . $name . '" name="' . $name . '"' . $required . ' value="1">';
			$output .= "\n\t\t\t\t\t\t" . '<span>' . $label . '</span>';
			$output .= "\n\t\t\t\t\t" . '</label>';
		} else {
			$input_type = in_array( $type, array( 'email', 'tel', 'url', 'number' ) ) ? $type : 'text';
			$output .= "\n\t\t\t\t\t" . '<label for="vbb-contact-' . $name . '">' . $label . ( $required ? ' *' : '' ) . '</label>';
			$output .= "\n\t\t\t\t\t" . '<input type="' . $input_type . '" id="vbb-contact-' . $name . '" name="' . $name . '"' . $required . ' placeholder="' . $placeholder . '" style="width:100%;padding:0.75rem;border:1px solid #ddd;border-radius:6px;">';
		}

		$output .= "\n\t\t\t\t" . '</div>';
	}

	// reCAPTCHA v3
	if ( $recaptcha === 'v3' && $recaptcha_key ) {
		$output .= "\n\t\t\t\t" . '<input type="hidden" name="vbb_recaptcha_token" id="vbb_recaptcha_token">';
		$output .= "\n\t\t\t\t" . '<script>grecaptcha.ready(function(){grecaptcha.execute("' . esc_js( $recaptcha_key ) . '",{action:"contact_form"}).then(function(token){document.getElementById("vbb_recaptcha_token").value=token;});});</script>';
	}

	$output .= "\n\t\t\t\t" . '<button type="submit" class="wp-block-button__link wp-element-button" style="width:100%;margin-top:1rem;">' . esc_html__( 'Enviar', 'vertical-block-base' ) . '</button>';
	$output .= "\n\t\t\t\t" . '<div class="vbb-form-message" style="margin-top:1rem;display:none;"></div>';
	$output .= "\n\t\t\t" . '</form>';
	$output .= "\n\t\t\t" . '<!-- /wp:html -->';

	$output .= "\n\t\t" . '</div>';
	$output .= "\n\t\t" . '<!-- /wp:column -->';

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}

/**
 * Bake a CTA Final section with heading, button, and optional eyebrow.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_cta_final( $data ) {
	$text      = '{{vbb_cta_final_text}}';
	$btn_text  = '{{vbb_cta_final_button_text}}';
	$btn_url   = '{{vbb_cta_final_button_url}}';
	$style     = isset( $data['style'] ) ? $data['style'] : 'A';

	switch ( $style ) {
		case 'B':
			// Style B: Split two-column (heading left, button right)
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-cta-final vbb-style-b","backgroundColor":"accent","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-cta-final vbb-style-b has-accent-background-color has-background">';
			$output .= "\n\t" . '<!-- wp:columns {"align":"wide","verticalAlignment":"center"} -->';
			$output .= "\n\t" . '<div class="wp-block-columns alignwide">';
			$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column">';
			$output .= "\n\t\t\t" . vbb_render_heading_block( $text, 2, 'left' );
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center","width":"auto"} -->';
			$output .= "\n\t\t" . '<div class="wp-block-column" style="flex-basis:auto;">';
			$output .= "\n\t\t\t" . vbb_render_cta_button( $btn_text, $btn_url, 'secondary' );
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:columns -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;

		case 'C':
			// Style C: Contained card with border radius, heading + subtitle + button
			$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-cta-final vbb-style-c","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-cta-final vbb-style-c">';
			$output .= "\n\t" . '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px"}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"640px"}} -->';
			$output .= "\n\t" . '<div class="wp-block-group has-surface-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);">';
			$output .= "\n\t\t" . vbb_render_heading_block( $text, 2, 'center' );
			$output .= "\n\t\t" . '<!-- wp:paragraph {"align":"center"} -->';
			$output .= "\n\t\t" . '<p class="has-text-align-center">{{vbb_cta_final_subtitle}}</p>';
			$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
if ( '' !== $btn_text && '' !== $btn_url ) {
			$output .= "\n\t\t" . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->';
			$output .= "\n\t\t" . '<div class="wp-block-buttons">';
			$output .= "\n\t\t\t" . vbb_render_cta_button( '{{vbb_cta_final_button_text}}', '{{vbb_cta_final_button_url}}', 'primary' );
			if ( '{{vbb_cta_final_secondary_cta}}' !== '' && '{{vbb_cta_final_secondary_url}}' !== '' ) {
				$output .= "\n\t\t\t" . vbb_render_cta_button( '{{vbb_cta_final_secondary_cta}}', '{{vbb_cta_final_secondary_url}}', 'secondary' );
			}
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:buttons -->';
			}
			$output .= "\n\t" . '</div>';
			$output .= "\n\t" . '<!-- /wp:group -->';
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;

		case 'A':
		default:
			// Style A: Full-width primary background, centered heading + button (current default)
			$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-cta-final vbb-style-a","backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->';
			$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-cta-final vbb-style-a has-base-color has-primary-background-color has-text-color has-background">';
			$output .= "\n\t" . vbb_render_heading_block( $text, 2, 'center' );
			if ( '' !== $btn_text && '' !== $btn_url ) {
				$output .= "\n\t" . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->';
				$output .= "\n\t" . '<div class="wp-block-buttons">';
				$output .= "\n\t\t" . vbb_render_cta_button( $btn_text, $btn_url, 'secondary' );
				$output .= "\n\t" . '</div>';
				$output .= "\n\t" . '<!-- /wp:buttons -->';
			}
			$output .= "\n" . '</div>';
			$output .= "\n" . '<!-- /wp:group -->';
			break;
	}

	return $output;
}

/**
 * Bake a Logo Cloud section.
 */
function vbb_bake_logo_cloud( $data ) {
	$heading = '{{vbb_logo_cloud_heading}}';
	$subtitle = isset( $data['subtitle'] ) ? vbb_esc_text( $data['subtitle'] ) : '';
	$logos = isset( $data['logos'] ) && is_array( $data['logos'] ) ? $data['logos'] : array();
	
	$output  = '<!-- wp:group {"className":"vbb-section vbb-section-logo-cloud","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group vbb-section vbb-section-logo-cloud">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	if ( '' !== $subtitle ) {
		$output .= "\n\t" . '<!-- wp:paragraph {"align":"center"} -->';
		$output .= "\n\t" . '<p class="has-text-align-center">' . $subtitle . '</p>';
		$output .= "\n\t" . '<!-- /wp:paragraph -->';
	}
	$output .= "\n\t" . '<!-- wp:columns {"style":{"spacing":{"margin":{"top":"40px"}}}} -->';
	$output .= "\n\t" . '<div class="wp-block-columns" style="margin-top:40px">';
	foreach ( array_slice( $logos, 0, 6 ) as $logo ) {
		$url = isset( $logo['url'] ) ? vbb_esc_url_value( $logo['url'] ) : 'https://via.placeholder.com/150x50';
		$output .= "\n\t\t" . '<!-- wp:column -->';
		$output .= "\n\t\t" . '<div class="wp-block-column">';
		$output .= "\n\t\t\t" . '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->';
		$output .= "\n\t\t\t" . '<figure class="wp-block-image size-full"><img src="' . $url . '" alt="Partner"/></figure>';
		$output .= "\n\t\t\t" . '<!-- /wp:image -->';
		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}
	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';
	return $output;
}

/**
 * Bake Pricing Tables section.
 */
function vbb_bake_pricing_tables( $data ) {
	$heading = '{{vbb_pricing_heading}}';
	$plans = isset( $data['plans'] ) && is_array( $data['plans'] ) ? $data['plans'] : array();
	
	$output  = '<!-- wp:group {"className":"vbb-section vbb-section-pricing-tables","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group vbb-section vbb-section-pricing-tables">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns {"style":{"spacing":{"margin":{"top":"40px"}}}} -->';
	$output .= "\n\t" . '<div class="wp-block-columns" style="margin-top:40px">';
	foreach ( array_slice( $plans, 0, 3 ) as $plan ) {
		$title = isset( $plan['title'] ) ? vbb_esc_text( $plan['title'] ) : 'Plan';
		$price = isset( $plan['price'] ) ? vbb_esc_text( $plan['price'] ) : '0';
		$featured = isset( $plan['featured'] ) && $plan['featured'] ? ' featured' : '';
		$output .= "\n\t\t" . '<!-- wp:column {"className":"pricing-card' . $featured . '"} -->';
		$output .= "\n\t\t" . '<div class="wp-block-column pricing-card' . $featured . '">';
		$output .= "\n\t\t\t" . '<!-- wp:heading {"level":3,"textAlign":"center"} -->';
		$output .= "\n\t\t\t" . '<h3 class="wp-block-heading has-text-align-center">' . $title . '</h3>';
		$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
		$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","fontSize":"large"} -->';
		$output .= "\n\t\t\t" . '<p class="has-text-align-center has-large-font-size"><strong>' . $price . '</strong></p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		$output .= "\n\t\t\t" . '<!-- wp:list -->';
		$output .= "\n\t\t\t" . '<ul>';
		if ( isset( $plan['features'] ) && is_array( $plan['features'] ) ) {
			foreach ( $plan['features'] as $f ) {
				$output .= "\n\t\t\t\t" . '<li>' . vbb_esc_text( $f ) . '</li>';
			}
		}
		$output .= "\n\t\t\t" . '</ul>';
		$output .= "\n\t\t\t" . '<!-- /wp:list -->';
		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}
	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';
	return $output;
}

/**
 * Bake Team section.
 */
function vbb_bake_team_section( $data ) {
	$heading = '{{vbb_team_heading}}';
	$members = isset( $data['members'] ) && is_array( $data['members'] ) ? $data['members'] : array();
	
	$output  = '<!-- wp:group {"className":"vbb-section vbb-section-team","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group vbb-section vbb-section-team">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns {"style":{"spacing":{"margin":{"top":"40px"}}}} -->';
	$output .= "\n\t" . '<div class="wp-block-columns" style="margin-top:40px">';
	foreach ( array_slice( $members, 0, 4 ) as $member ) {
		$name = isset( $member['name'] ) ? vbb_esc_text( $member['name'] ) : 'Experto';
		$role = isset( $member['role'] ) ? vbb_esc_text( $member['role'] ) : 'Rol';
		$img  = isset( $member['image'] ) ? vbb_esc_url_value( $member['image'] ) : 'https://via.placeholder.com/300';
		$output .= "\n\t\t" . '<!-- wp:column -->';
		$output .= "\n\t\t" . '<div class="wp-block-column">';
		$output .= "\n\t\t\t" . '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->';
		$output .= "\n\t\t\t" . '<figure class="wp-block-image size-full"><img src="' . $img . '" alt="' . $name . '" /></figure>';
		$output .= "\n\t\t\t" . '<!-- /wp:image -->';
		$output .= "\n\t\t\t" . '<!-- wp:heading {"textAlign":"center","level":4} -->';
		$output .= "\n\t\t\t" . '<h4 class="wp-block-heading has-text-align-center">' . $name . '</h4>';
		$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
		$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","fontSize":"small"} -->';
		$output .= "\n\t\t\t" . '<p class="has-text-align-center has-small-font-size">' . $role . '</p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}
	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';
return $output;
}


/**
 * Bake a Stats/Numbers section with key metrics.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_stats( $data ) {
	$heading = '{{vbb_stats_heading}}';
	$items   = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();

	if ( empty( $items ) ) {
		$items = array(
			array(
				'value'       => '500+',
				'label'       => __( 'Proyectos Entregados', 'vertical-block-base' ),
				'icon'        => 'folder',
				'description' => __( 'Completados a tiempo y con éxito', 'vertical-block-base' ),
			),
			array(
				'value'       => '98%',
				'label'       => __( 'Satisfacción', 'vertical-block-base' ),
				'icon'        => 'heart',
				'description' => __( 'Clientes que nos recomiendan', 'vertical-block-base' ),
			),
			array(
				'value'       => '24/7',
				'label'       => __( 'Soporte', 'vertical-block-base' ),
				'icon'        => 'clock',
				'description' => __( 'Disponibles cuando nos necesitas', 'vertical-block-base' ),
			),
			array(
				'value'       => '50+',
				'label'       => __( 'Equipo Experto', 'vertical-block-base' ),
				'icon'        => 'groups',
				'description' => __( 'Especialistas en cada área', 'vertical-block-base' ),
			),
		);
	}

	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-stats","backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-stats has-base-color has-primary-background-color has-text-color has-background">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","textColor":"base"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
	$output .= "\n\t" . '<div class="wp-block-columns alignwide">';

	foreach ( array_slice( $items, 0, 4 ) as $item ) {
		$value       = isset( $item['value'] ) ? vbb_esc_text( $item['value'] ) : '';
		$label       = isset( $item['label'] ) ? vbb_esc_text( $item['label'] ) : '';
		$icon        = isset( $item['icon'] ) ? vbb_esc_text( $item['icon'] ) : '';
		$description = isset( $item['description'] ) ? vbb_esc_text( $item['description'] ) : '';

		$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center"} -->';
		$output .= "\n\t\t" . '<div class="wp-block-column" style="text-align:center;">';

		if ( $icon !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","className":"vbb-stat-icon"} -->';
			$output .= "\n\t\t\t" . '<p class="has-text-align-center vbb-stat-icon"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" style="font-size:3rem;height:3rem;width:3rem;"></span></p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		}

		if ( $value !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:heading {"level":1,"align":"center","className":"vbb-stat-value"} -->';
			$output .= "\n\t\t\t" . '<h1 class="wp-block-heading has-text-align-center vbb-stat-value">' . $value . '</h1>';
			$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
		}

		if ( $label !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:heading {"level":3,"align":"center","className":"vbb-stat-label"} -->';
			$output .= "\n\t\t\t" . '<h3 class="wp-block-heading has-text-align-center vbb-stat-label">' . $label . '</h3>';
			$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
		}

		if ( $description !== '' ) {
			$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","fontSize":"small"} -->';
			$output .= "\n\t\t\t" . '<p class="has-text-align-center has-small-font-size vbb-stat-desc">' . $description . '</p>';
			$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
		}

		$output .= "\n\t\t" . '</div>';
		$output .= "\n\t\t" . '<!-- /wp:column -->';
	}

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}


/**
 * Bake a Gallery/Portfolio section.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_gallery( $data ) {
	$heading  = '{{vbb_gallery_heading}}';
	$items    = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	$layout   = isset( $data['layout'] ) ? $data['layout'] : 'masonry'; // masonry, grid, carousel

	if ( empty( $items ) ) {
		$items = array(
			array( 'image' => 'https://via.placeholder.com/600x400', 'title' => 'Proyecto 1', 'category' => 'Web', 'url' => '#', 'description' => 'Descripción del proyecto' ),
			array( 'image' => 'https://via.placeholder.com/600x400', 'title' => 'Proyecto 2', 'category' => 'Mobile', 'url' => '#', 'description' => 'Descripción del proyecto' ),
			array( 'image' => 'https://via.placeholder.com/600x400', 'title' => 'Proyecto 3', 'category' => 'Branding', 'url' => '#', 'description' => 'Descripción del proyecto' ),
		);
	}

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-gallery vbb-gallery-' . esc_attr( $layout ) . '","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-gallery vbb-gallery-' . esc_attr( $layout ) . '">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';

	if ( $layout === 'carousel' ) {
		// Simple carousel structure
		$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
		$output .= "\n\t" . '<div class="wp-block-columns alignwide vbb-gallery-carousel">';
		foreach ( array_slice( $items, 0, 6 ) as $item ) {
			$image      = isset( $item['image'] ) ? vbb_esc_url_value( $item['image'] ) : 'https://via.placeholder.com/600x400';
			$title      = isset( $item['title'] ) ? vbb_esc_text( $item['title'] ) : '';
			$category   = isset( $item['category'] ) ? vbb_esc_text( $item['category'] ) : '';
			$url        = isset( $item['url'] ) ? vbb_esc_url_value( $item['url'] ) : '#';
			$description = isset( $item['description'] ) ? vbb_esc_text( $item['description'] ) : '';

			$output .= "\n\t\t" . '<!-- wp:column -->';
			$output .= "\n\t\t" . '<div class="wp-block-column vbb-gallery-item">';
			if ( $url !== '#' ) {
				$output .= "\n\t\t\t" . '<a href="' . esc_url( $url ) . '" class="vbb-gallery-link">';
			}
			$output .= "\n\t\t\t" . '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->';
			$output .= "\n\t\t\t" . '<figure class="wp-block-image size-large"><img src="' . esc_attr( $image ) . '" alt="' . esc_attr( $title ) . '" /></figure>';
			$output .= "\n\t\t\t" . '<!-- /wp:image -->';
			if ( $title !== '' ) {
				$output .= "\n\t\t\t" . '<!-- wp:heading {"level":4,"align":"center"} -->';
				$output .= "\n\t\t\t" . '<h4 class="wp-block-heading has-text-align-center">' . $title . '</h4>';
				$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
			}
			if ( $category !== '' ) {
				$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","className":"vbb-gallery-category"} -->';
				$output .= "\n\t\t\t" . '<p class="has-text-align-center vbb-gallery-category">' . $category . '</p>';
				$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
			}
			if ( $url !== '#' ) {
				$output .= "\n\t\t\t" . '</a>';
			}
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
		}
		$output .= "\n\t" . '</div>';
		$output .= "\n\t" . '<!-- /wp:columns -->';
	} else {
		// Grid / Masonry
		$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
		$output .= "\n\t" . '<div class="wp-block-columns alignwide vbb-gallery-grid">';
		foreach ( array_slice( $items, 0, 8 ) as $item ) {
			$image      = isset( $item['image'] ) ? vbb_esc_url_value( $item['image'] ) : 'https://via.placeholder.com/600x400';
			$title      = isset( $item['title'] ) ? vbb_esc_text( $item['title'] ) : '';
			$category   = isset( $item['category'] ) ? vbb_esc_text( $item['category'] ) : '';
			$url        = isset( $item['url'] ) ? vbb_esc_url_value( $item['url'] ) : '#';
			$description = isset( $item['description'] ) ? vbb_esc_text( $item['description'] ) : '';

			$output .= "\n\t\t" . '<!-- wp:column -->';
			$output .= "\n\t\t" . '<div class="wp-block-column vbb-gallery-item">';
			if ( $url !== '#' ) {
				$output .= "\n\t\t\t" . '<a href="' . esc_url( $url ) . '" class="vbb-gallery-link">';
			}
			$output .= "\n\t\t\t" . '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->';
			$output .= "\n\t\t\t" . '<figure class="wp-block-image size-large"><img src="' . esc_attr( $image ) . '" alt="' . esc_attr( $title ) . '" /></figure>';
			$output .= "\n\t\t\t" . '<!-- /wp:image -->';
			if ( $title !== '' ) {
				$output .= "\n\t\t\t" . '<!-- wp:heading {"level":4,"align":"center"} -->';
				$output .= "\n\t\t\t" . '<h4 class="wp-block-heading has-text-align-center">' . $title . '</h4>';
				$output .= "\n\t\t\t" . '<!-- /wp:heading -->';
			}
			if ( $category !== '' ) {
				$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center","className":"vbb-gallery-category"} -->';
				$output .= "\n\t\t\t" . '<p class="has-text-align-center vbb-gallery-category">' . $category . '</p>';
				$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
			}
			if ( $url !== '#' ) {
				$output .= "\n\t\t\t" . '</a>';
			}
			$output .= "\n\t\t" . '</div>';
			$output .= "\n\t\t" . '<!-- /wp:column -->';
		}
		$output .= "\n\t" . '</div>';
		$output .= "\n\t" . '<!-- /wp:columns -->';
	}

	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}


/**
 * Bake a Video section.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_video( $data ) {
	$heading      = '{{vbb_video_heading}}';
	$subtitle     = isset( $data['subtitle'] ) ? vbb_esc_text( $data['subtitle'] ) : '';
	$video_url    = isset( $data['video_url'] ) ? esc_url_raw( $data['video_url'] ) : '';
	$video_type   = isset( $data['video_type'] ) ? $data['video_type'] : 'youtube'; // youtube, vimeo, mp4
	$poster       = isset( $data['poster'] ) ? esc_url_raw( $data['poster'] ) : '';
	$cta_text     = '{{vbb_video_cta_text}}';
	$cta_url      = '{{vbb_video_cta_url}}';

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-video","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-video">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';

	if ( $subtitle !== '' ) {
		$output .= "\n\t" . '<!-- wp:paragraph {"align":"center"} -->';
		$output .= "\n\t" . '<p class="has-text-align-center">' . $subtitle . '</p>';
		$output .= "\n\t" . '<!-- /wp:paragraph -->';
	}

	$output .= "\n\t" . '<!-- wp:group {"className":"vbb-video-wrapper","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}}} -->';
	$output .= "\n\t" . '<div class="wp-block-group vbb-video-wrapper" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);">';

	if ( $video_type === 'mp4' && $video_url ) {
		// Self-hosted video
		$poster_attr = $poster ? ' poster="' . esc_attr( $poster ) . '"' : '';
		$output .= "\n\t\t" . '<video controls preload="metadata"' . $poster_attr . ' class="vbb-video-player" style="width:100%;max-width:960px;margin:0 auto;display:block;border-radius:12px;">';
		$output .= "\n\t\t\t" . '<source src="' . esc_attr( $video_url ) . '" type="video/mp4">';
		$output .= "\n\t\t\t" . 'Tu navegador no soporta video HTML5.';
		$output .= "\n\t\t" . '</video>';
	} elseif ( $video_type === 'vimeo' && $video_url ) {
		// Vimeo embed
		$video_id = '';
		if ( preg_match( '/(?:vimeo\.com\/)(\d+)/', $video_url, $matches ) ) {
			$video_id = $matches[1];
		} elseif ( preg_match( '/^\d+$/', $video_url ) ) {
			$video_id = $video_url;
		}
		if ( $video_id ) {
			$output .= "\n\t\t" . '<div class="vbb-video-embed" style="position:relative;width:100%;max-width:960px;margin:0 auto;border-radius:12px;overflow:hidden;">';
			$output .= "\n\t\t\t" . '<iframe src="https://player.vimeo.com/video/' . esc_attr( $video_id ) . '?title=0&byline=0&portrait=0" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen style="width:100%;height:auto;aspect-ratio:16/9;"></iframe>';
			$output .= "\n\t\t" . '</div>';
		}
	} else {
		// YouTube embed (default)
		$video_id = '';
		if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $video_url, $matches ) ) {
			$video_id = $matches[1];
		} elseif ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $video_url ) ) {
			$video_id = $video_url;
		}
		if ( $video_id ) {
			$output .= "\n\t\t" . '<div class="vbb-video-embed" style="position:relative;width:100%;max-width:960px;margin:0 auto;border-radius:12px;overflow:hidden;">';
			$output .= "\n\t\t\t" . '<iframe src="https://www.youtube.com/embed/' . esc_attr( $video_id ) . '?rel=0&modestbranding=1" width="560" height="315" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:auto;aspect-ratio:16/9;"></iframe>';
			$output .= "\n\t\t" . '</div>';
		}
	}

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:group -->';

	if ( $cta_text !== '' && $cta_url !== '' ) {
		$output .= "\n\t" . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}}} -->';
		$output .= "\n\t" . '<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--30);">';
		$output .= "\n\t\t" . vbb_render_cta_button( $cta_text, $cta_url, 'primary' );
		$output .= "\n\t" . '</div>';
		$output .= "\n\t" . '<!-- /wp:buttons -->';
	}

	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}


/**
 * Bake a Newsletter/Lead Capture section.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_newsletter( $data ) {
	$heading         = '{{vbb_newsletter_heading}}';
	$description     = isset( $data['description'] ) ? vbb_esc_text( $data['description'] ) : '';
	$placeholder     = isset( $data['placeholder'] ) ? esc_attr( $data['placeholder'] ) : 'tu@email.com';
	$button_text     = '{{vbb_newsletter_button_text}}';
	$success_message = '{{vbb_newsletter_success}}';
	$provider        = isset( $data['provider'] ) ? $data['provider'] : 'custom'; // mailchimp, convertkit, custom
	$list_id         = isset( $data['listId'] ) ? esc_attr( $data['listId'] ) : '';

	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-newsletter","backgroundColor":"secondary","textColor":"base","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-newsletter has-base-color has-secondary-background-color has-text-color has-background">';
	$output .= "\n\t" . '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->';
	$output .= "\n\t" . '<div class="wp-block-group alignwide">';
	$output .= "\n\t\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t\t" . '<!-- /wp:heading -->';

	if ( $description !== '' ) {
		$output .= "\n\t\t" . '<!-- wp:paragraph {"align":"center"} -->';
		$output .= "\n\t\t" . '<p class="has-text-align-center">' . $description . '</p>';
		$output .= "\n\t\t" . '<!-- /wp:paragraph -->';
	}

	$output .= "\n\t\t" . '<!-- wp:html -->';
	$output .= "\n\t\t" . '<form class="vbb-newsletter-form" method="POST" data-provider="' . esc_attr( $provider ) . '" data-list-id="' . esc_attr( $list_id ) . '" style="max-width:480px;margin:0 auto;">';
	$output .= "\n\t\t\t" . wp_nonce_field( 'vbb_newsletter_submit', 'vbb_newsletter_nonce', true, false );
	$output .= "\n\t\t\t" . '<input type="hidden" name="vbb_newsletter_action" value="subscribe">';
	$output .= "\n\t\t\t" . '<div class="vbb-newsletter-fields" style="display:flex;gap:0.75rem;flex-wrap:wrap;justify-content:center;">';
	$output .= "\n\t\t\t\t" . '<input type="email" name="email" placeholder="' . esc_attr( $placeholder ) . '" required style="flex:1;min-width:280px;padding:0.875rem 1rem;border:1px solid #ddd;border-radius:8px;font-size:1rem;">';
	$output .= "\n\t\t\t\t" . '<button type="submit" class="wp-block-button__link wp-element-button" style="padding:0.875rem 2rem;white-space:nowrap;">' . esc_html( $button_text ) . '</button>';
	$output .= "\n\t\t\t" . '</div>';
	$output .= "\n\t\t\t" . '<div class="vbb-newsletter-message" style="margin-top:1rem;text-align:center;display:none;"></div>';
	$output .= "\n\t\t" . '</form>';
	$output .= "\n\t\t" . '<script>';
	$output .= "\n\t\t\t" . 'document.querySelectorAll(".vbb-newsletter-form").forEach(function(form){';
	$output .= "\n\t\t\t\t" . 'form.addEventListener("submit",function(e){';
	$output .= "\n\t\t\t\t\t" . 'e.preventDefault();';
	$output .= "\n\t\t\t\t\t" . 'var btn=form.querySelector("button");btn.disabled=true;btn.innerHTML=\"Suscribiendo...\";';
	$output .= "\n\t\t\t\t\t" . 'fetch(form.action,{method:"POST",body:new FormData(form),headers:{"X-WP-Nonce":document.querySelector(\'[name=\"vbb_newsletter_nonce\"]\').value}}).then(function(r){return r.json();}).then(function(d){';
	$output .= "\n\t\t\t\t\t" . 'if(d.success){form.querySelector(".vbb-newsletter-message").innerHTML=d.message;form.querySelector(".vbb-newsletter-message").style.display="block";form.querySelector(".vbb-newsletter-message").style.color="green";form.reset();}else{form.querySelector(".vbb-newsletter-message").innerHTML=d.message;form.querySelector(".vbb-newsletter-message").style.display="block";form.querySelector(".vbb-newsletter-message").style.color="red";}';
	$output .= "\n\t\t\t\t\t" . 'btn.disabled=false;btn.innerHTML=\"' . esc_js( $button_text ) . '\";';
	$output .= "\n\t\t\t\t\t" . '});';
	$output .= "\n\t\t\t\t" . '});';
	$output .= "\n\t\t\t" . '});';
	$output .= "\n\t\t" . '</script>';
	$output .= "\n\t\t" . '<!-- /wp:html -->';

	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:group -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}


/**
 * Bake a Map/Location section.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_map( $data ) {
	$heading     = '{{vbb_map_heading}}';
	$address     = '{{vbb_map_address}}';
	$lat         = isset( $data['lat'] ) ? floatval( $data['lat'] ) : -34.6037;
	$lng         = isset( $data['lng'] ) ? floatval( $data['lng'] ) : -58.3816;
	$zoom        = isset( $data['zoom'] ) ? intval( $data['zoom'] ) : 15;
	$map_type    = isset( $data['map_type'] ) ? $data['map_type'] : 'roadmap'; // roadmap, satellite, hybrid, terrain
	$marker_title = isset( $data['marker_title'] ) ? vbb_esc_text( $data['marker_title'] ) : 'Ubicación';

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-map","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-map">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","level":2} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';

	if ( $address !== '' ) {
		$output .= "\n\t" . '<!-- wp:paragraph {"align":"center"} -->';
		$output .= "\n\t" . '<p class="has-text-align-center">' . $address . '</p>';
		$output .= "\n\t" . '<!-- /wp:paragraph -->';
	}

	$output .= "\n\t" . '<!-- wp:html -->';
	$output .= "\n\t" . '<div class="vbb-map-wrapper" style="width:100%;height:400px;border-radius:12px;overflow:hidden;margin-top:1.5rem;position:relative;">';
	$output .= "\n\t\t" . '<div id="vbb-map-' . uniqid() . '" class="vbb-map-canvas" style="width:100%;height:100%;" data-lat="' . esc_attr( $lat ) . '" data-lng="' . esc_attr( $lng ) . '" data-zoom="' . esc_attr( $zoom ) . '" data-type="' . esc_attr( $map_type ) . '" data-marker-title="' . esc_attr( $marker_title ) . '" data-address="' . esc_attr( $address ) . '"></div>';
	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<script>';
	$output .= "\n\t\t" . 'function vbbInitMap(){';
	$output .= "\n\t\t\t" . 'document.querySelectorAll(".vbb-map-canvas").forEach(function(el){';
	$output .= "\n\t\t\t\t" . 'if(typeof google!=="undefined"&&google.maps){';
	$output .= "\n\t\t\t\t\t" . 'var lat=parseFloat(el.dataset.lat),lng=parseFloat(el.dataset.lng),zoom=parseInt(el.dataset.zoom)||15,type=el.dataset.type||"roadmap",markerTitle=el.dataset.markerTitle||"Ubicacion";';
	$output .= "\n\t\t\t\t\t" . 'var map=new google.maps.Map(el,{center:{lat:lat,lng:lng},zoom:zoom,mapTypeId:google.maps.MapTypeId[type.toUpperCase()],styles:[{featureType:"poi",elementType:"labels",stylers:[{visibility:"off"}]},{featureType:"transit",elementType:"labels",stylers:[{visibility:"off"}]}]);';
	$output .= "\n\t\t\t\t\t" . 'new google.maps.Marker({position:{lat:lat,lng:lng},map:map,title:markerTitle});';
	$output .= "\n\t\t\t\t" . '}else{';
	$output .= "\n\t\t\t\t\t" . 'el.innerHTML=\'<div style="padding:2rem;text-align:center;background:#f5f5f5;border-radius:8px;color:#666;">Cargando mapa... (requiere API Key de Google Maps)</div>\';';
	$output .= "\n\t\t\t\t" . '}';
	$output .= "\n\t\t\t" . '});';
	$output .= "\n\t\t" . '}';
	$output .= "\n\t\t" . 'if(typeof google!=="undefined"&&google.maps){vbbInitMap();}else{window.vbbMapInitCallback=vbbInitMap;}';
	$output .= "\n\t" . '</script>';
	$output .= "\n\t" . '<!-- /wp:html -->';

	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';

	return $output;
}


/**
 * Bake full page content for a given page ID.
 *
 * Loads merged settings, determines sections from the vertical config,
 * dispatches each section through the baker pipeline, and updates the
 * post content via wp_update_post().
 *
 * @param int $page_id WordPress page ID.
 * @return void
 */
function vbb_bake_page_content( $page_id ) {
	$page_id = (int) $page_id;

	if ( ! function_exists( 'vbb_pro_get_page_settings' ) ) {
		return;
	}

	$settings        = vbb_pro_get_page_settings( $page_id );
	$vertical_config = vbb_get_vertical_config();
	$sections        = isset( $vertical_config['sections'] ) && is_array( $vertical_config['sections'] )
		? $vertical_config['sections']
		: array();

	// Find this page's section list from vertical config.
	$page_config    = vbb_get_vertical_page_by_id( $page_id );
	$section_types  = isset( $page_config['sections'] ) && is_array( $page_config['sections'] )
		? $page_config['sections']
		: array();

	// Filter enabled sections based on block toggles.
	if ( function_exists( 'vbb_pro_filter_sections' ) ) {
		$section_types = vbb_pro_filter_sections( $section_types, $page_id );
	}

	$content = '';
	foreach ( $section_types as $type ) {
		$content .= vbb_bake_section( $type, $page_config, $sections ) . "\n\n";
	}

	if ( '' === trim( $content ) ) {
		$content = '<!-- wp:paragraph --><p>'
			. esc_html__( 'Page content will appear here after baking.', 'vertical-block-base' )
			. '</p><!-- /wp:paragraph -->';
	}

	// Add data-block-key to section wrapper divs for preview click→card feature.
	$content = preg_replace_callback(
		'/class="([^"]*vbb-section-([a-z][a-z0-9-]*)[^"]*)"/',
		function ( $m ) {
			return 'class="' . $m[1] . '" data-block-key="' . $m[2] . '"';
		},
		$content
	);

	wp_update_post(
		array(
			'ID'           => $page_id,
			'post_content' => $content,
		)
	);
}
