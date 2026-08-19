<?php
/**
 * Asset Library — manages agency media assets with role tagging.
 *
 * Registers the `orke_asset` custom post type for agency-owned media assets
 * (SVG, PNG, JPEG). Provides role tagging via `_vbb_media_role` post meta and
 * filterable public URL generation for the Orkestone_JSON_Builder.
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the orke_asset CPT, upload MIME filtering, and URL generation.
 *
 * Assets are stored in the WordPress Media Library and referenced by ID/slug
 * from the generated vertical JSON. The public URL is resolved at build time
 * via the `orke_asset_base_url` filter, allowing agencies to use a CDN or
 * migrate domains without regenerating configurations (AD9).
 */
class Orkestone_Asset_Library {

	/**
	 * The CPT slug.
	 */
	const POST_TYPE = 'orke_asset';

	/**
	 * Allowed MIME types for asset uploads.
	 *
	 * @var array
	 */
	const ALLOWED_MIMES = array(
		'svg'  => 'image/svg+xml',
		'svgz' => 'image/svg+xml',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
	);

	/**
	 * Register the post type, meta fields, and upload hooks.
	 */
	public static function register(): void {
		self::register_post_type();
		self::register_meta_fields();
		self::register_hooks();
	}

	/**
	 * Register the orke_asset custom post type.
	 */
	private static function register_post_type(): void {
		$labels = array(
			'name'               => _x( 'Assets', 'post type general name', 'orkestone-agency-hub' ),
			'singular_name'      => _x( 'Asset', 'post type singular name', 'orkestone-agency-hub' ),
			'add_new'            => __( 'Upload Asset', 'orkestone-agency-hub' ),
			'add_new_item'       => __( 'Upload New Asset', 'orkestone-agency-hub' ),
			'edit_item'          => __( 'Edit Asset', 'orkestone-agency-hub' ),
			'view_item'          => __( 'View Asset', 'orkestone-agency-hub' ),
			'search_items'       => __( 'Search Assets', 'orkestone-agency-hub' ),
			'not_found'          => __( 'No assets found', 'orkestone-agency-hub' ),
			'not_found_in_trash' => __( 'No assets found in Trash', 'orkestone-agency-hub' ),
			'all_items'          => __( 'All Assets', 'orkestone-agency-hub' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=orke_configuration',
			'show_in_rest'    => true,
			'supports'        => array( 'title', 'editor', 'thumbnail' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'rewrite'         => false,
			'query_var'       => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register post meta for asset role tagging and URL storage.
	 */
	private static function register_meta_fields(): void {
		$meta_fields = array(
			'_vbb_media_role' => array(
				'type'              => 'string',
				'description'       => __( 'Media role tag (logo, hero-main, about-image, etc.)', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_vbb_asset_url'  => array(
				'type'              => 'string',
				'description'       => __( 'Public URL of the uploaded asset', 'orkestone-agency-hub' ),
				'sanitize_callback' => 'sanitize_url',
			),
		);

		foreach ( $meta_fields as $meta_key => $meta_args ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => $meta_args['type'],
					'description'       => $meta_args['description'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $meta_args['sanitize_callback'],
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Register WordPress hooks for MIME filtering and URL persistence.
	 */
	private static function register_hooks(): void {
		add_filter( 'upload_mimes', array( __CLASS__, 'filter_upload_mimes' ) );
		add_action( 'add_attachment', array( __CLASS__, 'store_asset_url_on_upload' ), 10, 1 );
	}

	/**
	 * Allow SVG, PNG, and JPEG uploads in the Media Library.
	 *
	 * WordPress blocks SVG uploads by default for security reasons.
	 * This filter safely enables them for the Asset Library.
	 *
	 * @param array $mimes Allowed MIME types map.
	 * @return array Filtered MIME types.
	 */
	public static function filter_upload_mimes( array $mimes ): array {
		foreach ( self::ALLOWED_MIMES as $extension => $mime ) {
			if ( ! isset( $mimes[ $extension ] ) ) {
				$mimes[ $extension ] = $mime;
			}
		}

		return $mimes;
	}

	/**
	 * Store the public asset URL when an attachment is added to the library.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public static function store_asset_url_on_upload( int $attachment_id ): void {
		$url = self::get_asset_url( $attachment_id );
		update_post_meta( $attachment_id, '_vbb_asset_url', $url );
	}

	/**
	 * Get the public URL for an asset.
	 *
	 * The base URL is filterable via `orke_asset_base_url`, defaulting to
	 * `site_url()`. This lets agencies migrate to a CDN or a new domain
	 * without regenerating their JSON configurations (AD9).
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return string Absolute public URL to the asset file.
	 */
	public static function get_asset_url( int $attachment_id ): string {
		$base_url = apply_filters( 'orke_asset_base_url', site_url() );
		$file     = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( ! empty( $file ) ) {
			return trailingslashit( $base_url ) . 'wp-content/uploads/' . ltrim( $file, '/' );
		}

		return wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Get the asset ID by its role tag.
	 *
	 * Useful for the JSON Builder to resolve assets by role.
	 *
	 * @param string $role The media role tag (e.g., 'logo', 'hero-main').
	 * @return int|null Post ID of the matching asset, or null.
	 */
	public static function get_asset_by_role( string $role ): ?int {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_vbb_media_role',
						'value' => $role,
					),
				),
				'fields'         => 'ids',
			)
		);

		return $query->have_posts() ? (int) $query->posts[0] : null;
	}

	/**
	 * Get the public URL for an asset by its role tag.
	 *
	 * Convenience method for the JSON Builder.
	 *
	 * @param string $role The media role tag.
	 * @return string|null Public URL, or null if no asset with that role.
	 */
	public static function get_url_by_role( string $role ): ?string {
		$asset_id = self::get_asset_by_role( $role );

		if ( null === $asset_id ) {
			return null;
		}

		$url = get_post_meta( $asset_id, '_vbb_asset_url', true );

		if ( empty( $url ) ) {
			$url = self::get_asset_url( $asset_id );
		}

		return $url ?: null;
	}
}
