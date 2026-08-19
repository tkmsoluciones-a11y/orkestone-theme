<?php
/**
 * Title: Testimonials
 * Slug: vertical-block-base/testimonials
 * Categories: vertical-block-base
 * Description: Testimonios base.
 */
$testimonial_quote = vbb_get_vertical_value(
	'sections.testimonials.items.0.quote',
	__( 'Una experiencia clara, profesional y enfocada en resolver.', 'vertical-block-base' )
);
$testimonial_author = vbb_get_vertical_value(
	'sections.testimonials.items.0.author',
	__( 'Cliente destacado', 'vertical-block-base' )
);
?>
<!-- wp:group {"align":"full","className":"vbb-section","backgroundColor":"accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section has-accent-background-color has-background">

  <!-- wp:heading {"textAlign":"center"} -->
  <h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Confianza y resultados', 'vertical-block-base' ); ?></h2>
  <!-- /wp:heading -->

  <!-- wp:quote {"className":"vbb-card","layout":{"type":"constrained","contentSize":"720px"}} -->
  <blockquote class="wp-block-quote vbb-card has-text-align-center">

    <!-- wp:paragraph {"fontSize":"large"} -->
    <p class="has-large-font-size"><?php echo vbb_esc_text( $testimonial_quote ); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph {"fontSize":"small","textColor":"muted"} -->
    <p class="has-small-font-size has-muted-color has-text-color">
      <strong><?php echo vbb_esc_text( $testimonial_author ); ?></strong>
    </p>
    <!-- /wp:paragraph -->

  </blockquote>
  <!-- /wp:quote -->
</div>
<!-- /wp:group -->
