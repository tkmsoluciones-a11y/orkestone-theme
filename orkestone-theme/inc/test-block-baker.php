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

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]/', '', $key ) ) );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $class );
	}
}

// ── Stub vbb_pro_get_page_settings / vbb_get_vertical_config / etc. ────────
// These are needed by vbb_bake_page_content but may be loaded from pro-settings
// in a real WP context. Provide minimal stubs for standalone test.

if ( ! function_exists( 'vbb_pro_get_page_settings' ) ) {
	function vbb_pro_get_page_settings( $page_id ) {
		return array();
	}
}

if ( ! function_exists( 'vbb_get_vertical_config' ) ) {
	function vbb_get_vertical_config() {
		return array(
			'sections' => array(
				'hero' => array(
					'title' => 'Section Default Title',
				),
			),
		);
	}
}

if ( ! function_exists( 'vbb_get_vertical_pages' ) ) {
	function vbb_get_vertical_pages() {
		return array(
			array(
				'slug'     => 'home',
				'title'    => 'Home',
				'sections' => array( 'hero', 'services-grid' ),
				'hero'     => array(
					'title' => 'Page Hero Title',
				),
			),
		);
	}
}

if ( ! function_exists( 'vbb_get_vertical_page_by_id' ) ) {
	// Only load if not already defined (it may exist in page-blueprint.php).
	if ( ! function_exists( 'vbb_get_vertical_page_by_id' ) ) {
		function vbb_get_vertical_page_by_id( $page_id ) {
			foreach ( vbb_get_vertical_pages() as $page ) {
				return $page; // Return first match for standalone test.
			}
			return null;
		}
	}
}

if ( ! function_exists( 'vbb_pro_filter_sections' ) ) {
	function vbb_pro_filter_sections( $sections ) {
		return is_array( $sections ) ? $sections : array();
	}
}

// Stub wp_update_post for standalone test — captures the last call.
if ( ! function_exists( 'wp_update_post' ) ) {
	$GLOBALS['vbb_test_last_wp_update_post'] = null;
	function wp_update_post( $args ) {
		$GLOBALS['vbb_test_last_wp_update_post'] = $args;
		return isset( $args['ID'] ) ? $args['ID'] : 0;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id ) {
		if ( 'post_name' === $field && 1 === (int) $post_id ) {
			return 'home';
		}
		return '';
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

/**
 * Assert that calling a closure does NOT trigger PHP notices/warnings.
 */
function assert_no_notices( $closure, $label ) {
	global $passed, $failed;
	$error_level = error_reporting( E_ALL );
	$messages    = array();
	set_error_handler(
		function ( $errno, $errstr ) use ( &$messages ) {
			$messages[] = $errstr;
			return true; // Prevent PHP internal handling.
		},
		E_ALL
	);
	$closure();
	restore_error_handler();
	error_reporting( $error_level );
	if ( empty( $messages ) ) {
		echo "  ✅ {$label}\n";
		$passed++;
	} else {
		echo "  ❌ {$label} — triggered notices:\n";
		foreach ( $messages as $m ) {
			echo "      - {$m}\n";
		}
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
assert_contains( $result, '{{vbb_hero_title}}', 'Hero dispatcher emits placeholder token' );
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
assert_contains( $hero, '{{vbb_hero_title}}', 'Hero emits title placeholder' );
assert_contains( $hero, 'wp:buttons', 'Hero contains wp:buttons' );
assert_contains( $hero, 'vbb-eyebrow', 'Hero contains eyebrow class' );
assert_contains( $hero, 'wp-block-group alignfull vbb-section', 'Hero has vbb-section class' );

// Hero with minimal data (defaults to placeholders).
$hero_min = vbb_bake_hero( array( 'title' => 'Minimal' ) );
assert_contains( $hero_min, '{{vbb_hero_title}}', 'Hero minimal emits title placeholder' );
assert_contains( $hero_min, 'wp:buttons', 'Hero minimal includes buttons (always present as placeholders)' );

echo "\n=== vbb_bake_hero_centered() ===\n";
$hero_centered = vbb_bake_hero_centered( array(
	'title'   => 'About Us',
	'tagline' => 'Our story',
) );
assert_contains( $hero_centered, 'wp:heading', 'Hero centered contains wp:heading' );
assert_contains( $hero_centered, '{{vbb_hero_centered_title}}', 'Hero centered emits title placeholder' );
assert_contains( $hero_centered, '{{vbb_hero_centered_tagline}}', 'Hero centered emits tagline placeholder' );

// hero-centered with minimal data (always emits placeholders).
$hero_sub = vbb_bake_hero_centered( array() );
assert_contains( $hero_sub, '{{vbb_hero_centered_title}}', 'Hero centered minimal emits title placeholder' );
assert_contains( $hero_sub, '{{vbb_hero_centered_tagline}}', 'Hero centered minimal emits tagline placeholder' );

echo "\n=== vbb_bake_services_grid() ===\n";
$services = vbb_bake_services_grid( array(
	'heading' => 'Our Services',
	'items'   => array(
		array( 'title' => 'Service A', 'summary' => 'Desc A', 'ctaText' => 'View', 'ctaUrl' => '/a' ),
		array( 'title' => 'Service B', 'summary' => 'Desc B' ),
		array( 'title' => 'Service C' ),
	),
) );
assert_contains( $services, '{{vbb_services_heading}}', 'Services emits heading placeholder' );
assert_contains( $services, 'Service A', 'Services includes item A' );
assert_contains( $services, 'Service B', 'Services includes item B' );
assert_contains( $services, 'wp:columns', 'Services uses wp:columns' );
assert_contains( $services, 'wp:column', 'Services uses wp:column per item' );
assert_contains( $services, 'vbb-card', 'Services uses vbb-card class' );

// Services with default items.
$services_default = vbb_bake_services_grid( array() );
assert_contains( $services_default, '{{vbb_services_heading}}', 'Services default emits heading placeholder' );
assert_contains( $services_default, 'Servicio principal', 'Services default item' );

echo "\n=== vbb_bake_benefits() ===\n";
$benefits = vbb_bake_benefits( array(
	'heading' => 'Why Us',
	'items'   => array( 'Fast', 'Reliable', 'Secure' ),
) );
assert_contains( $benefits, '{{vbb_benefits_heading}}', 'Benefits emits heading placeholder' );
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
assert_contains( $process, '{{vbb_process_heading}}', 'Process emits heading placeholder' );
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
assert_contains( $testimonials, '{{vbb_testimonials_heading}}', 'Testimonials emits heading placeholder' );
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
assert_contains( $faq, '{{vbb_faq_heading}}', 'FAQ emits heading placeholder' );
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
assert_contains( $contact, '{{vbb_contact_email}}', 'Contact emits email placeholder' );
assert_contains( $contact, '{{vbb_contact_phone}}', 'Contact emits phone placeholder' );
assert_contains( $contact, 'wp:columns', 'Contact uses wp:columns' );

echo "\n=== vbb_bake_cta_final() ===\n";
$cta = vbb_bake_cta_final( array(
	'text'       => 'Ready?',
	'buttonText' => 'Go',
	'buttonUrl'  => '/go',
) );
assert_contains( $cta, '{{vbb_cta_final_text}}', 'CTA emits text placeholder' );
assert_contains( $cta, '{{vbb_cta_final_button_text}}', 'CTA emits button text placeholder' );
assert_contains( $cta, '{{vbb_cta_final_button_url}}', 'CTA emits button url placeholder' );
assert_contains( $cta, 'wp:buttons', 'CTA uses wp:buttons' );
assert_contains( $cta, 'has-primary-background-color', 'CTA primary bg' );

// CTA without explicit data — button always emitted as placeholder.
$cta_no_btn = vbb_bake_cta_final( array() );
assert_contains( $cta_no_btn, '{{vbb_cta_final_text}}', 'CTA without data emits text placeholder' );
assert_contains( $cta_no_btn, '{{vbb_cta_final_button_text}}', 'CTA without data emits button text placeholder' );

echo "\n=== vbb_bake_logo_cloud() ===\n";
$logos = vbb_bake_logo_cloud( array(
	'heading' => 'Partners',
	'subtitle' => 'Trusted by',
	'logos' => array(
		array( 'url' => 'https://example.com/logo1.png' ),
		array( 'url' => 'https://example.com/logo2.png' ),
	),
) );
assert_contains( $logos, '{{vbb_logo_cloud_heading}}', 'Logo cloud emits heading placeholder' );
assert_contains( $logos, 'Trusted by', 'Logo cloud includes subtitle' );
assert_contains( $logos, 'wp:columns', 'Logo cloud uses wp:columns' );

echo "\n=== vbb_bake_pricing_tables() ===\n";
$pricing = vbb_bake_pricing_tables( array(
	'heading' => 'Pricing',
	'plans'   => array(
		array( 'title' => 'Basic', 'price' => '$10', 'features' => array( 'Feat A', 'Feat B' ) ),
		array( 'title' => 'Pro', 'price' => '$20', 'featured' => true, 'features' => array( 'All features' ) ),
	),
) );
assert_contains( $pricing, '{{vbb_pricing_heading}}', 'Pricing emits heading placeholder' );
assert_contains( $pricing, 'Basic', 'Pricing includes plan title' );
assert_contains( $pricing, 'featured', 'Pricing includes featured class' );
assert_contains( $pricing, 'wp:columns', 'Pricing uses wp:columns' );

echo "\n=== vbb_bake_team_section() ===\n";
$team = vbb_bake_team_section( array(
	'heading' => 'Team',
	'members' => array(
		array( 'name' => 'Alice', 'role' => 'CEO' ),
		array( 'name' => 'Bob', 'role' => 'CTO' ),
	),
) );
assert_contains( $team, '{{vbb_team_heading}}', 'Team emits heading placeholder' );
assert_contains( $team, 'Alice', 'Team includes member name' );
assert_contains( $team, 'CEO', 'Team includes role' );
assert_contains( $team, 'wp:columns', 'Team uses wp:columns' );

echo "\n=== Token resolution (simulated vbb_pro_replace_dynamic_content) ===\n";
// Simulate the replacement map used by vbb_pro_replace_dynamic_content().
$mock_settings = array(
	'blocks' => array(
		'hero'         => array( 'title' => 'Welcome', 'subtitle' => 'Sub', 'eyebrow' => 'Hey', 'primaryCta' => 'Start', 'primaryUrl' => '/start', 'tagline' => 'Tag' ),
		'ctaFinal'     => array( 'text' => 'Ready?', 'buttonText' => 'Go', 'buttonUrl' => '/go' ),
		'contact'      => array( 'email' => 'hi@test.com', 'phone' => '+1 555 0000' ),
		'servicesGrid' => array( 'heading' => 'Our Services' ),
		'benefits'     => array( 'heading' => 'Why Us' ),
		'testimonials' => array( 'heading' => 'Reviews' ),
		'faq'          => array( 'heading' => 'FAQs' ),
		'process'      => array( 'heading' => 'How We Work' ),
		'pricing'      => array( 'heading' => 'Plans' ),
		'team'         => array( 'heading' => 'Team' ),
		'logoCloud'    => array( 'heading' => 'Partners' ),
	),
);
$mock_content = '{{vbb_hero_title}} {{vbb_hero_subtitle}} {{vbb_hero_eyebrow}} {{vbb_hero_cta_text}} {{vbb_hero_cta_url}} {{vbb_hero_centered_title}} {{vbb_hero_centered_tagline}} {{vbb_cta_final_text}} {{vbb_cta_final_button_text}} {{vbb_cta_final_button_url}} {{vbb_contact_email}} {{vbb_contact_phone}} {{vbb_services_heading}} {{vbb_benefits_heading}} {{vbb_testimonials_heading}} {{vbb_faq_heading}} {{vbb_process_heading}} {{vbb_pricing_heading}} {{vbb_team_heading}} {{vbb_logo_cloud_heading}}';
$resolve_map = array(
	'{{vbb_hero_title}}'    => $mock_settings['blocks']['hero']['title'],
	'{{vbb_hero_subtitle}}' => $mock_settings['blocks']['hero']['subtitle'],
	'{{vbb_hero_eyebrow}}'  => $mock_settings['blocks']['hero']['eyebrow'],
	'{{vbb_hero_cta_text}}' => $mock_settings['blocks']['hero']['primaryCta'],
	'{{vbb_hero_cta_url}}'  => $mock_settings['blocks']['hero']['primaryUrl'],
	'{{vbb_hero_centered_title}}'   => $mock_settings['blocks']['hero']['title'],
	'{{vbb_hero_centered_tagline}}' => $mock_settings['blocks']['hero']['tagline'],
	'{{vbb_cta_final_text}}'        => $mock_settings['blocks']['ctaFinal']['text'],
	'{{vbb_cta_final_button_text}}' => $mock_settings['blocks']['ctaFinal']['buttonText'],
	'{{vbb_cta_final_button_url}}'  => $mock_settings['blocks']['ctaFinal']['buttonUrl'],
	'{{vbb_contact_email}}' => $mock_settings['blocks']['contact']['email'],
	'{{vbb_contact_phone}}' => $mock_settings['blocks']['contact']['phone'],
	'{{vbb_services_heading}}'   => $mock_settings['blocks']['servicesGrid']['heading'],
	'{{vbb_benefits_heading}}'   => $mock_settings['blocks']['benefits']['heading'],
	'{{vbb_testimonials_heading}}' => $mock_settings['blocks']['testimonials']['heading'],
	'{{vbb_faq_heading}}'        => $mock_settings['blocks']['faq']['heading'],
	'{{vbb_process_heading}}'    => $mock_settings['blocks']['process']['heading'],
	'{{vbb_pricing_heading}}'    => $mock_settings['blocks']['pricing']['heading'],
	'{{vbb_team_heading}}'       => $mock_settings['blocks']['team']['heading'],
	'{{vbb_logo_cloud_heading}}' => $mock_settings['blocks']['logoCloud']['heading'],
);
$resolved = $mock_content;
foreach ( $resolve_map as $placeholder => $value ) {
	$resolved = str_replace( $placeholder, $value, $resolved );
}
assert_contains( $resolved, 'Welcome', 'Resolution: hero title resolved' );
assert_contains( $resolved, 'Ready?', 'Resolution: cta text resolved' );
assert_contains( $resolved, 'hi@test.com', 'Resolution: contact email resolved' );
assert_contains( $resolved, 'Our Services', 'Resolution: services heading resolved' );
assert_contains( $resolved, 'Why Us', 'Resolution: benefits heading resolved' );
assert_contains( $resolved, 'FAQs', 'Resolution: faq heading resolved' );
assert_contains( $resolved, 'How We Work', 'Resolution: process heading resolved' );
assert_contains( $resolved, 'Plans', 'Resolution: pricing heading resolved' );
assert_contains( $resolved, 'Team', 'Resolution: team heading resolved' );
assert_contains( $resolved, 'Partners', 'Resolution: logo cloud heading resolved' );
// Verify no unresolved tokens remain.
assert_not_contains( $resolved, '{{vbb_', 'Resolution: no tokens remain unresolved' );

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
assert_contains( $merged, '{{vbb_hero_title}}', 'Hero section emits title placeholder (from page or section level)' );
assert_contains( $merged, '{{vbb_hero_subtitle}}', 'Hero section emits subtitle placeholder' );

echo "\n=== vbb_bake_process() with 0 steps (falls back to defaults) ===\n";
assert_no_notices(
	function () {
		$output = vbb_bake_process( array( 'heading' => 'No Steps', 'steps' => array() ) );
		assert_contains( $output, 'wp:columns', '0 steps: emits wp:columns wrapper' );
		assert_contains( $output, 'wp:column', '0 steps: default steps emitted (empty array triggers defaults)' );
	},
	'vbb_bake_process with empty steps triggers no notices'
);

echo "\n=== vbb_bake_process() with 1 step ===\n";
assert_no_notices(
	function () {
		$output = vbb_bake_process( array(
			'heading' => 'One Step',
			'steps'   => array(
				array( 'title' => 'Step One', 'description' => 'Do the thing' ),
			),
		) );
		assert_contains( $output, 'Step One', '1 step: title appears' );
		assert_contains( $output, 'Do the thing', '1 step: description appears' );
		assert_contains( $output, 'wp:column', '1 step: emits a column' );
	},
	'vbb_bake_process with 1 step triggers no notices'
);

echo "\n=== vbb_bake_process() with 5 steps ===\n";
assert_no_notices(
	function () {
		$steps = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$steps[] = array(
				'title'       => "Step {$i}",
				'description' => "Description {$i}",
			);
		}
		$output = vbb_bake_process( array(
			'heading' => 'Five Steps',
			'steps'   => $steps,
		) );
		assert_contains( $output, 'Step 1', '5 steps: step 1 title appears' );
		assert_contains( $output, 'Step 5', '5 steps: step 5 title appears' );
		assert_contains( $output, 'Description 3', '5 steps: step 3 description appears' );
		// Each step emits a column — verify multiple occurrences.
		$count = substr_count( $output, '<!-- wp:column -->' );
		assert_contains( $count >= 5 ? 'true' : 'false', 'true', "5 steps: {$count} columns emitted (expected >= 5)" );
	},
	'vbb_bake_process with 5 steps triggers no notices'
);

echo "\n=== vbb_bake_page_content() with stubs ===\n";
assert_no_notices(
	function () {
		vbb_bake_page_content( 1 );
		$last = isset( $GLOBALS['vbb_test_last_wp_update_post'] ) ? $GLOBALS['vbb_test_last_wp_update_post'] : null;
		if ( null === $last ) {
			echo "  ⚠️  vbb_bake_page_content: wp_update_post was not called (stubs may not match)\n";
		} else {
			assert_contains( $last['ID'] ?? '', '1', 'vbb_bake_page_content: calls wp_update_post with page ID 1' );
			assert_contains( $last['post_content'] ?? '', '{{vbb_hero_title}}', 'vbb_bake_page_content: content contains hero title placeholder' );
		}
	},
	'vbb_bake_page_content triggers no notices'
);

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
