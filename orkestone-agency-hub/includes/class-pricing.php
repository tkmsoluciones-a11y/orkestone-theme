<?php
/**
 * Pricing Calculator — computes itemized budget from briefing form data.
 *
 * Pure math engine (AD10): does NOT validate form completeness. Calculates
 * total based on the formula:
 *
 *     Total = BASE_PRICE + (PAGE_PRICE × count(pages))
 *           + Σ(section_complexity[type])
 *           + (ITEM_PRICE × total_model_items)
 *           + PREMIUM_SURCHARGE × count(premium_sections)
 *
 * All constants are filterable via `apply_filters('orke_agency_pricing')`,
 * allowing agencies to customize pricing without modifying plugin code.
 *
 * @package OrkestoneAgencyHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure calculator engine for client configuration pricing.
 *
 * Returns an itemized budget breakdown array with component, quantity,
 * unit price, subtotal, and a grand total. Designed to be displayed
 * as an HTML table in the Hub admin UI (REQ-AH10).
 */
class Orkestone_Pricing {

	/**
	 * Default base price charged per configuration.
	 *
	 * @var float
	 */
	const BASE_PRICE = 499.00;

	/**
	 * Default price charged per page.
	 *
	 * @var float
	 */
	const PAGE_PRICE = 99.00;

	/**
	 * Default price charged per content model item.
	 *
	 * @var float
	 */
	const ITEM_PRICE = 10.00;

	/**
	 * Default surcharge for premium section types.
	 *
	 * @var float
	 */
	const PREMIUM_SURCHARGE = 50.00;

	/**
	 * Section types considered "premium" for surcharge calculation.
	 *
	 * @var array
	 */
	const PREMIUM_SECTIONS = array(
		'hero',
		'hero-centered',
		'pricing',
		'cta',
	);

	/**
	 * Raw form data submitted by the agency.
	 *
	 * @var array
	 */
	private $form_data = array();

	/**
	 * Resolved pricing constants after filter application.
	 *
	 * @var array
	 */
	private $pricing = array();

	/**
	 * Constructor.
	 *
	 * @param array $form_data The briefing form data with keys:
	 *                         branding, pages, content_models, navigation, seo.
	 */
	public function __construct( array $form_data = array() ) {
		$this->form_data = $form_data;
		$this->pricing   = $this->get_pricing_constants();
	}

	/**
	 * Calculate the itemized budget from the form data.
	 *
	 * Returns an array with items (component breakdown) and total (sum).
	 * The items array is suitable for rendering as an HTML table with
	 * component name, quantity, unit price, and subtotal (REQ-AH10).
	 *
	 * @return array {
	 *     Budget breakdown.
	 *
	 *     @type array  $items Array of item rows, each with:
	 *                         label, qty, unit_price, subtotal.
	 *     @type float  $total Grand total of all items.
	 * }
	 */
	public function calculate(): array {
		$items = array();
		$pages = isset( $this->form_data['pages'] ) ? $this->form_data['pages'] : array();

		// Base price.
		$items[] = array(
			'label'      => __( 'Base Price', 'orkestone-agency-hub' ),
			'qty'        => 1,
			'unit_price' => $this->pricing['base_price'],
			'subtotal'   => $this->pricing['base_price'],
		);

		// Pages.
		$page_count = count( $pages );
		if ( $page_count > 0 ) {
			$page_total = $page_count * $this->pricing['page_price'];
			$items[]    = array(
				'label'      => __( 'Pages', 'orkestone-agency-hub' ),
				'qty'        => $page_count,
				'unit_price' => $this->pricing['page_price'],
				'subtotal'   => $page_total,
			);
		}

		// Section complexity.
		$section_complexity_total = $this->calculate_section_complexity( $pages );
		if ( $section_complexity_total > 0 ) {
			$section_count = $this->count_total_sections( $pages );
			$items[]       = array(
				'label'      => __( 'Section Complexity', 'orkestone-agency-hub' ),
				'qty'        => $section_count,
				'unit_price' => round( $section_complexity_total / $section_count, 2 ),
				'subtotal'   => $section_complexity_total,
			);
		}

		// Content model items.
		$item_count = $this->count_content_model_items();
		if ( $item_count > 0 ) {
			$content_total = $item_count * $this->pricing['item_price'];
			$items[]       = array(
				'label'      => __( 'Content Items', 'orkestone-agency-hub' ),
				'qty'        => $item_count,
				'unit_price' => $this->pricing['item_price'],
				'subtotal'   => $content_total,
			);
		}

		// Premium section surcharge.
		$premium_count = $this->count_premium_sections( $pages );
		if ( $premium_count > 0 ) {
			$premium_total = $premium_count * $this->pricing['premium_surcharge'];
			$items[]       = array(
				'label'      => __( 'Premium Sections', 'orkestone-agency-hub' ),
				'qty'        => $premium_count,
				'unit_price' => $this->pricing['premium_surcharge'],
				'subtotal'   => $premium_total,
			);
		}

		$total = array_sum( array_column( $items, 'subtotal' ) );

		return array(
			'items' => $items,
			'total' => round( $total, 2 ),
		);
	}

	/**
	 * Calculate the total cost from section complexity.
	 *
	 * Each section type has a complexity weight. The sum of all section
	 * complexity weights across all pages contributes to the total.
	 *
	 * @param array $pages Array of page configurations.
	 * @return float Total section complexity cost.
	 */
	private function calculate_section_complexity( array $pages ): float {
		$total = 0;

		foreach ( $pages as $page ) {
			if ( empty( $page['sections'] ) || ! is_array( $page['sections'] ) ) {
				continue;
			}

			foreach ( $page['sections'] as $section ) {
				$type = '';

				if ( is_string( $section ) ) {
					$type = $section;
				} elseif ( is_array( $section ) && ! empty( $section['type'] ) ) {
					$type = $section['type'];
				}

				if ( ! empty( $type ) && isset( Orkestone_JSON_Builder::SECTION_COMPLEXITY[ $type ] ) ) {
					$total += Orkestone_JSON_Builder::SECTION_COMPLEXITY[ $type ];
				} else {
					// Default complexity for unknown sections.
					$total += 1;
				}
			}
		}

		return $total;
	}

	/**
	 * Count total number of sections across all pages.
	 *
	 * @param array $pages Array of page configurations.
	 * @return int Total section count.
	 */
	private function count_total_sections( array $pages ): int {
		$count = 0;

		foreach ( $pages as $page ) {
			if ( ! empty( $page['sections'] ) && is_array( $page['sections'] ) ) {
				$count += count( $page['sections'] );
			}
		}

		return $count;
	}

	/**
	 * Count the number of premium sections across all pages.
	 *
	 * @param array $pages Array of page configurations.
	 * @return int Premium section count.
	 */
	private function count_premium_sections( array $pages ): int {
		$count = 0;

		foreach ( $pages as $page ) {
			if ( empty( $page['sections'] ) || ! is_array( $page['sections'] ) ) {
				continue;
			}

			foreach ( $page['sections'] as $section ) {
				$type = '';

				if ( is_string( $section ) ) {
					$type = $section;
				} elseif ( is_array( $section ) && ! empty( $section['type'] ) ) {
					$type = $section['type'];
				}

				if ( in_array( $type, self::PREMIUM_SECTIONS, true ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Count total items across all content model types.
	 *
	 * Counts entries in services, team, pricing, faq, and testimonials.
	 *
	 * @return int Total content model item count.
	 */
	private function count_content_model_items(): int {
		$count         = 0;
		$content_models = isset( $this->form_data['content_models'] ) ? $this->form_data['content_models'] : array();

		$model_types = array( 'services', 'team', 'pricing', 'faq', 'testimonials' );
		foreach ( $model_types as $type ) {
			if ( isset( $content_models[ $type ] ) && is_array( $content_models[ $type ] ) ) {
				$count += count( $content_models[ $type ] );
			}
		}

		return $count;
	}

	/**
	 * Get pricing constants, filtered for customization.
	 *
	 * All default constants can be overridden via the
	 * `orke_agency_pricing` filter (REQ-AH9). The filter receives
	 * an associative array with all pricing values.
	 *
	 * @return array Filtered pricing constants.
	 */
	private function get_pricing_constants(): array {
		$defaults = array(
			'base_price'       => self::BASE_PRICE,
			'page_price'       => self::PAGE_PRICE,
			'item_price'       => self::ITEM_PRICE,
			'premium_surcharge' => self::PREMIUM_SURCHARGE,
		);

		/**
		 * Filter the Agency Hub pricing constants.
		 *
		 * Allows agencies to customize all pricing values globally.
		 *
		 * @since 1.0.0
		 *
		 * @param array $defaults Default pricing constants.
		 */
		return apply_filters( 'orke_agency_pricing', $defaults );
	}
}
