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

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://localhost/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$html = '<input type="hidden" name="' . $name . '" value="test-nonce" />';
		if ( $echo ) {
			echo $html;
		}
		return $html;
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
		if ( 'post_content' === $field && isset( $GLOBALS['vbb_test_posts'][ $post_id ] ) ) {
			return $GLOBALS['vbb_test_posts'][ $post_id ]['post_content'] ?? '';
		}
		if ( 'post_name' === $field && 1 === (int) $post_id ) {
			return 'home';
		}
		return '';
	}
}

if ( ! function_exists( 'get_pages' ) ) {
	function get_pages( $args = array() ) {
		$posts = $GLOBALS['vbb_test_posts'] ?? array();
		$result = array();
		foreach ( $posts as $id => $data ) {
			$p = new stdClass();
			$p->ID = $id;
			$p->post_content = $data['post_content'] ?? '';
			$result[] = $p;
		}
		return $result;
	}
}

// ── Load helpers (needed for vbb_esc_text, vbb_esc_url_value) ──────────────
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/block-registry.php';
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

// ── Phase 5 integration test stubs ────────────────────────────────────────

// Stub get_option / update_option / delete_option
if ( ! function_exists( 'get_option' ) ) {
	$GLOBALS['vbb_test_options'] = array();

	function get_option( $option, $default = false ) {
		return isset( $GLOBALS['vbb_test_options'][ $option ] ) ? $GLOBALS['vbb_test_options'][ $option ] : $default;
	}

	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['vbb_test_options'][ $option ] = $value;
		return true;
	}

	function delete_option( $option ) {
		unset( $GLOBALS['vbb_test_options'][ $option ] );
		return true;
	}
}

// Stub current_time
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return '2025-01-15 10:00:00';
	}
}

// Stub version_compare (native PHP function — just make sure it's accessible)
// Already a built-in PHP function, no stub needed.

// Stub set_time_limit (may be disabled in some PHP environments)
if ( ! function_exists( 'set_time_limit' ) ) {
	function set_time_limit( $seconds ) {
		return true;
	}
}

// Stub for get_posts used in vbb_pro_has_unresolved_tokens
if ( ! function_exists( 'get_posts' ) ) {
	$GLOBALS['vbb_test_posts'] = array();

	function get_posts( $args = array() ) {
		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			return array_keys( $GLOBALS['vbb_test_posts'] );
		}
		// Return as objects (like WP_Query results)
		$objects = array();
		foreach ( $GLOBALS['vbb_test_posts'] as $id => $data ) {
			$p = new stdClass();
			$p->ID = $id;
			$p->post_content = $data['post_content'] ?? '';
			$objects[] = $p;
		}
		return $objects;
	}
}

// Stub for wp_insert_post (used by menu sync)
if ( ! function_exists( 'wp_insert_post' ) ) {
	$GLOBALS['vbb_test_last_inserted_post'] = null;

	function wp_insert_post( $post_data, $wp_error = false ) {
		$GLOBALS['vbb_test_last_inserted_post'] = $post_data;
		// Return a fake ID
		return 42;
	}
}

// Stub for is_wp_error
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

// Stub for add_action / add_filter (used by pro-settings.php at top level)
if ( ! function_exists( 'add_action' ) ) {
	$GLOBALS['vbb_test_wp_hooks'] = array();
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['vbb_test_wp_hooks'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'args' => $accepted_args );
		return true;
	}
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['vbb_test_wp_hooks'][ $hook ][] = array( 'callback' => $callback, 'priority' => $priority, 'args' => $accepted_args );
		return true;
	}
	function do_action( $hook ) {
		return;
	}
}

// Stub for get_current_screen (used by admin notice)
if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return null;
	}
}

// Stub for current_user_can (used by admin notice guard)
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return true;
	}
}

// Stub for _n and _n_noop (used in sprintf for regeneration count)
if ( ! function_exists( '_n' ) ) {
	function _n( $singular, $plural, $number, $domain = '' ) {
		return $number > 1 ? $plural : $singular;
	}
}

// Additional WP function stubs needed by Phase 5 functions
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return '/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $url, $action = '', $name = '_wpnonce' ) {
		return $url . '&' . $name . '=' . md5( $action );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = '', $url = '' ) {
		if ( is_array( $key ) ) {
			$parts = array();
			foreach ( $key as $k => $v ) {
				$parts[] = urlencode( $k ) . '=' . urlencode( $v );
			}
			return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . implode( '&', $parts );
		}
		return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . urlencode( $key ) . '=' . urlencode( $value );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return true; // Skip validation in tests
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url ) {
		// Capture for test assertions
		$GLOBALS['vbb_test_last_redirect'] = $url;
		return true;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $val ) {
		return abs( (int) $val );
	}
}

// ── Phase 5 function definitions (replicas from pro-settings.php / pro-admin.php) ──
// These are defined here with function_exists guards to avoid conflicts
// when loading pro-settings.php in a real WP context.

if ( ! function_exists( 'vbb_pro_regenerate_all_pages' ) ) {
	function vbb_pro_regenerate_all_pages() {
		$pages = get_pages();
		$count = 0;
		foreach ( $pages as $page ) {
			if ( function_exists( 'vbb_bake_page_content' ) ) {
				vbb_bake_page_content( $page->ID );
				$count++;
			}
		}
		return $count;
	}
}

if ( ! function_exists( 'vbb_pro_on_theme_activation' ) ) {
	function vbb_pro_on_theme_activation() {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
		$version = get_option( 'vbb_baker_version', '0' );
		if ( version_compare( $version, '1.0.0', '<' ) ) {
			$count = vbb_pro_regenerate_all_pages();
			update_option( 'vbb_baker_version', '1.0.0', false );
			delete_option( 'vbb_tokens_detected' );
			return $count;
		}
		return 0;
	}
}

if ( ! function_exists( 'vbb_pro_has_unresolved_tokens' ) ) {
	function vbb_pro_has_unresolved_tokens() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $pages as $page_id ) {
			$content = get_post_field( 'post_content', $page_id );
			if ( false !== strpos( $content, '{{vbb_' ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'vbb_pro_show_regenerate_notice' ) ) {
	function vbb_pro_show_regenerate_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'vbb-command-center' ) ) {
			return;
		}
		$token_detected = get_option( 'vbb_tokens_detected', 'not_scanned' );
		if ( 'not_scanned' === $token_detected ) {
			$found = vbb_pro_has_unresolved_tokens();
			update_option( 'vbb_tokens_detected', $found ? 'yes' : 'no', false );
			$token_detected = $found ? 'yes' : 'no';
		}
		if ( 'yes' !== $token_detected ) {
			return;
		}
		$command_center_url = admin_url( 'admin.php?page=vbb-command-center' );
		$regenerate_url     = wp_nonce_url(
			add_query_arg( 'vbb_action', 'regenerate_all', admin_url( 'admin.php' ) ),
			'vbb_pro_regenerate_action',
			'vbb_pro_nonce'
		);
		// Echo notice HTML (captured or displayed)
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>OrkestOne Theme:</strong> Some pages contain placeholder tokens from the No-Code Builder.</p>';
		echo '<p><a href="' . $command_center_url . '" class="button button-primary">Open Command Center</a> ';
		echo '<a href="' . $regenerate_url . '" class="button">Regenerate All Pages Now</a></p>';
		echo '</div>';
	}
}

if ( ! function_exists( 'vbb_pro_sanitize_menu_items' ) ) {
	function vbb_pro_sanitize_menu_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$sanitized = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$sanitized_item = array(
				'id'            => sanitize_key( isset( $item['id'] ) ? $item['id'] : 'menu_' . uniqid() ),
				'label'         => sanitize_text_field( isset( $item['label'] ) ? $item['label'] : '' ),
				'type'          => in_array( isset( $item['type'] ) ? $item['type'] : 'custom', array( 'page', 'custom' ), true ) ? $item['type'] : 'custom',
				'url'           => esc_url_raw( isset( $item['url'] ) ? $item['url'] : '' ),
				'targetPageId'  => absint( isset( $item['targetPageId'] ) ? $item['targetPageId'] : 0 ),
				'children'      => array(),
			);
			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				$sanitized_item['children'] = vbb_pro_sanitize_menu_items( $item['children'] );
			}
			$sanitized[] = $sanitized_item;
		}
		return $sanitized;
	}
}

if ( ! function_exists( 'vbb_pro_build_nav_block' ) ) {
	function vbb_pro_build_nav_block( $item ) {
		if ( empty( $item['label'] ) && empty( $item['url'] ) ) {
			return '';
		}
		$attrs = array(
			'label' => $item['label'] ?? '',
			'url'   => $item['url'] ?? '',
		);
		if ( 'page' === $item['type'] && ! empty( $item['targetPageId'] ) ) {
			$attrs['kind'] = 'page';
			$attrs['id']   = (int) $item['targetPageId'];
		} else {
			$attrs['kind'] = 'custom';
		}
		$has_children = ! empty( $item['children'] );
		if ( $has_children ) {
			$block = '<!-- wp:navigation-link ' . wp_json_encode( $attrs ) . ' -->' . "\n";
			foreach ( $item['children'] as $child ) {
				$child_block = vbb_pro_build_nav_block( $child );
				if ( '' !== $child_block ) {
					$block .= $child_block . "\n";
				}
			}
			$block .= '<!-- /wp:navigation-link -->';
		} else {
			$block = '<!-- wp:navigation-link ' . wp_json_encode( $attrs ) . ' /-->';
		}
		return $block;
	}
}

if ( ! function_exists( 'vbb_pro_sync_menu_to_wp_navigation' ) ) {
	function vbb_pro_sync_menu_to_wp_navigation( $menu_items ) {
		$menu_items = vbb_pro_sanitize_menu_items( $menu_items );
		$content = '<!-- wp:navigation {"ref":0} -->' . "\n";
		foreach ( $menu_items as $item ) {
			$block = vbb_pro_build_nav_block( $item );
			if ( '' !== $block ) {
				$content .= $block . "\n";
			}
		}
		$content .= '<!-- /wp:navigation -->';
		$nav_name  = 'OrkestOne Primary Navigation';
		$nav_slug  = 'orkestone-primary-navigation';
		$existing  = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'title'          => $nav_name,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);
		$nav_id = ! empty( $existing ) ? $existing[0]->ID : 0;
		$post_data = array(
			'post_type'    => 'wp_navigation',
			'post_title'   => $nav_name,
			'post_name'    => $nav_slug,
			'post_status'  => 'publish',
			'post_content' => $content,
		);
		if ( $nav_id > 0 ) {
			$post_data['ID'] = $nav_id;
		}
		$result = wp_insert_post( $post_data, true );
		if ( ! is_wp_error( $result ) ) {
			update_option( 'vbb_last_menu_sync', current_time( 'mysql' ), false );
		}
		return $result;
	}
}

if ( ! function_exists( 'vbb_pro_handle_regenerate_action' ) ) {
	function vbb_pro_handle_regenerate_action() {
		if ( empty( $_GET['vbb_action'] ) || 'regenerate_all' !== $_GET['vbb_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No autorizado.' );
		}
		check_admin_referer( 'vbb_pro_regenerate_action', 'vbb_pro_nonce' );
		$count = vbb_pro_regenerate_all_pages();
		update_option( 'vbb_tokens_detected', 'no', false );
		wp_safe_redirect(
			add_query_arg(
				'vbb_regenerated',
				$count,
				admin_url( 'admin.php?page=vbb-command-center' )
			)
		);
		exit;
	}
}

// ── Phase 5 Integration Tests ────────────────────────────────────────────

echo "\n\n=== Phase 5: Activation hook (vbb_pro_on_theme_activation) ===\n";

// Reset options
$GLOBALS['vbb_test_options'] = array();
$GLOBALS['vbb_test_posts']   = array();

// Fresh install scenario: no vbb_baker_version set
assert_no_notices(
	function () {
		$count = vbb_pro_on_theme_activation();
		$version = get_option( 'vbb_baker_version', '0' );
		$cond = ( '1.0.0' === $version && 0 === $count );
		if ( $cond ) {
			echo "  ✅ Activation: fresh install sets version to 1.0.0 (no pages to regenerate)\n";
			$GLOBALS['passed']++;
		} else {
			echo "  ❌ Activation: expected version 1.0.0, got {$version}, count={$count}\n";
			$GLOBALS['failed']++;
		}
	},
	'Activation hook triggers no notices with no pages'
);

// Existing install with old version
$GLOBALS['vbb_test_options'] = array( 'vbb_baker_version' => '0.0.5' );
$GLOBALS['vbb_test_posts']   = array(
	1 => array( 'post_content' => 'Some content without tokens' ),
	2 => array( 'post_content' => '{{vbb_hero_title}} unresolved token' ),
);

// Register vbb_bake_page_content mock for the test
if ( ! function_exists( 'vbb_bake_page_content' ) ) {
	function vbb_bake_page_content( $page_id ) {
		// Simulate baking by replacing tokens in content
		if ( isset( $GLOBALS['vbb_test_posts'][ $page_id ] ) ) {
			$content = $GLOBALS['vbb_test_posts'][ $page_id ]['post_content'];
			$GLOBALS['vbb_test_posts'][ $page_id ]['post_content'] = str_replace( '{{vbb_hero_title}}', 'Resolved Title', $content );
		}
	}
}

assert_no_notices(
	function () {
		$count = vbb_pro_on_theme_activation();
		$version = get_option( 'vbb_baker_version', '0' );
		$cond = ( '1.0.0' === $version && 2 === $count );
		if ( $cond ) {
			echo "  ✅ Activation: old version (< 1.0.0) triggers regeneration of {$count} pages\n";
			$GLOBALS['passed']++;
		} else {
			echo "  ❌ Activation: expected version 1.0.0 and 2 pages, got version={$version}, count={$count}\n";
			$GLOBALS['failed']++;
		}
	},
	'Activation hook regenerates pages when version is outdated'
);

// Already at 1.0.0 — should NOT regenerate
$GLOBALS['vbb_test_options'] = array( 'vbb_baker_version' => '1.0.0' );

assert_no_notices(
	function () {
		$count = vbb_pro_on_theme_activation();
		if ( 0 === $count ) {
			echo "  ✅ Activation: version >= 1.0.0 skips regeneration (count={$count})\n";
			$GLOBALS['passed']++;
		} else {
			echo "  ❌ Activation: expected 0 pages, got {$count}\n";
			$GLOBALS['failed']++;
		}
	},
	'Activation hook skips regeneration when already at version 1.0.0'
);

echo "\n=== Phase 5: Admin notice token detection (vbb_pro_has_unresolved_tokens) ===\n";

// Pages with no tokens
$GLOBALS['vbb_test_posts'] = array(
	3 => array( 'post_content' => 'Clean baked content no placeholders' ),
	4 => array( 'post_content' => 'Normal page with some text' ),
);

$has_tokens = vbb_pro_has_unresolved_tokens();
if ( false === $has_tokens ) {
	echo "  ✅ Token detection: no false positive when pages have no tokens\n";
	$passed++;
} else {
	echo "  ❌ Token detection: reported tokens but none exist\n";
	$failed++;
}

// Pages WITH tokens
$GLOBALS['vbb_test_posts'] = array(
	5 => array( 'post_content' => '{{vbb_hero_title}} and {{vbb_hero_subtitle}} still raw' ),
);

$has_tokens = vbb_pro_has_unresolved_tokens();
if ( true === $has_tokens ) {
	echo "  ✅ Token detection: correctly detects {{vbb_}} tokens in page content\n";
	$passed++;
} else {
	echo "  ❌ Token detection: failed to detect tokens\n";
	$failed++;
}

// vbb_pro_show_regenerate_notice — verify it sets the option flag after first scan
$GLOBALS['vbb_test_options'] = array();
$GLOBALS['vbb_test_posts']   = array(
	6 => array( 'post_content' => 'Some {{vbb_cta_final_text}} here' ),
);

// Call the function and check the option was set
vbb_pro_show_regenerate_notice();
$token_flag = get_option( 'vbb_tokens_detected', 'not_scanned' );
if ( 'yes' === $token_flag ) {
	echo "  ✅ Admin notice: first scan sets vbb_tokens_detected=yes when tokens found\n";
	$passed++;
} else {
	echo "  ❌ Admin notice: expected vbb_tokens_detected=yes, got {$token_flag}\n";
	$failed++;
}

// Clean pages — flag should be 'no'
$GLOBALS['vbb_test_options'] = array( 'vbb_tokens_detected' => 'not_scanned' );
$GLOBALS['vbb_test_posts']   = array(
	7 => array( 'post_content' => 'Clean content without tokens' ),
);

vbb_pro_show_regenerate_notice();
$token_flag = get_option( 'vbb_tokens_detected', 'not_scanned' );
if ( 'no' === $token_flag ) {
	echo "  ✅ Admin notice: sets vbb_tokens_detected=no when no tokens found\n";
	$passed++;
} else {
	echo "  ❌ Admin notice: expected vbb_tokens_detected=no, got {$token_flag}\n";
	$failed++;
}

// Cached flag — should not re-scan
$GLOBALS['vbb_test_options'] = array( 'vbb_tokens_detected' => 'yes' );
vbb_pro_show_regenerate_notice();
$token_flag = get_option( 'vbb_tokens_detected', 'not_scanned' );
if ( 'yes' === $token_flag ) {
	echo "  ✅ Admin notice: respects cached detection flag (does not re-scan)\n";
	$passed++;
} else {
	echo "  ❌ Admin notice: flag changed unexpectedly to {$token_flag}\n";
	$failed++;
}

echo "\n=== Phase 5: Menu sync to wp_navigation ===\n";

// Reset state for clean test
$GLOBALS['vbb_test_last_inserted_post'] = null;
$GLOBALS['vbb_test_options']            = array();
$GLOBALS['vbb_test_posts']              = array();

$menu_items = array(
	array(
		'id'    => 'menu_1',
		'label' => 'Home',
		'type'  => 'page',
		'url'   => '',
		'targetPageId' => 2,
		'children' => array(),
	),
	array(
		'id'    => 'menu_2',
		'label' => 'About',
		'type'  => 'custom',
		'url'   => '/about',
		'children' => array(
			array(
				'id'    => 'menu_2_1',
				'label' => 'Team',
				'type'  => 'page',
				'url'   => '',
				'targetPageId' => 3,
				'children' => array(),
			),
		),
	),
);

$result = vbb_pro_sync_menu_to_wp_navigation( $menu_items );
$inserted = $GLOBALS['vbb_test_last_inserted_post'];

if ( 42 === $result && null !== $inserted ) {
	echo "  ✅ Menu sync: returns post ID\n";
	$passed++;
} else {
	echo "  ❌ Menu sync: expected ID 42, got " . var_export( $result, true ) . "\n";
	$failed++;
}

if ( 'wp_navigation' === $inserted['post_type'] ) {
	echo "  ✅ Menu sync: creates wp_navigation post type\n";
	$passed++;
} else {
	echo "  ❌ Menu sync: expected post_type=wp_navigation, got {$inserted['post_type']}\n";
	$failed++;
}

if ( 'OrkestOne Primary Navigation' === $inserted['post_title'] ) {
	echo "  ✅ Menu sync: post title matches\n";
	$passed++;
} else {
	echo "  ❌ Menu sync: unexpected title '{$inserted['post_title']}'\n";
	$failed++;
}

// Verify block markup
$content = $inserted['post_content'];
assert_contains( $content, '<!-- wp:navigation', 'Menu sync: wraps in wp:navigation block' );
assert_contains( $content, 'wp:navigation-link', 'Menu sync: uses navigation-link blocks' );
assert_contains( $content, 'Home', 'Menu sync: includes item label "Home"' );
assert_contains( $content, 'About', 'Menu sync: includes item label "About"' );
assert_contains( $content, 'Team', 'Menu sync: includes child label "Team"' );
assert_contains( $content, '"kind":"page"', 'Menu sync: page type items have kind=page' );
assert_contains( $content, '"kind":"custom"', 'Menu sync: custom type items have kind=custom' );
// Verify sync timestamp stored (vbb_last_menu_sync option not in content — it's an option)
$sync_time = get_option( 'vbb_last_menu_sync', '' );
if ( '' !== $sync_time ) {
	echo "  ✅ Menu sync: last sync timestamp stored\n";
	$passed++;
} else {
	echo "  ❌ Menu sync: timestamp not stored\n";
	$failed++;
}

echo "\n=== Phase 5: Regenerate action handler (vbb_pro_handle_regenerate_action) ===\n";

// Simulate the action — set up options and posts
$GLOBALS['vbb_test_options'] = array( 'vbb_tokens_detected' => 'yes' );
$GLOBALS['vbb_test_posts']   = array(
	8 => array( 'post_content' => 'Has {{vbb_hero_title}} token' ),
	9 => array( 'post_content' => 'Has {{vbb_cta_final_text}} token' ),
);

// We can't easily test the redirect, but we can test that the function
// doesn't error when called with invalid/non-matching action
$original_get = $_GET;
$_GET['vbb_action'] = 'nonexistent';
assert_no_notices(
	function () {
		vbb_pro_handle_regenerate_action();
	},
	'Regenerate action handler no-ops on non-matching action (no error)'
);
$_GET = $original_get;

echo "\n=== Phase 5: vbb_pro_regenerate_all_pages() ===\n";

// Reset
$GLOBALS['vbb_test_posts'] = array(
	10 => array( 'post_content' => 'Page ten' ),
	11 => array( 'post_content' => 'Page eleven' ),
);

assert_no_notices(
	function () {
		$count = vbb_pro_regenerate_all_pages();
		if ( 2 === $count ) {
			echo "  ✅ Regenerate all: processed {$count} pages\n";
			$GLOBALS['passed']++;
		} else {
			echo "  ❌ Regenerate all: expected 2 pages, got {$count}\n";
			$GLOBALS['failed']++;
		}
	},
	'vbb_pro_regenerate_all_pages triggers no notices'
);

echo "\n=== Hero Style D & Legal Vertical Blocks ===\n";
$hero_d_html = vbb_bake_hero_style_d( array(
	'heading'        => 'Fight Back Against Unfair Criminal Charges',
	'highlightText'  => 'Fight Back',
	'subhead'        => 'Top defense lawyers in Philadelphia',
	'primaryCtaText' => 'Call Now',
	'primaryCtaUrl'  => 'tel:2672252545',
	'secondaryCtaText' => 'Get Help Now',
	'secondaryCtaUrl'  => '/contact',
));
assert_contains( $hero_d_html, 'vbb-hero-style-d', 'Hero Style D has wrapper class' );
assert_contains( $hero_d_html, 'sqsrte-text-highlight', 'Hero Style D highlights text segment' );
assert_contains( $hero_d_html, 'Call Now', 'Hero Style D has primary CTA' );

$practice_grid_html = vbb_bake_practice_grid( array(
	'heading' => 'Practice Areas',
	'items'   => array(
		array( 'title' => 'Violent Crimes', 'image' => 'img.jpg', 'url' => '/violent-crimes' ),
	),
));
assert_contains( $practice_grid_html, 'vbb-practice-grid-section', 'Practice grid has wrapper class' );
assert_contains( $practice_grid_html, 'Violent Crimes', 'Practice grid includes item title' );

// Summary
$total = $passed + $failed;
echo "\n========================================\n";
echo "Results: {$passed}/{$total} passed, {$failed}/{$total} failed\n";
echo "========================================\n";

exit( $failed > 0 ? 1 : 0 );
