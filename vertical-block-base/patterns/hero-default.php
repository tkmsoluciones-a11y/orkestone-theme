<?php
/**
 * Title: Hero default
 * Slug: vertical-block-base/hero-default
 * Categories: vertical-block-base, featured
 * Description: Hero principal con texto de vertical y CTA.
 */
$hero_title    = vbb_get_vertical_value( 'pages.0.hero.title', vbb_get_vertical_value( 'brand.tagline', __( 'Construye una presencia digital clara y confiable', 'vertical-block-base' ) ) );
$hero_subtitle = vbb_get_vertical_value( 'pages.0.hero.subtitle', __( 'Un theme base Gutenberg preparado para adaptar contenido, páginas y modelos por vertical.', 'vertical-block-base' ) );
$hero_eyebrow  = vbb_get_vertical_value( 'pages.0.hero.eyebrow', vbb_get_vertical_value( 'name', __( 'Vertical Block Base', 'vertical-block-base' ) ) );
$cta_text      = vbb_get_vertical_value( 'pages.0.hero.primaryCta', __( 'Comenzar', 'vertical-block-base' ) );
$cta_url       = vbb_get_vertical_value( 'pages.0.hero.primaryUrl', '/contacto' );
?>
<!-- wp:group {"align":"full","className":"vbb-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section has-accent-background-color has-background">
	<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"860px"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:paragraph {"align":"center","className":"vbb-eyebrow"} -->
		<p class="has-text-align-center vbb-eyebrow"><?php echo vbb_esc_text( $hero_eyebrow ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"x-large"} -->
		<h1 class="wp-block-heading has-text-align-center has-x-large-font-size"><?php echo vbb_esc_text( $hero_title ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
		<p class="has-text-align-center has-large-font-size"><?php echo vbb_esc_text( $hero_subtitle ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo vbb_esc_url_value( $cta_url ); ?>"><?php echo vbb_esc_text( $cta_text ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
