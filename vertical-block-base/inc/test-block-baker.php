<?php
/**
 * Standalone verification tests for the Block Baker.
 *
 * Run: php inc/test-block-baker.php
 *
 * @package VerticalBlockBase
 */

// ── WordPress function stubs ────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( (string) $email, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = '' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]/', '-', $title ), '-' ) );
	}
}

// ── Load helpers (needed for vbb_esc_text, vbb_esc_url_value) ──────────────
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/block-baker.php';

// ── Test helpers ───────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function assert_contains( $haystack, $needle, $label ) {
	global $passed, $failed;
	if ( false !== strpos( $haystack, $needle ) ) {
		echo "  ✅ {$label}\n";
		$passed++;
	} else {
		echo "  ❌ {$label} — expected to contain:\n      \"{$needle}\"\n";
		$failed++;
	}
}

function assert_not_contains( $haystack, $needle, $label ) {
	global $passed, $failed;
	if ( false === strpos( $haystack, $needle ) ) {
		echo "  ✅ {$label}\n";
		$passed++;
	} else {
		echo "  ❌ {$label} — expected NOT to contain:\n      \"{$needle}\"\n";
		$failed++;
	}
}

// ── Tests ──────────────────────────────────────────────────────────────────

echo "=== vbb_svg_placeholder() ===\n";
$placeholder = vbb_svg_placeholder();
assert_contains( $placeholder, 'data:image/svg+xml', 'Returns data URI' );
assert_contains( $placeholder, 'svg', 'Contains SVG namespace' );

echo "\n=== vbb_bake_section() dispatcher ===\n";

// Known type routes correctly.
$page      = array(
	'hero' => array(
		'title'      => 'Welcome',
		'subtitle'   => 'Sub',
		'primaryCta' => 'Start',
		'primaryUrl' => '/start',
	),
);
$sections  = array();
$result    = vbb_bake_section( 'hero', $page, $sections );
assert_contains( $result, 'wp:heading', 'Hero dispatcher produces wp:heading' );
assert_contains( $result, 'Welcome', 'Hero dispatcher includes title text' );
assert_contains( $result, 'wp:buttons', 'Hero dispatcher produces wp:buttons' );

// Unknown type returns fallback.
$unknown = vbb_bake_section( 'custom_x', array(), array() );
assert_contains( $unknown, 'Unknown: custom_x', 'Unknown type returns fallback' );
assert_contains( $unknown, 'wp:paragraph', 'Fallback uses wp:paragraph' );

echo "\n=== vbb_bake_hero() ===\n";
$hero = vbb_bake_hero( array(
	'eyebrow'    => 'Welcome to',
	'title'      => 'Our Site',
	'subtitle'   => 'Best place',
	'primaryCta' => 'Get started',
	'primaryUrl' => '/start',
) );
assert_contains( $hero, 'wp:heading', 'Hero contains wp:heading' );
assert_contains( $hero, 'Our Site', 'Hero contains title text' );
assert_contains( $hero, 'wp:buttons', 'Hero contains wp:buttons' );
assert_contains( $hero, 'vbb-eyebrow', 'Hero contains eyebrow class' );
assert_contains( $hero, 'wp-block-group alignfull vbb-section', 'Hero has vbb-section class' );

// Hero with minimal data.
$hero_min = vbb_bake_hero( array( 'title' => 'Minimal' ) );
assert_contains( $hero_min, 'Minimal', 'Hero minimal has title' );
assert_not_contains( $hero_min, 'wp:buttons', 'Hero minimal no buttons' );

echo "\n=== vbb_bake_hero_centered() ===\n";
$hero_centered = vbb_bake_hero_centered( array(
	'title'   => 'About Us',
	'tagline' => 'Our story',
) );
assert_contains( $hero_centered, 'wp:heading', 'Hero centered contains wp:heading' );
assert_contains( $hero_centered, 'About Us', 'Hero centered contains title' );
assert_contains( $hero_centered, 'Our story', 'Hero centered contains tagline' );

// hero-centered with subtitle fallback.
$hero_sub = vbb_bake_hero_centered( array(
	'title'    => 'Services',
	'subtitle' => 'What we do',
) );
assert_contains( $hero_sub, 'What we do', 'Hero centered reads subtitle as tagline' );

echo "\n=== vbb_bake_services_grid() ===\n";
$services = vbb_bake_services_grid( array(
	'heading' => 'Our Services',
	'items'   => array(
		array( 'title' => 'Service A', 'summary' => 'Desc A', 'ctaText' => 'View', 'ctaUrl' => '/a' ),
		array( 'title' => 'Service B', 'summary' => 'Desc B' ),
		array( 'title' => 'Service C' ),
	),
) );
assert_contains( $services, 'Our Services', 'Services has heading' );
assert_contains( $services, 'Service A', 'Services includes item A' );
assert_contains( $services, 'Service B', 'Services includes item B' );
assert_contains( $services, 'wp:columns', 'Services uses wp:columns' );
assert_contains( $services, 'wp:column', 'Services uses wp:column per item' );
assert_contains( $services, 'vbb-card', 'Services uses vbb-card class' );

// Services with default items.
$services_default = vbb_bake_services_grid( array() );
assert_contains( $services_default, 'Servicios principales', 'Services default heading' );
assert_contains( $services_default, 'Servicio principal', 'Services default item' );

echo "\n=== vbb_bake_benefits() ===\n";
$benefits = vbb_bake_benefits( array(
	'heading' => 'Why Us',
	'items'   => array( 'Fast', 'Reliable', 'Secure' ),
) );
assert_contains( $benefits, 'Why Us', 'Benefits has heading' );
assert_contains( $benefits, 'Fast', 'Benefits includes first item' );
assert_contains( $benefits, 'Reliable', 'Benefits includes second item' );
assert_contains( $benefits, 'wp:columns', 'Benefits uses wp:columns' );
assert_contains( $benefits, 'has-primary-background-color', 'Benefits primary bg' );

echo "\n=== vbb_bake_process() ===\n";
$process = vbb_bake_process( array(
	'heading' => 'How We Work',
	'steps'   => array(
		array( 'title' => 'Step 1', 'description' => 'Plan' ),
		array( 'title' => 'Step 2', 'description' => 'Build' ),
	),
) );
assert_contains( $process, 'How We Work', 'Process has heading' );
assert_contains( $process, 'Step 1', 'Process includes step 1' );
assert_contains( $process, 'Step 2', 'Process includes step 2' );
assert_contains( $process, 'wp:columns', 'Process uses wp:columns' );

echo "\n=== vbb_bake_testimonials() ===\n";
$testimonials = vbb_bake_testimonials( array(
	'heading' => 'Reviews',
	'items'   => array(
		array( 'quote' => 'Great service!', 'author' => 'Jane D.' ),
	),
) );
assert_contains( $testimonials, 'Reviews', 'Testimonials has heading' );
assert_contains( $testimonials, 'Great service!', 'Testimonials includes quote' );
assert_contains( $testimonials, 'Jane D.', 'Testimonials includes author' );
assert_contains( $testimonials, 'wp:quote', 'Testimonials uses wp:quote' );

echo "\n=== vbb_bake_faq() ===\n";
$faq = vbb_bake_faq( array(
	'heading' => 'FAQ',
	'items'   => array(
		array( 'question' => 'Q1?', 'answer' => 'A1.' ),
		array( 'question' => 'Q2?', 'answer' => 'A2.' ),
	),
) );
assert_contains( $faq, 'FAQ', 'FAQ has heading' );
assert_contains( $faq, 'Q1?', 'FAQ includes q1' );
assert_contains( $faq, 'A1.', 'FAQ includes a1' );
assert_contains( $faq, 'Q2?', 'FAQ includes q2' );
assert_contains( $faq, 'wp:details', 'FAQ uses wp:details' );

echo "\n=== vbb_bake_contact_section() ===\n";
$contact = vbb_bake_contact_section( array(
	'heading' => 'Get in Touch',
	'email'   => 'hi@example.com',
	'phone'   => '+1 555 0000',
) );
assert_contains( $contact, 'Get in Touch', 'Contact has heading' );
assert_contains( $contact, 'hi@example.com', 'Contact includes email' );
assert_contains( $contact, '+1 555 0000', 'Contact includes phone' );
assert_contains( $contact, 'wp:columns', 'Contact uses wp:columns' );

echo "\n=== vbb_bake_cta_final() ===\n";
$cta = vbb_bake_cta_final( array(
	'text'       => 'Ready?',
	'buttonText' => 'Go',
	'buttonUrl'  => '/go',
) );
assert_contains( $cta, 'Ready?', 'CTA has text' );
assert_contains( $cta, 'Go', 'CTA has button text' );
assert_contains( $cta, 'wp:buttons', 'CTA uses wp:buttons' );
assert_contains( $cta, 'has-primary-background-color', 'CTA primary bg' );

// CTA without button.
$cta_no_btn = vbb_bake_cta_final( array( 'text' => 'Just text' ) );
assert_contains( $cta_no_btn, 'Just text', 'CTA without button has text' );
assert_not_contains( $cta_no_btn, 'wp:buttons', 'CTA without button omits buttons' );

echo "\n=== Section-level data fallback ===\n";
$page_data = array(
	'hero' => array(
		'title' => 'Page Hero',
	),
);
$section_data = array(
	'hero' => array(
		'title'    => 'Section Hero',
		'subtitle' => 'Section Sub',
	),
);
$merged = vbb_bake_section( 'hero', $page_data, $section_data );
assert_contains( $merged, 'Page Hero', 'Page-level title overrides section-level' );
assert_contains( $merged, 'Section Sub', 'Section-level subtitle appears when page has none' );

echo "\n=== Final block structure ===\n";
$hero2 = vbb_bake_hero( array(
	'title' => 'Test',
) );
assert_contains( $hero2, '<!-- wp:', 'Output has opening block comments' );
assert_contains( $hero2, '<!-- /wp:', 'Output has closing block comments' );
assert_contains( $hero2, '-->', 'Block comment delimiters are complete' );

// Summary
$total = $passed + $failed;
echo "\n========================================\n";
echo "Results: {$passed}/{$total} passed, {$failed}/{$total} failed\n";
echo "========================================\n";

exit( $failed > 0 ? 1 : 0 );
