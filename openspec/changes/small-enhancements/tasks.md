
# Tasks: Small Enhancements — Star 2.0, Font Selection, Dark Mode

**Change**: small-enhancements
**Status**: applied
**Next**: sdd-verify
**Delivery Strategy**: 1 PR with 3 commits (one per enhancement group). Star 2.0 first (10 min), then Fonts (30 min), then Dark Mode (70 min). Total ~110 min dev + ~45 min review.

---

## Enhancement 1: Star 2.0 — Button Glow (10 min)

- [x] **T1. Add glow intensity CSS var + hover/focus glow on `.wp-block-button__link`**: Add `--vbb-pro-glow-intensity` (default `8px`) to the `:root` block in `pro-css-vars.php` (line 117-127). Add `transition: box-shadow 0.3s ease` to the existing `.wp-block-button__link` rule, and add `:hover`/`:focus-visible` glow using `box-shadow` with `--vbb-pro-primary` at 60% opacity. Gate with `:not([disabled])` — disabled buttons get `box-shadow: none !important`. No JS changes needed.
  Est: 10 min | Review: 5 min
  **Files**: `orkestone-theme/inc/pro-css-vars.php`
  **Acceptance Criteria**:
  - [ ] `--vbb-pro-glow-intensity` is defined in `:root` with default `8px`
  - [ ] `.wp-block-button__link` has `transition: box-shadow 0.3s ease`
  - [ ] `:hover` and `:focus-visible` produce a colored glow using `--vbb-pro-primary` at 60% opacity
  - [ ] Disabled buttons (`[disabled]`) show NO glow via `!important`
  - [ ] Zero JS changes — pure CSS
  - [ ] Verify glow appears in preview iframe (frontend page)

---

## Enhancement 2: Font Export/Import (30 min)

- [x] **T2. Add curated Google Fonts JSON + `renderTypography()` dropdown logic**: Add a static `CC.fonts` array (~60 popular Google Fonts) grouped by category (sans-serif, serif, display, handwriting, monospace) in `admin-pro.js`. Replace the existing text `<input>` fields in `renderTypography()` with custom dropdowns that render each font option in its own typeface via inline `font-family` style. Include a search input above the dropdown list for filtering. Preserve manual text entry as a fallback for custom fonts not in the curated list — show a text input when the user selects "Custom…" from the dropdown. On font selection, call the existing `onFieldChange('typography.heading', value)` / `onFieldChange('typography.body', value)` to trigger save + preview update via postMessage.
  Est: 20 min | Review: 10 min
  **Files**: `orkestone-theme/assets/js/admin-pro.js`
  **Acceptance Criteria**:
  - [ ] `CC.fonts` array defined with ~60 fonts grouped by category
  - [ ] `renderTypography()` renders dropdowns instead of text inputs for heading and body fonts
  - [ ] Each dropdown option previews in its own typeface
  - [ ] Search input filters the font list as the user types
  - [ ] "Custom…" option at the top reveals a text input for manual font entry
  - [ ] Existing saved font values are pre-selected in the dropdown
  - [ ] Selecting a font calls `onFieldChange()` — triggers save + postMessage CSS var update
  - [ ] No regression: existing saved `typography.heading` / `typography.body` values still load and display

- [x] **T3. Add Google Fonts `<link>` injection in preview `<head>`**: In `pro-admin.php`'s `vbb_pro_inject_preview_script()`, inject a `<link rel="stylesheet">` for Google Fonts based on the saved `typography.heading` and `typography.body` settings. Use `font-display: swap` in the URL to avoid blocking text render (FOUT acceptable). Wrap in a check to only inject when the font family looks like a Google Font (not a system font stack like "Georgia, serif").
  Est: 5 min | Review: 5 min
  **Files**: `orkestone-theme/inc/pro-admin.php`
  **Acceptance Criteria**:
  - [ ] Google Fonts `<link>` is injected in preview `<head>` when `?vbb_preview=` is present
  - [ ] Only injected when font setting is a single-word font name (not a CSS font stack)
  - [ ] URL includes `display=swap` parameter
  - [ ] Full preview reload shows the selected Google Font rendered on the page
  - [ ] No blocking of text render while font downloads (FOUT visible but acceptable)

- [x] **T4. Add font dropdown search styling**: Add CSS in `admin-pro.css` for the custom font dropdown: dropdown container (positioned, z-index), search input, scrollable option list with max-height, font preview in option items, active/hover states, and the "Custom…" text input fallback.
  Est: 5 min | Review: 5 min
  **Files**: `orkestone-theme/assets/css/admin-pro.css`
  **Acceptance Criteria**:
  - [ ] Dropdown container has proper positioning and z-index (above other card content)
  - [ ] Search input is styled consistently with existing admin inputs
  - [ ] Option list is scrollable with a max-height
  - [ ] Active/hover states are visible on dropdown options
  - [ ] Custom font text input matches the styling of existing text inputs

---

## Enhancement 3: Dark Mode (70 min)

- [x] **T5. Define `--vbb-admin-*` CSS custom properties + dark palette overrides**: Add CSS custom properties block on `.vbb-command-center { ... }` with all `--vbb-admin-*` properties (background, text, text-secondary, border, border-light, surface, card-bg, accent, accent-hover, danger, focus-ring, skeleton-bg, toast-success-bg, toast-error-bg, menu-drag, overlay — ~16 vars). Add `.vbb-command-center--dark { ... }` block with inverted values for all of them (dark surface `#1a1d23`, text `#e4e7eb`, muted `#9aa0a6`, borders `#333840`, etc.). Both blocks go in `admin-pro.css`.
  Est: 15 min | Review: 10 min
  **Files**: `orkestone-theme/assets/css/admin-pro.css`
  **Acceptance Criteria**:
  - [ ] All `--vbb-admin-*` CSS vars defined on `.vbb-command-center` with light-palette defaults
  - [ ] `.vbb-command-center--dark` defines overrides for every `--vbb-admin-*` var
  - [ ] CSS vars use `var(--vbb-admin-*, <fallback>)` pattern with original hex fallback
  - [ ] All 16 vars from the Interfaces/Contracts section are covered
  - [ ] Dark palette values match design spec (bg: #1a1d23, text: #e4e7eb, etc.)

- [x] **T6. Add dark mode toggle button to Command Center header**: In `pro-admin.php`, modify `vbb_pro_render_command_center()` to inject a dark mode toggle button (sun/moon icon) next to the `<h1>` title. The button should have `id="vbb-cc-dark-toggle"` and an accessible `aria-label`. Add the `.vbb-command-center` class wrapper if not already present (it is — on line 436 `<div class="wrap vbb-pro-wrap vbb-command-center">`).
  Est: 5 min | Review: 5 min
  **Files**: `orkestone-theme/inc/pro-admin.php`
  **Acceptance Criteria**:
  - [ ] Dark mode toggle button exists next to `<h1>` in Command Center
  - [ ] Button has `id="vbb-cc-dark-toggle"`
  - [ ] Button has `aria-label="Toggle dark mode"`
  - [ ] Button renders sun icon (🌙) / moon icon (☀️) — can use unicode text
  - [ ] Layout does not break on narrow screens (< 900px)

- [x] **T7. Add dark mode JS toggle init**: In `admin-pro.js`, add `CC.initDarkMode()` called from `init()`. Logic: check `localStorage.getItem('vbb-cc-dark-mode')` → if `'true'` add `.vbb-command-center--dark` class, if `'false'` remove it, if `null` check `matchMedia('(prefers-color-scheme: dark)')` and apply. Add click handler on the toggle button that toggles the class and writes to `localStorage`. Update the toggle button icon text to match current state.
  Est: 15 min | Review: 5 min
  **Files**: `orkestone-theme/assets/js/admin-pro.js`
  **Acceptance Criteria**:
  - [ ] `CC.initDarkMode()` called from `init()`
  - [ ] On load: `localStorage` check takes priority over `prefers-color-scheme`
  - [ ] Class `.vbb-command-center--dark` is applied before paint (no FOUC of light mode)
  - [ ] Toggle button click: toggles class, writes `localStorage`, updates icon
  - [ ] `localStorage` key is `vbb-cc-dark-mode`, values `'true'` / `'false'`
  - [ ] Preview iframe is NOT affected — no class added to iframe content
  - [ ] Zero console errors on toggle

- [x] **T8. Migrate hardcoded admin colors to `var(--vbb-admin-*)`**: Find-and-replace pass across `admin-pro.css`: replace every hardcoded color value scoped to `.vbb-command-center` / `.vbb-cc-*` / `#vbb-cc-*` with `var(--vbb-admin-<key>, <original-hex>)`. Focus on ~25 unique color values: backgrounds, text, borders, status bars, toasts, skeletons, menu editor, empty states, overlays, focus rings. Use the closest semantic var from the defined set. Keep original hex as CSS var fallback for backward compat.
  Est: 20 min | Review: 15 min
  **Files**: `orkestone-theme/assets/css/admin-pro.css`
  **Acceptance Criteria**:
  - [ ] Every hardcoded color in `admin-pro.css` scoped to `vbb-cc-*` / `vbb-command-center` is migrated
  - [ ] Each `var()` includes the original hex as fallback: `var(--vbb-admin-text, #172033)`
  - [ ] No color values leaked from adjacent selectors (`.vbb-pro-*` outside `.vbb-command-center`)
  - [ ] Light mode renders identically to before the migration (fallback ensures this)
  - [ ] Zero `color: #...` or `background: #...` remain on `.vbb-cc-*` selectors (verifiable via grep)
  - [ ] `grep -c '#[0-9a-f]\{6\}'` on `admin-pro.css` for `vbb-cc-` selectors returns 0 after migration

- [ ] **T9. Visual QA — dark mode coverage**: Manual visual pass across all Command Center UI surfaces in both light and dark modes. Check: page selector card, all setting card types (Brand, Site Config, Navigation, Colors, Typography, Layout, Menu Editor, Blocks, Color Mode), status bar (idle/saving/saved/error), toast notifications, skeleton loading, color picker/copy button, preset buttons, refresh button, toolbar sidebar buttons, overlay, menu editor items, empty states, save flash animation. Verify preview iframe is NOT affected by dark mode toggle.
  Est: 15 min | Review: 0 min (QA task)
  **Files**: Visual inspection only
  **Acceptance Criteria**:
  - [ ] All cards render correctly in light mode (no color difference from pre-migration baseline)
  - [ ] All cards render correctly in dark mode (no unreadable text, invisible borders, or missed colors)
  - [ ] Status bar states (idle, saving, saved, error) visible in both modes
  - [ ] Toast types (success, error, info, confirm) visible in both modes
  - [ ] Skeleton loading uses dark-palette placeholder colors in dark mode
  - [ ] Menu editor renders with correct backgrounds, borders, drag handles in dark mode
  - [ ] Copy button tooltip visible in dark mode
  - [ ] Focus rings visible on all interactive elements in both modes
  - [ ] Preview iframe remains light when admin dark mode is active
  - [ ] No layout shifts, broken text contrast, or unreadable states in either mode

---

## Regression Checklist (must verify after all tasks)

- [ ] **R1**: Existing `colorMode` setting (Light/Dark/Auto) still controls frontend palette independently — set to dark → preview shows dark palette, admin stays in selected dark mode state
- [ ] **R2**: postMessage bridge still updates preview on non-content setting changes — change a color → preview updates without full reload
- [ ] **R3**: Old saved `typography.heading` / `typography.body` values load correctly in new dropdown
- [ ] **R4**: CTA buttons still clickable/functional — glow `box-shadow` does not overlap adjacent elements
- [ ] **R5**: Full preview reload still works — Google Fonts stylesheet loads without blocking page render
- [ ] **R6**: Dark mode toggle button in header doesn't break toolbar layout on narrow screens
- [ ] **R7**: Tab order includes dark mode toggle — focus rings visible on all interactive elements
- [ ] **R8**: Zero JS console errors on Chrome, Firefox, Safari, Edge
- [ ] **R9**: Zero PHP warnings/notices (no PHP changes expected, but verify)
