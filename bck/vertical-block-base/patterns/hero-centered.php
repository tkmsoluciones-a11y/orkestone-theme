<?php
/**
 * Title: Hero centered
 * Slug: vertical-block-base/hero-centered
 * Categories: vertical-block-base, featured
 * Description: Hero centrado para páginas internas.
 */
$page_title = vbb_get_vertical_value( 'name', __( 'Página', 'vertical-block-base' ) );
$tagline    = vbb_get_vertical_value( 'brand.tagline', __( 'Contenido preparado para adaptar por vertical.', 'vertical-block-base' ) );
?>
<!-- wp:group {"align":"full","className":"vbb-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section has-accent-background-color has-background">
	<!-- wp:heading {"textAlign":"center","level":1} -->
	<h1 class="wp-block-heading has-text-align-center"><?php echo vbb_esc_text( $page_title ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
	<p class="has-text-align-center has-large-font-size"><?php echo vbb_esc_text( $tagline ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
