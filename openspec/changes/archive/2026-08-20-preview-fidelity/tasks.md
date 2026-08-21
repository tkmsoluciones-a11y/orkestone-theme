# Tasks: preview-fidelity (Spec 1)

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~80–120 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Chain strategy | pending |
| Delivery strategy | single-pr |
| Decision needed before apply | No |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Affected Files

| File | Role |
|---|---|
| `orkestone-theme/inc/pro-css-vars.php` | Remove duplicate listener; confirm PHP CSS variable source of truth |
| `orkestone-theme/inc/pro-admin.php` | Confirm sole receiver `vbb_pro_inject_preview_script()` at priority 5 |
| `orkestone-theme/assets/js/admin-pro.js` | Extend `buildCssVars()`, expand `_blockKeyToSectionClass()`, fix dark mode preview |

---

## Phase 1: PHP Receiver Consolidation

- [x] 1.1 Confirm `vbb_pro_preview_message_listener()` is registered on `wp_head` at priority 31 inside `inc/pro-css-vars.php` (not `inc/pro-admin.php`).
- [x] 1.2 Remove the `add_action('wp_head', 'vbb_pro_preview_message_listener', 31)` hook and the `vbb_pro_preview_message_listener()` function body from `inc/pro-css-vars.php`.
- [x] 1.3 Confirm `vbb_pro_inject_preview_script()` is registered on `wp_head` at priority 5 in `inc/pro-admin.php` (the sole surviving receiver).
- [x] 1.4 Verify no other code path calls or references the removed function (grep for `vbb_pro_preview_message_listener` across the theme directory).

---

## Phase 2: JS CSS Variable & Rule Parity

- [x] 2.1 In `buildCssVars()` (`admin-pro.js` ~L3689), add missing `:root` variable emissions: `--vbb-pro-shadow`, `--vbb-pro-section-spacing`, `--vbb-pro-button-radius`, `--vbb-pro-button-shadow`, `--vbb-pro-button-padding`, `--vbb-pro-text-color`, `--vbb-pro-heading-color`, `--vbb-pro-glow-intensity` — pulling values from `s.palettes[mode]` and `s.layout` where available.
- [x] 2.2 In `buildCssVars()` after the existing class-level override block (L3721), append rulesets for `.vbb-pro-button`, `.vbb-pro-content`, `.vbb-pro-wide`, `.vbb-pro-card`, `.vbb-pro-nav` referencing the new and existing custom properties.
- [x] 2.3 In the footer section of `buildCssVars()` (L3741–3751), extend footer variable output to emit a `.vbb-site-footer :root` ruleset binding footer tokens to CSS vars in addition to (or instead of) inline class vars.

---

## Phase 3: Block & Page-Scoped Selector Mapping

- [x] 3.1 Extend `_blockKeyToSectionClass()` map (L3757–3770) to include: `stats → 'stats'`, `gallery → 'gallery'`, `video → 'video'`, `newsletter → 'newsletter'`, `map → 'map'`, `comparison → 'comparison'`, `blog → 'blog'`.
- [x] 3.2 Verify `map`, `comparison`, and `blog` have corresponding entries in `_sectionInfo` (L3779–3797); add missing entries if absent.
- [x] 3.3 Add pageScoped awareness: when `buildCssVars` is called with a page-scoped context or `s.pageScoped` flag is set, emit block variable rules under `.vbb-pro-page-wrapper` instead of bare `.vbb-section-*` selectors, so the preview iframe reflects root-level styling.

---

## Phase 4: Dark Mode Preview Fix

- [x] 4.1 Locate the dark mode preview filter logic in `admin-pro.js` around the `buildCssVars('dark')` call sites (L188, L3550).
- [x] 4.2 Ensure dark mode CSS variables are resolved directly from `s.palettes.dark` without applying a secondary CSS `filter: invert()` pass to the preview iframe — the dark palette values are authoritative and must not be inverted again.
- [x] 4.3 Confirm the fix is preview-only (guarded by `overrideMode === 'dark'` or CC preview context); production `vbb_pro_print_css_vars()` output is untouched.

---

## Phase 5: Verification

- [x] 5.1 Code review: confirmed `buildCssVars()` variable set matches `vbb_pro_print_css_vars()` output — all 14 palette vars + shadow/spacing/button-radius/glow/text-color/heading-color/button-padding/button-shadow present on both sides.
- [x] 5.2 Code review: `vbb_pro_preview_message_listener()` removed; `vbb_pro_inject_preview_script()` at priority 5 is the sole `vbb:css-vars` receiver (confirmed via grep — zero references to old listener name remain).
- [x] 5.3 Code review: all 7 block-key resolution scenarios verified — `_blockKeyToSectionClass()` map includes `stats→stats`, `gallery→gallery`, `video→video`, `newsletter→newsletter`, `map→map`, `comparison→comparison`, `blog→blog`.
- [x] 5.4 Code review: `_blockKeyToSectionClass()` returns `.vbb-pro-page-wrapper .vbb-section-*` when `CC.state.currentPageId` is set (page-scoped context in CC).
- [x] 5.5 Code review: `<style id="vbb-dark-preview-style">` with `filter:invert(1) hue-rotate(180deg)` removed from `pro-css-vars.php`; dark palette now driven by JS `buildCssVars('dark')` postMessage only.
- [x] 5.6 Code review: `vbb_pro_print_css_vars()` is completely untouched (confirmed function still outputs identical CSS); only `vbb_pro_preview_message_listener()` (preview-only) was removed.
- [x] 5.7 Code review: no PHP syntax issues in `pro-css-vars.php`; `admin-pro.js` helper functions (`_shadowValue`, `_spacingValue`) and map expansions syntax-verified.

---

## Rollback

Revert `admin-pro.js` to prior version and restore `vbb_pro_preview_message_listener()` and its `add_action` hook in `inc/pro-css-vars.php`. No database migration to reverse.