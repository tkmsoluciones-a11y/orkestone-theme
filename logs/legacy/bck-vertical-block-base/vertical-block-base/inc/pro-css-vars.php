<?php
/** Frontend CSS variables for Pro Elite. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function vbb_pro_shadow_value( $shadow ) {
	switch ( $shadow ) {
		case 'none': return 'none';
		case 'medium': return '0 18px 48px rgba(15,23,36,.18)';
		case 'strong': return '0 24px 72px rgba(15,23,36,.28)';
		case 'soft':
		default: return '0 12px 34px rgba(15,23,36,.12)';
	}
}

function vbb_pro_spacing_value( $spacing ) {
	switch ( $spacing ) {
		case 'compact': return 'clamp(1.75rem, 4vw, 3.5rem)';
		case 'wide': return 'clamp(4rem, 8vw, 7rem)';
		case 'comfortable':
		default: return 'clamp(3rem, 6vw, 5rem)';
	}
}

function vbb_pro_css_palette_vars( $palette ) {
	return sprintf(
		'--vbb-pro-primary:%1$s;--vbb-pro-secondary:%2$s;--vbb-pro-accent:%3$s;--vbb-pro-background:%4$s;--vbb-pro-surface:%5$s;--vbb-pro-text:%6$s;--vbb-pro-muted-text:%7$s;',
		esc_html( $palette['primary'] ),
		esc_html( $palette['secondary'] ),
		esc_html( $palette['accent'] ),
		esc_html( $palette['background'] ),
		esc_html( $palette['surface'] ),
		esc_html( $palette['text'] ),
		esc_html( $palette['mutedText'] )
	);
}

function vbb_pro_print_css_vars() {
	$s = vbb_pro_get_settings();
	$button_radius = 'pill' === $s['buttons']['style'] ? '999px' : ( 'square' === $s['buttons']['style'] ? '0px' : $s['layout']['radius'] );
	$light_vars = vbb_pro_css_palette_vars( $s['palettes']['light'] );
	$dark_vars  = vbb_pro_css_palette_vars( $s['palettes']['dark'] );
	$base_vars  = 'dark' === $s['colorMode'] ? $dark_vars : $light_vars;
	?>
	<style id="vbb-pro-elite-css-vars">
	:root{
		<?php echo $base_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		--vbb-pro-heading-font: <?php echo esc_html( $s['typography']['heading'] ); ?>;
		--vbb-pro-body-font: <?php echo esc_html( $s['typography']['body'] ); ?>;
		--vbb-pro-content-width: <?php echo esc_html( $s['layout']['contentWidth'] ); ?>;
		--vbb-pro-wide-width: <?php echo esc_html( $s['layout']['wideWidth'] ); ?>;
		--vbb-pro-radius: <?php echo esc_html( $s['layout']['radius'] ); ?>;
		--vbb-pro-shadow: <?php echo esc_html( vbb_pro_shadow_value( $s['layout']['shadow'] ) ); ?>;
		--vbb-pro-section-spacing: <?php echo esc_html( vbb_pro_spacing_value( $s['layout']['spacingScale'] ) ); ?>;
		--vbb-pro-button-radius: <?php echo esc_html( $button_radius ); ?>;
	}
	<?php if ( 'auto' === $s['colorMode'] ) : ?>
	@media (prefers-color-scheme: dark){:root{<?php echo $dark_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>}}
	<?php endif; ?>
	body{background:var(--vbb-pro-background);color:var(--vbb-pro-text);font-family:var(--vbb-pro-body-font)}
	h1,h2,h3,h4,h5,h6{font-family:var(--vbb-pro-heading-font)}
	.wp-block-button__link{border-radius:var(--vbb-pro-button-radius);background:var(--vbb-pro-secondary);color:var(--vbb-pro-primary);<?php echo ! empty( $s['buttons']['uppercase'] ) ? 'text-transform:uppercase;letter-spacing:.08em;' : ''; ?>}
	.wp-site-blocks > *{--wp--style--global--content-size:var(--vbb-pro-content-width);--wp--style--global--wide-size:var(--vbb-pro-wide-width)}
	.vbb-pro-card,.wp-block-group.is-style-card{border-radius:var(--vbb-pro-radius);box-shadow:var(--vbb-pro-shadow)}
	.has-vbb-pro-surface-background-color{background-color:var(--vbb-pro-surface)}
	</style>
	<?php
}
add_action( 'wp_head', 'vbb_pro_print_css_vars', 30 );

function vbb_pro_body_classes( $classes ) {
	$s = vbb_pro_get_settings();
	foreach ( $s['blocks'] as $block => $enabled ) {
		$classes[] = 'vbb-block-' . sanitize_html_class( $block ) . '-' . ( $enabled ? 'on' : 'off' );
	}
	$classes[] = 'vbb-pro-elite-enabled';
	$classes[] = 'vbb-color-mode-' . sanitize_html_class( $s['colorMode'] );
	return $classes;
}
add_filter( 'body_class', 'vbb_pro_body_classes' );
