# Design: Small Enhancements

## Technical Approach

Three independent frontend-only enhancements to the Command Center admin UI. Star 2.0 is pure CSS (progressive enhancement via `box-shadow` animation). Font Selection replaces text inputs with a curated Google Fonts dropdown (static JSON array, no external API dependency). Dark Mode migrates ~30 hardcoded colors to CSS custom properties with `.vbb-command-center--dark` class toggling, persisted via `localStorage` with `prefers-color-scheme` detection on first load.

## Architecture Decisions

### Decision: Star 2.0 animation strategy

| Option | Tradeoff |
|--------|----------|
| A: `@keyframes` + `box-shadow` | Simple, no pseudo-elements, works with existing `.wp-block-button__link` rule |
| B: `::before`/`::after` ring | More complex, needs `pointer-events: none`, harder to coordinate with existing border-radius |

**Chosen: A** — `box-shadow` transition on `:hover`/`:focus-visible`, gated by `:not([disabled])`. Per spec: glow uses the button's `--vbb-pro-primary` color at 60% opacity. No `@keyframes` needed — a CSS `transition: box-shadow 0.3s ease` is sufficient for the hover enter/exit. The spec's `--vbb-pro-glow-intensity` controls spread. Disabled buttons get `box-shadow: none !important`.

### Decision: Font list source

| Option | Tradeoff |
|--------|----------|
| A: Curated static JSON (~60 fonts) | Always available, no API dependency, predictable UX. User can still type any font manually via the existing text-input fallback. |
| B: Google Fonts API fetch (~1500 fonts) | Fresh data but depends on API availability, slower load, needs search/filter UX. The spec's fallback path (text input on API failure) is half the solution. |

**Chosen: A** — Static curated list of ~60 popular Google Fonts grouped by category (Sans, Serif, Display, Handwriting, Monospace). Stored as a JSON array in `admin-pro.js`. Font-family preview rendered via inline `font-family` style on each `<option>` (or a custom dropdown for better UX). On font selection: `onFieldChange` updates settings, `postMessage` pushes `vbb:css-vars` with the new `--vbb-pro-heading-font` / `--vbb-pro-body-font`. Full preview loads inject a `<link rel="stylesheet">` in the preview `<head>` via `wp_head`.

### Decision: Dark mode palette values

**Chosen**: Dark surface `#1a1d23`, text `#e4e7eb`, muted `#9aa0a6`, borders `#333840`. Accent green (#2c5f2d) replaced with muted off-white `#c8d6c8` for backgrounds, kept at `#3d8b40` for interactive elements. Based on the existing light palette's structure: 7 palette keys (primary, secondary, accent, background, surface, text, mutedText) plus ~20 admin-specific vars for component surfaces, borders, status indicators, toasts, skeleton loading, and menu editor.

### Decision: Dark mode toggle location

| Option | Tradeoff |
|--------|----------|
| A: CC header, right of `<h1>` | Visible, matches "toolbar" UX pattern. Needs minimal PHP change. |
| B: Sidebar toolbar | Less prominent, competes with existing action buttons (Save, Export, etc.). |

**Chosen: A** — PHP modifies `vbb_pro_render_command_center()` to inject a sun/moon icon button next to the `<h1>` title. JS on init: checks `localStorage.getItem('vbb-cc-dark-mode')` → falls back to `matchMedia('(prefers-color-scheme: dark)')` → applies/removes `.vbb-command-center--dark`. Toggle writes `localStorage` on click.

## Data Flow

```
Star 2.0          CSS var change on :hover/:focus  →   browser paints box-shadow   →   no JS needed
                     (pure CSS, .wp-block-button__link transition)

Font Selection    user picks font
                     → onFieldChange('typography.heading', 'Inter')
                     → debouncedSave → XHR POST /vertical-settings
                     → saveSettings success → CC.buildCssVars()
                     → postMessage({type:'vbb:css-vars', styleTag:...})
                     → preview iframe: styleEl.textContent = styleTag
                     → full preview reload: wp_head injects <link href="https://fonts.googleapis.com/...">

Dark Mode         user clicks toggle
                     → JS: CC.el.commandCenter.classList.toggle('vbb-command-center--dark')
                     → localStorage.setItem('vbb-cc-dark-mode', 'true'|'false')
                     → init: localStorage ? 'true' : matchMedia('(prefers-color-scheme: dark)')
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/pro-css-vars.php` | Modify | Add `--vbb-pro-glow-intensity` var (custom property) to the `:root` block (line 117-127) |
| `inc/pro-admin.php` | Modify | Inject Google Fonts `<link>` in preview `<head>` (within `vbb_pro_inject_preview_script` or adjacent). Add dark mode toggle button next to `<h1>` in `vbb_pro_render_command_center()`. |
| `assets/css/admin-pro.css` | Modify | Add `--vbb-admin-*` CSS vars on `.vbb-command-center`. Add `.vbb-command-center--dark` overrides. Add font dropdown search styling. Add glow transition on `.wp-block-button__link`. |
| `assets/js/admin-pro.js` | Modify | Add `renderTypography()` dropdown logic (replaces text inputs). Add curated Google Fonts JSON. Add dark mode toggle init. Add `buildCssVars()` font family injection (already exists — minor tweak). |
| *(none)* functions.php | *No change* | Toggle lives in CC header (PHP render), not global admin menu. |

## Interfaces / Contracts

**CSS custom properties** (new, scoped to `.vbb-command-center`):

```css
--vbb-admin-bg: #fff;
--vbb-admin-text: #172033;
--vbb-admin-text-secondary: #667085;
--vbb-admin-border: #dcdcde;
--vbb-admin-border-light: #eaeced;
--vbb-admin-surface: #f6f7f7;
--vbb-admin-card-bg: #fff;
--vbb-admin-accent: #2c5f2d;
--vbb-admin-accent-hover: #3d8b40;
--vbb-admin-danger: #b32d2e;
--vbb-admin-focus-ring: rgba(44,95,45,.15);
--vbb-admin-skeleton-bg: #f0f0f1;
--vbb-admin-toast-success-bg: #edfaef;
--vbb-admin-toast-error-bg: #fcf0f1;
--vbb-admin-menu-drag: #999;
--vbb-admin-overlay: rgba(255,255,255,.85);
```

**Dark overrides** invert all of the above (e.g., `--vbb-admin-bg: #1a1d23`, `--vbb-admin-text: #e4e7eb`).

**localStorage key**: `vbb-cc-dark-mode` — values `'true'` | `'false'`.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `localStorage` read/write for dark mode | Manual in browser console |
| Visual | Star glow on hover/focus in preview | Visual inspection across 3 browsers |
| Visual | Dark mode palette coverage (~30 vars) | Visual QA: every card, toast, status bar, skeleton, menu editor |
| Integration | Font dropdown changes settings + preview updates | Select font → verify XHR + postMessage + iframe paint |
| Regression | Preview isolation (dark mode doesn't affect iframe) | Enable dark mode → inspect iframe computed styles |
| Accessibility | Keyboard nav through toggle + focus glow on buttons | Tab through CC, verify `:focus-visible` glow on CTA |

## Migration / Rollout

No migration required. Dark mode CSS var migration is a find-and-replace of hardcoded colors → `var(--vbb-admin-*)` with fallback to the original hex value for backward compat. The `var(--name, fallback)` pattern ensures existing colors render if the var is undefined (e.g., during a partial deployment).

## Open Questions

- [x] **Font list source**: Resolved — static curated list (~60 fonts), no API dependency.
- [x] **Dark palette values**: Resolved — defined above (dark surface #1a1d23, text #e4e7eb).
- [ ] **Font dropdown UX**: Need to decide between native `<select>` with inline font preview (limited UX but simple) vs. custom dropdown with search (better UX, more JS). Recommendation: custom dropdown with search, reusing the existing `buildOptions` and `_handleChange` patterns.
- [ ] **Dark mode CSS transition**: Recommend instant snap (no transition) to avoid layout thrashing on the ~30 vars. Can revisit with `transition: background-color .2s, color .2s` if performance is acceptable.
- [ ] **Glow `--vbb-pro-glow-intensity` unit**: Spread value — default `8px` as per spec. Must use `px` unit for `box-shadow` spread.
