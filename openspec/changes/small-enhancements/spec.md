# Spec: Small Enhancements — Star 2.0, Font Selection, Dark Mode

**Change**: small-enhancements
**Status**: draft
**Next**: sdd-design
**Review Budget**: 600 lines (1 PR, or 2 chained PRs if dark mode CSS migration is large)

---

## Executive Summary

Three independent but scoped enhancements to the Command Center admin UI and preview system. **Star 2.0** adds a glow animation to CTA buttons in the live preview. **Font Selection** replaces raw text inputs with Google Fonts dropdowns so users can visually pick and preview typefaces. **Dark Mode** introduces a light/dark toggle for the admin chrome only (preview iframe is never affected), persisted via `localStorage` and respecting `prefers-color-scheme`. All three are frontend-only changes: no new PHP endpoints, no schema changes, no breaking changes to existing settings.

---

## 1. Star 2.0 — CTA Button Glow Effect

**Tags**: `feature`, `visual`, `CSS animations`, `low risk`

### Requirement

CTA buttons in the Command Center live preview MUST display a configurable glow animation on hover and focus. The effect MUST degrade gracefully on browsers that do not support `box-shadow` animations (IE11, older Safari); the button remains functional without the glow. The glow intensity MUST be controllable via the existing CSS custom property `--vbb-pro-glow-intensity` (defaults to `8px`). Disabled buttons MUST NOT show the glow under any state.

### Scenarios

#### Scenario 1: Default State — No Glow

- **Given** the Command Center preview is rendering a CTA button
- **When** the button is idle (no hover, no focus)
- **Then** the button MUST NOT display any glow ring
- **And** the computed style of `box-shadow` MUST be `none` or the default button shadow (no extra glow layer)

#### Scenario 2: Hover — Glowing Ring Appears

- **Given** the Command Center preview is rendering a CTA button in normal state
- **When** the user hovers the cursor over the button
- **Then** the button MUST display a colored glow ring via `box-shadow`
- **And** the glow MUST use the button's `--vbb-pro-primary` color at 60% opacity
- **And** the glow spread MUST equal the computed value of `--vbb-pro-glow-intensity` (default `8px`)
- **And** the transition MUST be smooth (CSS `transition: box-shadow 0.3s ease`)

#### Scenario 3: Focus — Glowing Ring Appears (Accessibility)

- **Given** the Command Center preview is rendering a CTA button
- **When** the button receives keyboard focus (Tab navigation)
- **Then** the button MUST display the same glow effect as hover
- **And** the focus ring MUST remain visible until the element loses focus (not removed on `:focus-visible` unless the browser supports it natively)
- **And** the glow MUST NOT be hidden by `outline: none` — the glow IS the focus indicator

#### Scenario 4: Disabled — No Glow

- **Given** the Command Center preview is rendering a CTA button with the `disabled` attribute
- **When** the user hovers or focuses the disabled button
- **Then** the button MUST NOT display any glow effect
- **And** the button MUST retain its disabled visual state (reduced opacity, no pointer cursor)

#### Scenario 5: Configurable Intensity

- **Given** the Command Center CSS declares `--vbb-pro-glow-intensity: 12px`
- **When** a CTA button is hovered
- **Then** the glow `box-shadow` spread MUST be `12px` (matching the custom property)

#### Scenario 6: Browser Fallback

- **Given** the preview is rendered in a browser that does not support `box-shadow` animations (e.g., IE11)
- **When** the user hovers a CTA button
- **Then** the button MUST NOT display a glow effect
- **And** the button MUST remain fully functional with all click and form behaviors intact
- **And** no JavaScript errors MUST be thrown

---

## 2. Font Selection — Google Fonts Dropdown

**Tags**: `feature`, `UX`, `Google Fonts`, `preview`

### Requirement

The Command Center settings panel MUST replace the existing free-text `<input>` fields for `typography.heading` and `typography.body` with an interactive dropdown that lists accessible Google Fonts. The dropdown MUST show font previews in their own typeface. A selected font MUST be loaded in the live preview (via postMessage CSS variable injection) and on full preview load. If the Google Fonts Web API (`webfonts.googleapis.com`) is unreachable or returns an error, the dropdown MUST display a warning message and fall back to a text input for manual font entry. The selected font family MUST be stored in the existing schema keys (`typography.heading`, `typography.body`) with no schema changes.

### Scenarios

#### Scenario 1: Select Font Via Dropdown

- **Given** the Command Center settings panel is open
- **When** the user clicks the `typography.heading` dropdown
- **Then** a list of Google Fonts MUST appear with each item rendered in its own typeface
- **When** the user selects "Inter" from the list
- **Then** the `typography.heading` field MUST be set to `"Inter"`
- **And** the live preview MUST update the heading font to Inter via postMessage CSS variable injection (no full iframe reload)
- **And** the status bar MUST show "Saving…" → "Saved ✓"

#### Scenario 2: Font Loads on Full Preview

- **Given** a font has been selected and saved (e.g., `typography.body: "Playfair Display"`)
- **When** the user triggers a full preview reload (refresh button or page change)
- **Then** the preview iframe MUST load the Google Fonts stylesheet for Playfair Display
- **And** the preview `<body>` MUST render with `font-family: 'Playfair Display', serif`

#### Scenario 3: FOUT (Flash of Unstyled Text)

- **Given** the user has selected a Google Font for `typography.body`
- **When** the preview iframe loads
- **Then** the system MUST NOT block text rendering while the font downloads
- **And** a FOUT (Flash of Unstyled Text) is ACCEPTABLE — the preview shows system fallback text until the Google Font loads
- **And** no `font-display: block;` is used; `font-display: swap;` MUST be applied in the loaded stylesheet

#### Scenario 4: Google Fonts API Unavailable

- **Given** the Google Fonts Web API (`webfonts.googleapis.com`) is unreachable (network blocked, DNS failure)
- **When** the Command Center settings panel loads
- **Then** the dropdown MUST display an inline warning: "Google Fonts unavailable. Enter a font name manually."
- **And** a text `<input>` MUST be shown instead of the dropdown for manual entry
- **And** the user MUST be able to type any font family name manually
- **And** the text input MUST still save to `typography.heading` / `typography.body` as before

#### Scenario 5: Font List Curation

- **Given** the Google Fonts API is available
- **When** the dropdown populates
- **Then** it MUST display a curated subset of fonts (recommended: 50–100 popular, web-safe Google Fonts) OR all available fonts filtered by category (sans-serif, serif, display, handwriting, monospace)
- **And** the dropdown MUST NOT display every single Google Font if the list exceeds 200 items without a search/filter mechanism

---

## 3. Dark Mode — Command Center Admin Toggle

**Tags**: `feature`, `visual`, `admin`, `colors`, `toggle`

### Requirement

The Command Center admin UI MUST provide a Dark Mode toggle button in the header toolbar. The toggle MUST persist the user's choice in `localStorage` under the key `vbb-cc-dark-mode`. On initial load, the system MUST check `localStorage` first; if no stored preference exists, it MUST respect the OS-level `prefers-color-scheme: dark` media query. All hardcoded color values in the admin CSS (approximately 25 unique colors) MUST be migrated to CSS custom properties scoped to `.vbb-command-center`. When dark mode is active, the class `.vbb-command-center--dark` MUST be added to the Command Center container. The dark mode toggle MUST affect ONLY the admin chrome — the preview iframe MUST remain unaffected.

### Scenarios

#### Scenario 1: Toggle Dark Mode On

- **Given** the Command Center is open in light mode
- **When** the user clicks the Dark Mode toggle button in the toolbar
- **Then** the `.vbb-command-center` element MUST receive the class `.vbb-command-center--dark`
- **And** all UI colors MUST switch to the dark palette (dark backgrounds, light text, adjusted accent colors)
- **And** the toggle button icon MUST change to indicate dark mode is active (e.g., sun icon)
- **And** the preview iframe MUST remain in light mode (no class change, no CSS variable injection)

#### Scenario 2: Persistence Across Reload

- **Given** the user has activated Dark Mode in a previous session
- **When** the user reloads the Command Center page
- **Then** the system MUST read `localStorage.getItem('vbb-cc-dark-mode')` returning `'true'`
- **And** the `.vbb-command-center--dark` class MUST be applied on initial render
- **And** no flash of light mode MUST be visible (the class MUST be applied before paint)

#### Scenario 3: Preview Isolation

- **Given** Dark Mode is active in the Command Center
- **When** the user inspects the preview iframe's `<html>` or `<body>` element
- **Then** the preview iframe MUST NOT have any dark mode classes
- **And** the preview MUST render using the original light/dark palette as configured in the settings (global `colorMode` setting, NOT the admin dark mode)

#### Scenario 4: System Dark Preference Auto-Apply

- **Given** the user has NOT previously toggled dark mode (no `localStorage` entry for `vbb-cc-dark-mode`)
- **And** the OS/browser is set to `prefers-color-scheme: dark`
- **When** the Command Center loads
- **Then** the system MUST detect `prefers-color-scheme: dark`
- **And** MUST apply `.vbb-command-center--dark` automatically
- **And** the toggle button MUST reflect the active dark state
- **When** the user toggles off dark mode
- **Then** `localStorage.setItem('vbb-cc-dark-mode', 'false')` MUST be written
- **And** the `.vbb-command-center--dark` class MUST be removed

#### Scenario 5: Toggle Off Returns to Light Mode

- **Given** Dark Mode is active
- **When** the user clicks the Dark Mode toggle button
- **Then** the `.vbb-command-center--dark` class MUST be removed
- **And** all UI colors MUST return to the light palette
- **And** `localStorage.setItem('vbb-cc-dark-mode', 'false')` MUST be persisted

---

## Shared Constraints (All Three Enhancements)

| Constraint | Description |
|------------|-------------|
| **C1. No DB schema changes** | No new options, post meta, or database tables. Star 2.0 uses CSS vars only. Font selection uses existing `typography.*` keys. Dark Mode uses `localStorage`. |
| **C2. No new PHP entry points** | All three are frontend-only. No new REST endpoints, admin-post actions, or AJAX handlers. |
| **C3. No breaking changes to existing settings** | Existing `typography.heading`, `typography.body` text input values remain compatible. Existing color mode toggle (`colorMode` setting) is independent of admin dark mode. |
| **C4. Preview-only changes (Star 2.0, Font)** | Star 2.0 and Font Selection affect only the preview iframe or preview-related CSS. They MUST NOT alter the admin UI beyond the font dropdown replacement. |
| **C5. Admin-only changes (Dark Mode)** | Dark Mode affects only the `.vbb-command-center` chrome. It MUST NOT affect the public frontend or the preview iframe. |

---

## Regression Areas

The following existing features MUST continue to work after the small enhancements changes.

| Area | What to Verify | Risk if Broken |
|------|---------------|----------------|
| **R1. Existing color mode toggle** (`colorMode: light/dark/auto`) in settings still controls frontend palette independently. | Set color mode to dark → frontend shows dark palette. Set CC dark mode → admin goes dark, but preview remains light. | CSS custom property migration (25 → CSS vars) could accidentally change the light default values if a hex color is mistranscribed. |
| **R2. postMessage bridge and preview updates** | Changing a setting still updates preview via postMessage. Font selection sends CSS var update via postMessage. | Font dropdown's `change` handler must call the same `onFieldChange` / `debouncedSave` as existing fields. |
| **R3. Existing `typography.heading` / `typography.body` values** | Old saved values ("Inter", "Open Sans", custom fonts) remain in the database and load correctly. | Font dropdown must read and display the existing saved value on load. Custom fonts not in the Google Fonts list must still display correctly. |
| **R4. CTA button functionality** | CTA buttons still navigate to URLs, trigger modals, submit forms. Glow CSS must not interfere with `pointer-events` or click handling. | `box-shadow` with large spread values could overlap adjacent elements but must not block clicks via `pointer-events: none`. |
| **R5. Preview iframe loading** | Full preview load (refresh, page change) still works. Font stylesheet loads without blocking page render. | `font-display: swap` ensures non-blocking. No JS errors from font loading. |
| **R6. Admin UI layout** | Dark mode toggle button in header must not break toolbar layout on narrow screens (< 900px). | Adding a new button to the toolbar must respect existing responsive breakpoints. |
| **R7. Accessibility — keyboard navigation** | Tab order through Command Center includes the new Dark Mode toggle. Focus rings remain visible on all interactive elements. | Star 2.0 focus glow uses `box-shadow` which is visible — and must not be removed by `outline: none` on CTA buttons. |

---

## Spec Gaps & Ambiguities (Risks for Design Phase)

| Gap | Description | Impact |
|-----|-------------|--------|
| **G1. Font list source** | "Curated subset or all fonts filtered" — the spec does not mandate whether to hardcode a curated list (static JSON) or fetch dynamically from the Google Fonts API. Hardcoded lists are more reliable but require manual updates. Dynamic fetching gives fresh data but depends on API availability. | Design must decide: static curated list vs dynamic API fetch with fallback. Static is simpler and matches the fallback requirement naturally. |
| **G2. Dark Mode CSS migration completeness** | "All ~25 hardcoded colors" — the exact count and location of these colors is unknown until a CSS audit is done. Some colors may be in inline styles or JS-generated elements. | Design must include a CSS audit task to inventory every hardcoded color. If colors are found in JS (e.g., `element.style.color = '#333'`), those must also be migrated. |
| **G3. Dark Mode palette values** | The spec does not define the dark mode color values themselves. What shade of dark background? What muted text color? What accent treatment? | Design must define the dark palette as CSS custom properties (e.g., `--vbb-cc-bg-dark`, `--vbb-cc-text-dark`) with specific hex values. |
| **G4. Font dropdown UX — search vs scroll** | If showing 50–100 fonts, a simple `<select>` is usable but poor UX. If showing 200+, a search/filter input is needed. | Design must decide: native `<select>` with grouped fonts, custom dropdown with search, or a combobox pattern. Affects JS bundle size and accessibility. |
| **G5. Dark Mode transition between states** | The spec does not specify whether the dark/light switch should animate (CSS transition) or snap instantly. | Instant switch is simpler and avoids layout thrashing. But a brief CSS transition on `background-color` and `color` (200ms) would feel polished. Tradeoff: transitions on every element may cause performance issues. |
| **G6. Google Fonts versioning** | Google Fonts CSS URLs include version parameters. If a font updates, the old version may be cached. | Design should consider adding a version query param (e.g., `&v=1`) to the Google Fonts URL for cache busting, or rely on the Google CDN's own cache headers. |
| **G7. Dark Mode and existing `colorMode` interaction** | The global settings have `colorMode: light | dark | auto` which controls frontend colors. The new admin dark mode is independent. If a user sets both to dark, the admin is dark AND the preview is dark — but through different mechanisms. Is this a problem? | No functional conflict, but the design should document the separation clearly to avoid developer confusion. |

---

## Success Gates (for sdd-verify)

- [ ] **Star 2.0**: CTA button shows glow on hover — verified via screenshot diff or browser test
- [ ] **Star 2.0**: CTA button shows glow on keyboard focus (accessibility)
- [ ] **Star 2.0**: Disabled CTA button shows NO glow on hover
- [ ] **Star 2.0**: `--vbb-pro-glow-intensity: 12px` produces a 12px glow spread
- [ ] **Star 2.0**: No JavaScript errors on browsers without `box-shadow` animation support
- [ ] **Font Selection**: Google Fonts dropdown replaces text inputs for `typography.heading` and `typography.body`
- [ ] **Font Selection**: Selecting "Inter" updates preview with Inter font via postMessage
- [ ] **Font Selection**: Full preview reload loads Google Fonts stylesheet
- [ ] **Font Selection**: Google Fonts API unavailable → dropdown falls back to text input with warning
- [ ] **Font Selection**: FOUT is visible (acceptable) — no `font-display: block` used
- [ ] **Dark Mode**: Toggle button exists in Command Center toolbar
- [ ] **Dark Mode**: `.vbb-command-center--dark` class is applied on toggle
- [ ] **Dark Mode**: Preference persists across page reload (`localStorage`)
- [ ] **Dark Mode**: System `prefers-color-scheme: dark` is respected on first load
- [ ] **Dark Mode**: Preview iframe remains light when admin dark mode is active
- [ ] **Dark Mode**: All ~25 hardcoded colors migrated to CSS custom properties (verified via grep: zero `color: #` or `background: #` in admin CSS scoped to `.vbb-command-center`)
- [ ] **Dark Mode**: No flash of light mode on page load with dark preference
- [ ] **Shared**: All 7 regression areas pass automated or manual check
- [ ] **Shared**: Zero PHP warnings/notices (no PHP changes expected, but verify after deployment)
- [ ] **Shared**: Zero JS console errors on Chrome, Firefox, Safari, and Edge
