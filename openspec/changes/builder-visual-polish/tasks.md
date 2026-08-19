# Tasks: Builder Visual Polish (UI/UX)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~950 (Stage 1: ~540, Stage 2: ~410) |
| 400-line budget risk | High |
| Review budget (spec) | 800 lines |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Stage 1) → PR 2 (Stage 2) |
| Delivery strategy | force-chained |
| Chain strategy | stacked-to-main |

```
Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High
```

### Suggested Work Units

| Unit | Goal | Likely PR | Base Branch | Est. Lines |
|------|------|-----------|-------------|------------|
| 1 | UX Feedback & Polish (status bar, toasts, skeletons, UI polish, empty states) | PR 1 | `main` | ~540 |
| 2 | Advanced Preview & Per-Block Colors (postMessage bridge, CSS injection, per-block color model) | PR 2 | `main` | ~410 |

**Rationale**: Stage 1 is purely frontend (CSS/JS/DOM) — zero data model changes, zero database migrations. It delivers immediate UX value independently. Stage 2 adds architectural changes (postMessage, per-block colors) that build on Stage 1's shared API but do not depend on Stage 1's CSS output. Both can merge to `main` independently in order. Stacked-to-main avoids a long-lived feature branch and lets Stage 1 ship as soon as it's ready.

---

## Stage 1 — UX Feedback & Polish (PR 1)

- [x] **TASK-VP1.1** — Render status bar DOM in `vbb_pro_render_command_center()` and add CSS for idle/saving/saved/error states with transitions.
  - Files: `inc/pro-admin.php`, `assets/css/admin-pro.css`
  - Verify: Modify field → `#vbb-cc-status-bar` shows "Saving…" with spinner, then "Saved ✓" (green, 2s fade)
  - Complexity: Low

- [x] **TASK-VP1.2** — Create toast container DOM, `CC.showToast()` JS method, toast CSS with 4 types (success/error/info/confirm), stacking layout, slide-in animation, auto-dismiss timers, and dismiss button.
  - Files: `assets/js/admin-pro.js`, `assets/css/admin-pro.css`, `inc/pro-admin.php`
  - Verify: Call `CC.showToast()` rapidly 3× → 3 stacked toasts in `#vbb-cc-toast-container`
  - Complexity: Medium

- [x] **TASK-VP1.3** — Replace ALL `alert()` and `confirm()` calls in `admin-pro.js` with `CC.showToast()` and `CC.showStatus()`. Covers: Regenerate Pages confirmation, Reset confirmation, error dialogs, save results.
  - Files: `assets/js/admin-pro.js`
  - Verify: Zero `alert(` occurrences in `admin-pro.js` after change; each path shows toast/status instead
  - Complexity: Low

- [x] **TASK-VP1.4** — Add `.vbb-saved-flash` CSS animation (green border pulse, 1s) and JS class toggling after successful save in `CC._handleChange` / save callback.
  - Files: `assets/css/admin-pro.css`, `assets/js/admin-pro.js`
  - Verify: Change field → green border flash on save confirmation
  - Complexity: Low

- [x] **TASK-VP1.5** — Replace static "Loading…" text with skeleton shimmer cards. Add `.vbb-cc-skeleton` CSS with 3 height variants (short/medium/tall) and shimmer `@keyframes` animation. JS renders skeletons before data fetch, replaces with real cards on data.
  - Files: `assets/css/admin-pro.css`, `assets/js/admin-pro.js`
  - Verify: Initial load shows `.vbb-cc-skeleton` elements, not text; real cards replace skeletons after data
  - Complexity: Medium

- [x] **TASK-VP1.6** — UI polish: scoped font stack (Inter + system fallback), card radius 18px, left-border accent on hover, layered shadows (default/hover/active), smooth CSS transitions on focus rings/card hover/save indicators/field focus.
  - Files: `assets/css/admin-pro.css`
  - Verify: Inspect `.vbb-cc-card` → border-radius 18px, hover shows left accent border, shadow deepens on hover
  - Complexity: Low

- [x] **TASK-VP1.7** — Color picker hex display: render hex text next to each `input[type="color"]` with editable hex input and copy-to-clipboard button ("Copied" tooltip). Add empty states with icons/CTAs for Menu Editor, Blocks, Pages. Remove `#vbb-cc-menu-status`, unify under global status bar.
  - Files: `assets/js/admin-pro.js`, `assets/css/admin-pro.css`, `inc/pro-admin.php`
  - Verify: Hex value shown next to color swatch, copy button works; empty lists show CTA not "No data" text
  - Complexity: Medium

---

## Stage 2 — Advanced Preview & Per-Block Colors (PR 2)

- [x] **TASK-VP2.1** — Extend settings data model: add `blocks.{key}.colors` sub-object (7 keys: primary, secondary, accent, background, surface, text, mutedText). Update `vbb_pro_sanitize_settings()` to sanitize per-block colors via `sanitize_hex_color()`. Add empty `colors` array for backward-compat blocks. Create `vbb_pro_block_color_keys()` helper returning the 7 allowed keys.
  - Files: `inc/pro-settings.php`
  - Verify: Submit valid hex colors → stored correctly; invalid hex → falls back to empty (inherit)
  - Complexity: Medium

- [x] **TASK-VP2.2** — Create `vbb_pro_section_class_for_block()` mapping block key → section CSS class (handles `contact`→`contact-section`, `pricing`→`pricing-tables` exceptions). Update `vbb_pro_print_css_vars()` to emit `.vbb-section-{type} { --vbb-pro-{colorKey}: {value}; }` for each block with non-empty colors. Ensure per-page block color overrides generate `.page-id-{id} .vbb-section-{type}` selectors with higher specificity.
  - Files: `inc/pro-css-vars.php`
  - Verify: Set `hero.colors.background=#ff0000` → frontend shows `.vbb-section-hero{--vbb-pro-background:#ff0000}`
  - Complexity: Medium

- [x] **TASK-VP2.3** — Implement postMessage bridge: `CC.postMessage()` method with `targetOrigin` from `vbbCommandCenterData.previewOrigin`. Send `vbb:css-vars` and `vbb:setting-update` message types. Add `CC.supportsPostMessage` flag with try/catch fallback to `CC.refreshPreview()`. Define full protocol (6 message types with `vbb:` prefix). Handle `vbb:ready` signal from iframe.
  - Files: `assets/js/admin-pro.js`
  - Verify: Change color → `window.postMessage` fires with `vbb:css-vars`; if postMessage fails → fallback to full reload
  - Complexity: High

- [x] **TASK-VP2.4** — Inject inline script into preview page `<head>` (in `vbb_pro_render_command_center()`) that receives `vbb:css-vars` messages and updates/creates `#vbb-pro-injected-css` `<style>` element. Script validates `event.origin` against `?vbb_origin=` URL param. Sends `vbb:ready` signal to parent on load.
  - Files: `inc/pro-admin.php`
  - Verify: After color change, inspect iframe `document.head` → `#vbb-pro-injected-css` contains updated CSS vars
  - Complexity: Medium

- [x] **TASK-VP2.5** — Add preview loading overlay (`#vbb-cc-preview-overlay`) with spinner, shown on iframe `load` start, hidden on `load`/`error` events. Error state with retry button. Add responsive preset buttons (Desktop/Tablet 768px/Mobile 375px) in preview toolbar with active highlight. Implement resize handle for iframe container.
  - Files: `assets/js/admin-pro.js`, `assets/css/admin-pro.css`, `inc/pro-admin.php`
  - Verify: Click "Tablet" → iframe width 768px, button highlighted; iframe loading → overlay spinner, `load` event → overlay hides
  - Complexity: Medium

- [x] **TASK-VP2.6** — Add per-block color picker UI in `renderBlockSettings()`: when block is enabled and expanded, render a "Colors" section with 5 color pickers (excludes `primary`, `secondary`). Each picker uses `data-path="blocks.{key}.colors.{colorKey}"`. Include hex display and copy button matching TASK-VP1.7 pattern.
  - Files: `assets/js/admin-pro.js`
  - Verify: Enable hero block → "Colors" section with 5 swatches; changing color triggers `debouncedSave()` and preview update
  - Complexity: Medium

- [x] **TASK-VP2.7** — Split color picker event handlers: `input` event → `CC._handleColorInput()` (preview-only via postMessage, no XHR); `change` event (blur) → `CC._handleChange()` → `CC.debouncedSave()`. Non-color fields unchanged. Add `CC.buildCssVars()` method generating full CSS vars string for postMessage payload.
  - Files: `assets/js/admin-pro.js`
  - Verify: Drag color slider → Network tab: zero XHR. Release → one XHR fires
  - Complexity: Medium
