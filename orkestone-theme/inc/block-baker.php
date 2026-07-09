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
 * Map of known section types to their baker functions.
 *
 * @return array<string, callable>
 */
function vbb_get_baker_map() {
	return array(
		'hero'            => 'vbb_bake_hero',
		'hero-centered'   => 'vbb_bake_hero_centered',
		'services-grid'   => 'vbb_bake_services_grid',
		'benefits'        => 'vbb_bake_benefits',
		'process'         => 'vbb_bake_process',
		'testimonials'    => 'vbb_bake_testimonials',
		'faq'             => 'vbb_bake_faq',
		'contact-section' => 'vbb_bake_contact_section',
		'cta-final'       => 'vbb_bake_cta_final',
		'logoCloud'       => 'vbb_bake_logo_cloud',
		'pricing'         => 'vbb_bake_pricing_tables',
		'team'            => 'vbb_bake_team_section',
	);
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
 * Bake a Hero section with eyebrow, title, subtitle, and CTA button.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_hero( $data ) {
	$effect     = isset( $data['effect'] ) ? sanitize_key( $data['effect'] ) : 'fade';
	
	// Image resolution
	$image_id   = isset( $data['image_id'] ) ? (int) $data['image_id'] : 0;
	$remote_url = isset( $data['image_url'] ) ? $data['image_url'] : '';
	$image_url  = vbb_resolve_image_url( $image_id, $remote_url );
	
	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero vbb-effect-' . $effect . '","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-hero vbb-effect-' . $effect . ' has-accent-background-color has-background">';
	$output .= "\n\t" . '<!-- wp:columns {"align":"wide"} -->';
	$output .= "\n\t" . '<div class="wp-block-columns alignwide">';
	$output .= "\n\t\t" . '<!-- wp:column {"verticalAlignment":"center"} -->';
	$output .= "\n\t\t" . '<div class="wp-block-column">';
	
	if ( '' !== $image_url ) {
		$output .= "\n\t\t\t" . '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->';
		$output .= "\n\t\t\t" . '<figure class="wp-block-image size-large"><img src="' . $image_url . '" alt="Hero Image" /></figure>';
		$output .= "\n\t\t\t" . '<!-- /wp:image -->';
	}
	
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
	$output .= "\n\t\t\t\t" . '<!-- wp:button -->';
	$output .= "\n\t\t\t\t" . '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{vbb_hero_cta_url}}">{{vbb_hero_cta_text}}</a></div>';
	$output .= "\n\t\t\t\t" . '<!-- /wp:button -->';
	$output .= "\n\t\t\t" . '</div>';
	$output .= "\n\t\t\t" . '<!-- /wp:buttons -->';
	
	$output .= "\n\t\t" . '</div>';
	$output .= "\n\t\t" . '<!-- /wp:column -->';
	$output .= "\n\t" . '</div>';
	$output .= "\n\t" . '<!-- /wp:columns -->';
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';
	
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
			__( 'Arquitectura reusable por vertical.', 'vertical-block-base' ),
			__( 'Edición visual con Gutenberg y Site Editor.', 'vertical-block-base' ),
			__( 'Contenido inicial controlado por JSON.', 'vertical-block-base' ),
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
		if ( is_string( $item ) ) {
			$text = vbb_esc_text( $item );
		} elseif ( is_array( $item ) && isset( $item['text'] ) ) {
			$text = vbb_esc_text( $item['text'] );
		} else {
			continue;
		}

		$output .= "\n\t\t" . '<!-- wp:column -->';
		$output .= "\n\t\t" . '<div class="wp-block-column">';
		$output .= "\n\t\t\t" . '<!-- wp:paragraph {"align":"center"} -->';
		$output .= "\n\t\t\t" . '<p class="has-text-align-center">' . $text . '</p>';
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

	if ( empty( $items ) ) {
		$items = array(
			array(
				'quote'  => __( 'Una experiencia clara, profesional y enfocada en resolver.', 'vertical-block-base' ),
				'author' => __( 'Cliente destacado', 'vertical-block-base' ),
			),
		);
	}

	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-testimonials","backgroundColor":"accent","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-testimonials has-accent-background-color has-background">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';

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
 * Bake a Contact section with email, phone, and form placeholder.
 *
 * @param array $data Merged section data.
 * @return string
 */
function vbb_bake_contact_section( $data ) {
	$heading = isset( $data['heading'] ) ? vbb_esc_text( $data['heading'] ) : __( 'Contacto', 'vertical-block-base' );
	$email   = '{{vbb_contact_email}}';
	$phone   = '{{vbb_contact_phone}}';

	$output  = '<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-contact-section","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignwide vbb-section vbb-section-contact-section">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center">' . $heading . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	$output .= "\n\t" . '<!-- wp:columns -->';
	$output .= "\n\t" . '<div class="wp-block-columns">';
	$output .= "\n\t\t" . '<!-- wp:column -->';
	$output .= "\n\t\t" . '<div class="wp-block-column">';

	if ( '' !== $email ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p><strong>' . esc_html__( 'Email:', 'vertical-block-base' ) . '</strong> <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	if ( '' !== $phone ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p><strong>' . esc_html__( 'Teléfono:', 'vertical-block-base' ) . '</strong> ' . $phone . '</p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	if ( '' === $email && '' === $phone ) {
		$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
		$output .= "\n\t\t\t" . '<p>' . esc_html__( 'Conecta este espacio con tu formulario favorito.', 'vertical-block-base' ) . '</p>';
		$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
	}

	$output .= "\n\t\t" . '</div>';
	$output .= "\n\t\t" . '<!-- /wp:column -->';
	$output .= "\n\t\t" . '<!-- wp:column -->';
	$output .= "\n\t\t" . '<div class="wp-block-column">';
	$output .= "\n\t\t\t" . '<!-- wp:paragraph -->';
	$output .= "\n\t\t\t" . '<p>' . esc_html__( 'Conecta este espacio con tu formulario favorito o con un bloque de formulario compatible con Gutenberg.', 'vertical-block-base' ) . '</p>';
	$output .= "\n\t\t\t" . '<!-- /wp:paragraph -->';
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
	
	$output  = '<!-- wp:group {"align":"full","className":"vbb-section vbb-section-cta-final","backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->';
	$output .= "\n" . '<div class="wp-block-group alignfull vbb-section vbb-section-cta-final has-base-color has-primary-background-color has-text-color has-background">';
	$output .= "\n\t" . '<!-- wp:heading {"textAlign":"center","textColor":"base"} -->';
	$output .= "\n\t" . '<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color">' . $text . '</h2>';
	$output .= "\n\t" . '<!-- /wp:heading -->';
	
	if ( '' !== $btn_text && '' !== $btn_url ) {
		$output .= "\n\t" . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->';
		$output .= "\n\t" . '<div class="wp-block-buttons">';
		$output .= "\n\t\t" . '<!-- wp:button {"backgroundColor":"secondary","textColor":"contrast"} -->';
		$output .= "\n\t\t" . '<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-secondary-background-color has-text-color has-background wp-element-button" href="' . $btn_url . '">' . $btn_text . '</a></div>';
		$output .= "\n\t\t" . '<!-- /wp:button -->';
		$output .= "\n\t" . '</div>';
		$output .= "\n\t" . '<!-- /wp:buttons -->';
	}
	
	$output .= "\n" . '</div>';
	$output .= "\n" . '<!-- /wp:group -->';
	
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
		$section_types = vbb_pro_filter_sections( $section_types );
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

	wp_update_post(
		array(
			'ID'           => $page_id,
			'post_content' => $content,
		)
	);
}

