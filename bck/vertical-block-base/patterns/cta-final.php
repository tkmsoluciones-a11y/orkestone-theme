<?php
/**
 * Title: CTA final
 * Slug: vertical-block-base/cta-final
 * Categories: vertical-block-base, call-to-action
 * Description: Llamado a la acción final.
 */
$cta_text = vbb_get_vertical_value( 'cta.final.text', __( '¿Listo para avanzar?', 'vertical-block-base' ) );
$cta_btn  = vbb_get_vertical_value( 'cta.final.buttonText', __( 'Contactar ahora', 'vertical-block-base' ) );
$cta_url  = vbb_get_vertical_value( 'cta.final.buttonUrl', '/contacto' );
?>
<!-- wp:group {"align":"full","className":"vbb-section","backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section has-base-color has-primary-background-color has-text-color has-background">
	<!-- wp:heading {"textAlign":"center","textColor":"base"} -->
	<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color"><?php echo vbb_esc_text( $cta_text ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button {"backgroundColor":"secondary","textColor":"contrast"} -->
		<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-secondary-background-color has-text-color has-background wp-element-button" href="<?php echo vbb_esc_url_value( $cta_url ); ?>"><?php echo vbb_esc_text( $cta_btn ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
