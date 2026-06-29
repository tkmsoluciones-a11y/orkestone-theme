<?php
/**
 * Title: FAQ
 * Slug: vertical-block-base/faq
 * Categories: vertical-block-base
 * Description: Preguntas frecuentes base.
 */
?>
<!-- wp:group {"align":"wide","className":"vbb-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide vbb-section">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center">Preguntas frecuentes</h2>
	<!-- /wp:heading -->

	<!-- wp:details -->
	<details class="wp-block-details"><summary>¿Puedo cambiar la vertical?</summary><!-- wp:paragraph --><p>Sí. Edita <code>config/active-vertical.json</code> y apunta a otra configuración dentro de <code>config/verticals</code>.</p><!-- /wp:paragraph --></details>
	<!-- /wp:details -->

	<!-- wp:details -->
	<details class="wp-block-details"><summary>¿Esto reemplaza theme.json?</summary><!-- wp:paragraph --><p>No. El JSON de vertical complementa a <code>theme.json</code>, pero no lo reemplaza.</p><!-- /wp:paragraph --></details>
	<!-- /wp:details -->
</div>
<!-- /wp:group -->
