<?php
/**
 * Title: Footer commercial
 * Slug: vertical-block-base/footer-commercial
 * Categories: vertical-block-base, footer
 * Description: Footer comercial con datos de vertical.
 */
$site_name = vbb_get_vertical_value( 'brand.siteName', get_bloginfo( 'name' ) );
$tagline   = vbb_get_vertical_value( 'brand.tagline', get_bloginfo( 'description' ) );
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background">
	<!-- wp:columns {"align":"wide"} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":2,"textColor":"base"} -->
			<h2 class="wp-block-heading has-base-color has-text-color"><?php echo vbb_esc_text( $site_name ); ?></h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo vbb_esc_text( $tagline ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} /-->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
	<p class="has-text-align-center has-small-font-size">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo vbb_esc_text( $site_name ); ?>. Todos los derechos reservados.</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
