# Preview Fidelity Specification

## Purpose

Defines requirements for the Command Center live preview to produce CSS that matches front-end production output exactly. Covers variable parity between PHP and JS, CSS rule emission, block/page-scoped selectors, single-message-receiver architecture, and correct dark mode rendering.

## Requirements

### Requirement: CSS Variable Parity

`buildCssVars()` in `assets/js/admin-pro.js` MUST emit every CSS custom property produced by PHP `vbb_pro_print_css_vars()`, including `--vbb-pro-shadow`, `--vbb-pro-section-spacing`, `--vbb-pro-button-radius`, `--vbb-pro-button-shadow`, `--vbb-pro-button-padding`, `--vbb-pro-text-color`, `--vbb-pro-heading-color`, `--vbb-pro-glow-intensity`, and any future additions to the PHP source.

#### Scenario: Full variable set matches PHP

- GIVEN PHP `vbb_pro_print_css_vars()` defines a set of named custom properties
- WHEN `buildCssVars()` is called
- THEN the emitted CSS includes every named variable from the PHP source with identical custom property names and computed values
- AND a programmatic diff between the PHP variable list and JS output is zero

#### Scenario: New PHP variable triggers JS update

- GIVEN a variable `--vbb-pro-custom-color` is added to `vbb_pro_print_css_vars()`
- WHEN `buildCssVars()` is called before the JS emitter is updated
- THEN the preview is considered out of spec until `buildCssVars()` includes the new variable

### Requirement: Essential CSS Rule Emission

`buildCssVars()` MUST emit CSS rulesets for button styles, global content width, wide width constraint, card styles, navigation styles, and footer `:root` bindings.

#### Scenario: Core rule blocks present in preview CSS

- GIVEN the command center preview initializes
- WHEN `buildCssVars()` assembles its CSS output
- THEN the output contains rulesets for selectors `.vbb-pro-button`, `.vbb-pro-content`, `.vbb-pro-wide`, `.vbb-pro-card`, `.vbb-pro-nav`, and `.vbb-pro-footer :root`
- AND each ruleset references the corresponding custom properties from the variable set

### Requirement: Block and Page-Scoped Selector Mapping

`_blockKeyToSectionClass()` in JS MUST map every block key registered in PHP (`stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`, and any future registered keys) to its `.vbb-pro-section-*` class, and MUST support page-scoped selectors when page-level scoping is active.

#### Scenario: All PHP block keys resolve correctly

- GIVEN PHP registers block keys `stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`
- WHEN `_blockKeyToSectionClass()` receives each key
- THEN each key returns its mapped `.vbb-pro-section-*` class
- AND an unmapped key produces a fallback class or explicit error rather than silent incorrect markup

#### Scenario: Page-scoped selectors emit at root level

- GIVEN page-scoped rendering is enabled in preview mode
- WHEN block CSS is assembled with page-level scope active
- THEN selectors are emitted as root-level wrappers, not nested under a transient container
- AND the preview iframe reflects page-level styling

### Requirement: Single Preview Message Receiver

PHP MUST remove `vbb_pro_preview_message_listener()` and leave `vbb_pro_inject_preview_script()` (priority 5) as the sole receiver for `vbb:css-vars` messages from the Command Center.

#### Scenario: Only one listener registered

- GIVEN the WordPress hook system initializes
- WHEN the preview script injection phase runs at priority 5
- THEN exactly one callback handles `vbb:css-vars` messages
- AND `vbb_pro_preview_message_listener()` is no longer registered on any priority

#### Scenario: Message accumulates without duplication

- GIVEN the preview iframe sends a `vbb:css-vars` postMessage payload
- WHEN the sole receiver processes the payload
- THEN CSS variables accumulate without duplication or race conditions
- AND no listener overwrites the other's work

### Requirement: Dark Mode Preview Color Correctness

The Command Center dark preview MUST render dark palette variables correctly without causing a double-inversion color bug from conflicting CSS filters.

#### Scenario: Dark palette renders without inversion

- GIVEN dark preview mode is activated in the Command Center
- WHEN the preview iframe applies dark palette CSS variables
- THEN background and text colors match the intended dark palette
- AND no inverted-color artifact appears
- AND no CSS filter applies a second inversion pass to already-dark values

#### Scenario: Dark preview fix does not affect production

- GIVEN production CSS is generated via PHP `vbb_pro_print_css_vars()`
- WHEN no dark-mode customizer setting is active
- THEN production output is unchanged by the preview filter fix
- AND only preview-specific CSS is modified