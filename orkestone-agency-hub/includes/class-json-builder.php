<?php
/**
 * JSON Builder — maps briefing form data to the Orkestone vertical JSON schema.
 *
 * Transforms the 4-tab briefing form submission into a valid vertical
 * configuration array matching the `default.json` schema that the theme's
 * `vbb_validate_vertical_config()` expects. Handles schema versioning,
 * asset URL resolution, and vertical key generation.
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps form data to a valid Orkestone vertical configuration array.
 *
 * The output of build() is designed to pass vbb_validate_vertical_config()
 * with all 7 required top-level keys: schemaVersion, verticalKey, name,
 * brand, navigation, pages, and contentModels.
 *
 * @see vbb_validate_vertical_config() in the Orkestone theme.
 */
class Orkestone_JSON_Builder {

	/**
	 * Default section complexity costs used by the pricing engine.
	 * Simple sections cost 1 unit, premium sections (hero, pricing, cta) cost 2.
	 */
	const SECTION_COMPLEXITY = array(
		'hero'             => 2,
		'hero-centered'    => 2,
		'pricing'          => 2,
		'cta'              => 2,
		'services-grid'    => 1,
		'about'            => 1,
		'contact-section'  => 1,
		'process'          => 1,
		'testimonials'     => 1,
		'faq-section'      => 1,
		'features'         => 1,
		'gallery'          => 1,
		'stats'            => 1,
		'team-grid'        => 1,
		'logo-cloud'       => 1,
		'content'          => 1,
		'split'            => 1,
		'form'             => 1,
	);

	/**
	 * Build a full vertical configuration array from briefing form data.
	 *
	 * @param array $form_data The submitted form data with keys:
	 *                         branding, pages, content_models, navigation, seo.
	 * @return array Vertical configuration array matching the default.json schema.
	 */
	public function build( array $form_data ): array {
		$branding      = isset( $form_data['branding'] ) ? $form_data['branding'] : array();
		$pages         = isset( $form_data['pages'] ) ? $form_data['pages'] : array();
		$content_models = isset( $form_data['content_models'] ) ? $form_data['content_models'] : array();
		$navigation    = isset( $form_data['navigation'] ) ? $form_data['navigation'] : array();
		$seo           = isset( $form_data['seo'] ) ? $form_data['seo'] : array();

		$site_name = isset( $branding['site_name'] ) ? $branding['site_name'] : '';

		$config = array(
			'schemaVersion' => $this->get_schema_version(),
			'verticalKey'   => $this->generate_vertical_key( $site_name ),
			'name'          => $site_name,
			'brand'         => $this->build_brand( $branding ),
			'navigation'    => $this->build_navigation( $navigation ),
			'pages'         => $this->build_pages( $pages ),
			'contentModels' => $this->build_content_models( $content_models ),
			'graphics'      => $this->build_graphics( $branding ),
			'seoDefaults'   => $this->build_seo_defaults( $seo, $site_name ),
		);

		return $config;
	}

	/**
	 * Validate that a vertical config array has all required top-level keys.
	 *
	 * @param array $config The configuration array to validate.
	 * @return bool True if all required keys are present and non-empty.
	 */
	public function validate( array $config ): bool {
		$required_keys = array(
			'schemaVersion',
			'verticalKey',
			'name',
			'brand',
			'navigation',
			'pages',
			'contentModels',
		);

		foreach ( $required_keys as $key ) {
			if ( ! array_key_exists( $key, $config ) ) {
				return false;
			}

			if ( is_string( $config[ $key ] ) && '' === trim( $config[ $key ] ) ) {
				return false;
			}
		}

		if ( empty( $config['name'] ) ) {
			return false;
		}

		if ( ! is_array( $config['pages'] ) || count( $config['pages'] ) < 1 ) {
			return false;
		}

		return true;
	}

	/**
	 * Encode a configuration array as a formatted JSON string.
	 *
	 * @param array $config The configuration array.
	 * @return string JSON-encoded string with pretty print and unescaped unicode/slashes.
	 */
	public function get_json( array $config ): string {
		return wp_json_encode(
			$config,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	/**
	 * Get the schema version from the theme or default.
	 *
	 * Checks for vbb_get_schema_version() (added in Phase 3) and falls back
	 * to "1.0.0" if the theme function does not yet exist.
	 *
	 * @return string SemVer schema version string.
	 */
	private function get_schema_version(): string {
		if ( function_exists( 'vbb_get_schema_version' ) ) {
			return vbb_get_schema_version();
		}

		return '1.0.0';
	}

	/**
	 * Generate a vertical key from the site name.
	 *
	 * Sanitizes the site name as a WordPress slug and prepends "orke-".
	 * Falls back to a UUID-based key if the site name is empty.
	 *
	 * @param string $site_name The site name from the branding tab.
	 * @return string Unique vertical key.
	 */
	private function generate_vertical_key( string $site_name ): string {
		if ( ! empty( $site_name ) ) {
			return 'orke-' . sanitize_title( $site_name );
		}

		return 'orke-' . wp_generate_uuid4();
	}

	/**
	 * Build the brand section of the vertical config.
	 *
	 * @param array $branding Branding data from the form.
	 * @return array Brand configuration.
	 */
	private function build_brand( array $branding ): array {
		$brand = array(
			'primaryColor'   => isset( $branding['primary_color'] ) ? $branding['primary_color'] : '#1a365d',
			'secondaryColor' => isset( $branding['secondary_color'] ) ? $branding['secondary_color'] : '#e2e8f0',
			'accentColor'    => isset( $branding['accent_color'] ) ? $branding['accent_color'] : '#3b82f6',
			'headingFont'    => isset( $branding['heading_font'] ) ? $branding['heading_font'] : 'Inter',
			'bodyFont'       => isset( $branding['body_font'] ) ? $branding['body_font'] : 'Inter',
		);

		if ( ! empty( $branding['tagline'] ) ) {
			$brand['tagline'] = $branding['tagline'];
		}

		$logo_url = $this->resolve_logo_url( $branding );
		if ( null !== $logo_url ) {
			$brand['logo'] = $logo_url;
		}

		return $brand;
	}

	/**
	 * Resolve the logo URL from branding data.
	 *
	 * Checks for a logo_id (attachment ID) first, then falls back to
	 * a direct logo_url field. Uses the filterable asset base URL.
	 *
	 * @param array $branding Branding data.
	 * @return string|null Absolute URL to the logo, or null.
	 */
	private function resolve_logo_url( array $branding ): ?string {
		if ( ! empty( $branding['logo_id'] ) ) {
			if ( class_exists( 'Orkestone_Asset_Library' ) ) {
				$url = Orkestone_Asset_Library::get_url_by_role( 'logo' );

				if ( null !== $url ) {
					return $url;
				}
			}

			$url = wp_get_attachment_url( intval( $branding['logo_id'] ) );

			if ( false !== $url ) {
				return $url;
			}
		}

		if ( ! empty( $branding['logo_url'] ) ) {
			return $branding['logo_url'];
		}

		return null;
	}

	/**
	 * Build the navigation section from form data.
	 *
	 * @param array $navigation Navigation menu items from the form.
	 * @return array Navigation configuration.
	 */
	private function build_navigation( array $navigation ): array {
		$items = array();

		foreach ( $navigation as $item ) {
			if ( empty( $item['label'] ) ) {
				continue;
			}

			$nav_item = array(
				'label' => sanitize_text_field( $item['label'] ),
				'url'   => ! empty( $item['url'] ) ? sanitize_url( $item['url'] ) : '/',
			);

			// Preserve url_slug so the theme importer can resolve page IDs downstream.
			if ( ! empty( $item['url_slug'] ) ) {
				$nav_item['url_slug'] = sanitize_text_field( $item['url_slug'] );
			}

			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				$nav_item['children'] = $this->build_navigation( $item['children'] );
			}

			$items[] = $nav_item;
		}

		return $items;
	}

	/**
	 * Build the pages array from form data.
	 *
	 * Each page can have multiple sections with type-specific configuration.
	 *
	 * @param array $pages Page data from the form.
	 * @return array Pages configuration.
	 */
	private function build_pages( array $pages ): array {
		$result = array();

		foreach ( $pages as $page ) {
			if ( empty( $page['title'] ) ) {
				continue;
			}

			$page_config = array(
				'title'    => sanitize_text_field( $page['title'] ),
				'slug'     => ! empty( $page['slug'] ) ? sanitize_title( $page['slug'] ) : sanitize_title( $page['title'] ),
				'sections' => $this->build_sections( $page ),
			);

			$result[] = $page_config;
		}

		return $result;
	}

	/**
	 * Build section configurations for a page.
	 *
	 * @param array $page Page data.
	 * @return array Section configurations.
	 */
	private function build_sections( array $page ): array {
		$sections = array();

		if ( ! empty( $page['sections'] ) && is_array( $page['sections'] ) ) {
			foreach ( $page['sections'] as $section ) {
				if ( is_string( $section ) ) {
					$sections[] = array(
						'type' => sanitize_title( $section ),
					);
				} elseif ( is_array( $section ) && ! empty( $section['type'] ) ) {
					$section_config = array(
						'type' => sanitize_title( $section['type'] ),
					);

					if ( ! empty( $section['heading'] ) ) {
						$section_config['heading'] = sanitize_text_field( $section['heading'] );
					}

					if ( ! empty( $section['content'] ) ) {
						$section_config['content'] = wp_kses_post( $section['content'] );
					}

					if ( ! empty( $section['background'] ) ) {
						$section_config['background'] = sanitize_hex_color( $section['background'] );
					}

					$sections[] = $section_config;
				}
			}
		}

		return $sections;
	}

	/**
	 * Build content models from form data.
	 *
	 * Handles services, team members, pricing plans, FAQ items, and testimonials.
	 *
	 * @param array $content_models Content model data from the form.
	 * @return array Content models configuration.
	 */
	private function build_content_models( array $content_models ): array {
		$models = array();

		if ( ! empty( $content_models['services'] ) && is_array( $content_models['services'] ) ) {
			$models['services'] = array();
			foreach ( $content_models['services'] as $service ) {
				if ( ! empty( $service['title'] ) ) {
					$models['services'][] = array(
						'title'       => sanitize_text_field( $service['title'] ),
						'description' => isset( $service['description'] ) ? wp_kses_post( $service['description'] ) : '',
						'icon'        => isset( $service['icon'] ) ? sanitize_text_field( $service['icon'] ) : '',
					);
				}
			}
		}

		if ( ! empty( $content_models['team'] ) && is_array( $content_models['team'] ) ) {
			$models['team'] = array();
			foreach ( $content_models['team'] as $member ) {
				if ( ! empty( $member['name'] ) ) {
					$models['team'][] = array(
						'name'        => sanitize_text_field( $member['name'] ),
						'role'        => isset( $member['role'] ) ? sanitize_text_field( $member['role'] ) : '',
						'description' => isset( $member['description'] ) ? wp_kses_post( $member['description'] ) : '',
						'photo'       => isset( $member['photo'] ) ? sanitize_url( $member['photo'] ) : '',
					);
				}
			}
		}

		if ( ! empty( $content_models['pricing'] ) && is_array( $content_models['pricing'] ) ) {
			$models['pricing'] = array();
			foreach ( $content_models['pricing'] as $plan ) {
				if ( ! empty( $plan['plan'] ) ) {
					$models['pricing'][] = array(
						'plan'        => sanitize_text_field( $plan['plan'] ),
						'price'       => isset( $plan['price'] ) ? floatval( $plan['price'] ) : 0,
						'currency'    => isset( $plan['currency'] ) ? sanitize_text_field( $plan['currency'] ) : 'USD',
						'description' => isset( $plan['description'] ) ? wp_kses_post( $plan['description'] ) : '',
						'features'    => isset( $plan['features'] ) && is_array( $plan['features'] ) ? array_map( 'sanitize_text_field', $plan['features'] ) : array(),
					);
				}
			}
		}

		if ( ! empty( $content_models['faq'] ) && is_array( $content_models['faq'] ) ) {
			$models['faq'] = array();
			foreach ( $content_models['faq'] as $item ) {
				if ( ! empty( $item['question'] ) ) {
					$models['faq'][] = array(
						'question' => sanitize_text_field( $item['question'] ),
						'answer'   => isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '',
					);
				}
			}
		}

		if ( ! empty( $content_models['testimonials'] ) && is_array( $content_models['testimonials'] ) ) {
			$models['testimonials'] = array();
			foreach ( $content_models['testimonials'] as $testimonial ) {
				if ( ! empty( $testimonial['quote'] ) ) {
					$models['testimonials'][] = array(
						'quote'  => wp_kses_post( $testimonial['quote'] ),
						'author' => isset( $testimonial['author'] ) ? sanitize_text_field( $testimonial['author'] ) : '',
						'role'   => isset( $testimonial['role'] ) ? sanitize_text_field( $testimonial['role'] ) : '',
					);
				}
			}
		}

		return $models;
	}

	/**
	 * Build graphics configuration from branding data.
	 *
	 * @param array $branding Branding data.
	 * @return array Graphics configuration.
	 */
	private function build_graphics( array $branding ): array {
		$graphics = array();

		if ( ! empty( $branding['logo_id'] ) || ! empty( $branding['logo_url'] ) ) {
			$favicon_id = isset( $branding['favicon_id'] ) ? intval( $branding['favicon_id'] ) : 0;
			$favicon_url = isset( $branding['favicon_url'] ) ? $branding['favicon_url'] : '';

			if ( $favicon_id > 0 ) {
				$url = wp_get_attachment_url( $favicon_id );
				if ( false !== $url ) {
					$graphics['favicon'] = $url;
				}
			} elseif ( ! empty( $favicon_url ) ) {
				$graphics['favicon'] = $favicon_url;
			}

			if ( empty( $graphics['favicon'] ) ) {
				$graphics['favicon'] = '';
			}
		}

		return $graphics;
	}

	/**
	 * Build SEO defaults from form data.
	 *
	 * @param array  $seo       SEO data from the form.
	 * @param string $site_name The site name for title pattern fallback.
	 * @return array SEO defaults configuration.
	 */
	private function build_seo_defaults( array $seo, string $site_name ): array {
		$defaults = array(
			'titlePattern' => ! empty( $seo['title_pattern'] ) ? sanitize_text_field( $seo['title_pattern'] ) : ( ! empty( $site_name ) ? '%page% | ' . $site_name : '%page%' ),
		);

		if ( ! empty( $seo['meta_description'] ) ) {
			$defaults['metaDescription'] = sanitize_textarea_field( $seo['meta_description'] );
		}

		if ( ! empty( $seo['og_image_id'] ) ) {
			$url = wp_get_attachment_url( intval( $seo['og_image_id'] ) );
			if ( false !== $url ) {
				$defaults['ogImage'] = $url;
			}
		}

		return $defaults;
	}
}
