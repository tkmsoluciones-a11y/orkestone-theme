<?php
/**
 * Briefing Form — 4-tab admin page for client configuration briefings.
 *
 * Renders the Hub's primary admin screen where agencies enter client
 * requirements across 4 tabs: Branding, Pages & Sections, Content & Models,
 * and Navigation & SEO. Handles form submission, validation, JSON generation,
 * and budget calculation triggers.
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the briefing form admin page, submission handling, and
 * integration with the JSON Builder and Pricing Calculator.
 */
class Orkestone_Briefing_Form {

	/**
	 * The admin page slug.
	 */
	const PAGE_SLUG = 'orke-new-configuration';

	/**
	 * Nonce action for form submission.
	 */
	const NONCE_ACTION = 'orke_briefing_form_save';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = '_orke_briefing_nonce';

	/**
	 * Register WordPress hooks for the briefing form.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		add_action( 'admin_post_orke_save_briefing', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_orke_calculate_budget', array( __CLASS__, 'handle_calculate_budget' ) );
	}

	/**
	 * Add the briefing form admin page under the Configuration post type menu.
	 */
	public static function add_admin_page(): void {
		add_submenu_page(
			'edit.php?post_type=orke_configuration',
			__( 'New Configuration', 'orkestone-agency-hub' ),
			__( 'New Configuration', 'orkestone-agency-hub' ),
			'edit_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the briefing form admin page.
	 *
	 * Loads the template file with form state passed as variables.
	 * Handles display of validation errors, saved data, and budget results.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'orkestone-agency-hub' ) );
		}

		$saved_data    = self::get_saved_form_data();
		$errors        = self::get_form_errors();
		$budget_result = self::get_budget_result();
		$generated_json = self::get_generated_json();

		$form_action = admin_url( 'admin-post.php' );
		$calculate_action = admin_url( 'admin-post.php' );
		$nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

		$tab_labels = array(
			'branding'    => __( 'Branding', 'orkestone-agency-hub' ),
			'pages'       => __( 'Pages & Sections', 'orkestone-agency-hub' ),
			'content'     => __( 'Content & Models', 'orkestone-agency-hub' ),
			'navigation'  => __( 'Navigation & SEO', 'orkestone-agency-hub' ),
		);

		$active_tab = self::get_active_tab( $errors );

		include ORKE_AGENCY_HUB_DIR . 'templates/admin-briefing-form.php';
	}

	/**
	 * Handle briefing form save submission.
	 *
	 * Validates nonce, sanitizes input, generates JSON via the builder,
	 * calculates budget via the pricing engine, and stores everything
	 * as an orke_configuration draft post.
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'orkestone-agency-hub' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$form_data = self::sanitize_form_data( $_POST );
		$errors    = self::validate_form_data( $form_data );

		if ( ! empty( $errors ) ) {
			self::store_form_errors( $errors );
			self::store_form_data( $form_data );
			wp_safe_redirect(
				add_query_arg(
					array(
						'post_type' => 'orke_configuration',
						'page'      => self::PAGE_SLUG,
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		// Generate JSON and budget.
		$json_builder = new Orkestone_JSON_Builder();
		$config       = $json_builder->build( $form_data );
		$json_string  = $json_builder->get_json( $config );

		$pricing  = new Orkestone_Pricing( $form_data );
		$budget   = $pricing->calculate();

		// Create the configuration post.
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'orke_configuration',
				'post_status'  => 'draft',
				'post_title'   => isset( $form_data['branding']['site_name'] ) ? $form_data['branding']['site_name'] : __( 'New Configuration', 'orkestone-agency-hub' ),
				'post_content' => $json_string,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::store_form_errors( array( 'general' => __( 'Failed to create configuration. Please try again.', 'orkestone-agency-hub' ) ) );
			self::store_form_data( $form_data );
			wp_safe_redirect(
				add_query_arg(
					array(
						'post_type' => 'orke_configuration',
						'page'      => self::PAGE_SLUG,
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		// Store meta fields.
		$vertical_key = isset( $config['verticalKey'] ) ? $config['verticalKey'] : '';
		update_post_meta( $post_id, '_orke_vertical_key', $vertical_key );
		update_post_meta( $post_id, '_orke_client_data', wp_json_encode( $form_data ) );
		update_post_meta( $post_id, '_orke_budget_amount', $budget['total'] );
		update_post_meta( $post_id, '_orke_vertical_json_blob', $json_string );

		// Clear stored form data.
		delete_transient( 'orke_briefing_form_data' );
		delete_transient( 'orke_briefing_form_errors' );
		delete_transient( 'orke_briefing_budget_result' );
		delete_transient( 'orke_briefing_generated_json' );

		// Redirect to the new configuration edit screen.
		wp_safe_redirect(
			add_query_arg(
				array(
					'post'   => $post_id,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Handle calculate budget request (AJAX-like via admin-post).
	 *
	 * Generates a budget without saving and stores the result in a transient
	 * for display on the form page.
	 */
	public static function handle_calculate_budget(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'orkestone-agency-hub' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$form_data = self::sanitize_form_data( $_POST );
		$errors    = self::validate_form_data( $form_data );

		if ( ! empty( $errors ) ) {
			self::store_form_errors( $errors );
			self::store_form_data( $form_data );
			wp_safe_redirect(
				add_query_arg(
					array(
						'post_type' => 'orke_configuration',
						'page'      => self::PAGE_SLUG,
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		// Generate JSON preview and budget.
		$json_builder = new Orkestone_JSON_Builder();
		$config       = $json_builder->build( $form_data );
		$json_string  = $json_builder->get_json( $config );

		$pricing      = new Orkestone_Pricing( $form_data );
		$budget       = $pricing->calculate();

		self::store_budget_result( $budget );
		self::store_generated_json( $json_string );
		self::store_form_data( $form_data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'orke_configuration',
					'page'      => self::PAGE_SLUG,
					'budget'    => 'calculated',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize incoming form data.
	 *
	 * @param array $raw_data Raw POST data.
	 * @return array Sanitized form data.
	 */
	private static function sanitize_form_data( array $raw_data ): array {
		$data = array();

		// Branding tab.
		$data['branding'] = array(
			'site_name'      => isset( $raw_data['orke_site_name'] ) ? sanitize_text_field( wp_unslash( $raw_data['orke_site_name'] ) ) : '',
			'tagline'        => isset( $raw_data['orke_tagline'] ) ? sanitize_text_field( wp_unslash( $raw_data['orke_tagline'] ) ) : '',
			'logo_id'        => isset( $raw_data['orke_logo_id'] ) ? intval( $raw_data['orke_logo_id'] ) : 0,
			'logo_url'       => isset( $raw_data['orke_logo_url'] ) ? sanitize_url( wp_unslash( $raw_data['orke_logo_url'] ) ) : '',
			'favicon_id'     => isset( $raw_data['orke_favicon_id'] ) ? intval( $raw_data['orke_favicon_id'] ) : 0,
			'primary_color'   => isset( $raw_data['orke_primary_color'] ) ? sanitize_hex_color( wp_unslash( $raw_data['orke_primary_color'] ) ) : '#1a365d',
			'secondary_color' => isset( $raw_data['orke_secondary_color'] ) ? sanitize_hex_color( wp_unslash( $raw_data['orke_secondary_color'] ) ) : '#e2e8f0',
			'accent_color'    => isset( $raw_data['orke_accent_color'] ) ? sanitize_hex_color( wp_unslash( $raw_data['orke_accent_color'] ) ) : '#3b82f6',
			'heading_font'    => isset( $raw_data['orke_heading_font'] ) ? sanitize_text_field( wp_unslash( $raw_data['orke_heading_font'] ) ) : 'Inter',
			'body_font'       => isset( $raw_data['orke_body_font'] ) ? sanitize_text_field( wp_unslash( $raw_data['orke_body_font'] ) ) : 'Inter',
		);

		// Pages tab.
		$data['pages'] = array();
		if ( isset( $raw_data['orke_pages'] ) && is_array( $raw_data['orke_pages'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_pages'] ) as $index => $page ) {
				$title = isset( $page['title'] ) ? sanitize_text_field( $page['title'] ) : '';
				if ( empty( $title ) && isset( $page['page_title'] ) ) {
					$title = sanitize_text_field( $page['page_title'] );
				}

				if ( empty( $title ) ) {
					continue;
				}

				$page_data = array(
					'title'    => $title,
					'slug'     => isset( $page['slug'] ) ? sanitize_title( $page['slug'] ) : sanitize_title( $title ),
					'sections' => array(),
				);

				if ( isset( $page['sections'] ) && is_array( $page['sections'] ) ) {
					foreach ( $page['sections'] as $section ) {
						$page_data['sections'][] = sanitize_text_field( $section );
					}
				}

				$data['pages'][] = $page_data;
			}
		}

		// Content models tab.
		$data['content_models'] = array();

		// Services.
		$data['content_models']['services'] = array();
		if ( isset( $raw_data['orke_services'] ) && is_array( $raw_data['orke_services'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_services'] ) as $service ) {
				if ( ! empty( $service['title'] ) ) {
					$data['content_models']['services'][] = array(
						'title'       => sanitize_text_field( $service['title'] ),
						'description' => isset( $service['description'] ) ? sanitize_textarea_field( $service['description'] ) : '',
						'icon'        => isset( $service['icon'] ) ? sanitize_text_field( $service['icon'] ) : '',
					);
				}
			}
		}

		// Team members.
		$data['content_models']['team'] = array();
		if ( isset( $raw_data['orke_team'] ) && is_array( $raw_data['orke_team'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_team'] ) as $member ) {
				if ( ! empty( $member['name'] ) ) {
					$data['content_models']['team'][] = array(
						'name'        => sanitize_text_field( $member['name'] ),
						'role'        => isset( $member['role'] ) ? sanitize_text_field( $member['role'] ) : '',
						'description' => isset( $member['description'] ) ? sanitize_textarea_field( $member['description'] ) : '',
					);
				}
			}
		}

		// Pricing plans.
		$data['content_models']['pricing'] = array();
		if ( isset( $raw_data['orke_pricing'] ) && is_array( $raw_data['orke_pricing'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_pricing'] ) as $plan ) {
				if ( ! empty( $plan['plan'] ) ) {
					$data['content_models']['pricing'][] = array(
						'plan'     => sanitize_text_field( $plan['plan'] ),
						'price'    => isset( $plan['price'] ) ? floatval( $plan['price'] ) : 0,
						'currency' => isset( $plan['currency'] ) ? sanitize_text_field( $plan['currency'] ) : 'USD',
					);
				}
			}
		}

		// FAQ items.
		$data['content_models']['faq'] = array();
		if ( isset( $raw_data['orke_faq'] ) && is_array( $raw_data['orke_faq'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_faq'] ) as $item ) {
				if ( ! empty( $item['question'] ) ) {
					$data['content_models']['faq'][] = array(
						'question' => sanitize_text_field( $item['question'] ),
						'answer'   => isset( $item['answer'] ) ? sanitize_textarea_field( $item['answer'] ) : '',
					);
				}
			}
		}

		// Testimonials.
		$data['content_models']['testimonials'] = array();
		if ( isset( $raw_data['orke_testimonials'] ) && is_array( $raw_data['orke_testimonials'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_testimonials'] ) as $t ) {
				if ( ! empty( $t['quote'] ) ) {
					$data['content_models']['testimonials'][] = array(
						'quote'  => sanitize_textarea_field( $t['quote'] ),
						'author' => isset( $t['author'] ) ? sanitize_text_field( $t['author'] ) : '',
						'role'   => isset( $t['role'] ) ? sanitize_text_field( $t['role'] ) : '',
					);
				}
			}
		}

		// Navigation tab.
		$data['navigation'] = array();
		if ( isset( $raw_data['orke_menu_items'] ) && is_array( $raw_data['orke_menu_items'] ) ) {
			foreach ( wp_unslash( $raw_data['orke_menu_items'] ) as $item ) {
				$label = isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '';
				if ( ! empty( $label ) ) {
					$data['navigation'][] = array(
						'label' => $label,
						'url'   => isset( $item['url'] ) ? sanitize_url( $item['url'] ) : '/',
					);
				}
			}
		}

		// SEO.
		$data['seo'] = array(
			'title_pattern'    => isset( $raw_data['orke_title_pattern'] ) ? sanitize_text_field( wp_unslash( $raw_data['orke_title_pattern'] ) ) : '',
			'meta_description' => isset( $raw_data['orke_meta_description'] ) ? sanitize_textarea_field( wp_unslash( $raw_data['orke_meta_description'] ) ) : '',
			'og_image_id'      => isset( $raw_data['orke_og_image_id'] ) ? intval( $raw_data['orke_og_image_id'] ) : 0,
		);

		return $data;
	}

	/**
	 * Validate form data against business rules.
	 *
	 * Minimum requirements (REQ-AH4):
	 * - Site name ≥ 1 character
	 * - At least 1 page defined
	 * - At least 1 menu item
	 *
	 * @param array $data Sanitized form data.
	 * @return array Associative array of field → error message.
	 */
	private static function validate_form_data( array $data ): array {
		$errors = array();

		// Branding: site name required.
		if ( empty( $data['branding']['site_name'] ) ) {
			$errors['site_name'] = __( 'Site name is required.', 'orkestone-agency-hub' );
		}

		// Pages: at least 1 required.
		if ( empty( $data['pages'] ) || count( $data['pages'] ) < 1 ) {
			$errors['pages'] = __( 'At least one page is required.', 'orkestone-agency-hub' );
		}

		// Navigation: at least 1 menu item required.
		if ( empty( $data['navigation'] ) || count( $data['navigation'] ) < 1 ) {
			$errors['navigation'] = __( 'At least one menu item is required.', 'orkestone-agency-hub' );
		}

		return $errors;
	}

	/**
	 * Get the active tab based on errors or request parameter.
	 *
	 * @param array $errors Current validation errors.
	 * @return string Active tab key.
	 */
	private static function get_active_tab( array $errors ): string {
		// If there are errors, switch to the tab with the first error.
		if ( isset( $errors['site_name'] ) ) {
			return 'branding';
		}

		if ( isset( $errors['pages'] ) ) {
			return 'pages';
		}

		if ( isset( $errors['navigation'] ) ) {
			return 'navigation';
		}

		// Default to branding (first tab).
		return 'branding';
	}

	/**
	 * Store form data in a transient for display after redirect.
	 *
	 * @param array $data Sanitized form data.
	 */
	private static function store_form_data( array $data ): void {
		set_transient( 'orke_briefing_form_data', $data, HOUR_IN_SECONDS );
	}

	/**
	 * Retrieve stored form data.
	 *
	 * @return array Stored form data or empty array.
	 */
	private static function get_saved_form_data(): array {
		$data = get_transient( 'orke_briefing_form_data' );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Store validation errors in a transient.
	 *
	 * @param array $errors Associative array of errors.
	 */
	private static function store_form_errors( array $errors ): void {
		set_transient( 'orke_briefing_form_errors', $errors, HOUR_IN_SECONDS );
	}

	/**
	 * Retrieve stored validation errors.
	 *
	 * @return array Stored errors or empty array.
	 */
	private static function get_form_errors(): array {
		$errors = get_transient( 'orke_briefing_form_errors' );

		if ( false !== $errors ) {
			delete_transient( 'orke_briefing_form_errors' );
		}

		return is_array( $errors ) ? $errors : array();
	}

	/**
	 * Store budget calculation result.
	 *
	 * @param array $result Budget result from Orkestone_Pricing::calculate().
	 */
	private static function store_budget_result( array $result ): void {
		set_transient( 'orke_briefing_budget_result', $result, HOUR_IN_SECONDS );
	}

	/**
	 * Retrieve stored budget result.
	 *
	 * @return array Budget result or empty array.
	 */
	private static function get_budget_result(): array {
		$result = get_transient( 'orke_briefing_budget_result' );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Store generated JSON string for preview.
	 *
	 * @param string $json Generated JSON string.
	 */
	private static function store_generated_json( string $json ): void {
		set_transient( 'orke_briefing_generated_json', $json, HOUR_IN_SECONDS );
	}

	/**
	 * Retrieve stored generated JSON.
	 *
	 * @return string Generated JSON or empty string.
	 */
	private static function get_generated_json(): string {
		$json = get_transient( 'orke_briefing_generated_json' );
		return is_string( $json ) ? $json : '';
	}
}
