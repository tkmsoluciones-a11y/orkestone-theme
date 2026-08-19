<?php
/**
 * Title: Contact section
 * Slug: vertical-block-base/contact-section
 * Categories: vertical-block-base
 * Description: Sección de contacto.
 */
$email = vbb_get_vertical_value( 'contact.email', 'contacto@example.com' );
$phone = vbb_get_vertical_value( 'contact.phone', '+00 000 000 000' );
?>
<!-- wp:group {"align":"wide","className":"vbb-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide vbb-section">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center">Contacto</h2>
	<!-- /wp:heading -->

	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:paragraph --><p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr( sanitize_email( $email ) ); ?>"><?php echo esc_html( sanitize_email( $email ) ); ?></a></p><!-- /wp:paragraph -->
			<!-- wp:paragraph --><p><strong>Teléfono:</strong> <?php echo vbb_esc_text( $phone ); ?></p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:paragraph --><p>Conecta este espacio con tu formulario favorito o con un bloque de formulario compatible con Gutenberg.</p><!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
