# Spec: Visual Enhancements — Top 5

**Change**: visual-enhancements-top5
**Status**: draft
**Next**: sdd-design

---

## 1. Preview Zoom X2

**Tags**: `feature`, `UI`, `CSS transform`, `toolbar`

### Requirement

The Command Center preview toolbar MUST include a zoom toggle button that applies a 2x CSS scale transform to the preview iframe via the CSS class `.vbb-cc-preview-scrollable--zoomed` on the iframe wrapper. The zoom MUST be toggleable — clicking the button again MUST return the iframe to original scale. The zoom state MUST NOT persist across page refresh (future optional enhancement via localStorage).

### Scenarios

#### Scenario 1: User activates zoom

**Given** the Command Center preview toolbar is visible
**When** the user clicks the zoom button
**Then** the iframe wrapper receives class `.vbb-cc-preview-scrollable--zoomed`
**And** the iframe content renders at 2x scale with `transform-origin: top left`
**And** scrollbars appear when scaled content overflows the viewport

#### Scenario 2: User deactivates zoom

**Given** the iframe is currently zoomed to 2x
**When** the user clicks the zoom button again
**Then** the iframe wrapper loses the zoomed class
**And** the iframe returns to its original scale and viewport dimensions

#### Scenario 3: Zoom with narrow viewport

**Given** the Command Center is resized below 600px width
**When** the user activates zoom
**Then** the scaled iframe maintains `overflow: auto` on the wrapper
**And** no layout shift occurs in the surrounding grid containers

---

## 2. Smooth Transitions UI

**Tags**: `feature`, `UX`, `CSS transitions`

### Requirement

The system MUST define CSS custom properties `--vbb-cc-transition-fast`, `--vbb-cc-transition-normal`, and `--vbb-cc-transition-slow` on `.vbb-command-center`. Utility classes `.vbb-cc-timing-fast`, `.vbb-cc-timing-normal`, `.vbb-cc-timing-slow` MUST be available for direct element usage. All stateful UI interactions (card hover elevation, dropdown expand/collapse, focus rings, block visibility toggles) MUST use these variables instead of hardcoded transition values. No element SHOULD use `transition: all` on large containers for performance reasons. Dropdown panels MUST use `max-height` transitions with opacity fade to avoid flicker, not `display: none` toggling.

### Scenarios

#### Scenario 1: Card hover effect

**Given** a `.vbb-cc-card` element in the Command Center
**When** the user hovers over the card
**Then** the card's box-shadow and border-color transition smoothly using `--vbb-cc-transition-normal`

#### Scenario 2: Dropdown expand

**Given** a font family dropdown in the Appearance card
**When** the user clicks to expand the dropdown
**Then** the dropdown panel animates from `max-height: 0` to `max-height: 120px` with easing, without visual snap or flicker

#### Scenario 3: Block visibility toggle

**Given** a block toggle switch in the Blocks card
**When** the user toggles a block ON
**Then** the block's settings panel fades and slides in using `--vbb-cc-transition-slow`

---

## 3. Onboarding Animation

**Tags**: `feature`, `UX`, `soft`

### Requirement

Command Center cards MUST animate into view with a staggered scale-up effect on every `init()` call. Each card MUST receive an inline `animation-delay` style based on its DOM index (index × `--vbb-cc-stagger-delay`, default 80ms). The animation MUST use the CSS keyframe `vbb-cc-card-reveal`, which MUST work on both `<div>` and `<section>` containers. The animation MUST replay on every `init()` including re-loads from server — not a one-time effect. If JavaScript is unavailable, the cards MUST render without the animation (no broken layout).

### Scenarios

#### Scenario 1: Full card grid animation

**Given** the Command Center renders its initial card grid (9 cards)
**When** `init()` completes
**Then** each card animates in sequence with increasing `animation-delay` (0ms, 80ms, 160ms, …)
**And** the total animation completes within ~720ms (9 × 80ms)

#### Scenario 2: Animation replay on re-load

**Given** the Command Center is already visible
**When** the user triggers a settings re-load from the server
**Then** all cards replay the stagger animation

#### Scenario 3: No-JS fallback

**Given** JavaScript is disabled or fails to load
**When** the Command Center renders
**Then** all cards are fully visible immediately without the reveal animation
**And** no layout or functionality is broken

---

## 4. Preset Selector A/B

**Tags**: `feature`, `UX`, `velocity`

### Requirement

The Command Center MUST include a toggleable preset selector UI component that lists available theme presets (Light, Semidark, Dark, Inverted). Selecting a preset MUST call `vbb_pro_get_preset_settings()` and apply the returned config to the Command Center UI. The preset MUST apply global theme settings only and MUST NOT overwrite page-specific overrides. Preset selection MUST NOT require a page reload.

### Scenarios

#### Scenario 1: User selects a preset

**Given** the preset selector is expanded in the Command Center
**When** the user selects "Semidark" from the preset list
**Then** the Command Center UI updates to the semidark theme settings
**And** the change is applied without a page reload

#### Scenario 2: Preset with local overrides

**Given** the user has manually changed a per-page font size override
**When** the user selects a new preset
**Then** the global theme settings update
**And** the per-page font size override is preserved (not overwritten)

#### Scenario 3: Preset selector dismissed

**Given** the preset selector is open
**When** the user toggles the preset selector off
**Then** the applied preset settings remain visible in the UI
**And** the settings are only persisted when the user explicitly saves

---

## 5. Dark Mode Preview

**Tags**: `feature`, `preview`, `override`

### Requirement

The preview toolbar MUST include a dark mode preview toggle button independent of the Command Center's Dark Mode setting. Toggling it MUST inject dark-theme CSS variables into the preview iframe via postMessage with payload `{ type: 'vbb-cc-dark-mode-preview', enabled: true/false }`. The toggle MUST use `buildCssVars()` for CSS var override injection. The toggle state MUST NOT persist to the WordPress database — it resets to OFF on page refresh. When disabled, preview CSS vars MUST revert to the default (light) theme.

### Scenarios

#### Scenario 1: Toggle dark preview ON

**Given** the Command Center is in light mode
**When** the user clicks the "Dark Preview" toggle
**Then** a postMessage with `{ type: 'vbb-cc-dark-mode-preview', enabled: true }` is sent to the iframe
**And** the iframe content switches to dark theme CSS variables immediately

#### Scenario 2: Toggle resets on refresh

**Given** the user enabled Dark Preview
**When** the user refreshes the page (F5)
**Then** the Dark Preview toggle returns to OFF position
**And** the preview iframe renders with the default (server-configured) theme

#### Scenario 3: Dark Preview with CC dark mode

**Given** the Command Center is in dark mode
**When** the user disables the Dark Preview toggle (but keeps CC dark mode ON)
**Then** the preview resets to the CC's default theme (matching the CC dark baseline)
**And** a subsequent save persists CC dark mode without the Dark Preview override

---

## Shared Constraints

- All CSS transitions MUST work on modern browsers that support CSS Custom Properties and transforms (Chrome 90+, Firefox 88+, Safari 15+)
- Zoom iframe overflow MUST NOT cause layout shifts on surrounding grid containers
- All five enhancements MUST be independently toggleable without breaking existing functionality
- No new database schemas or PHP REST endpoints — all changes are frontend-only (CSS and JS)

## Dependencies

- **builder-visual-polish** must be complete (provides postMessage bridge, preview toolbar markup, and preview container structure)
- **pro-admin.php** must contain the preview container with `.vbb-cc-preview-scrollable` wrapper
- `buildCssVars()` JS function must exist for CSS var injection
- `vbb_pro_get_preset_settings()` PHP function must exist for preset loading
