<?php
/**
 * Title: Footer commercial
 * Slug: vertical-block-base/footer-commercial
 * Categories: vertical-block-base, footer
 * Description: Footer comercial con datos de vertical.
 */
$settings    = vbb_pro_get_settings();
$footer      = isset( $settings['footerConfig'] ) ? $settings['footerConfig'] : array();
$c1          = isset( $footer['column1'] ) ? $footer['column1'] : array();
$c2          = isset( $footer['column2'] ) ? $footer['column2'] : array();
$bb          = isset( $footer['bottomBar'] ) ? $footer['bottomBar'] : array();
$site_name   = vbb_get_vertical_value( 'brand.siteName', get_bloginfo( 'name' ) );

// Fallback: if no footerConfig is set, use static defaults
$has_config  = ! empty( $footer );
$logo_url    = ! empty( $c1['logoUrl'] ) ? $c1['logoUrl'] : '';
$description = ! empty( $c1['description'] ) ? $c1['description'] : vbb_get_vertical_value( 'brand.tagline', get_bloginfo( 'description' ) );
$column2_title = ! empty( $c2['title'] ) ? $c2['title'] : 'Acceso rápido';
$items       = isset( $c2['items'] ) && is_array( $c2['items'] ) ? $c2['items'] : array();
$copyright   = ! empty( $bb['copyright'] ) ? str_replace( '{year}', gmdate( 'Y' ), $bb['copyright'] ) : '&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . '. Todos los derechos reservados.';
$btn_text    = ! empty( $bb['button']['text'] ) ? $bb['button']['text'] : '';
$btn_url     = ! empty( $bb['button']['url'] ) ? $bb['button']['url'] : '';

// Social links
$socials = array(
	'facebook'  => isset( $c1['socialFacebook'] ) ? $c1['socialFacebook'] : '',
	'instagram' => isset( $c1['socialInstagram'] ) ? $c1['socialInstagram'] : '',
	'linkedin'  => isset( $c1['socialLinkedin'] ) ? $c1['socialLinkedin'] : '',
	'twitter'   => isset( $c1['socialTwitter'] ) ? $c1['socialTwitter'] : '',
);
$has_socials = false;
foreach ( $socials as $url ) { if ( ! empty( $url ) ) { $has_socials = true; break; } }
?>
<!-- wp:group {"align":"full","className":"vbb-site-footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-site-footer" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:0;">
	<!-- wp:columns {"align":"wide"} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column -->
		<div class="wp-block-column">
			<?php if ( $logo_url ) : ?>
			<!-- wp:image {"id":0,"sizeSlug":"full","linkDestination":"none","className":"vbb-footer-logo"} -->
			<figure class="wp-block-image size-full vbb-footer-logo"><img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height:48px;width:auto;" /></figure>
			<!-- /wp:image -->
			<?php endif; ?>

			<?php if ( $description ) : ?>
			<!-- wp:paragraph {"className":"vbb-footer-desc"} -->
			<p class="vbb-footer-desc"><?php echo esc_html( $description ); ?></p>
			<!-- /wp:paragraph -->
			<?php endif; ?>

			<?php if ( $has_socials ) : ?>
			<!-- wp:paragraph {"className":"vbb-footer-socials"} -->
			<p class="vbb-footer-socials">
				<?php foreach ( $socials as $name => $url ) : ?>
					<?php if ( ! empty( $url ) ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</p>
			<!-- /wp:paragraph -->
			<?php endif; ?>
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<?php if ( ! empty( $column2_title ) || ! empty( $items ) ) : ?>
			<!-- wp:heading {"level":3,"className":"vbb-footer-links-title"} -->
			<h3 class="wp-block-heading vbb-footer-links-title"><?php echo esc_html( $column2_title ); ?></h3>
			<!-- /wp:heading -->
			<?php endif; ?>

			<?php if ( ! empty( $items ) ) : ?>
			<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-navigation vbb-footer-links">
				<ul class="wp-block-navigation__container">
					<?php foreach ( $items as $item ) : ?>
						<?php $text = isset( $item['text'] ) ? $item['text'] : ''; ?>
						<?php $url  = isset( $item['url'] ) ? $item['url'] : ''; ?>
						<?php if ( $text && $url ) : ?>
						<li class="wp-block-navigation-item">
							<a class="wp-block-navigation-item__content" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $text ); ?></a>
						</li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<!-- /wp:navigation -->
			<?php endif; ?>
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:group {"align":"full","className":"vbb-footer-bottom","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","margin":{"top":"var:preset|spacing|40"}}},"border":{"top":{"color":"rgba(255,255,255,0.15)","width":"1px","style":"solid"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignfull vbb-footer-bottom" style="border-top-style:solid;border-top-width:1px;border-top-color:rgba(255,255,255,0.15);margin-top:var(--wp--preset--spacing--40);padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);">
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo $copyright; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<!-- /wp:paragraph -->

		<?php if ( $btn_text && $btn_url ) : ?>
		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button {"backgroundColor":"accent","className":"is-style-fill"} -->
			<div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-accent-background-color has-background wp-element-button" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_text ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
		<?php endif; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
