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

/**
 * Map a block settings key to its frontend CSS section class.
 *
 * @param string $block_key The block key (e.g. 'hero', 'servicesGrid').
 * @return string CSS class selector (e.g. '.vbb-section-hero').
 */
function vbb_pro_section_class_for_block( $block_key ) {
	$map = array(
		'hero'          => 'hero',
		'hero-centered' => 'hero-centered',
		'heroCentered'  => 'hero-centered',
		'servicesGrid'  => 'services-grid',
		'benefits'      => 'benefits',
		'process'       => 'process',
		'testimonials'  => 'testimonials',
		'faq'           => 'faq',
		'contact'       => 'contact-section',   // exception
		'ctaFinal'      => 'cta-final',
		'logoCloud'     => 'logo-cloud',
		'pricing'       => 'pricing-tables',    // exception
		'team'          => 'team',
		'stats'         => 'stats',
		'gallery'       => 'gallery',
		'video'         => 'video',
		'newsletter'    => 'newsletter',
		'map'           => 'map',
		'comparison'    => 'comparison',
		'blog'          => 'blog',
		'divider'       => 'divider',
	);
	$suffix = isset( $map[ $block_key ] ) ? $map[ $block_key ] : str_replace( '_', '-', $block_key );
	return '.vbb-section-' . $suffix;
}

/**
 * Build per-block scoped CSS variable rules.
 *
 * @param array $blocks      Blocks settings array.
 * @param bool  $per_page    Whether these are per-page overrides (adds .page-id- prefix).
 * @param int   $page_id     Page ID for per-page overrides.
 * @return string CSS rules string.
 */
function vbb_pro_block_scoped_css_vars( $blocks, $per_page = false, $page_id = 0 ) {
	$css = '';
	if ( empty( $blocks ) || ! is_array( $blocks ) ) {
		return $css;
	}
	foreach ( $blocks as $bk => $block ) {
		if ( ! is_array( $block ) || empty( $block['colors'] ) || ! is_array( $block['colors'] ) ) {
			continue;
		}
		$block_vars = array();
		foreach ( $block['colors'] as $ckey => $cval ) {
			if ( '' !== $cval ) {
				$block_vars[] = '--vbb-pro-' . $ckey . ':' . esc_html( $cval );
			}
		}
		if ( empty( $block_vars ) ) {
			continue;
		}
		$selector = vbb_pro_section_class_for_block( $bk );
		if ( $per_page && $page_id > 0 ) {
			$selector = '.page-id-' . (int) $page_id . ' ' . $selector;
		}
		$css .= $selector . '{' . implode( ';', $block_vars ) . '}';
	}
	return $css;
}

function vbb_pro_print_css_vars() {
	$s = vbb_pro_get_settings();
	$button_radius = 'pill' === $s['buttons']['style'] ? '999px' : ( 'square' === $s['buttons']['style'] ? '0px' : $s['layout']['radius'] );
	$light_vars = vbb_pro_css_palette_vars( $s['palettes']['light'] );
	$dark_vars  = vbb_pro_css_palette_vars( $s['palettes']['dark'] );
	$base_vars  = 'dark' === $s['colorMode'] ? $dark_vars : $light_vars;

	$block_scoped = vbb_pro_block_scoped_css_vars( $s['blocks'] );

	// Per-page block color overrides
	$page_id    = get_the_ID();
	$page_scoped = '';
	if ( $page_id ) {
		$page_settings = vbb_pro_get_cached_page_settings( $page_id );
		if ( isset( $page_settings['blocks'] ) ) {
			$page_scoped = vbb_pro_block_scoped_css_vars( $page_settings['blocks'], true, $page_id );
		}
	}
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
		--vbb-pro-glow-intensity: 8px;
		/* Override hardcoded theme.json presets with VBB dynamic palette */
		--vbb-pro-base: <?php echo 'dark' === $s['colorMode'] ? esc_html( $s['palettes']['dark']['background'] ?? $s['palettes']['dark']['text'] ) : '#FFFFFF'; ?>;
		--wp--preset--color--primary: var(--vbb-pro-primary);
		--wp--preset--color--secondary: var(--vbb-pro-secondary);
		--wp--preset--color--accent: var(--vbb-pro-accent);
		--wp--preset--color--base: var(--vbb-pro-base);
		--wp--preset--color--contrast: var(--vbb-pro-text);
		--wp--preset--color--muted: var(--vbb-pro-mutedText);
	}
	/* Non-standard slugs (not in theme.json palette) need class-level overrides */
	.has-background-background-color{background-color:var(--vbb-pro-background)!important}
	.has-surface-background-color{background-color:var(--vbb-pro-surface)!important}
	<?php if ( 'auto' === $s['colorMode'] ) : ?>
	@media (prefers-color-scheme: dark){:root{
		<?php echo $dark_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		--vbb-pro-base:<?php echo esc_html( $s['palettes']['dark']['background'] ?? $s['palettes']['dark']['text'] ); ?>;
		--wp--preset--color--primary: var(--vbb-pro-primary);
		--wp--preset--color--secondary: var(--vbb-pro-secondary);
		--wp--preset--color--accent: var(--vbb-pro-accent);
		--wp--preset--color--base: var(--vbb-pro-base);
		--wp--preset--color--contrast: var(--vbb-pro-text);
		--wp--preset--color--muted: var(--vbb-pro-mutedText);
	}}
	<?php endif; ?>
	/* Frontend dark mode toggle — responde a data-theme en <html> */
	html[data-theme="dark"]{
		<?php echo $dark_vars; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		--vbb-pro-base:<?php echo esc_html( $s['palettes']['dark']['background'] ?? $s['palettes']['dark']['text'] ); ?>;
		--wp--preset--color--primary: var(--vbb-pro-primary);
		--wp--preset--color--secondary: var(--vbb-pro-secondary);
		--wp--preset--color--accent: var(--vbb-pro-accent);
		--wp--preset--color--base: var(--vbb-pro-base);
		--wp--preset--color--contrast: var(--vbb-pro-text);
		--wp--preset--color--muted: var(--vbb-pro-mutedText);
	}
	body{background:var(--vbb-pro-background);color:var(--vbb-pro-text);font-family:var(--vbb-pro-body-font)}
	.vbb-section-hero img[src=""],.vbb-section-hero .vbb-hero-bg-image[style*="url('')"],.vbb-section-hero img:not([src]){display:none!important}
	.vbb-section-hero.vbb-style-a:has(img[src=""]) .wp-block-columns{justify-content:center}
	.vbb-section-hero.vbb-style-a:has(img[src=""]) .wp-block-column:first-child{display:none}
	h1,h2,h3,h4,h5,h6{font-family:var(--vbb-pro-heading-font)}
	.wp-block-button__link{border-radius:var(--vbb-pro-button-radius);background:var(--vbb-pro-secondary);color:var(--vbb-pro-primary);transition:box-shadow .3s ease;<?php echo ! empty( $s['buttons']['uppercase'] ) ? 'text-transform:uppercase;letter-spacing:.08em;' : ''; ?>}
	.wp-block-button__link:hover,.wp-block-button__link:focus-visible{box-shadow:0 0 0 var(--vbb-pro-glow-intensity,8px) color-mix(in srgb,var(--vbb-pro-primary) 60%,transparent);outline:none}
	.wp-block-button__link[disabled]{box-shadow:none!important;cursor:default;pointer-events:none}
	.wp-site-blocks > *{--wp--style--global--content-size:var(--vbb-pro-content-width);--wp--style--global--wide-size:var(--vbb-pro-wide-width)}
	.vbb-pro-card,.wp-block-group.is-style-card{border-radius:var(--vbb-pro-radius);box-shadow:var(--vbb-pro-shadow)}
	.has-vbb-pro-surface-background-color{background-color:var(--vbb-pro-surface)}
	/* Navigation & Menu — type */
	.vbb-nav-sticky .wp-block-group:has(.wp-block-navigation){position:sticky;top:0;z-index:100;background:var(--vbb-pro-background);box-shadow:0 2px 12px rgba(0,0,0,.08)}
	.vbb-nav-hamburger .wp-block-navigation__responsive-container-open{display:flex!important}
	.vbb-nav-hamburger .wp-block-navigation__responsive-container:not(.is-menu-open){display:none!important}
	/* Navigation & Menu — style */
	.vbb-nav-style-modern .wp-block-navigation-item__content{position:relative;padding-bottom:2px}
	.vbb-nav-style-modern .wp-block-navigation-item__content::after{content:'';position:absolute;bottom:0;left:0;width:0;height:2px;background:var(--vbb-pro-primary);transition:width .3s ease}
	.vbb-nav-style-modern .wp-block-navigation-item__content:hover::after,.vbb-nav-style-modern .current-menu-item .wp-block-navigation-item__content::after{width:100%}
	.vbb-nav-style-minimal .wp-block-navigation-item__content{opacity:.75;transition:opacity .3s ease}
	.vbb-nav-style-minimal .wp-block-navigation-item__content:hover{opacity:1}
	.vbb-nav-style-classic .wp-block-navigation .wp-block-navigation__container{gap:0!important}
	.vbb-nav-style-classic .wp-block-navigation-item{padding:.5rem 1rem;border-right:1px solid rgba(0,0,0,.08)}
	.vbb-nav-style-classic .wp-block-navigation-item:last-child{border-right:none}
	.vbb-nav-style-pill .wp-block-navigation-item__content{background:var(--vbb-pro-surface);padding:.35rem 1rem!important;border-radius:999px;transition:background .3s ease,color .3s ease}
	.vbb-nav-style-pill .wp-block-navigation-item__content:hover{background:var(--vbb-pro-primary);color:var(--vbb-pro-background)}
	/* Navigation — menu colors + dark toggle colors */
	<?php if ( ! empty( $s['menuConfig']['bgColor'] ) ) : ?>
	.vbb-nav-bg-custom .wp-block-group:has(.wp-block-navigation){background-color:<?php echo esc_html( $s['menuConfig']['bgColor'] ); ?>!important}
	<?php endif; ?>
	<?php if ( ! empty( $s['menuConfig']['textColor'] ) ) : ?>
	.vbb-nav-text-custom .wp-block-navigation-item__content,.vbb-nav-text-custom .wp-block-site-title a{color:<?php echo esc_html( $s['menuConfig']['textColor'] ); ?>!important}
	<?php endif; ?>
	<?php if ( ! empty( $s['menuConfig']['darkBtnBg'] ) || ! empty( $s['menuConfig']['darkBtnText'] ) ) : ?>
	.vbb-dark-toggle-custom .vbb-dark-mode-toggle{<?php echo ! empty( $s['menuConfig']['darkBtnBg'] ) ? 'background-color:' . esc_html( $s['menuConfig']['darkBtnBg'] ) . '!important;' : ''; ?><?php echo ! empty( $s['menuConfig']['darkBtnText'] ) ? 'color:' . esc_html( $s['menuConfig']['darkBtnText'] ) . '!important;border-color:' . esc_html( $s['menuConfig']['darkBtnText'] ) . '!important;' : ''; ?>}
	<?php endif; ?>
	/* Top Bar */
	.vbb-top-bar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;padding:.4rem 1rem;font-size:.85rem;line-height:1.4;margin:0}
	.vbb-top-bar + *{margin-top:0!important}
	.vbb-top-bar + header,.vbb-top-bar + .wp-block-template-part,.vbb-top-bar + [class*="wp-block-group"]{margin-top:0!important}
	.vbb-top-bar-sep{margin:0 .35rem;opacity:.5}
	.vbb-top-bar a{color:inherit;text-decoration:none}
	.vbb-top-bar a:hover{text-decoration:underline;opacity:.8}
	.vbb-top-bar-info{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
	.vbb-top-bar-info span{display:inline-flex;align-items:center;gap:4px}
	.vbb-top-bar-social{display:flex;align-items:center;gap:.5rem}
	.vbb-top-bar-social a{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;transition:opacity .2s;font-size:1rem;line-height:1;text-decoration:none;color:inherit}
	.vbb-top-bar-social a:hover{opacity:.75}
	/* CTA button in nav */
	.vbb-nav-cta{display:inline-flex;align-items:center;padding:.45rem 1.1rem;border-radius:var(--vbb-pro-button-radius,6px);font-weight:600;font-size:.9rem;text-decoration:none;transition:opacity .2s;white-space:nowrap;line-height:1.4}
	.vbb-nav-cta:hover{opacity:.85}
	/* Brand & Header — menu display (show/hide title/logo) */
	.vbb-menu-logo-only .wp-block-site-title{display:none!important}
	.vbb-menu-title-only .custom-logo-link{display:none!important}
	/* Brand & Header — logo auto-fit to nav bar */
	.custom-logo{max-height:48px;width:auto;height:auto;display:block}
	/* Brand & Header — title color + background + anti-aliasing */
	.wp-block-site-title a{color:<?php echo esc_html( $s['headerConfig']['textColor'] ?? '#000000' ); ?>!important;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility}
	header .wp-block-group:first-child,.wp-block-group:has(.wp-block-site-title){background-color:<?php echo esc_html( $s['headerConfig']['bgColor'] ?? '#ffffff' ); ?>!important}
	<?php if ( 'dark' === $s['colorMode'] ) : ?>
	.vbb-color-mode-dark header .wp-block-group:first-child,.vbb-color-mode-dark .wp-block-group:has(.wp-block-site-title){background-color:var(--vbb-pro-surface)!important}
	.vbb-color-mode-dark .wp-block-site-title a{color:var(--vbb-pro-text)!important}
	<?php endif; ?>
	html[data-theme="dark"] header .wp-block-group:first-child,html[data-theme="dark"] .wp-block-group:has(.wp-block-site-title){background-color:var(--vbb-pro-surface)!important}
	html[data-theme="dark"] .wp-block-site-title a{color:var(--vbb-pro-text)!important}
	/* Frontend dark mode toggle — icono + visibilidad */
	.vbb-dark-mode-toggle{background:var(--vbb-pro-surface);border:1px solid var(--vbb-pro-muted-text);border-radius:8px;width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;line-height:1;padding:0;transition:background .2s,border-color .2s,transform .3s;position:relative;color:var(--vbb-pro-text)}
	.vbb-dark-mode-toggle:hover{background:var(--vbb-pro-primary);color:var(--vbb-pro-background);border-color:var(--vbb-pro-primary)}
	.vbb-dark-mode-toggle:focus-visible{outline:2px solid var(--vbb-pro-primary);outline-offset:2px}
	.vbb-dark-mode-toggle[aria-pressed="true"]{transform:rotate(180deg);background:var(--vbb-pro-primary);color:var(--vbb-pro-background);border-color:var(--vbb-pro-primary)}
	.vbb-dark-mode-toggle .vbb-dark-mode-label{display:none}
	.vbb-dark-mode-toggle .vbb-status-dot{display:none}
	.vbb-dark-mode-toggle::before{content:"☖";font-size:1.2rem;pointer-events:none}
	/* Footer — scoped vars */
	.vbb-site-footer{--vbb-footer-bg:<?php echo esc_html( $s['footerConfig']['bgColor'] ?? '#1a1a2e' ); ?>;--vbb-footer-text:<?php echo esc_html( $s['footerConfig']['textColor'] ?? '#ffffff' ); ?>;--vbb-footer-link:<?php echo esc_html( $s['footerConfig']['linkColor'] ?? '#b8b8d0' ); ?>;--vbb-footer-link-hover:<?php echo esc_html( $s['footerConfig']['linkHoverColor'] ?? '#ffffff' ); ?>;--vbb-footer-bottom-bg:<?php echo esc_html( $s['footerConfig']['bottomBarBgColor'] ?? '#0d0d1a' ); ?>}
	<?php echo $block_scoped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo $page_scoped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</style>
	<?php
}
add_action( 'wp_head', 'vbb_pro_print_css_vars', 30 );

function vbb_pro_body_classes( $classes ) {
	$s = vbb_pro_get_settings();
	foreach ( $s['blocks'] as $block => $val ) {
		$enabled = is_array( $val ) ? ! empty( $val['enabled'] ) : ! empty( $val );
		$classes[] = 'vbb-block-' . sanitize_html_class( $block ) . '-' . ( $enabled ? 'on' : 'off' );
	}
	$classes[] = 'vbb-pro-elite-enabled';
	$classes[] = 'vbb-color-mode-' . sanitize_html_class( $s['colorMode'] );
	// Navigation & Menu
	$menu_type  = $s['menuConfig']['type'] ?? 'standard';
	$menu_style = $s['menuConfig']['style'] ?? 'modern';
	$classes[] = 'vbb-nav-' . sanitize_html_class( $menu_type );
	$classes[] = 'vbb-nav-style-' . sanitize_html_class( $menu_style );
	if ( ! empty( $s['menuConfig']['bgColor'] ) )   { $classes[] = 'vbb-nav-bg-custom'; }
	if ( ! empty( $s['menuConfig']['textColor'] ) ) { $classes[] = 'vbb-nav-text-custom'; }
	if ( ! empty( $s['menuConfig']['darkBtnBg'] ) || ! empty( $s['menuConfig']['darkBtnText'] ) ) { $classes[] = 'vbb-dark-toggle-custom'; }
	return $classes;
}
add_filter( 'body_class', 'vbb_pro_body_classes' );

/**
 * Map vertical section type to blocks settings key.
 *
 * @param string $section_type Section type from vertical JSON (e.g. 'services-grid').
 * @return string|null
 */
function vbb_block_key_for_section( $section_type ) {
	$map = array(
		'hero'            => 'hero',
		'hero-centered'   => 'hero',
		'services-grid'   => 'servicesGrid',
		'benefits'        => 'benefits',
		'process'         => 'process',
		'testimonials'    => 'testimonials',
		'faq'             => 'faq',
		'contact-section' => 'contact',
		'cta-final'       => 'ctaFinal',
		'logo-cloud'      => 'logoCloud',
		'pricing'         => 'pricing',
		'team'            => 'team',
		'stats'           => 'stats',
		'gallery'         => 'gallery',
		'video'           => 'video',
		'newsletter'      => 'newsletter',
		'map'             => 'map',
		'comparison'      => 'comparison',
		'blog'            => 'blog',
		'divider'         => 'divider',
	);
	return isset( $map[ $section_type ] ) ? $map[ $section_type ] : null;
}

/**
 * Print block visibility JS based on current toggle state.
 * This handles FSE patterns where render_block filters are bypassed.
 */
function vbb_print_block_visibility_js() {
	$s = vbb_pro_get_settings();
	if ( empty( $s['blocks'] ) ) {
		return;
	}

	// Get the current page ID to apply the correct overrides
	$page_id = get_the_ID();
	$page_settings = vbb_pro_get_cached_page_settings( $page_id );
		// Get the vertical configuration for this specific page to know the order of sections
	$vertical_config = function_exists( 'vbb_get_vertical_config' ) ? vbb_get_vertical_config() : array();
	$page_data = isset( $vertical_config['pages'][$page_id] ) ? $vertical_config['pages'][$page_id] : array();
	$sections = isset( $page_data['sections'] ) ? $page_data['sections'] : array();

	// If it's the home page or no specific sections found, fallback to the home vertical
	if ( empty( $sections ) ) {
		$home_page = vbb_get_vertical_page( 'home' );
		$sections = isset( $home_page['sections'] ) ? $home_page['sections'] : array();
	}

	$hidden_indices = array();
	$index = 0;
	foreach ( $sections as $section_type ) {
		$index++;
		$block_key = vbb_block_key_for_section( $section_type );
		if ( null !== $block_key && isset( $page_settings['blocks'][ $block_key ] ) ) {
			$block_val = $page_settings['blocks'][ $block_key ];
			// Only hide if explicitly set to enabled: false, not merely because the toggle is off
			$is_explicitly_hidden = is_array( $block_val ) && false === $block_val['enabled'];
			if ( $is_explicitly_hidden ) {
				$hidden_indices[] = $index;
			}
		}
	}

	if ( empty( $hidden_indices ) ) {
		return;
	}

	$hidden_json = json_encode( $hidden_indices );
	?>
	<script id="vbb-block-visibility-js">
	(function() {
		document.addEventListener('DOMContentLoaded', function() {
			var indices = <?php echo $hidden_json; ?>;
			var sections = document.querySelectorAll('.vbb-section');
			indices.forEach(function(idx) {
				var el = sections[idx - 1];
				if (el) {
					el.style.display = 'none';
				}
			});
		});
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'vbb_print_block_visibility_js', 35 );

/**
 * Apply VBB headerConfig settings to the frontend.
 * Overrides site title, logo, and adds menu type body class.
 */
function vbb_pro_apply_header_config() {
	$s = vbb_pro_get_settings();

	// Override site title if set
	if ( ! empty( $s['headerConfig']['siteTitle'] ) ) {
		add_filter( 'pre_option_blogname', function( $value ) use ( $s ) {
			return ! empty( $s['headerConfig']['siteTitle'] ) ? $s['headerConfig']['siteTitle'] : $value;
		}, 10, 1 );
	}

	// Override custom logo if URL set
	if ( ! empty( $s['headerConfig']['logoUrl'] ) ) {
		add_filter( 'get_custom_logo', function( $html ) use ( $s ) {
			$logo_url = esc_url( $s['headerConfig']['logoUrl'] );
			$home_url = esc_url( home_url( '/' ) );
			$blogname = get_bloginfo( 'name' );
			return '<a href="' . $home_url . '" class="custom-logo-link" rel="home">'
				. '<img src="' . $logo_url . '" class="custom-logo" alt="' . esc_attr( $blogname ) . '">'
				. '</a>';
		}, 10, 1 );
	}

	// Body class for menu type
	add_filter( 'body_class', function( $classes ) use ( $s ) {
		if ( ! empty( $s['headerConfig']['menuType'] ) ) {
			$classes[] = 'vbb-menu-' . sanitize_html_class( $s['headerConfig']['menuType'] );
		}
		return $classes;
	}, 10, 1 );
}
add_action( 'wp', 'vbb_pro_apply_header_config' );

/**
 * Output the top bar HTML before the header template part.
 */
function vbb_pro_top_bar_html() {
	$s = vbb_pro_get_settings();
	if ( empty( $s['topBar']['enabled'] ) ) {
		return '';
	}
	$tb = $s['topBar'];
	$bg = ! empty( $tb['bgColor'] ) ? 'background-color:' . esc_attr( $tb['bgColor'] ) . ';' : '';
	$tc = ! empty( $tb['textColor'] ) ? 'color:' . esc_attr( $tb['textColor'] ) . ';' : '';
	$style = $bg || $tc ? ' style="' . $bg . $tc . '"' : '';

	$social = '';
	$icons = array(
		'socialFacebook'  => 'f',
		'socialInstagram' => '',
		'socialLinkedin'  => 'in',
	);
	foreach ( $icons as $key => $label ) {
		if ( ! empty( $tb[ $key ] ) ) {
			$social .= '<a href="' . esc_url( $tb[ $key ] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $key ) . '">' . $label . '</a>';
		}
	}

	$has_info = false;
	for ( $i = 1; $i <= 3; $i++ ) {
		if ( ! empty( $tb[ 'info' . $i ]['text'] ) ) {
			$has_info = true;
			break;
		}
	}
	if ( ! $has_info && empty( $social ) ) {
		return '';
	}

	$html  = '<div class="vbb-top-bar"' . $style . '>';
	$info_items = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$key = 'info' . $i;
		if ( empty( $tb[ $key ]['text'] ) ) { continue; }
		$text = esc_html( $tb[ $key ]['text'] );
		$link = ! empty( $tb[ $key ]['link'] ) ? esc_url( $tb[ $key ]['link'] ) : '';
		if ( $link ) {
			$target = strpos( $link, 'http' ) === 0 ? ' target="_blank" rel="noopener"' : '';
			$info_items[] = '<a href="' . $link . '"' . $target . '>' . $text . '</a>';
		} else {
			$info_items[] = '<span>' . $text . '</span>';
		}
	}
	if ( ! empty( $info_items ) ) {
		$html .= '<div class="vbb-top-bar-info">' . implode( '<span class="vbb-top-bar-sep">|</span>', $info_items ) . '</div>';
	}
	if ( ! empty( $social ) ) {
		$html .= '<div class="vbb-top-bar-social">' . $social . '</div>';
	}
	$html .= '</div>';
	return $html;
}

// Prepend top bar before the header template part
add_filter( 'render_block_core/template-part', function( $block_content, $block ) {
	if ( isset( $block['attrs']['slug'] ) && 'header' === $block['attrs']['slug'] ) {
		$top_bar = vbb_pro_top_bar_html();
		if ( $top_bar ) {
			$block_content = $top_bar . $block_content;
		}
	}
	return $block_content;
}, 10, 2 );

// Append CTA button to navigation blocks
add_filter( 'render_block_core/navigation', function( $block_content, $block ) {
	$s = vbb_pro_get_settings();
	if ( empty( $s['menuConfig']['ctaButton']['enabled'] ) ) {
		return $block_content;
	}
	$cta = $s['menuConfig']['ctaButton'];
	if ( empty( $cta['text'] ) ) {
		return $block_content;
	}
	$style = '';
	if ( ! empty( $cta['bgColor'] ) )   { $style .= 'background-color:' . esc_attr( $cta['bgColor'] ) . ';'; }
	if ( ! empty( $cta['textColor'] ) ) { $style .= 'color:' . esc_attr( $cta['textColor'] ) . ';'; }
	$url = ! empty( $cta['url'] ) ? esc_url( $cta['url'] ) : '#';
	$cta_html = '<a href="' . $url . '" class="vbb-nav-cta" style="' . $style . '">' . esc_html( $cta['text'] ) . '</a>';
	return $block_content . $cta_html;
}, 10, 2 );
