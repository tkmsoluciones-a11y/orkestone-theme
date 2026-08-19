# Tasks: Visual Enhancements (Top 5)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~315 (+160 CSS, +130 JS, +25 PHP) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | single-pr |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Low

## Phase 1: CSS Foundation

- [x] 1.1 **Transition CSS vars**: Add `--vbb-cc-transition-{fast(0.15s),normal(0.3s),slow(0.5s)}` on `.vbb-command-center`. Wire into `buildCssVars()` in `admin-pro.js`.
- [x] 1.2 **Card reveal keyframe**: Add `@keyframes vbb-cc-card-reveal` — opacity 0→1, translateY(16px)→0, ease-out, duration via `--vbb-cc-transition-normal`. In `assets/css/admin-pro.css`.
- [x] 1.3 **Timing utility classes**: Add `.vbb-cc-timing-{fast,normal,slow}`. Viewport and card transitions use normal timing. No `transition: all`.
- [x] 1.4 **Font dropdown CSS refactor**: Replace `display:none` toggle with `max-height: 0→280px` + `opacity` + `overflow:hidden` transition. In `assets/css/admin-pro.css`.

## Phase 2: Core Features — JS + PHP

- [x] 2.1 **Zoom X2 toggle**: Button in preview toolbar (`inc/pro-admin.php`). Toggles `.vbb-cc-preview-viewport--zoomed` on viewport. `scale(2)` with `transform-origin: top left`, overflow scroll, no localStorage.
- [x] 2.2 **Card hover transitions**: Add 0.3s `translateY(-2px)` + `box-shadow` expansion on `.vbb-cc-card:hover`. Performance-friendly selectors only.
- [x] 2.3 **Stagger card animation**: In `renderCards()`, loop `.vbb-cc-card` nodes and set `style.animationDelay = (i * 80) + 'ms'`. Add `.vbb-cc-card-animate` class. Animate on every `init()`.
- [x] 2.4 **Font dropdown JS refactor**: Refactored CSS to use max-height/opacity/visibility transition. JS class toggle behavior preserved — no JS changes needed for the animation aspect. Maintains search filter and custom input.
- [x] 2.5 **Preset selector**: New card in CC with `<select>` of presets + "Apply" button. `applyPreset()` deep-merges preset settings with existing, re-renders cards, calls `debouncedSave()`, sends CSS vars via postMessage.
- [x] 2.6 **Dark preview toggle**: Toolbar button sends `postMessage({ type: 'vbb:dark-preview', enabled })`. Parent builds dark CSS vars via `buildCssVars('dark')` and sends via `vbb:css-vars`. Receiver stores state on `data-vbb-dark-preview` attribute. No persistence — resets on refresh.

## Phase 3: Verification

- [x] 3.1 **Visual QA**: Test all 5 features in Chrome, Firefox, Safari, iOS. Verify no layout shifts, smooth transitions, dark preview reset on refresh, preset merge preserves overrides. (Manual — covered by verify-report.md)
- [x] 3.2 **Code review**: Verify preview sync, existing settings classes, no memory leaks (zoom toggle, preset selector). Confirm font dropdown works with search filter and custom input. (Manual — covered by verify-report.md)
