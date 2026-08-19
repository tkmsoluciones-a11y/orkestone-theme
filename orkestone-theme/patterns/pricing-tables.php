<?php
/**
 * Title: Pricing Tables
 * Slug: orkestone/pricing-tables
 * Categories: vertical-block-base
 */
$pricing_plans = vbb_get_vertical_value( 'pricing.plans', array() );
if ( empty( $pricing_plans ) ) {
	$pricing_plans = array(
		array(
			'name'        => __( 'Básico',    'vertical-block-base' ),
			'price'       => '$49/mes',
			'description' => '',
			'features'    => array(
				__( 'Acceso a herramientas básicas', 'vertical-block-base' ),
				__( 'Soporte por Email',            'vertical-block-base' ),
				__( '1 Usuario',                    'vertical-block-base' ),
			),
			'ctaText'     => __( 'Comenzar',   'vertical-block-base' ),
			'ctaUrl'      => '#',
			'featured'    => false,
		),
		array(
			'name'        => __( 'Pro',       'vertical-block-base' ),
			'price'       => '$99/mes',
			'description' => '',
			'features'    => array(
				__( 'Todo lo del Básico',        'vertical-block-base' ),
				__( 'Soporte Prioritario',      'vertical-block-base' ),
				__( '5 Usuarios',               'vertical-block-base' ),
				__( 'Análisis Avanzado',        'vertical-block-base' ),
			),
			'ctaText'     => __( 'Elegir Pro', 'vertical-block-base' ),
			'ctaUrl'      => '#',
			'featured'    => true,
		),
		array(
			'name'        => __( 'Enterprise', 'vertical-block-base' ),
			'price'       => __( 'Personalizado', 'vertical-block-base' ),
			'description' => '',
			'features'    => array(
				__( 'Soporte 24/7 Dedicado',  'vertical-block-base' ),
				__( 'Usuarios Ilimitados',    'vertical-block-base' ),
				__( 'Custom Integrations',    'vertical-block-base' ),
				__( 'SLA Garantizado',        'vertical-block-base' ),
			),
			'ctaText'     => __( 'Contactar', 'vertical-block-base' ),
			'ctaUrl'      => '#',
			'featured'    => false,
		),
	);
}
?>
<!-- wp:group {"className":"vbb-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group vbb-section">

  <!-- wp:heading {"textAlign":"center","level":2} -->
  <h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Planes Adaptados a tu Crecimiento', 'vertical-block-base' ); ?></h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph {"align":"center","className":"vbb-muted"} -->
  <p class="has-text-align-center vbb-muted"><?php echo esc_html__( 'Elige la opción que mejor se adapte a la escala de tu proyecto.', 'vertical-block-base' ); ?></p>
  <!-- /wp:paragraph -->

  <!-- wp:columns {"style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}},"blockGap":"var:preset|spacing|50"}} -->
  <div class="wp-block-columns" style="margin-top:var:preset|spacing|60); --wp--style--block-gap: var:preset|spacing|50;">

    <?php foreach ( $pricing_plans as $plan ) : ?>
      <?php $card_classes = 'wp-block-column pricing-card' . ( ! empty( $plan['featured'] ) ? ' featured' : '' ); ?>
      <!-- wp:column {"className":"<?php echo esc_attr( $card_classes ); ?>"} -->
      <div class="<?php echo esc_attr( $card_classes ); ?>">

        <!-- wp:heading {"level":3,"textAlign":"center"} -->
        <h3 class="wp-block-heading has-text-align-center"><?php echo vbb_esc_text( $plan['name'] ?? '' ); ?></h3>
        <!-- /wp:heading -->

        <!-- wp:paragraph {"align":"center","fontSize":"large"} -->
        <p class="has-text-align-center has-large-font-size"><strong><?php echo vbb_esc_text( $plan['price'] ?? '' ); ?></strong></p>
        <!-- /wp:paragraph -->

        <?php if ( ! empty( $plan['description'] ) ) : ?>
          <!-- wp:paragraph {"align":"center","fontSize":"small","className":"vbb-muted"} -->
          <p class="has-text-align-center has-small-font-size vbb-muted"><?php echo vbb_esc_text( $plan['description'] ); ?></p>
          <!-- /wp:paragraph -->
        <?php endif; ?>

        <!-- wp:list -->
        <ul>
          <?php foreach ( (array) ( $plan['features'] ?? array() ) as $feature ) : ?>
            <li><?php echo vbb_esc_text( $feature ); ?></li>
          <?php endforeach; ?>
        </ul>
        <!-- /wp:list -->

        <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
        <div class="wp-block-buttons">
          <!-- wp:button {"width":100} -->
          <div class="wp-block-button">
            <a class="wp-block-button__link wp-element-button"
               href="<?php echo vbb_esc_url_value( $plan['ctaUrl'] ?? '#' ); ?>">
              <?php echo vbb_esc_text( $plan['ctaText'] ?? __( 'Ver plan', 'vertical-block-base' ) ); ?>
            </a>
          </div>
          <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->

      </div>
      <!-- /wp:column -->
    <?php endforeach; ?>

  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
