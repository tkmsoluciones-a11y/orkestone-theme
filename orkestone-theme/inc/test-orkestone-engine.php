<?php
/**
 * Standalone verification tests for the Orkestone Engine.
 *
 * Covers reset orchestrator, config management, and edge cases.
 * Runs independently from test-block-baker.php.
 *
 * Run: php inc/test-orkestone-engine.php
 *
 * @package VerticalBlockBase
 */

// -- Bootstrap --

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// -- WordPress function stubs --

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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]/', '', $key ) ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( trim( preg_replace( '/[^a-zA-Z0-9_-]/', '-', $title ), '-' ) );
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

if ( ! function_exists( 'absint' ) ) {
	function absint( $val ) {
		return abs( (int) $val );
	}
}

if ( ! function_exists( 'set_time_limit' ) ) {
	function set_time_limit( $seconds ) {
		return true;
	}
}

if ( ! function_exists( 'wp_trash_post' ) ) {
	$GLOBALS['vbb_test_trashed'] = array();
	$GLOBALS['vbb_test_wp_trash_post_failures'] = array();

	function wp_trash_post( $post_id ) {
		$GLOBALS['vbb_test_trashed'][] = (int) $post_id;
		if ( in_array( (int) $post_id, $GLOBALS['vbb_test_wp_trash_post_failures'], true ) ) {
			return false;
		}
		return true;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	$GLOBALS['vbb_test_deleted'] = array();

	function wp_delete_post( $post_id, $force = false ) {
		$GLOBALS['vbb_test_deleted'][] = (int) $post_id;
		return true;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	$GLOBALS['vbb_test_last_inserted_post'] = null;
	$GLOBALS['vbb_test_next_post_id'] = 42;

	function wp_insert_post( $post_data, $wp_error = false ) {
		$GLOBALS['vbb_test_last_inserted_post'] = $post_data;
		$id = $GLOBALS['vbb_test_next_post_id']++;
		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	$GLOBALS['vbb_test_last_wp_update_post'] = null;

	function wp_update_post( $args ) {
		$GLOBALS['vbb_test_last_wp_update_post'] = $args;
		return isset( $args['ID'] ) ? $args['ID'] : 0;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	$GLOBALS['vbb_test_posts'] = array();

	function get_posts( $args = array() ) {
		$result = array();
		foreach ( $GLOBALS['vbb_test_posts'] as $id => $data ) {
			$p = new stdClass();
			$p->ID = $id;
			$p->post_content = $data['post_content'] ?? '';
			$p->post_type = $data['post_type'] ?? 'page';
			$result[] = $p;
		}
		return $result;
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

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
		return null;
	}
}

if ( ! function_exists( 'get_page_by_title' ) ) {
	function get_page_by_title( $title, $output = OBJECT, $post_type = 'page' ) {
		return null;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id ) {
		return '/?p=' . (int) $post_id;
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( $post_type ) {
		return true;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	$GLOBALS['vbb_test_post_meta'] = array();

	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$GLOBALS['vbb_test_post_meta'][ (int) $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( isset( $GLOBALS['vbb_test_post_meta'][ (int) $post_id ][ $key ] ) ) {
			return $GLOBALS['vbb_test_post_meta'][ (int) $post_id ][ $key ];
		}
		return $single ? '' : array();
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

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return '2025-01-15 10:00:00';
	}
}

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

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $attachment_id ) {
		return 'https://cdn.example.com/attachment-' . (int) $attachment_id . '.jpg';
	}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
	function get_template_directory_uri() {
		return 'https://example.com/wp-content/themes/orkestone-theme';
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	function wp_slash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'download_url' ) ) {
	function download_url( $url, $timeout = 300 ) {
		return tempnam( sys_get_temp_dir(), 'vbb-dl-' );
	}
}

if ( ! function_exists( 'media_sideload_image' ) ) {
	$GLOBALS['vbb_test_media_sideload_image_results'] = array();
	$GLOBALS['vbb_test_media_sideload_image_index'] = 0;

	function media_sideload_image( $url, $post_id = 0, $title = '', $return = 'html' ) {
		$idx = $GLOBALS['vbb_test_media_sideload_image_index']++;
		if ( isset( $GLOBALS['vbb_test_media_sideload_image_results'][ $idx ] ) ) {
			return $GLOBALS['vbb_test_media_sideload_image_results'][ $idx ];
		}
		return new WP_Error( 'vbb_sideload_failed', 'Stub: media_sideload_image not available' );
	}
}

if ( ! function_exists( 'media_handle_sideload' ) ) {
	function media_handle_sideload( $file_array, $post_id = 0, $title = '' ) {
		return 99;
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		return true;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url ) {
		$GLOBALS['vbb_test_last_redirect'] = $url;
		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {
		$GLOBALS['vbb_test_died'] = $message;
		exit;
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $class );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return true;
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return null;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Test Site';
	}
}

// -- Class stubs --

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $errors = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ] = array( $message );
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return ! empty( $codes ) ? $codes[0] : '';
		}

		public function get_error_message() {
			$codes = array_keys( $this->errors );
			if ( empty( $codes ) ) {
				return '';
			}
			$messages = $this->errors[ $codes[0] ];
			return ! empty( $messages ) ? $messages[0] : '';
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();

		public function __construct( $args = array() ) {
			if ( ! isset( $GLOBALS['vbb_test_wp_query_calls'] ) ) {
				$GLOBALS['vbb_test_wp_query_calls'] = array();
			}
			$GLOBALS['vbb_test_wp_query_calls'][] = $args;

			$post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'post';

			if ( isset( $GLOBALS['vbb_test_wp_query_results'][ $post_type ] ) ) {
				$this->posts = $GLOBALS['vbb_test_wp_query_results'][ $post_type ];
			} else {
				$this->posts = array();
			}
		}
	}
}

// -- is_wp_error (after WP_Error class) --

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// -- get_theme_file_path stub --

if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $path = '' ) {
		if ( isset( $GLOBALS['vbb_test_theme_file_path_fail'] ) && $GLOBALS['vbb_test_theme_file_path_fail'] ) {
			return '/nonexistent/vbb-test/' . ltrim( $path, '/' );
		}
		$full = sys_get_temp_dir() . '/vbb-test-config/' . ltrim( $path, '/' );
		$pdir = dirname( $full );
		if ( ! is_dir( $pdir ) ) {
			mkdir( $pdir, 0777, true );
		}
		return $full;
	}
}

// -- Load source files --
// Order: helpers.php -> block-baker.php -> reset-orchestrator.php -> vertical-importer.php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/block-baker.php';
require_once __DIR__ . '/reset-orchestrator.php';
require_once __DIR__ . '/vertical-importer.php';

// -- VBB stubs (for functions NOT in loaded source files) --

if ( ! function_exists( 'vbb_get_active_vertical_key' ) ) {
	function vbb_get_active_vertical_key() {
		if ( isset( $GLOBALS['vbb_test_active_vertical_key'] ) ) {
			return $GLOBALS['vbb_test_active_vertical_key'];
		}
		return 'default';
	}
}

if ( ! function_exists( 'vbb_get_active_vertical_settings' ) ) {
	function vbb_get_active_vertical_settings() {
		return array(
			'active' => vbb_get_active_vertical_key(),
			'fallback' => 'default',
		);
	}
}

if ( ! function_exists( 'vbb_get_vertical_config' ) ) {
	function vbb_get_vertical_config() {
		if ( isset( $GLOBALS['vbb_test_vertical_config'] ) ) {
			return $GLOBALS['vbb_test_vertical_config'];
		}
		return array(
			'verticalKey' => 'test-fixture',
			'pages' => array(),
			'sections' => array(),
			'navigation' => array( 'primary' => array() ),
			'importOptions' => array( 'homepageKey' => 'home', 'setFrontPage' => false ),
		);
	}
}

if ( ! function_exists( 'vbb_get_vertical_pages' ) ) {
	function vbb_get_vertical_pages() {
		$config = vbb_get_vertical_config();
		return isset( $config['pages'] ) && is_array( $config['pages'] ) ? $config['pages'] : array();
	}
}

if ( ! function_exists( 'vbb_get_vertical_page' ) ) {
	function vbb_get_vertical_page( $key ) {
		foreach ( vbb_get_vertical_pages() as $page ) {
			if ( isset( $page['key'] ) && $page['key'] === $key ) {
				return $page;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'vbb_load_vertical_by_key' ) ) {
	function vbb_load_vertical_by_key( $key ) {
		if ( isset( $GLOBALS['vbb_test_vertical_configs'][ $key ] ) ) {
			return $GLOBALS['vbb_test_vertical_configs'][ $key ];
		}
		return null;
	}
}

if ( ! function_exists( 'vbb_build_page_content_from_baked' ) ) {
	function vbb_build_page_content_from_baked( $page, $sections_config = array() ) {
		return '<!-- wp:paragraph --><p>Baked content for ' . ( isset( $page['slug'] ) ? $page['slug'] : 'unknown' ) . '</p><!-- /wp:paragraph -->';
	}
}

if ( ! function_exists( 'vbb_generate_page_id_map' ) ) {
	function vbb_generate_page_id_map() {
		if ( isset( $GLOBALS['vbb_test_page_id_map'] ) ) {
			return $GLOBALS['vbb_test_page_id_map'];
		}
		return array( 'home' => 10, 'about' => 11 );
	}
}
// -- Test helpers --

$passed = 0;
$failed = 0;

function assert_contains( $haystack, $needle, $label ) {
	global $passed, $failed;
	if ( false !== strpos( $haystack, $needle ) ) {
		echo "  ✅ $label\n";
		$passed++;
	} else {
		echo "  ❌ $label -- expected to contain:\n      \"$needle\"\n";
		$failed++;
	}
}

function assert_true( $value, $label ) {
	global $passed, $failed;
	if ( true === $value ) {
		echo "  ✅ $label\n";
		$passed++;
	} else {
		echo "  ❌ $label -- expected true, got " . var_export( $value, true ) . "\n";
		$failed++;
	}
}

function assert_false( $value, $label ) {
	global $passed, $failed;
	if ( false === $value ) {
		echo "  ✅ $label\n";
		$passed++;
	} else {
		echo "  ❌ $label -- expected false, got " . var_export( $value, true ) . "\n";
		$failed++;
	}
}

function assert_equals( $expected, $actual, $label ) {
	global $passed, $failed;
	if ( $expected === $actual ) {
		echo "  ✅ $label\n";
		$passed++;
	} else {
		echo "  ❌ $label -- expected " . var_export( $expected, true ) . ", got " . var_export( $actual, true ) . "\n";
		$failed++;
	}
}

function assert_array_has_keys( $array, $keys, $label ) {
	global $passed, $failed;
	if ( ! is_array( $array ) ) {
		echo "  ❌ $label -- expected array, got " . gettype( $array ) . "\n";
		$failed++;
		return;
	}
	$missing = array();
	foreach ( $keys as $key ) {
		if ( ! array_key_exists( $key, $array ) ) {
			$missing[] = $key;
		}
	}
	if ( empty( $missing ) ) {
		echo "  ✅ $label\n";
		$passed++;
	} else {
		echo "  ❌ $label -- missing keys: " . implode( ', ', $missing ) . "\n";
		$failed++;
	}
}

function assert_no_notices( $closure, $label ) {
	global $passed, $failed;
	$error_level = error_reporting( E_ALL );
	$messages = array();
	set_error_handler(
		function ( $errno, $errstr ) use ( &$messages ) {
			$messages[] = $errstr;
			return true;
		},
		E_ALL
	);
	$closure();
	restore_error_handler();
	error_reporting( $error_level );
	if ( empty( $messages ) ) {
		echo "  ✅ $label\n";
		$passed++;
	} else {
		echo "  ❌ $label -- triggered notices:\n";
		foreach ( $messages as $m ) {
			echo "      - $m\n";
		}
		$failed++;
	}
}

function reset_test_state() {
	$GLOBALS['vbb_test_trashed'] = array();
	$GLOBALS['vbb_test_deleted'] = array();
	$GLOBALS['vbb_test_wp_trash_post_failures'] = array();
	$GLOBALS['vbb_test_wp_query_calls'] = array();
	$GLOBALS['vbb_test_wp_query_results'] = array();
	$GLOBALS['vbb_test_post_meta'] = array();
	$GLOBALS['vbb_test_options'] = array();
	$GLOBALS['vbb_test_posts'] = array();
	$GLOBALS['vbb_test_last_inserted_post'] = null;
	$GLOBALS['vbb_test_last_wp_update_post'] = null;
	$GLOBALS['vbb_test_theme_file_path_fail'] = false;
	$GLOBALS['vbb_test_cache_invalidated'] = false;
	$GLOBALS['vbb_test_active_vertical_key'] = 'default';
	$GLOBALS['vbb_test_vertical_config'] = null;
	$GLOBALS['vbb_test_vertical_configs'] = array();
	$GLOBALS['vbb_test_page_id_map'] = null;
	$GLOBALS['vbb_test_media_sideload_image_results'] = array();
	$GLOBALS['vbb_test_media_sideload_image_index'] = 0;
	unset( $GLOBALS['vbb_test_died'] );
}

// ===== SECTION 1: Reset Orchestrator -- vbb_reset_vertical_pages() =====

echo "\n=== Section 1: vbb_reset_vertical_pages() -- matching pages trashed (REQ-R1) ===\n";

reset_test_state();

$GLOBALS['vbb_test_wp_query_results']['page'] = array( 101, 102 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array();

assert_no_notices(
	function () {
		$report = vbb_reset_vertical_pages( 'law-firm' );

		assert_equals( 2, $report['pages_trashed'], 'REQ-R1: pages_trashed == 2' );
		assert_equals( 0, $report['navigation_trashed'], 'REQ-R1: navigation_trashed == 0' );
		assert_equals( array(), $report['errors'], 'REQ-R1: no errors' );
		assert_equals( array( 101, 102 ), $GLOBALS['vbb_test_trashed'], 'REQ-R1: wp_trash_post called with 101, 102' );

		$first_query = isset( $GLOBALS['vbb_test_wp_query_calls'][0] ) ? $GLOBALS['vbb_test_wp_query_calls'][0] : null;
		assert_true( null !== $first_query, 'REQ-R1: WP_Query was instantiated' );
		if ( $first_query ) {
			assert_equals( 'page', $first_query['post_type'], 'REQ-R1: WP_Query post_type = page' );
			assert_equals( '_vbb_vertical', $first_query['meta_query'][0]['key'], 'REQ-R1: meta_query key = _vbb_vertical' );
			assert_equals( 'law-firm', $first_query['meta_query'][0]['value'], 'REQ-R1: meta_query value = law-firm' );
		}
	},
	'REQ-R1: vbb_reset_vertical_pages with matching pages triggers no notices'
);

echo "\n=== Section 1: vbb_reset_vertical_pages() -- navigation trashed (REQ-R2) ===\n";

reset_test_state();

$GLOBALS['vbb_test_wp_query_results']['page'] = array();
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array( 201 );

assert_no_notices(
	function () {
		$report = vbb_reset_vertical_pages( 'law-firm' );

		assert_equals( 0, $report['pages_trashed'], 'REQ-R2: pages_trashed == 0' );
		assert_equals( 1, $report['navigation_trashed'], 'REQ-R2: navigation_trashed == 1' );
		assert_true( in_array( 201, $GLOBALS['vbb_test_trashed'], true ), 'REQ-R2: wp_trash_post called with nav ID 201' );
	},
	'REQ-R2: vbb_reset_vertical_pages with navigation triggers no notices'
);

echo "\n=== Section 1: vbb_reset_vertical_pages() -- non-matching untouched (REQ-R3) ===\n";

reset_test_state();

$GLOBALS['vbb_test_wp_query_results']['page'] = array();
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array();

assert_no_notices(
	function () {
		$report = vbb_reset_vertical_pages( 'other-vertical' );

		assert_equals( 0, $report['pages_trashed'], 'REQ-R3: pages_trashed == 0' );
		assert_equals( 0, $report['navigation_trashed'], 'REQ-R3: navigation_trashed == 0' );
		assert_equals( array(), $GLOBALS['vbb_test_trashed'], 'REQ-R3: wp_trash_post NOT called' );
	},
	'REQ-R3: vbb_reset_vertical_pages with no matching posts triggers no notices'
);

echo "\n=== Section 1: vbb_reset_vertical_pages() -- empty key no-op (REQ-R4, S3) ===\n";

reset_test_state();

$GLOBALS['vbb_test_wp_query_results']['page'] = array( 999 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array( 888 );

assert_no_notices(
	function () {
		$report = vbb_reset_vertical_pages( '' );

		assert_equals( 0, $report['pages_trashed'], 'REQ-R4: pages_trashed == 0' );
		assert_equals( 0, $report['navigation_trashed'], 'REQ-R4: navigation_trashed == 0' );
		assert_equals( array(), $report['errors'], 'REQ-R4: errors empty' );

		$query_calls = isset( $GLOBALS['vbb_test_wp_query_calls'] ) ? $GLOBALS['vbb_test_wp_query_calls'] : array();
		assert_equals( 0, count( $query_calls ), 'S3: WP_Query was NOT instantiated' );
		assert_equals( array(), $GLOBALS['vbb_test_trashed'], 'S3: wp_trash_post was NOT called' );
	},
	'REQ-R4/S3: vbb_reset_vertical_pages with empty key triggers no notices'
);

// ===== SECTION 2: Config Management -- vbb_update_active_vertical_config() =====

echo "\n=== Section 2: vbb_update_active_vertical_config() -- writes valid JSON (REQ-R5) ===\n";

reset_test_state();

assert_no_notices(
	function () {
		$result = vbb_update_active_vertical_config( 'ecommerce' );

		assert_false( is_wp_error( $result ), 'REQ-R5: result is NOT a WP_Error' );
		assert_array_has_keys( $result, array( 'active', 'fallback', 'path' ), 'REQ-R5: result has active, fallback, path' );
		assert_equals( 'ecommerce', $result['active'], 'REQ-R5: result.active == ecommerce' );
		assert_equals( 'default', $result['fallback'], 'REQ-R5: result.fallback == default' );

		$path = $result['path'];
		assert_true( file_exists( $path ), 'REQ-R5: config file exists at path' );

		$written = json_decode( file_get_contents( $path ), true );
		assert_true( is_array( $written ), 'REQ-R5: written content is valid JSON' );
		assert_equals( 'ecommerce', $written['active'], 'REQ-R5: file JSON has active == ecommerce' );
		assert_equals( 'default', $written['fallback'], 'REQ-R5: file JSON has fallback == default' );

		if ( file_exists( $path ) ) {
			unlink( $path );
		}
	},
	'REQ-R5: vbb_update_active_vertical_config with valid key triggers no notices'
);

echo "\n=== Section 2: vbb_update_active_vertical_config() -- empty key WP_Error (REQ-R6) ===\n";

reset_test_state();

assert_no_notices(
	function () {
		$result = vbb_update_active_vertical_config( '' );

		assert_true( is_wp_error( $result ), 'REQ-R6: result is a WP_Error' );
		assert_equals( 'vbb_empty_key', $result->get_error_code(), 'REQ-R6: error code is vbb_empty_key' );
	},
	'REQ-R6: vbb_update_active_vertical_config with empty key triggers no notices'
);

echo "\n=== Section 2: vbb_update_active_vertical_config() -- write failure WP_Error (REQ-R7) ===\n";

reset_test_state();

$GLOBALS['vbb_test_theme_file_path_fail'] = true;

// Use error suppression (@) because file_put_contents on /nonexistent triggers
// a PHP warning that is normally handled by WordPress' WP_DEBUG error handler.
$wp_error_level = error_reporting( 0 );
$result = @vbb_update_active_vertical_config( 'ecommerce' );

assert_true( is_wp_error( $result ), 'REQ-R7: result is a WP_Error' );
assert_equals( 'vbb_config_write_failed', $result->get_error_code(), 'REQ-R7: error code is vbb_config_write_failed' );

error_reporting( $wp_error_level );

$GLOBALS['vbb_test_theme_file_path_fail'] = false;

// ===== SECTION 3: Edge Cases =====

echo "\n=== Section 3: Edge -- reset with null key (sanitize_key converted to empty) ===\n";

reset_test_state();

$wp_error_level = error_reporting( 0 );
$report = vbb_reset_vertical_pages( null );
error_reporting( $wp_error_level );

assert_equals( 0, $report['pages_trashed'], 'Edge: null key pages_trashed == 0' );
assert_equals( 0, $report['navigation_trashed'], 'Edge: null key navigation_trashed == 0' );
assert_equals( array(), $report['errors'], 'Edge: null key errors empty' );

echo "\n=== Section 3: Edge -- config with null key (sanitize_key converts to empty) ===\n";

reset_test_state();

$wp_error_level = error_reporting( 0 );
$result = vbb_update_active_vertical_config( null );
error_reporting( $wp_error_level );

assert_true( is_wp_error( $result ), 'Edge: null key returns WP_Error' );
assert_equals( 'vbb_empty_key', $result->get_error_code(), 'Edge: null key error code is vbb_empty_key' );

echo "\n=== Section 3: Edge -- config with uppercase/sanitized key ===\n";

reset_test_state();

assert_no_notices(
	function () {
		$result = vbb_update_active_vertical_config( '  UPPERCASE-KEY  ' );

		assert_false( is_wp_error( $result ), 'Edge: sanitized key is not WP_Error' );
		assert_equals( 'uppercase-key', $result['active'], 'Edge: key is lowered and trimmed' );

		$path = $result['path'];
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
	},
	'vbb_update_active_vertical_config with uppercase key triggers no notices'
);

echo "\n=== Section 3: Edge -- trash failure captured in errors ===\n";

reset_test_state();

$GLOBALS['vbb_test_wp_query_results']['page'] = array( 301, 302 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array();
$GLOBALS['vbb_test_wp_trash_post_failures'] = array( 302 );

assert_no_notices(
	function () {
		$report = vbb_reset_vertical_pages( 'law-firm' );

		assert_equals( 1, $report['pages_trashed'], 'Edge: pages_trashed == 1 (only 301 succeeded)' );
		assert_true( count( $report['errors'] ) > 0, 'Edge: errors array has failure entries' );
	},
	'Edge: vbb_reset_vertical_pages trash failure captured in errors'
);

echo "\n=== Section 3: Edge -- reset with clean state returns empty report ===\n";

reset_test_state();

assert_no_notices(
	function () {
		$report = vbb_reset_vertical_pages( 'test-key' );
		assert_equals( 0, $report['pages_trashed'], 'Edge: no side effects pages' );
		assert_equals( 0, $report['navigation_trashed'], 'Edge: no side effects nav' );
	},
	'Edge: vbb_reset_vertical_pages with clean state triggers no notices'
);

// ===== SECTION 4: REQ-R14 -- wp_trash_post used, wp_delete_post never called =====

echo "\n=== Section 4: REQ-R14 -- wp_trash_post vs wp_delete_post ===\n";

reset_test_state();

$GLOBALS['vbb_test_wp_query_results']['page'] = array( 401, 402 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array( 501 );

vbb_reset_vertical_pages( 'test-key' );

assert_equals( 0, count( $GLOBALS['vbb_test_deleted'] ), 'REQ-R14: wp_delete_post was NEVER called during reset' );
assert_true( count( $GLOBALS['vbb_test_trashed'] ) > 0, 'REQ-R14: wp_trash_post WAS called during reset' );
assert_equals( array( 401, 402, 501 ), $GLOBALS['vbb_test_trashed'], 'REQ-R14: all matching posts trashed via wp_trash_post' );

// ===== SECTION 5: Pipeline Integration Tests =====

echo "\n=== Section 5: S1 -- Full import with valid fixture ===\n";

reset_test_state();

$fixture_config = array(
	'schemaVersion' => '1.0.0',
	'verticalKey'   => 'test-fixture',
	'name'          => 'Test Fixture',
	'brand'         => array( 'siteName' => 'Test', 'tagline' => 'Test' ),
	'navigation'    => array(
		'primary' => array(
			array( 'label' => 'Home', 'url' => '/' ),
			array( 'label' => 'About', 'url' => '/about' ),
		),
	),
	'pages'    => array(
		array( 'key' => 'home', 'title' => 'Home', 'slug' => 'home', 'sections' => array( 'hero' ) ),
		array( 'key' => 'about', 'title' => 'About', 'slug' => 'about', 'sections' => array( 'hero-centered' ) ),
	),
	'sections'      => array( 'hero' => array( 'type' => 'hero' ) ),
	'graphics'      => array(
		'images' => array(
			array( 'url' => 'https://example.com/img1.jpg', 'title' => 'Image 1' ),
			array( 'url' => 'https://example.com/img2.jpg', 'title' => 'Image 2' ),
			array( 'url' => 'https://example.com/img3.jpg', 'title' => 'Image 3' ),
			array( 'url' => 'https://example.com/img4.jpg', 'title' => 'Image 4' ),
		),
	),
	'importOptions' => array(
		'homepageKey'  => 'home',
		'setFrontPage' => false,
	),
);

$GLOBALS['vbb_test_vertical_configs']['test-fixture'] = $fixture_config;
$GLOBALS['vbb_test_vertical_config']                  = $fixture_config;
$GLOBALS['vbb_test_active_vertical_key']              = 'previous-vertical';
$GLOBALS['vbb_test_wp_query_results']['page']         = array( 101, 102 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array( 201 );
$GLOBALS['vbb_test_wp_query_results']['attachment']    = array();
$GLOBALS['vbb_test_page_id_map']                       = array( 'home' => 10, 'about' => 11 );

// Configure media sideload: first 3 succeed, 4th fails.
$GLOBALS['vbb_test_media_sideload_image_results'] = array(
	42,
	43,
	44,
	new WP_Error( 'vbb_sideload_failed', 'Simulated failure' ),
);

assert_no_notices(
	function () {
		$result = vbb_import_vertical_full( 'test-fixture' );

		assert_array_has_keys( $result, array( 'success', 'vertical', 'reset', 'configUpdated', 'media', 'pages', 'navigation', 'woocommerce', 'frontPage', 'report' ), 'S1: result has all expected keys' );
		assert_true( $result['success'], 'S1: success === true' );
		assert_equals( 'test-fixture', $result['vertical'], 'S1: vertical === test-fixture' );
		assert_true( $result['configUpdated'], 'S1: configUpdated === true' );

		// Reset: 2 pages + 1 nav trashed from previous-vertical.
		assert_array_has_keys( $result['reset'], array( 'pages_trashed', 'navigation_trashed', 'errors' ), 'S1: reset has report keys' );
		assert_equals( 2, $result['reset']['pages_trashed'], 'S1: reset.pages_trashed === 2' );
		assert_equals( 1, $result['reset']['navigation_trashed'], 'S1: reset.navigation_trashed === 1' );

		// Report: pages.
		assert_equals( 2, $result['report']['pages_created'], 'S1: report.pages_created === 2' );
		assert_equals( 0, $result['report']['pages_errors'], 'S1: report.pages_errors === 0' );

		// Report: media.
		assert_equals( 3, $result['report']['media_sideloaded'], 'S1: report.media_sideloaded === 3' );
		assert_equals( 1, $result['report']['media_failed'], 'S1: report.media_failed === 1' );

		// Navigation created from fixture.
		assert_true( $result['navigation']['created'], 'S1: navigation.created === true' );
		assert_equals( 2, $result['navigation']['items'], 'S1: navigation.items === 2' );

		// WooCommerce not configured (no woocommerce key in fixture).
		assert_false( $result['woocommerce']['configured'], 'S1: woocommerce.configured === false' );

		// Front page skipped (setFrontPage disabled).
		assert_false( $result['frontPage']['applied'], 'S1: frontPage.applied === false' );
	},
	'S1: full pipeline import triggers no notices'
);

echo "\n=== Section 5: S2 -- Same-vertical re-import (no reset) ===\n";

reset_test_state();

$GLOBALS['vbb_test_vertical_configs']['test-fixture'] = $fixture_config;
$GLOBALS['vbb_test_vertical_config']                  = $fixture_config;
$GLOBALS['vbb_test_active_vertical_key']              = 'test-fixture'; // same key → no reset
$GLOBALS['vbb_test_wp_query_results']['attachment']    = array();
$GLOBALS['vbb_test_page_id_map']                       = array( 'home' => 10, 'about' => 11 );

// Media sideload all succeed.
$GLOBALS['vbb_test_media_sideload_image_results'] = array( 42, 43, 44, 45 );

assert_no_notices(
	function () {
		$result = vbb_import_vertical_full( 'test-fixture' );

		assert_true( $result['success'], 'S2: success === true' );
		assert_equals( 'test-fixture', $result['vertical'], 'S2: vertical === test-fixture' );

		// Reset was NOT triggered.
		assert_true( null === $result['reset'], 'S2: reset is null (same vertical, no reset)' );

		// Pipeline still proceeds.
		assert_equals( 2, $result['report']['pages_created'], 'S2: pages_created === 2' );
		assert_equals( 4, $result['report']['media_sideloaded'], 'S2: media_sideloaded === 4' );
		assert_true( $result['navigation']['created'], 'S2: navigation.created === true' );
	},
	'S2: same-vertical re-import triggers no notices'
);

echo "\n=== Section 5: S4 -- Cross-vertical switch (reset old vertical) ===\n";

reset_test_state();

$ecommerce_config = array(
	'schemaVersion' => '1.0.0',
	'verticalKey'   => 'ecommerce',
	'name'          => 'Ecommerce',
	'brand'         => array( 'siteName' => 'Shop', 'tagline' => 'Buy' ),
	'navigation'    => array(
		'primary' => array(
			array( 'label' => 'Shop', 'url' => '/shop' ),
		),
	),
	'pages'         => array(
		array( 'key' => 'shop', 'title' => 'Shop', 'slug' => 'shop', 'sections' => array( 'hero' ) ),
	),
	'sections'      => array( 'hero' => array( 'type' => 'hero' ) ),
	'graphics'      => array( 'images' => array() ),
	'importOptions' => array(
		'homepageKey'  => 'shop',
		'setFrontPage' => false,
	),
);

$GLOBALS['vbb_test_vertical_configs']['ecommerce'] = $ecommerce_config;
$GLOBALS['vbb_test_vertical_config']               = $ecommerce_config;
$GLOBALS['vbb_test_active_vertical_key']           = 'law-firm';
$GLOBALS['vbb_test_wp_query_results']['page']      = array( 301, 302, 303, 304, 305 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array( 401 );
$GLOBALS['vbb_test_wp_query_results']['attachment']    = array();
$GLOBALS['vbb_test_page_id_map']                       = array( 'shop' => 50 );

// Verify reset was called with old key (law-firm)
$before_query_count = isset( $GLOBALS['vbb_test_wp_query_calls'] ) ? count( $GLOBALS['vbb_test_wp_query_calls'] ) : 0;

assert_no_notices(
	function () {
		$result = vbb_import_vertical_full( 'ecommerce' );

		assert_true( $result['success'], 'S4: success === true' );
		assert_equals( 'ecommerce', $result['vertical'], 'S4: vertical === ecommerce' );

		// Reset was triggered for law-firm.
		assert_true( is_array( $result['reset'] ), 'S4: reset is array (switch from law-firm)' );
		assert_equals( 5, $result['reset']['pages_trashed'], 'S4: reset.pages_trashed === 5 (law-firm pages)' );
		assert_equals( 1, $result['reset']['navigation_trashed'], 'S4: reset.navigation_trashed === 1 (law-firm nav)' );

		// REQ-R14: wp_trash_post used, NOT wp_delete_post.
		assert_true( count( $GLOBALS['vbb_test_trashed'] ) > 0, 'S4/REQ-R14: wp_trash_post called during cross-switch reset' );
		assert_equals( 0, count( $GLOBALS['vbb_test_deleted'] ), 'S4/REQ-R14: wp_delete_post NEVER called during cross-switch' );

		// Pipeline completed for ecommerce.
		assert_equals( 1, $result['report']['pages_created'], 'S4: pages_created === 1 (shop page)' );
		assert_true( $result['navigation']['created'], 'S4: navigation.created === true' );
	},
	'S4: cross-vertical switch triggers no notices'
);

echo "\n=== Section 5: S5 -- Pipeline abort on missing vertical ===\n";

reset_test_state();

assert_no_notices(
	function () {
		$result = vbb_import_vertical_full( 'nonexistent' );

		assert_false( $result['success'], 'S5: success === false' );
		assert_equals( 'nonexistent', $result['vertical'], 'S5: vertical === nonexistent' );
		assert_true( false !== strpos( $result['error'], 'nonexistent' ), 'S5: error references the missing key' );

		// No pipeline steps should have executed past loading.
		$query_calls = isset( $GLOBALS['vbb_test_wp_query_calls'] ) ? $GLOBALS['vbb_test_wp_query_calls'] : array();
		assert_equals( 0, count( $query_calls ), 'S5: no WP_Query calls (pipeline aborted before reset)' );
		assert_equals( 0, count( $GLOBALS['vbb_test_trashed'] ), 'S5: no posts trashed' );
	},
	'S5: pipeline abort on missing vertical triggers no notices'
);

echo "\n=== Section 5: S6 -- Config write failure aborts pipeline ===\n";

reset_test_state();

$GLOBALS['vbb_test_vertical_configs']['ecommerce'] = $ecommerce_config;
$GLOBALS['vbb_test_vertical_config']               = $ecommerce_config;
$GLOBALS['vbb_test_active_vertical_key']           = 'previous-key';
$GLOBALS['vbb_test_wp_query_results']['page']      = array( 101 );
$GLOBALS['vbb_test_wp_query_results']['wp_navigation'] = array();
$GLOBALS['vbb_test_wp_query_results']['attachment']    = array();
$GLOBALS['vbb_test_page_id_map']                       = array( 'shop' => 50 );

// Fail the config write.
$GLOBALS['vbb_test_theme_file_path_fail'] = true;

$wp_error_level = error_reporting( 0 );
$result = @vbb_import_vertical_full( 'ecommerce' );
error_reporting( $wp_error_level );

assert_false( $result['success'], 'S6: success === false' );
assert_equals( 'ecommerce', $result['vertical'], 'S6: vertical === ecommerce' );
assert_true( false !== strpos( $result['error'], 'write' ), 'S6: error message mentions write failure' );

// Reset WAS performed (pre-config step), but no further steps.
assert_true( is_array( $result['reset'] ), 'S6: reset ran before config failure' );
assert_equals( 1, $result['reset']['pages_trashed'], 'S6: reset trashed 1 page' );

// Verify no further steps executed: only reset queries ran.
$query_calls = isset( $GLOBALS['vbb_test_wp_query_calls'] ) ? $GLOBALS['vbb_test_wp_query_calls'] : array();
assert_true( count( $query_calls ) <= 3, 'S6: no WP_Query calls beyond reset (media/pages/nav aborted)' );

$GLOBALS['vbb_test_theme_file_path_fail'] = false;

// ===== Summary =====

$total = $passed + $failed;
echo "\n========================================\n";
echo "Results: $passed/$total passed, $failed/$total failed\n";
echo "========================================\n";

exit( $failed > 0 ? 1 : 0 );