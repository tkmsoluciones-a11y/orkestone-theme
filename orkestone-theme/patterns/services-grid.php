<?php
/**
 * Title: Services grid
 * Slug: vertical-block-base/services-grid
 * Categories: vertical-block-base
 * Description: Grid de servicios declarado por modelo de contenido.
 */
$items = vbb_get_content_model_items( 'service' );
if ( empty( $items ) ) {
	$items = array(
		array(
			'title'   => __( 'Servicio principal', 'vertical-block-base' ),
			'summary'  => __( 'Describe aquí el primer servicio de la vertical.', 'vertical-block-base' ),
			'ctaText'  => __( 'Ver más', 'vertical-block-base' ),
			'ctaUrl'   => '#',
		),
		array(
			'title'   => __( 'Servicio estratégico', 'vertical-block-base' ),
			'summary'  => __( 'Describe aquí un segundo servicio relevante.', 'vertical-block-base' ),
			'ctaText'  => __( 'Ver más', 'vertical-block-base' ),
			'ctaUrl'   => '#',
		),
		array(
			'title'   => __( 'Servicio especializado', 'vertical-block-base' ),
			'summary'  => __( 'Describe aquí una solución especializada.', 'vertical-block-base' ),
			'ctaText'  => __( 'Ver más', 'vertical-block-base' ),
			'ctaUrl'   => '#',
		),
	);
}
?>
<!-- wp:group {"align":"wide","className":"vbb-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide vbb-section">

  <!-- wp:heading {"textAlign":"center"} -->
  <h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Servicios principales', 'vertical-block-base' ); ?></h2>
  <!-- /wp:heading -->

  <!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|60"}}} -->
  <div class="wp-block-columns alignwide" style="--wp--style--block-gap: var:preset|spacing|60;">

    <?php foreach ( array_slice( $items, 0, 3 ) as $item ) : ?>
      <!-- wp:column -->
      <div class="wp-block-column">

        <!-- wp:group {"className":"vbb-card","layout":{"type":"constrained"}} -->
        <div class="wp-block-group vbb-card">

          <!-- wp:heading {"level":3} -->
          <h3 class="wp-block-heading"><?php echo vbb_esc_text( $item['title'] ?? '' ); ?></h3>
          <!-- /wp:heading -->

          <!-- wp:paragraph -->
          <p><?php echo vbb_esc_text( $item['summary'] ?? '' ); ?></p>
          <!-- /wp:paragraph -->

          <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"start"}} -->
          <div class="wp-block-buttons">
            <!-- wp:button {"className":"is-link"} -->
            <div class="wp-block-button is-link">
              <a class="wp-block-button__link wp-element-button" href="<?php echo vbb_esc_url_value( $item['ctaUrl'] ?? '#' ); ?>">
                <?php echo vbb_esc_text( $item['ctaText'] ?? __( 'Ver más', 'vertical-block-base' ) ); ?>
              </a>
            </div>
            <!-- /wp:button -->
          </div>
          <!-- /wp:buttons -->

        </div>
        <!-- /wp:group -->

      </div>
      <!-- /wp:column -->
    <?php endforeach; ?>

  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
