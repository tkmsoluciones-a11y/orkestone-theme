<?php
/**
 * Title: Benefits
 * Slug: vertical-block-base/benefits
 * Categories: vertical-block-base
 * Description: Beneficios clave de la vertical.
 */
$benefits = vbb_get_vertical_value(
	'sections.benefits.items',
	array(
		__( 'Arquitectura reusable por vertical.', 'vertical-block-base' ),
		__( 'Edición visual con Gutenberg y Site Editor.', 'vertical-block-base' ),
		__( 'Contenido inicial controlado por JSON.', 'vertical-block-base' ),
	)
);
?>
<!-- wp:group {"align":"full","className":"vbb-section","backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section has-base-color has-primary-background-color has-text-color has-background">
	<!-- wp:heading {"textAlign":"center","textColor":"base"} -->
	<h2 class="wp-block-heading has-text-align-center has-base-color has-text-color"><?php echo esc_html__( 'Beneficios', 'vertical-block-base' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:list -->
	<ul>
		<?php foreach ( (array) $benefits as $benefit ) : ?>
		<li><?php echo vbb_esc_text( $benefit ); ?></li>
		<?php endforeach; ?>
	</ul>
	<!-- /wp:list -->
</div>
<!-- /wp:group -->
