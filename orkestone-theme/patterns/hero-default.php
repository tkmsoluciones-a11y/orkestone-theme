<?php
/**
 * Title: Hero default
 * Slug: vertical-block-base/hero-default
 * Categories: vertical-block-base, featured
 * Description: Hero principal con texto de vertical y CTA.
 */
$hero_title    = vbb_get_vertical_value( 'pages.0.hero.title',    vbb_get_vertical_value( 'brand.tagline', __( 'Construye una presencia digital clara y confiable', 'vertical-block-base' ) ) );
$hero_subtitle = vbb_get_vertical_value( 'pages.0.hero.subtitle', __( 'Un theme base Gutenberg preparado para adaptar contenido, páginas y modelos por vertical.', 'vertical-block-base' ) );
$hero_eyebrow  = vbb_get_vertical_value( 'pages.0.hero.eyebrow',  vbb_get_vertical_value( 'name', __( 'Vertical Block Base', 'vertical-block-base' ) ) );
$cta_text      = vbb_get_vertical_value( 'pages.0.hero.primaryCta', __( 'Comenzar', 'vertical-block-base' ) );
$cta_url       = vbb_get_vertical_value( 'pages.0.hero.primaryUrl', '/contacto' );
$cta_secondary_text = vbb_get_vertical_value( 'pages.0.hero.secondaryCta', '' );
$cta_secondary_url  = vbb_get_vertical_value( 'pages.0.hero.secondaryUrl', '' );
?>
<!-- wp:group {"align":"full","className":"vbb-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section has-accent-background-color has-background">

  <!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"780px"},"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
  <div class="wp-block-group alignwide vbb-section-inner">

    <!-- wp:paragraph {"align":"center","className":"vbb-eyebrow"} -->
    <p class="has-text-align-center vbb-eyebrow"><?php echo vbb_esc_text( $hero_eyebrow ); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:heading {"textAlign":"center","level":1,"fontSize":"x-large"} -->
    <h1 class="wp-block-heading has-text-align-center has-x-large-font-size"><?php echo vbb_esc_text( $hero_title ); ?></h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center","fontSize":"large","className":"vbb-muted"} -->
    <p class="has-text-align-center has-large-font-size vbb-muted"><?php echo vbb_esc_text( $hero_subtitle ); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap","verticalAlignment":"center"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
    <div class="wp-block-buttons" style="margin-top:var:preset|spacing|50)">
      <?php if ( ! empty( $cta_secondary_text ) && ! empty( $cta_secondary_url ) ) : ?>
        <!-- wp:button {"className":"is-link"} -->
        <div class="wp-block-button is-link">
          <a class="wp-block-button__link wp-element-button" href="<?php echo vbb_esc_url_value( $cta_secondary_url ); ?>"><?php echo vbb_esc_text( $cta_secondary_text ); ?></a>
        </div>
        <!-- /wp:button -->
      <?php endif; ?>
      <!-- wp:button -->
      <div class="wp-block-button">
        <a class="wp-block-button__link wp-element-button" href="<?php echo vbb_esc_url_value( $cta_url ); ?>"><?php echo vbb_esc_text( $cta_text ); ?></a>
      </div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->

  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
