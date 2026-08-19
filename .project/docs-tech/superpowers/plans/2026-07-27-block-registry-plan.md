# Block Registry Refactor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a central block registry (`inc/block-registry.php`) as the single source of truth for all 19 block types, unifying PHP and JS data structures, adding effects and media library support.

**Architecture:** Registry-first: all block field definitions live in PHP, exposed to JS via REST endpoint. Baker functions and admin settings derive from registry. Effects via IntersectionObserver + CSS transitions. Generic JS renderer iterates registry fields instead of per-block HTML templates.

**Tech Stack:** WordPress (PHP 8+), vanilla JS, WP REST API, wp.media, IntersectionObserver

## Global Constraints

- Zero new external dependencies (no GSAP, no AOS, no React)
- Must not break existing profiles (backward compat with old key names)
- All PHP files loaded via `functions.php` require_once pattern
- Registry loaded BEFORE block-baker.php in `functions.php` execution order
- CSS class naming: `vbb-effect-{name}` prefix
- JS data attribute naming: `data-path="blocks.{key}.{field}"`
- Baker functions receive single `$data` array parameter
- All 19 blocks must have same structure: `hero`, `heroCentered`, `servicesGrid`, `benefits`, `process`, `testimonials`, `faq`, `contact`, `ctaFinal`, `logoCloud`, `pricing`, `team`, `stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`, `divider`

---

### Task 1: Create `inc/block-registry.php` with `vbb_get_block_registry()`

**Files:**
- Create: `orkestone-theme/inc/block-registry.php`
- Add to: `orkestone-theme/functions.php` — insert `'inc/block-registry.php'` BEFORE `'inc/block-baker.php'` in the `$vertical_block_base_files` array

**Interfaces:**
- Produces: `vbb_get_block_registry(): array` — full 19-block registry
- Produces: `vbb_get_block_def(string $key): ?array` — single block definition
- Produces: `vbb_get_baker_map(): array` — maps block key → baker function name
- Produces: `vbb_get_block_defaults(): array` — default values per block
- Produces: `vbb_sanitize_block_data(string $key, array $data): array` — validates data against registry schema

- [ ] **Step 1: Create the registry file with block definitions and helper functions**

```php
<?php
/**
 * Block Registry — canonical definitions for all 19 section block types.
 *
 * Single source of truth for field structure, baker maps, defaults, and
 * sanitization. Both PHP bakers and JS admin consume this.
 *
 * @package VerticalBlockBase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the complete block registry.
 *
 * @return array<string, array> Keyed by block key.
 */
function vbb_get_block_registry() {
	$blocks = array();

	// -- Standard repeatable blocks (item-based) --

	$blocks['servicesGrid'] = array(
		'label'      => 'Services Grid',
		'icon'       => 'dashicons-grid-view',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Services',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'ctaText', 'label' => 'CTA Text', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'ctaUrl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '' ),
				),
			),
		),
	);

	$blocks['benefits'] = array(
		'label'      => 'Benefits',
		'icon'       => 'dashicons-yes-alt',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Benefits',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['process'] = array(
		'label'      => 'Process',
		'icon'       => 'dashicons-editor-ol',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Steps',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'number', 'label' => 'Step Number', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
				),
			),
		),
	);

	$blocks['testimonials'] = array(
		'label'      => 'Testimonials',
		'icon'       => 'dashicons-testimonial',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Testimonials',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'author', 'label' => 'Author Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'role', 'label' => 'Role / Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'avatar', 'label' => 'Avatar Image', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'rating', 'label' => 'Rating (1-5)', 'type' => 'number', 'default' => 5 ),
				),
			),
		),
	);

	$blocks['faq'] = array(
		'label'      => 'FAQ',
		'icon'       => 'dashicons-editor-help',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'FAQ Items',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'question', 'label' => 'Question', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['pricing'] = array(
		'label'      => 'Pricing',
		'icon'       => 'dashicons-tag',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Pricing Plans',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'name', 'label' => 'Plan Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'price', 'label' => 'Price', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'period', 'label' => 'Period', 'type' => 'text', 'default' => '/month' ),
					array( 'key' => 'features', 'label' => 'Features (one per line)', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'ctaText', 'label' => 'CTA Text', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'ctaUrl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'featured', 'label' => 'Featured Plan', 'type' => 'checkbox', 'default' => false ),
				),
			),
		),
	);

	$blocks['team'] = array(
		'label'      => 'Team',
		'icon'       => 'dashicons-groups',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Team Members',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'name', 'label' => 'Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'role', 'label' => 'Role', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'bio', 'label' => 'Bio', 'type' => 'textarea', 'default' => '' ),
					array( 'key' => 'image', 'label' => 'Photo', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'linkedin', 'label' => 'LinkedIn URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'twitter', 'label' => 'Twitter/X URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'github', 'label' => 'GitHub URL', 'type' => 'url', 'default' => '' ),
				),
			),
		),
	);

	$blocks['logoCloud'] = array(
		'label'      => 'Logo Cloud',
		'icon'       => 'dashicons-building',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Logos',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'name', 'label' => 'Company Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'logo', 'label' => 'Logo Image', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'url', 'label' => 'Link URL', 'type' => 'url', 'default' => '' ),
				),
			),
		),
	);

	$blocks['stats'] = array(
		'label'      => 'Stats',
		'icon'       => 'dashicons-chart-bar',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom', 'flip' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Stats',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'value', 'label' => 'Value', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'icon', 'label' => 'Icon (dashicons slug)', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['gallery'] = array(
		'label'      => 'Gallery',
		'icon'       => 'dashicons-format-gallery',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'items',
				'label'       => 'Gallery Items',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'image', 'label' => 'Image', 'type' => 'image', 'default' => '' ),
					array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'category', 'label' => 'Category', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'url', 'label' => 'Link URL', 'type' => 'url', 'default' => '' ),
					array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	// -- Simple blocks (no repeatable items) --

	$blocks['hero'] = array(
		'label'      => 'Hero',
		'icon'       => 'dashicons-welcome-widgets-menus',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => '' ),
			array( 'key' => 'eyebrow', 'label' => 'Eyebrow Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryCta', 'label' => 'Primary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryUrl', 'label' => 'Primary CTA URL', 'type' => 'url', 'default' => '#' ),
			array( 'key' => 'secondaryCta', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'secondaryUrl', 'label' => 'Secondary CTA URL', 'type' => 'url', 'default' => '' ),
			array( 'key' => 'image', 'label' => 'Background Image', 'type' => 'image', 'default' => '' ),
		),
	);

	$blocks['heroCentered'] = array(
		'label'      => 'Hero Centered',
		'icon'       => 'dashicons-align-center',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => '' ),
			array( 'key' => 'eyebrow', 'label' => 'Eyebrow Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryCta', 'label' => 'Primary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'primaryUrl', 'label' => 'Primary CTA URL', 'type' => 'url', 'default' => '#' ),
			array( 'key' => 'secondaryCta', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'secondaryUrl', 'label' => 'Secondary CTA URL', 'type' => 'url', 'default' => '' ),
		),
	);

	$blocks['ctaFinal'] = array(
		'label'      => 'CTA Final',
		'icon'       => 'dashicons-megaphone',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'text', 'label' => 'Main Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'buttonText', 'label' => 'Button Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'buttonUrl', 'label' => 'Button URL', 'type' => 'url', 'default' => '#' ),
			array( 'key' => 'secondaryCta', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'secondaryUrl', 'label' => 'Secondary CTA URL', 'type' => 'url', 'default' => '' ),
		),
	);

	$blocks['divider'] = array(
		'label'      => 'Divider',
		'icon'       => 'dashicons-minus',
		'styles'     => array( 'A' ),
		'effects'    => array( 'none' ),
		'hasColors'  => false,
		'fields'     => array(
			array( 'key' => 'type', 'label' => 'Type', 'type' => 'select', 'default' => 'line', 'options' => array( 'line' => 'Line', 'space' => 'Space', 'wave' => 'Wave', 'dots' => 'Dots' ) ),
			array( 'key' => 'color', 'label' => 'Color', 'type' => 'color', 'default' => '' ),
			array( 'key' => 'thickness', 'label' => 'Thickness (px)', 'type' => 'number', 'default' => 2 ),
			array( 'key' => 'margin', 'label' => 'Vertical Margin (px)', 'type' => 'number', 'default' => 40 ),
		),
	);

	// -- Custom blocks (special rendering, no generic renderer) --

	$blocks['contact'] = array(
		'label'      => 'Contact',
		'icon'       => 'dashicons-email',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'email', 'label' => 'Email', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'address', 'label' => 'Address', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'formEndpoint', 'label' => 'Form Endpoint', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'recaptcha', 'label' => 'reCAPTCHA', 'type' => 'select', 'default' => 'none', 'options' => array( 'none' => 'Sin reCAPTCHA', 'v2' => 'reCAPTCHA v2', 'v3' => 'reCAPTCHA v3' ) ),
			array( 'key' => 'recaptchaKey', 'label' => 'reCAPTCHA Site Key', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'recaptchaSecret', 'label' => 'reCAPTCHA Secret Key', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'formFields',
				'label'       => 'Form Fields',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'type', 'label' => 'Field Type', 'type' => 'select', 'default' => 'text', 'options' => array( 'text' => 'Text', 'email' => 'Email', 'tel' => 'Tel', 'url' => 'URL', 'number' => 'Number', 'textarea' => 'Textarea', 'select' => 'Select', 'checkbox' => 'Checkbox' ) ),
					array( 'key' => 'name', 'label' => 'Field Name', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'label', 'label' => 'Field Label', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'placeholder', 'label' => 'Placeholder', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'required', 'label' => 'Required', 'type' => 'checkbox', 'default' => false ),
					array( 'key' => 'options', 'label' => 'Options (JSON for select)', 'type' => 'textarea', 'default' => '' ),
				),
			),
		),
	);

	$blocks['video'] = array(
		'label'      => 'Video',
		'icon'       => 'dashicons-video-alt3',
		'styles'     => array( 'A', 'B', 'C' ),
		'effects'    => array( 'none', 'fade', 'slide-up', 'zoom' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'video_url', 'label' => 'Video URL', 'type' => 'url', 'default' => '' ),
			array( 'key' => 'video_type', 'label' => 'Video Type', 'type' => 'select', 'default' => 'youtube', 'options' => array( 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'mp4' => 'MP4 (self-hosted)' ) ),
			array( 'key' => 'poster', 'label' => 'Poster Image', 'type' => 'image', 'default' => '' ),
			array( 'key' => 'cta_text', 'label' => 'CTA Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'url', 'default' => '' ),
		),
	);

	$blocks['newsletter'] = array(
		'label'      => 'Newsletter',
		'icon'       => 'dashicons-email-alt',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => '' ),
			array( 'key' => 'placeholder', 'label' => 'Email Placeholder', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'button_text', 'label' => 'Button Text', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'provider', 'label' => 'Provider', 'type' => 'select', 'default' => 'custom', 'options' => array( 'custom' => 'Custom Endpoint', 'mailchimp' => 'Mailchimp', 'convertkit' => 'ConvertKit' ) ),
			array( 'key' => 'listId', 'label' => 'List ID', 'type' => 'text', 'default' => '' ),
		),
	);

	$blocks['map'] = array(
		'label'      => 'Map',
		'icon'       => 'dashicons-location',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none', 'fade', 'slide-up' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'address', 'label' => 'Address', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'lat', 'label' => 'Latitude', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'lng', 'label' => 'Longitude', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'zoom', 'label' => 'Zoom Level', 'type' => 'number', 'default' => 15 ),
			array( 'key' => 'map_type', 'label' => 'Map Type', 'type' => 'select', 'default' => 'roadmap', 'options' => array( 'roadmap' => 'Roadmap', 'satellite' => 'Satellite', 'hybrid' => 'Hybrid', 'terrain' => 'Terrain' ) ),
			array( 'key' => 'marker_title', 'label' => 'Marker Title', 'type' => 'text', 'default' => '' ),
		),
	);

	$blocks['comparison'] = array(
		'label'      => 'Comparison Table',
		'icon'       => 'dashicons-table-col-after',
		'styles'     => array( 'A', 'B' ),
		'effects'    => array( 'none' ),
		'hasColors'  => true,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array(
				'key'         => 'rows',
				'label'       => 'Comparison Rows',
				'type'        => 'repeatable',
				'default'     => array(),
				'item_fields' => array(
					array( 'key' => 'feature', 'label' => 'Feature', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'plan1', 'label' => 'Plan 1', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'plan2', 'label' => 'Plan 2', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'plan3', 'label' => 'Plan 3', 'type' => 'text', 'default' => '' ),
					array( 'key' => 'highlight', 'label' => 'Highlight Row', 'type' => 'checkbox', 'default' => false ),
				),
			),
		),
	);

	$blocks['blog'] = array(
		'label'      => 'Blog Posts',
		'icon'       => 'dashicons-admin-post',
		'styles'     => array( 'A' ),
		'effects'    => array( 'none' ),
		'hasColors'  => false,
		'fields'     => array(
			array( 'key' => 'heading', 'label' => 'Section Heading', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'category', 'label' => 'Category Slug', 'type' => 'text', 'default' => '' ),
			array( 'key' => 'limit', 'label' => 'Post Limit', 'type' => 'number', 'default' => 6 ),
			array( 'key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'default' => 'grid', 'options' => array( 'grid' => 'Grid', 'list' => 'List', 'masonry' => 'Masonry' ) ),
			array( 'key' => 'showExcerpt', 'label' => 'Show Excerpt', 'type' => 'checkbox', 'default' => true ),
			array( 'key' => 'showDate', 'label' => 'Show Date', 'type' => 'checkbox', 'default' => true ),
			array( 'key' => 'showAuthor', 'label' => 'Show Author', 'type' => 'checkbox', 'default' => true ),
		),
	);

	return $blocks;
}

/**
 * Get a single block definition by key.
 *
 * @param string $key Block key.
 * @return array|null Block definition or null if not found.
 */
function vbb_get_block_def( $key ) {
	$registry = vbb_get_block_registry();
	return isset( $registry[ $key ] ) ? $registry[ $key ] : null;
}

/**
 * Generate the baker function map from the registry.
 * Replaces the hardcoded map in block-baker.php.
 *
 * @return array<string, string> Block key => baker function name.
 */
function vbb_get_baker_map() {
	$registry = vbb_get_block_registry();
	$map      = array();

	// Key mapping: registry key → baker key (kebab-case for baker dispatcher).
	$key_to_baker_key = array(
		'hero'            => 'hero',
		'heroCentered'    => 'hero-centered',
		'servicesGrid'    => 'services-grid',
		'benefits'        => 'benefits',
		'process'         => 'process',
		'testimonials'    => 'testimonials',
		'faq'             => 'faq',
		'contact'         => 'contact-section',
		'ctaFinal'        => 'cta-final',
		'logoCloud'       => 'logoCloud',
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

	// Baker function names by block key.
	$baker_functions = array(
		'hero'          => 'vbb_bake_hero',
		'heroCentered'  => 'vbb_bake_hero_centered',
		'servicesGrid'  => 'vbb_bake_services_grid',
		'benefits'      => 'vbb_bake_benefits',
		'process'       => 'vbb_bake_process',
		'testimonials'  => 'vbb_bake_testimonials',
		'faq'           => 'vbb_bake_faq',
		'contact'       => 'vbb_bake_contact_section',
		'ctaFinal'      => 'vbb_bake_cta_final',
		'logoCloud'     => 'vbb_bake_logo_cloud',
		'pricing'       => 'vbb_bake_pricing_tables',
		'team'          => 'vbb_bake_team_section',
		'stats'         => 'vbb_bake_stats',
		'gallery'       => 'vbb_bake_gallery',
		'video'         => 'vbb_bake_video',
		'newsletter'    => 'vbb_bake_newsletter',
		'map'           => 'vbb_bake_map',
		'comparison'    => 'vbb_bake_comparison',
		'blog'          => 'vbb_bake_blog',
		'divider'       => 'vbb_bake_divider',
	);

	foreach ( $registry as $key => $def ) {
		if ( isset( $key_to_baker_key[ $key ] ) && isset( $baker_functions[ $key ] ) ) {
			$map[ $key_to_baker_key[ $key ] ] = $baker_functions[ $key ];
		}
	}

	return $map;
}

/**
 * Get default values for all blocks from the registry.
 *
 * @return array<string, array> Block key => default data array.
 */
function vbb_get_block_defaults() {
	$registry = vbb_get_block_registry();
	$defaults = array();

	foreach ( $registry as $key => $def ) {
		$block_defaults = array(
			'enabled' => true,
			'style'   => $def['styles'][0] ?? 'A',
			'colors'  => array(),
			'effect'  => 'none',
		);

		foreach ( $def['fields'] as $field ) {
			$block_defaults[ $field['key'] ] = $field['default'];
		}

		$defaults[ $key ] = $block_defaults;
	}

	return $defaults;
}

/**
 * Sanitize block data against the registry schema.
 *
 * @param string $key  Block key.
 * @param array  $data Raw block data.
 * @return array Sanitized block data.
 */
function vbb_sanitize_block_data( $key, $data ) {
	$def = vbb_get_block_def( $key );
	if ( ! $def ) {
		return $data;
	}

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$sanitized = array();

	foreach ( $def['fields'] as $field ) {
		$fkey    = $field['key'];
		$ftype   = $field['type'];
		$default = $field['default'];

		$value = isset( $data[ $fkey ] ) ? $data[ $fkey ] : $default;

		switch ( $ftype ) {
			case 'text':
			case 'select':
				$sanitized[ $fkey ] = sanitize_text_field( (string) $value );
				break;
			case 'textarea':
				$sanitized[ $fkey ] = sanitize_textarea_field( (string) $value );
				break;
			case 'number':
				$sanitized[ $fkey ] = intval( $value );
				break;
			case 'url':
				$sanitized[ $fkey ] = esc_url_raw( (string) $value );
				break;
			case 'color':
				$sanitized[ $fkey ] = sanitize_hex_color( (string) $value ) ?: '';
				break;
			case 'checkbox':
				$sanitized[ $fkey ] = ! empty( $value );
				break;
			case 'image':
				if ( is_array( $value ) ) {
					$sanitized[ $fkey ] = array(
						'id'  => isset( $value['id'] ) ? intval( $value['id'] ) : 0,
						'url' => isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '',
					);
				} else {
					$sanitized[ $fkey ] = esc_url_raw( (string) $value );
				}
				break;
			case 'repeatable':
				if ( is_array( $value ) && isset( $field['item_fields'] ) ) {
					$sanitized_items = array();
					foreach ( $value as $item ) {
						if ( ! is_array( $item ) ) {
							continue;
						}
						$sanitized_item = array();
						foreach ( $field['item_fields'] as $ifield ) {
							$ivalue = isset( $item[ $ifield['key'] ] ) ? $item[ $ifield['key'] ] : $ifield['default'];
							switch ( $ifield['type'] ) {
								case 'text':
								case 'select':
									$sanitized_item[ $ifield['key'] ] = sanitize_text_field( (string) $ivalue );
									break;
								case 'textarea':
									$sanitized_item[ $ifield['key'] ] = sanitize_textarea_field( (string) $ivalue );
									break;
								case 'number':
									$sanitized_item[ $ifield['key'] ] = intval( $ivalue );
									break;
								case 'url':
									$sanitized_item[ $ifield['key'] ] = esc_url_raw( (string) $ivalue );
									break;
								case 'checkbox':
									$sanitized_item[ $ifield['key'] ] = ! empty( $ivalue );
									break;
								case 'image':
									$sanitized_item[ $ifield['key'] ] = esc_url_raw( (string) $ivalue );
									break;
								default:
									$sanitized_item[ $ifield['key'] ] = sanitize_text_field( (string) $ivalue );
							}
						}
						$sanitized_items[] = $sanitized_item;
					}
					$sanitized[ $fkey ] = $sanitized_items;
				} else {
					$sanitized[ $fkey ] = array();
				}
				break;
			default:
				$sanitized[ $fkey ] = sanitize_text_field( (string) $value );
		}
	}

	// Preserve non-field keys (enabled, style, colors, effect).
	foreach ( array( 'enabled', 'style', 'colors', 'effect' ) as $meta_key ) {
		if ( isset( $data[ $meta_key ] ) ) {
			$sanitized[ $meta_key ] = $data[ $meta_key ];
		}
	}

	// Ensure style is valid.
	if ( isset( $sanitized['style'] ) && ! in_array( $sanitized['style'], $def['styles'], true ) ) {
		$sanitized['style'] = $def['styles'][0];
	}

	return $sanitized;
}
```

- [ ] **Step 2: Update `functions.php` load order**

Edit `functions.php` to insert `'inc/block-registry.php'` BEFORE `'inc/block-baker.php'`:

```php
$vertical_block_base_files = array(
	'inc/helpers.php',
	'inc/vertical-validator.php',
	'inc/vertical-storage.php',
	'inc/vertical-loader.php',
	'inc/content-model.php',
	'inc/pattern-registry.php',
	'inc/block-registry.php',  // ← ADDED before block-baker
	'inc/block-baker.php',
	'inc/reset-orchestrator.php',
	'inc/page-blueprint.php',
	'inc/vertical-importer.php',
	'inc/setup.php',
	'inc/enqueue.php',
	'inc/admin-verticals.php',
);
```

- [ ] **Step 3: Quick verify no syntax errors**

Run: `php -l inc/block-registry.php`

---

### Task 2: Add missing baker functions for the 7 unregistered blocks

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` — add 7 baker functions at end of file

**Interfaces:**
- Consumes: Registry `vbb_get_baker_map()` now includes keys: `stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`, `divider`
- Produces: Baker functions that render each block type to Gutenberg HTML

- [ ] **Step 1: Add `vbb_bake_comparison()` baker**

Add before the existing `vbb_bake_page_content()` function (around line 1528):

```php
/**
 * Baker for comparison table.
 *
 * @param array $data Block data.
 * @return string
 */
function vbb_bake_comparison( $data ) {
	$heading = isset( $data['heading'] ) ? esc_html( $data['heading'] ) : '';
	$rows    = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();

	$html  = '<!-- wp:group {"className":"vbb-section vbb-comparison"} -->';
	$html .= '<div class="vbb-section vbb-comparison">';

	if ( $heading ) {
		$html .= '<!-- wp:heading {"className":"vbb-section-title"} -->';
		$html .= '<h2 class="vbb-section-title">' . $heading . '</h2>';
		$html .= '<!-- /wp:heading -->';
	}

	if ( ! empty( $rows ) ) {
		$html .= '<!-- wp:table -->';
		$html .= '<table class="vbb-comparison-table"><tbody>';
		foreach ( $rows as $row ) {
			$highlight = ! empty( $row['highlight'] ) ? ' class="vbb-comparison-highlight"' : '';
			$html     .= '<tr' . $highlight . '>';
			$html     .= '<td>' . esc_html( $row['feature'] ?? '' ) . '</td>';
			$html     .= '<td>' . esc_html( $row['plan1'] ?? '' ) . '</td>';
			$html     .= '<td>' . esc_html( $row['plan2'] ?? '' ) . '</td>';
			$html     .= '<td>' . esc_html( $row['plan3'] ?? '' ) . '</td>';
			$html     .= '</tr>';
		}
		$html .= '</tbody></table>';
		$html .= '<!-- /wp:table -->';
	}

	$html .= '</div>';
	$html .= '<!-- /wp:group -->';

	return $html;
}
```

- [ ] **Step 2: Add `vbb_bake_blog()` baker**

```php
/**
 * Baker for blog posts section.
 * Renders recent posts dynamically from WP_Query.
 *
 * @param array $data Block data.
 * @return string
 */
function vbb_bake_blog( $data ) {
	$heading  = isset( $data['heading'] ) ? esc_html( $data['heading'] ) : '';
	$category = isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : '';
	$limit    = isset( $data['limit'] ) ? intval( $data['limit'] ) : 6;
	$layout   = isset( $data['layout'] ) ? sanitize_text_field( $data['layout'] ) : 'grid';

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		'category_name'  => $category,
	);

	$query = new WP_Query( $args );

	$html  = '<!-- wp:group {"className":"vbb-section vbb-blog"} -->';
	$html .= '<div class="vbb-section vbb-blog">';

	if ( $heading ) {
		$html .= '<!-- wp:heading {"className":"vbb-section-title"} -->';
		$html .= '<h2 class="vbb-section-title">' . $heading . '</h2>';
		$html .= '<!-- /wp:heading -->';
	}

	if ( $query->have_posts() ) {
		$html .= '<!-- wp:columns {"className":"vbb-blog-grid vbb-blog-layout-' . esc_attr( $layout ) . '"} -->';
		$html .= '<div class="wp-block-columns vbb-blog-grid vbb-blog-layout-' . esc_attr( $layout ) . '">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_title  = get_the_title();
			$post_link   = get_permalink();
			$excerpt     = get_the_excerpt();
			$post_date   = get_the_date();
			$author_name = get_the_author();
			$thumb       = get_the_post_thumbnail_url( get_the_ID(), 'medium' );

			$html .= '<!-- wp:column -->';
			$html .= '<div class="wp-block-column vbb-blog-card">';

			if ( $thumb ) {
				$html .= '<div class="vbb-blog-card-thumb"><img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $post_title ) . '" /></div>';
			}

			$html .= '<h3 class="vbb-blog-card-title"><a href="' . esc_url( $post_link ) . '">' . esc_html( $post_title ) . '</a></h3>';

			if ( ! empty( $data['showDate'] ) ) {
				$html .= '<span class="vbb-blog-card-date">' . esc_html( $post_date ) . '</span>';
			}
			if ( ! empty( $data['showAuthor'] ) ) {
				$html .= '<span class="vbb-blog-card-author">' . esc_html( $author_name ) . '</span>';
			}
			if ( ! empty( $data['showExcerpt'] ) && $excerpt ) {
				$html .= '<p class="vbb-blog-card-excerpt">' . esc_html( $excerpt ) . '</p>';
			}

			$html .= '</div>';
			$html .= '<!-- /wp:column -->';
		}

		$html .= '</div>';
		$html .= '<!-- /wp:columns -->';
		wp_reset_postdata();
	}

	$html .= '</div>';
	$html .= '<!-- /wp:group -->';

	return $html;
}
```

- [ ] **Step 3: Add `vbb_bake_divider()` baker**

```php
/**
 * Baker for divider/separator.
 *
 * @param array $data Block data.
 * @return string
 */
function vbb_bake_divider( $data ) {
	$type      = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'line';
	$color     = isset( $data['color'] ) ? sanitize_hex_color( $data['color'] ) : '';
	$thickness = isset( $data['thickness'] ) ? intval( $data['thickness'] ) : 2;
	$margin    = isset( $data['margin'] ) ? intval( $data['margin'] ) : 40;

	$style = '';
	if ( $color ) {
		$style .= ' border-color: ' . $color . ';';
	}
	if ( $thickness && 'space' !== $type ) {
		$style .= ' border-width: ' . $thickness . 'px;';
	}
	if ( $margin ) {
		$style .= ' margin-top: ' . $margin . 'px; margin-bottom: ' . $margin . 'px;';
	}

	$className = 'vbb-section vbb-divider vbb-divider-type-' . $type;

	$html  = '<!-- wp:separator {"className":"' . $className . '"} -->';
	$html .= '<hr class="wp-block-separator ' . $className . '" style="' . $style . '" />';
	$html .= '<!-- /wp:separator -->';

	return $html;
}
```

- [ ] **Step 4: Verify all 20 block keys work in baker map**

Run: `php -r "require 'inc/block-registry.php'; print_r(array_keys(vbb_get_baker_map()));"`

Expected output includes: `stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`, `divider` alongside existing 12 keys.

---

### Task 3: Update `vbb_pro_default_settings()` and `vbb_pro_sanitize_settings()` from registry

**Files:**
- Modify: `orkestone-theme/inc/pro-settings.php`

**Interfaces:**
- Consumes: `vbb_get_block_registry()`, `vbb_get_block_defaults()`, `vbb_sanitize_block_data()`
- Produces: Refactored `vbb_pro_default_settings()` that uses registry for block defaults
- Produces: Refactored `vbb_pro_sanitize_settings()` that uses `vbb_sanitize_block_data()`

- [ ] **Step 1: Refactor `vbb_pro_default_settings()` block defaults section**

Replace the block defaults loop (lines 245-266) with:

```php
// Generate block defaults from registry.
$registry = function_exists( 'vbb_get_block_registry' ) ? vbb_get_block_registry() : array();
$block_defaults = function_exists( 'vbb_get_block_defaults' ) ? vbb_get_block_defaults() : array();
$blocks  = array();

foreach ( $block_defaults as $bk => $default_block ) {
	$block = $default_block;

	// Merge vertical JSON data as defaults.
	if ( 'hero' === $bk ) {
		$block = array_merge( $block, $vertical_hero_data );
	} elseif ( 'ctaFinal' === $bk ) {
		$block = array_merge( $block, $vertical_cta_final );
	} elseif ( 'contact' === $bk ) {
		$block = array_merge( $block, $vertical_contact );
	} elseif ( isset( $vertical_sections[ $bk ] ) ) {
		$block = array_merge( $block, $vertical_sections[ $bk ] );
	}

	$blocks[ $bk ] = $block;
}
```

Keep the existing vertical data extraction (lines 148-243) — it still runs before this section.

- [ ] **Step 2: Refactor block sanitization in `vbb_pro_sanitize_settings()`**

Replace the block sanitization section (lines 406-441) with:

```php
// Block sanitization from registry.
$registry = function_exists( 'vbb_get_block_registry' ) ? vbb_get_block_registry() : array();

foreach ( $defaults['blocks'] as $key => $fallback ) {
	$block_val = $settings['blocks'][ $key ] ?? $fallback;

	if ( is_array( $block_val ) ) {
		if ( isset( $registry[ $key ] ) ) {
			$out['blocks'][ $key ] = vbb_sanitize_block_data( $key, $block_val );
		} else {
			$out['blocks'][ $key ] = $block_val;
		}
	} else {
		// Convert boolean to object for consistency.
		$out['blocks'][ $key ] = array( 'enabled' => ! empty( $block_val ) );
	}
}

// Sanitize per-block colors (registry-independent).
$allowed_color_keys = vbb_pro_block_color_keys();
foreach ( $out['blocks'] as $bk => &$block ) {
	if ( is_array( $block ) && isset( $block['colors'] ) && is_array( $block['colors'] ) ) {
		$sanitized_colors = array();
		foreach ( $allowed_color_keys as $ckey ) {
			$val = isset( $block['colors'][ $ckey ] ) ? $block['colors'][ $ckey ] : '';
			$sanitized_colors[ $ckey ] = ( '' !== $val ) ? ( sanitize_hex_color( $val ) ?: '' ) : '';
		}
		$block['colors'] = $sanitized_colors;
	} elseif ( is_array( $block ) && ! isset( $block['colors'] ) ) {
		$block['colors'] = array();
	}
}
unset( $block );
```

- [ ] **Step 3: Verify defaults match registry structure**

Run: `wp eval "print_r(vbb_pro_default_settings());"`

Verify that `['blocks']` has all 19+1 keys and each block has `effect` field.

---

### Task 4: Fix logoCloud data path mismatch

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` — `vbb_bake_logo_cloud()` function (line 999)

**Problem:** JS saves logos under `items[].logo`, baker reads `logos[].url`.

- [ ] **Step 1: Update `vbb_bake_logo_cloud()` to read `items` array**

```php
function vbb_bake_logo_cloud( $data ) {
	$heading = isset( $data['heading'] ) ? esc_html( $data['heading'] ) : '';
	// Read from 'items' array (canonical), fall back to 'logos' (legacy).
	$logos = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : ( isset( $data['logos'] ) && is_array( $data['logos'] ) ? $data['logos'] : array() );
	
	$html  = '<!-- wp:group {"className":"vbb-section vbb-logo-cloud"} -->';
	$html .= '<div class="vbb-section vbb-logo-cloud">';

	if ( $heading ) {
		$html .= '<!-- wp:heading {"className":"vbb-section-title"} -->';
		$html .= '<h2 class="vbb-section-title">' . $heading . '</h2>';
		$html .= '<!-- /wp:heading -->';
	}

	if ( ! empty( $logos ) ) {
		$html .= '<!-- wp:columns {"className":"vbb-logo-grid"} -->';
		$html .= '<div class="wp-block-columns vbb-logo-grid">';
		foreach ( $logos as $logo_item ) {
			$name    = isset( $logo_item['name'] ) ? esc_html( $logo_item['name'] ) : '';
			$logo    = isset( $logo_item['logo'] ) ? esc_url( $logo_item['logo'] ) : ( isset( $logo_item['url'] ) ? esc_url( $logo_item['url'] ) : '' );
			$link    = isset( $logo_item['url'] ) ? esc_url( $logo_item['url'] ) : '';
			$logoUrl = isset( $logo_item['link'] ) ? esc_url( $logo_item['link'] ) : $link;

			$html .= '<!-- wp:column {"className":"vbb-logo-item"} -->';
			$html .= '<div class="wp-block-column vbb-logo-item">';
			if ( $logoUrl ) {
				$html .= '<a href="' . $logoUrl . '" target="_blank" rel="noopener">';
			}
			if ( $logo ) {
				$html .= '<img src="' . $logo . '" alt="' . $name . '" class="vbb-logo-img" />';
			}
			if ( $logoUrl ) {
				$html .= '</a>';
			}
			$html .= '</div>';
			$html .= '<!-- /wp:column -->';
		}
		$html .= '</div>';
		$html .= '<!-- /wp:columns -->';
	}

	$html .= '</div>';
	$html .= '<!-- /wp:group -->';

	return $html;
}
```

- [ ] **Step 2: Verify backward compat**

Save a profile with old `logos[].url` structure, bake it, confirm logos render (fallback path).

---

### Task 5: Fix pricing features array conversion

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` — `vbb_bake_pricing_tables()` (line 1036)

**Problem:** JS stores features as textarea (newline-separated), baker expects array.

- [ ] **Step 1: Update `vbb_bake_pricing_tables()` to normalize features**

Locate where features are read and add normalization:

```php
// Normalize features: textarea string → array if needed.
$features = isset( $plan['features'] ) ? $plan['features'] : array();
if ( is_string( $features ) ) {
	$features = array_filter( array_map( 'trim', explode( "\n", $features ) ) );
}
```

- [ ] **Step 2: Verify rendering**

Verify a pricing table with features as textarea string renders correctly as bullet list.

---

### Task 6: Fix team baker (bio, social links, image)

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` — `vbb_bake_team_section()` (line 1081)

**Problem:** Team baker may not render bio, social links, or image as proper image field.

- [ ] **Step 1: Audit and update `vbb_bake_team_section()`**

Read the existing function at line 1081. Update it to render:
- `image` as `<img>` tag
- `bio` as `<p class="vbb-team-bio">`
- `linkedin`, `twitter`, `github` as social icon links

---

### Task 7: Fix process baker (number, icon)

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` — `vbb_bake_process()` (line 497)

**Problem:** Process baker may not render `number` step indicator or `icon`.

- [ ] **Step 1: Audit and update `vbb_bake_process()`**

Read the existing function. Ensure `number` is rendered as `.vbb-process-step-number` and `icon` as `.vbb-process-step-icon`.

---

### Task 8: Fix testimonials baker (role, star rendering)

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` — `vbb_bake_testimonials()` (line 562)

**Problem:** Testimonials baker may not render `role` or star rating from `rating`.

- [ ] **Step 1: Audit and update `vbb_bake_testimonials()`**

Read the existing function. Ensure:
- `role` is rendered after author name
- `rating` (1-5) renders as star icons (e.g., `★`/`☆` or numeric)

---

### Task 9: Add effect field to baker output

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php`

Every baker function needs to read `$data['effect']` and add the CSS class to the section wrapper.

- [ ] **Step 1: Add effect class logic to all baker section wrappers**

In `vbb_bake_section()` (line 45), after merging data, pass effect to the baker. Add a helper:

```php
/**
 * Get the effect CSS class from block data.
 *
 * @param array $data Block data.
 * @return string CSS class or empty string.
 */
function vbb_get_effect_class( $data ) {
	$effect = isset( $data['effect'] ) ? sanitize_text_field( $data['effect'] ) : 'none';
	if ( 'none' === $effect || empty( $effect ) ) {
		return '';
	}
	return ' vbb-effect-' . $effect;
}
```

Then in every baker function, replace `"vbb-section vbb-{type}"` with `"vbb-section vbb-{type}" . vbb_get_effect_class($data)`.

---

### Task 10: Create effects CSS

**Files:**
- Create: `orkestone-theme/assets/css/vbb-effects.css`
- Enqueue in: `orkestone-theme/inc/enqueue.php`

- [ ] **Step 1: Create `vbb-effects.css`**

```css
/* Block scroll effects — IntersectionObserver driven */

.vbb-effect-fade {
	opacity: 0;
	transition: opacity 0.3s ease;
}

.vbb-effect-slide-up {
	opacity: 0;
	transform: translateY(30px);
	transition: opacity 0.5s ease, transform 0.5s ease;
}

.vbb-effect-zoom {
	opacity: 0;
	transform: scale(0.95);
	transition: opacity 0.4s ease, transform 0.4s ease;
}

.vbb-effect-flip {
	opacity: 0;
	transform: perspective(800px) rotateY(90deg);
	transition: opacity 0.6s ease, transform 0.6s ease;
}

/* Visible state — added by IntersectionObserver */
.vbb-effect-visible {
	opacity: 1;
	transform: none;
}

/* Respect reduced motion preferences */
@media (prefers-reduced-motion: reduce) {
	.vbb-effect-fade,
	.vbb-effect-slide-up,
	.vbb-effect-zoom,
	.vbb-effect-flip {
		opacity: 1;
		transform: none;
		transition: none;
	}
}
```

- [ ] **Step 2: Enqueue the CSS**

In `inc/enqueue.php`, enqueue only when any block has an effect set (or always conditionally):

```php
function vbb_enqueue_effects() {
	$settings = function_exists( 'vbb_pro_get_settings' ) ? vbb_pro_get_settings() : array();
	$has_effects = false;
	if ( isset( $settings['blocks'] ) && is_array( $settings['blocks'] ) ) {
		foreach ( $settings['blocks'] as $block ) {
			if ( isset( $block['effect'] ) && 'none' !== $block['effect'] ) {
				$has_effects = true;
				break;
			}
		}
	}
	if ( $has_effects ) {
		wp_enqueue_style( 'vbb-effects', VBB_THEME_URI . '/assets/css/vbb-effects.css', array(), VBB_VERSION );
		wp_enqueue_script( 'vbb-effects', VBB_THEME_URI . '/assets/js/vbb-effects.js', array(), VBB_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'vbb_enqueue_effects' );
```

---

### Task 11: Create effects JS

**Files:**
- Create: `orkestone-theme/assets/js/vbb-effects.js`

- [ ] **Step 1: Create `vbb-effects.js`**

```javascript
/**
 * Block scroll effects via IntersectionObserver.
 *
 * Reads vbb-effect-* classes on section wrappers and applies
 * vbb-effect-visible when elements enter the viewport.
 *
 * @package VerticalBlockBase
 */
(function () {
	'use strict';

	if (typeof window === 'undefined' || !window.IntersectionObserver) {
		return;
	}

	// Check for reduced motion preference.
	var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	if (prefersReducedMotion) {
		return;
	}

	var observer = new IntersectionObserver(function (entries) {
		entries.forEach(function (entry) {
			if (entry.isIntersecting) {
				entry.target.classList.add('vbb-effect-visible');
				observer.unobserve(entry.target);
			}
		});
	}, {
		threshold: 0.15,
		rootMargin: '0px 0px -50px 0px',
	});

	// Observe all elements with effect classes.
	var effectClasses = ['vbb-effect-fade', 'vbb-effect-slide-up', 'vbb-effect-zoom', 'vbb-effect-flip'];
	var selectors = effectClasses.map(function (c) { return '.' + c; }).join(',');

	var els = document.querySelectorAll(selectors);
	els.forEach(function (el) {
		observer.observe(el);
	});
})();
```

---

### Task 12: Add REST endpoint `GET /orkestone/v1/blocks`

**Files:**
- Modify: `orkestone-theme/inc/pro-rest-api.php`

- [ ] **Step 1: Add the /blocks route inside `vbb_register_command_center_routes()`**

Add before the closing `}` of `vbb_register_command_center_routes()` (around line 683):

```php
// Block registry endpoint (read-only).
register_rest_route(
	$namespace,
	'/blocks',
	array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'vbb_rest_get_blocks',
		'permission_callback' => 'vbb_rest_command_center_permission',
	)
);
```

- [ ] **Step 2: Add the callback function**

Add after the existing route functions (before closing `?>` or end of file):

```php
/**
 * GET /orkestone/v1/blocks
 * Returns the block registry for JS consumption.
 *
 * @return WP_REST_Response
 */
function vbb_rest_get_blocks() {
	$registry = vbb_get_block_registry();

	// Transform to frontend-friendly format (strip internal fields, add baker info).
	$frontend = array();
	foreach ( $registry as $key => $def ) {
		$frontend[ $key ] = array(
			'key'        => $key,
			'label'      => $def['label'],
			'icon'       => $def['icon'],
			'styles'     => $def['styles'],
			'effects'    => $def['effects'],
			'hasColors'  => $def['hasColors'],
			'fields'     => $def['fields'],
		);
	}

	return new WP_REST_Response( $frontend, 200 );
}
```

- [ ] **Step 3: Verify endpoint works**

Run: `wp rest get /orkestone/v1/blocks --user=admin`

Expected: JSON with all 19 block definitions.

---

### Task 13: Add JS image field renderer with wp.media support

**Files:**
- Modify: `orkestone-theme/assets/js/admin-pro.js`

- [ ] **Step 1: Add `_renderImageField()` method to `CC` object**

Add after `renderRepeatableItems` (around line 1555):

```javascript
/**
 * Render an image/media field with wp.media picker.
 * @param {string} path - data-path attribute value (e.g. "blocks.hero.image")
 * @param {string} value - Current image URL
 * @returns {string} HTML
 */
_renderImageField: function (path, value) {
	var html = '';
	html += '<div class="vbb-cc-field vbb-cc-media-field" data-path="' + path + '">';
	html += '<div class="vbb-cc-media-preview">';
	if (value) {
		html += '<img src="' + CC.escAttr(value) + '" class="vbb-cc-media-thumb" style="max-width:150px;max-height:100px;object-fit:cover;border-radius:4px;" />';
	}
	html += '</div>';
	html += '<div class="vbb-cc-media-actions">';
	html += '<button class="button vbb-cc-media-btn" data-target="' + path + '">Seleccionar Imagen</button>';
	html += '<button class="button vbb-cc-media-remove-btn" data-target="' + path + '"' + (value ? '' : ' style="display:none;"') + '>Quitar</button>';
	html += '<input type="hidden" data-path="' + path + '" value="' + CC.escAttr(value || '') + '" />';
	html += '</div>';
	html += '</div>';
	return html;
},
```

- [ ] **Step 2: Wire global wp.media event delegation**

In the `init()` function (around line 115), add delegated handlers for media buttons:

```javascript
// Media library — delegated for dynamically added content.
document.addEventListener('click', function (e) {
	var mediaBtn = e.target.closest('.vbb-cc-media-btn');
	if (mediaBtn) {
		e.preventDefault();
		var targetPath = mediaBtn.getAttribute('data-target');
		var field = mediaBtn.closest('.vbb-cc-media-field');
		var preview = field.querySelector('.vbb-cc-media-preview');
		var hiddenInput = field.querySelector('input[type="hidden"]');
		var removeBtn = field.querySelector('.vbb-cc-media-remove-btn');

		var frame = wp.media({
			title: 'Seleccionar Imagen',
			library: { type: 'image' },
			button: { text: 'Usar esta imagen' },
			multiple: false,
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var imgUrl = attachment.url;
			var imgId = attachment.id;

			if (hiddenInput) {
				hiddenInput.value = imgUrl;
				hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
			}
			if (preview) {
				preview.innerHTML = '<img src="' + CC.escAttr(imgUrl) + '" class="vbb-cc-media-thumb" style="max-width:150px;max-height:100px;object-fit:cover;border-radius:4px;" />';
			}
			if (removeBtn) {
				removeBtn.style.display = '';
			}
		});

		frame.open();
	}

	var removeBtn = e.target.closest('.vbb-cc-media-remove-btn');
	if (removeBtn) {
		e.preventDefault();
		var targetPath = removeBtn.getAttribute('data-target');
		var field = removeBtn.closest('.vbb-cc-media-field');
		var preview = field.querySelector('.vbb-cc-media-preview');
		var hiddenInput = field.querySelector('input[type="hidden"]');

		if (hiddenInput) {
			hiddenInput.value = '';
			hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		if (preview) {
			preview.innerHTML = '';
		}
		removeBtn.style.display = 'none';
	}
});
```

---

### Task 14: Add REST endpoint fetch to admin-pro.js

**Files:**
- Modify: `orkestone-theme/assets/js/admin-pro.js`

- [ ] **Step 1: Fetch registry on init and store on `CC`**

In the `init()` function, add after other event bindings (around line 140):

```javascript
// Fetch block registry for generic rendering.
CC.registry = {};
CC.fetchRegistry();
```

Add a new method on `CC`:

```javascript
/**
 * Fetch block registry from REST endpoint.
 */
fetchRegistry: function () {
	var xhr = new XMLHttpRequest();
	xhr.open('GET', wpApiSettings.root + 'orkestone/v1/blocks');
	xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
	xhr.onload = function () {
		if (xhr.status === 200) {
			CC.registry = JSON.parse(xhr.responseText);
		}
	};
	xhr.send();
},
```

---

### Task 15: Add generic field renderer `_renderFromRegistry()`

**Files:**
- Modify: `orkestone-theme/assets/js/admin-pro.js`

- [ ] **Step 1: Add generic field rendering method**

Add to the `CC` object (after `_renderImageField`):

```javascript
/**
 * Render block settings from registry field definitions.
 * @param {string} key - Block key (e.g. 'servicesGrid')
 * @param {object} block - Block data from state
 * @param {string} prefix - Path prefix for data-path attributes
 * @returns {string} HTML
 */
renderFromRegistry: function (key, block, prefix) {
	var def = CC.registry[key];
	if (!def) return '';

	prefix = prefix || 'blocks.' + key;
	var html = '';
	var fields = def.fields || [];

	for (var fi = 0; fi < fields.length; fi++) {
		var field = fields[fi];
		var fkey = field.key;
		var ftype = field.type;
		var fpath = prefix + '.' + fkey;
		var value = block && block[fkey] !== undefined ? block[fkey] : field.default;

		if (ftype === 'repeatable') {
			// Repeatable items
			html += '<h4 style="margin:16px 0 8px;font-size:0.9rem;font-weight:600;">' + field.label + '</h4>';
			html += CC._renderRepeatableFromRegistry(fkey, value || [], field.item_fields, prefix);
		} else if (ftype === 'image') {
			html += CC._renderImageField(fpath, value || '');
		} else if (ftype === 'textarea') {
			html += '<div class="vbb-cc-field"><label>' + field.label + '</label><textarea data-path="' + fpath + '" placeholder="' + (field.placeholder || '') + '">' + CC.escAttr(value || '') + '</textarea></div>';
		} else if (ftype === 'select') {
			var opts = field.options || {};
			var htmlOpts = '';
			for (var ok in opts) {
				htmlOpts += '<option value="' + ok + '"' + (value === ok ? ' selected' : '') + '>' + opts[ok] + '</option>';
			}
			html += '<div class="vbb-cc-field"><label>' + field.label + '</label><select data-path="' + fpath + '">' + htmlOpts + '</select></div>';
		} else if (ftype === 'checkbox') {
			html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle">' +
				'<input type="checkbox" data-path="' + fpath + '" data-boolean="1"' + (value ? ' checked' : '') + '>' +
				'<span class="vbb-cc-toggle-track"></span>' +
				'<span class="vbb-cc-toggle-label">' + field.label + '</span>' +
				'</label></div>';
		} else if (ftype === 'color') {
			html += '<div class="vbb-cc-field"><label>' + field.label + '</label>' +
				'<div class="vbb-cc-color-swatch">' +
				'<input type="color" data-path="' + fpath + '" value="' + CC._validateColor(value || '') + '">' +
				'<input type="text" class="vbb-cc-hex-input" value="' + (value || '') + '" data-path="' + fpath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
				'</div></div>';
		} else if (ftype === 'number') {
			html += '<div class="vbb-cc-field"><label>' + field.label + '</label><input type="number" data-path="' + fpath + '" value="' + CC.escAttr(value !== undefined ? value : '') + '" /></div>';
		} else {
			// Default: text / url
			var inputType = (ftype === 'url') ? 'url' : 'text';
			html += '<div class="vbb-cc-field"><label>' + field.label + '</label><input type="' + inputType + '" data-path="' + fpath + '" value="' + CC.escAttr(value || '') + '" placeholder="' + (field.placeholder || '') + '" /></div>';
		}
	}

	// Effects selector if block supports multiple effects.
	if (def.effects && def.effects.length > 1) {
		var currentEffect = block.effect || 'none';
		html += '<div class="vbb-cc-field"><label>Effect</label><select data-path="' + prefix + '.effect">';
		for (var ei = 0; ei < def.effects.length; ei++) {
			var eVal = def.effects[ei];
			var eLabels = { none: 'Sin efecto', fade: 'Fade In', 'slide-up': 'Slide Up', zoom: 'Zoom In', flip: 'Flip' };
			html += '<option value="' + eVal + '"' + (currentEffect === eVal ? ' selected' : '') + '>' + (eLabels[eVal] || eVal) + '</option>';
		}
		html += '</select></div>';
	}

	return html;
},

/**
 * Render repeatable items from registry field definitions.
 * @param {string} blockKey
 * @param {Array} items
 * @param {Array} itemFields
 * @param {string} prefix
 * @returns {string} HTML
 */
_renderRepeatableFromRegistry: function (blockKey, items, itemFields, prefix) {
	var html = '';
	var itemPrefix = prefix + '.' + blockKey + '.items';
	var fieldsJson = JSON.stringify(itemFields);

	html += '<div class="vbb-cc-repeatable" data-block-key="' + blockKey + '">';

	if (items && items.length > 0) {
		for (var ii = 0; ii < items.length; ii++) {
			html += '<div class="vbb-cc-repeatable-item">';
			html += '<div class="vbb-cc-repeatable-item-header">';
			html += '<span class="vbb-cc-drag-handle">⠿</span>';
			html += '<span class="vbb-cc-item-title">' + (items[ii].title || items[ii].name || 'Item ' + (ii + 1)) + '</span>';
			html += '<button class="vbb-cc-remove-item" data-index="' + ii + '" data-prefix="' + itemPrefix + '" title="Remove">&times;</button>';
			html += '</div>';
			html += '<div class="vbb-cc-repeatable-item-fields">';

			// Render each field for this item.
			for (var fi = 0; fi < itemFields.length; fi++) {
				var fdef = itemFields[fi];
				var ikey = fdef.key;
				var ipath = itemPrefix + '.' + ii + '.' + ikey;
				var ival = items[ii] && items[ii][ikey] !== undefined ? items[ii][ikey] : fdef.default;

				if (fdef.type === 'image') {
					html += CC._renderImageField(ipath, ival || '');
				} else if (fdef.type === 'textarea') {
					html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label><textarea data-path="' + ipath + '" placeholder="' + (fdef.placeholder || '') + '">' + CC.escAttr(ival || '') + '</textarea></div>';
				} else if (fdef.type === 'select') {
					var opts = fdef.options || {};
					var optHtml = '';
					for (var ok in opts) {
						optHtml += '<option value="' + ok + '"' + (ival === ok ? ' selected' : '') + '>' + opts[ok] + '</option>';
					}
					html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label><select data-path="' + ipath + '">' + optHtml + '</select></div>';
				} else if (fdef.type === 'checkbox') {
					html += '<div class="vbb-cc-field"><label class="vbb-cc-toggle">' +
						'<input type="checkbox" data-path="' + ipath + '" data-boolean="1"' + (ival ? ' checked' : '') + '>' +
						'<span class="vbb-cc-toggle-track"></span>' +
						'<span class="vbb-cc-toggle-label">' + fdef.label + '</span>' +
						'</label></div>';
				} else if (fdef.type === 'color') {
					html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label>' +
						'<div class="vbb-cc-color-swatch">' +
						'<input type="color" data-path="' + ipath + '" value="' + CC._validateColor(ival || '') + '">' +
						'<input type="text" class="vbb-cc-hex-input" value="' + (ival || '') + '" data-path="' + ipath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
						'</div></div>';
				} else {
					html += '<div class="vbb-cc-field"><label>' + fdef.label + '</label><input type="' + (fdef.type === 'url' ? 'url' : 'text') + '" data-path="' + ipath + '" value="' + CC.escAttr(ival || '') + '" placeholder="' + (fdef.placeholder || '') + '" /></div>';
				}
			}

			html += '</div></div>';
		}
	}

	// Add item button.
	var fieldsJsonAttr = fieldsJson.replace(/"/g, '&quot;');
	html += '<button class="button vbb-cc-add-item" data-block-key="' + blockKey + '" data-prefix="' + itemPrefix + '" data-fields="' + fieldsJsonAttr + '">+ Add ' + (blockKey === 'items' ? 'Item' : 'Item') + '</button>';
	html += '</div>';

	return html;
},
```

---

### Task 16: Replace generic renderers in `renderBlockSettings()`

**Files:**
- Modify: `orkestone-theme/assets/js/admin-pro.js`

- [ ] **Step 1: Update `renderBlockSettings()` to use generic rendering for standard blocks**

Modify `renderBlockSettings` function (starting line 1232). Replace the `if/else if` chain for blocks: `servicesGrid`, `benefits`, `process`, `testimonials`, `faq`, `pricing`, `team`, `logoCloud`, `stats`, `gallery` with a generic call:

```javascript
renderBlockSettings: function (key, block) {
	// Generic rendering for standard blocks.
	var standardBlocks = ['servicesGrid', 'benefits', 'process', 'testimonials', 'faq', 'pricing', 'team', 'logoCloud', 'stats', 'gallery'];
	
	if (standardBlocks.indexOf(key) !== -1 && CC.registry[key]) {
		return CC._renderStandardBlock(key, block);
	}
	
	// Custom rendering for special blocks.
	// ... (keep existing code for: hero, heroCentered, ctaFinal, contact, video, newsletter, map, comparison, blog, divider)
},
```

Add the helper:

```javascript
/**
 * Render a standard block using registry field definitions.
 * @param {string} key
 * @param {object} block
 * @returns {string}
 */
_renderStandardBlock: function (key, block) {
	var html = '';
	html += CC.renderFromRegistry(key, block, 'blocks.' + key);

	// Style selector (same for all that support it).
	var def = CC.registry[key];
	if (def && def.styles && def.styles.length > 1) {
		var currentStyle = block.style || def.styles[0];
		html += '<div class="vbb-cc-style-selector">';
		html += '<label>Section Style</label>';
		html += '<div class="vbb-cc-style-buttons">';
		for (var si = 0; si < def.styles.length; si++) {
			html += '<button class="vbb-cc-style-btn' + (currentStyle === def.styles[si] ? ' vbb-cc-style-btn--active' : '') + '" data-path="blocks.' + key + '.style" data-style="' + def.styles[si] + '">' + def.styles[si] + '</button>';
		}
		html += '</div></div>';
	}

	// Per-block colors.
	var blockColors = (block && block.colors) || {};
	var perBlockKeys = ['accent', 'background', 'surface', 'text', 'mutedText'];
	html += '<div class="vbb-cc-block-colors">';
	html += '<h4 style="margin:12px 0 8px;font-size:0.85rem;font-weight:600;color:#444;">Block Colors</h4>';
	html += '<p class="description" style="margin:0 0 8px;font-size:0.8rem;">Override palette colors for this section only.</p>';
	html += '<div class="vbb-cc-color-grid">';
	for (var ci = 0; ci < perBlockKeys.length; ci++) {
		var ck = perBlockKeys[ci];
		var cpath = 'blocks.' + key + '.colors.' + ck;
		var val = blockColors[ck] || '';
		html += '<div class="vbb-cc-field"><label>' + ck + '</label>' +
			'<div class="vbb-cc-color-swatch">' +
			'<input type="color" data-path="' + cpath + '" value="' + CC._validateColor(val) + '">' +
			'<input type="text" class="vbb-cc-hex-input" value="' + val + '" data-path="' + cpath + '" pattern="^#[0-9a-fA-F]{6}$" maxlength="7">' +
			'</div></div>';
	}
	html += '</div></div>';

	return html;
},
```

---

### Task 17: Fix toggle rebinding bug

**Files:**
- Modify: `orkestone-theme/assets/js/admin-pro.js` — `_toggleBlockSettings()` (line 2597)

**Problem:** When toggling a block off and back on, the repeatable add/remove buttons lose event listeners.

- [ ] **Step 1: Add delegated add/remove handling and update `_toggleBlockSettings`**

The fix is two-fold:
1. Use event delegation for add/remove buttons so they never need rebinding
2. Ensure `_toggleBlockSettings` triggers re-binding of data-path inputs

Add delegated event handlers in `init()` (alongside the media delegation from Task 13):

```javascript
// Add repeatable item (delegated).
document.addEventListener('click', function (e) {
	var addBtn = e.target.closest('.vbb-cc-add-item');
	if (addBtn) {
		e.preventDefault();
		var blockKey = addBtn.getAttribute('data-block-key');
		var prefix = addBtn.getAttribute('data-prefix');
		var fields = JSON.parse(addBtn.getAttribute('data-fields') || '[]');
		var container = addBtn.closest('.vbb-cc-repeatable');
		var items = container.querySelectorAll('.vbb-cc-repeatable-item');
		var newIndex = items.length;

		// Create default item.
		var newItem = {};
		for (var fi = 0; fi < fields.length; fi++) {
			newItem[fields[fi].key] = fields[fi].default !== undefined ? fields[fi].default : '';
		}

		// Insert a hidden input to track the item.
		CC.state.settings.blocks[blockKey] = CC.state.settings.blocks[blockKey] || { items: [] };
		CC.state.settings.blocks[blockKey].items = CC.state.settings.blocks[blockKey].items || [];
		CC.state.settings.blocks[blockKey].items.push(newItem);

		// Re-render the repeatable section.
		var itemFields = fields;
		var reRendered = CC._renderRepeatableFromRegistry(blockKey, CC.state.settings.blocks[blockKey].items, itemFields, 'blocks.' + blockKey);
		var tempDiv = document.createElement('div');
		tempDiv.innerHTML = reRendered;
		container.innerHTML = tempDiv.querySelector('.vbb-cc-repeatable').innerHTML;
	}
});

// Remove repeatable item (delegated).
document.addEventListener('click', function (e) {
	var removeBtn = e.target.closest('.vbb-cc-remove-item');
	if (removeBtn) {
		e.preventDefault();
		var index = parseInt(removeBtn.getAttribute('data-index'), 10);
		var prefix = removeBtn.getAttribute('data-prefix');
		var parts = prefix.split('.');
		var blockKey = parts[2]; // blocks.{key}.items

		if (CC.state.settings.blocks[blockKey] && CC.state.settings.blocks[blockKey].items) {
			CC.state.settings.blocks[blockKey].items.splice(index, 1);
			// Re-render
			var container = removeBtn.closest('.vbb-cc-repeatable');
			var itemFields = JSON.parse(container.querySelector('.vbb-cc-add-item').getAttribute('data-fields') || '[]');
			var reRendered = CC._renderRepeatableFromRegistry(blockKey, CC.state.settings.blocks[blockKey].items, itemFields, 'blocks.' + blockKey);
			var tempDiv = document.createElement('div');
			tempDiv.innerHTML = reRendered;
			container.innerHTML = tempDiv.querySelector('.vbb-cc-repeatable').innerHTML;
		}
	}
});
```

Update `_toggleBlockSettings` to also bind the repeatable add/remove via the delegated path (no change needed since delegation handles it), but ensure media buttons get re-initialized. The existing `_toggleBlockSettings` already re-binds inputs via `querySelectorAll` — the delegated events cover add/remove.

The key fix: add the `'input[type="hidden"]'` selector to the existing re-binding query (line 2617):

```javascript
var newInputs = div.querySelectorAll(
	'input[type="text"], select, input[type="color"], input[type="checkbox"][data-path], input[type="hidden"]'
);
```

This ensures hidden fields (used by media picker) trigger `_handleChange`.

---

### Task 18: Cleanup and final testing

**Files:**
- Modify: `orkestone-theme/assets/js/admin-pro.js` — remove unused `_renderXxxCard` functions
- Test: All vertical configs

- [ ] **Step 1: Remove old render functions that are replaced by generic**

After verifying everything works, remove (or comment) the following functions:
- `servicesGrid` render block in `renderBlockSettings` 
- `benefits` render block
- `process` render block
- `testimonials` render block
- `faq` render block
- `pricing` render block
- `team` render block
- `logoCloud` render block
- `stats` render block
- `gallery` render block

Keep custom renderers for: hero, heroCentered, ctaFinal, contact, video, newsletter, map, comparison, blog, divider.

- [ ] **Step 2: Test with all vertical configs**

Switch between vertical configs and verify:
1. All 19 blocks render in admin
2. All 19 blocks bake correctly on page regenerate
3. Adding/removing repeatable items works after toggle off/on
4. Media library picker works for logoCloud, team, gallery, testimonials
5. Effects selector appears and CSS class is applied on frontend
6. Settings save and restore correctly
7. Old profiles with legacy keys (`logos[].url`, `plans[].features` string) still render

---

## Self-Review Checklist

- **Spec coverage:** Does each section from the spec map to a task?
  - Section 1 (Registry structure) → Task 1
  - Section 2 (All blocks) → Task 1 (registry definitions)
  - Section 3 (Effects system) → Tasks 9-11
  - Section 4 (Media library) → Tasks 13
  - Section 5 (PHP changes) → Tasks 1-8
  - Section 6 (JS changes) → Tasks 13-17
  - Section 7 (Effects frontend) → Tasks 10-11

- **Placeholder scan:** No TBD/TODO patterns. All code blocks contain real implementations.

- **Type consistency:** `vbb_get_baker_map()` returns same shape as before. `vbb_get_block_registry()` returns the new canonical structure. Sanitize functions match field types.
