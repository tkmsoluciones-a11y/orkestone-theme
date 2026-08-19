# Archive Report: Builder Visual Polish (UI/UX)

**Change**: builder-visual-polish
**Archived**: 2026-07-09
**Final Status**: Completed (with warnings)
**Artifact Store Mode**: both (OpenSpec + Engram)

---

## Change Summary

Elevated the Command Center from a functional tool to a professional-grade UX across two delivery stages (2 chained PRs, stacked-to-main):

### Stage 1 — UX Feedback & Polish
- **Status Bar** (`#vbb-cc-status-bar`): Persistent save state indicator with 4 states (idle/saving/saved/error) spanning full grid width, with spinner, checkmark, and error retry affordance.
- **Toast Notifications** (`CC.showToast()`): 4 types (success/error/info/confirm) with stacking layout, slide-in animation, independent auto-dismiss timers (3s success/info, persistent error/confirm), replacing all `alert()`/`confirm()` calls (zero remaining).
- **Per-Field Save Flash** (`.vbb-saved-flash`): Green border pulse animation (800ms) on changed field after successful save.
- **Loading Skeletons**: CSS shimmer cards with 3 height variants (short/medium/tall) replacing static "Loading…" text during initial data fetch.
- **UI Polish**: scoped font stack (Inter + system fallback), card border-radius 18px, left-border accent on hover, layered shadows (default/hover/active), smooth CSS transitions on focus rings/card hover/save indicators, color picker hex display with copy-to-clipboard, editable hex input synced with color picker, empty states with icons/CTAs, removed `#vbb-cc-menu-status`.

### Stage 2 — Advanced Preview & Per-Block Colors
- **postMessage Bridge** (CC → iframe): `CC.postMessage()` with origin security check via `?vbb_origin=` parameter (derived from `home_url()`), `CC.supportsPostMessage` flag with try/catch fallback to full iframe reload. Full 6-message protocol defined (`vbb:css-vars`, `vbb:setting-update`, `vbb:scroll-to`, `vbb:reload`, `vbb:ready`, `vbb:resize` with `vbb:` prefix).
- **CSS Variable Injection**: Eliminated full iframe reloads for color/typography changes — inline script in preview page receives `vbb:css-vars` messages, creates/updates `#vbb-pro-injected-css` `<style>` element, validates `event.origin` against `vbb_origin` URL param.
- **Per-Block Color Overrides**: Data model extension `blocks.{key}.colors` (7 palette keys stored, 5 exposed in UI: accent/background/surface/text/mutedText). `vbb_pro_sanitize_settings()` validates via `sanitize_hex_color()`. `vbb_pro_section_class_for_block()` maps block keys to section CSS classes with exceptions (contact→contact-section, pricing→pricing-tables). Block-scoped CSS vars via `.vbb-section-{type}` selectors + per-page `.page-id-{id} .vbb-section-{type}` overrides.
- **Responsive Preview Presets**: Desktop (full width), Tablet (768px), Mobile (375px) with active button highlighting. Preview loading overlay with spinner, shown on iframe `load` start, hidden on `load`/`error` events.
- **Commit-on-Blur Split**: Color `input` event → `CC._handleColorInput()` (preview-only via postMessage, no XHR). Color `change` event (blur) → `CC._handleChange()` → `CC.debouncedSave()`.

### Design Deviations (documented, non-blocking)
1. **Resize handle omitted** — responsive presets serve the same purpose.
2. **`vbb:setting-update` replaced** — `_handleColorInput` always sends full `vbb:css-vars` payload; simpler, avoids delta logic.
3. **Error retry in overlay delegated** — overlay shows informational text only; refresh button in toolbar serves as retry.

---

## Artifact Lineage

### OpenSpec (filesystem)
| Artifact | Path | Status |
|----------|------|--------|
| Exploration | `openspec/changes/builder-visual-polish/explore.md` | Final |
| Proposal | `openspec/changes/builder-visual-polish/proposal.md` | Final |
| Spec | `openspec/changes/builder-visual-polish/spec.md` | Final |
| Design | `openspec/changes/builder-visual-polish/design.md` | Final |
| Tasks | `openspec/changes/builder-visual-polish/tasks.md` | Final (14/14 tasks complete) |
| Apply Progress | `openspec/changes/builder-visual-polish/apply-progress.md` | Final |
| Verify Report | `openspec/changes/builder-visual-polish/verify-report.md` | Final |
| Archive Report | `openspec/changes/builder-visual-polish/archive-report.md` | This file |

### Engram (observation IDs)
| Artifact | Topic Key | Observation ID |
|----------|-----------|----------------|
| Explore | `sdd/builder-visual-polish/explore` | #1590 |
| Proposal | `sdd/builder-visual-polish/proposal` | #1591 |
| Spec | `sdd/builder-visual-polish/spec` | #1592 |
| Design | `sdd/builder-visual-polish/design` | #1593 |
| Tasks | `sdd/builder-visual-polish/tasks` | #1594 |
| Apply Progress | `sdd/builder-visual-polish/apply-progress` | #1597 |
| Verify Report | `sdd/builder-visual-polish/verify-report` | #1603 |
| Archive Report | `sdd/builder-visual-polish/archive-report` | This save |

### No main spec merge required
- No existing main specs at `openspec/specs/`
- No delta spec subdirectory existed — the spec was a standalone change spec
- The spec describes requirements satisfied once implemented and verified; no ongoing maintenance needed

---

## Key Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **postMessage origin verification** | `home_url()` passed via `?vbb_origin=` URL param on iframe, checked against `event.origin` in receiver | Avoids `home_url()` vs `site_url()` vs `admin_url()` ambiguity — preview always loads a frontend URL, so `home_url()` is the correct referrer |
| **CSS variable injection strategy** | Full `:root` + block-scoped vars rebuilt via `CC.buildCssVars()` and sent as complete `vbb:css-vars` string; receiver replaces `#vbb-pro-injected-css.textContent` | Simpler than maintaining per-field delta logic; eliminates race conditions with partial updates |
| **Block-scoped CSS selectors** | Explicit map in `vbb_pro_section_class_for_block()` with exceptions for `contact→contact-section` and `pricing→pricing-tables` | Guarantees correctness against baker output; `str_replace('_', '-', $key)` with `_doing_it_wrong()` fallback for unknown types |
| **Per-block color keys exposed** | All 7 palette keys stored; UI exposes only 5 (excludes `primary`, `secondary`) | Prevents brand fragmentation at the UI level; programmatic/API overrides of all 7 keys still work |
| **Commit-on-blur pattern** | Color `input` → preview-only via postMessage (no XHR); `change` (blur) → debounced XHR save | Prevents DDoS on server during color slider drag (up to 60fps input events); only final value persisted |
| **Toast behavior** | Stack (not replace), each toast is independent DOM element in flex column | Preserves message history; independent dismiss/timers per toast |
| **Skeleton dimensions** | 3 fixed height variants (short=100px, medium=160px, tall=200px) mapped to card types | Avoids complexity of per-card dynamic skeletons; seamless replacement with real cards |
| **postMessage degradation** | One-way flag `CC.supportsPostMessage` — set to `false` on first try/catch failure, session uses full reloads for remaining lifetime | Keeps fallback logic simple; no retry mechanism for postMessage within a session |
| **Section-to-class mapping fallback** | `str_replace('_', '-', $key)` with a `_doing_it_wrong()` notice in debug mode for unknown block types | Future-proof: new vertical sections work without errors, developers get notice during development |
| **Shared API contract (Stage 1→2)** | `CC.showStatus()`, `CC.showToast()`, `CC.refreshPreview()` with stable signatures across both stages | Enables independent chained PRs without breaking Stage 2's dependency on Stage 1 APIs |

---

## Verification Summary

| Metric | Value |
|--------|-------|
| **Verdict** | PASS WITH WARNINGS |
| **Tasks complete** | 14/14 |
| **Requirements compliant** | 22/23 (1 partial) |
| **Scenarios fully compliant** | 3/5 (2 partial — non-blocking) |
| **Design decisions followed** | 11/14 (3 documented deviations) |
| **Regression areas pass** | 14/14 |
| **CRITICAL issues** | 0 |
| **WARNINGS** | 2 |
| **SUGGESTIONS** | 3 |

### Requirements Compliance

| ID | Requirement | Result |
|----|-------------|--------|
| REQ-VP1 | Persistent save status bar (saving/saved/error) | ✅ PASS |
| REQ-VP2 | Replace all `alert()` calls with toast | ✅ PASS |
| REQ-VP3 | Toast types (success/error/info) with auto-dismiss | ✅ PASS |
| REQ-VP4 | Per-field green flash animation on save | ✅ PASS |
| REQ-VP5 | Loading skeletons replace "Loading..." text | ✅ PASS |
| REQ-VP6 | Enhanced font stack (Inter + system fallback) | ✅ PASS |
| REQ-VP7 | Refined card design (18px radius, left-border accent, shadows) | ✅ PASS |
| REQ-VP8 | Color picker hex values + copy-to-clipboard | ✅ PASS |
| REQ-VP9 | Preview iframe controls (refresh, URL display, resize handle) | ⚠️ PARTIAL — URL display element missing; resize handle intentionally omitted |
| REQ-VP10 | Empty states with illustrations/CTAs | ✅ PASS |
| REQ-VP11 | Remove `#vbb-cc-menu-status` | ✅ PASS |
| REQ-VP12 | Smooth CSS transitions (focus rings, cards, save indicator) | ✅ PASS |
| REQ-VP13 | Regenerate pages with confirmation toast | ✅ PASS |
| REQ-VP14 | postMessage bridge with origin security check | ✅ PASS |
| REQ-VP15 | CSS variable injection replaces full iframe reload | ✅ PASS |
| REQ-VP16 | Preview loading overlay (spinner, error state, retry) | ✅ PASS |
| REQ-VP17 | Responsive presets (Desktop/Tablet 768px/Mobile 375px) | ✅ PASS |
| REQ-VP18 | Data model: `blocks.{key}.colors` sub-object | ✅ PASS |
| REQ-VP19 | Sanitize `colors` sub-object with hex validation | ✅ PASS |
| REQ-VP20 | Block-scoped CSS vars with correct selectors | ✅ PASS |
| REQ-VP21 | Per-block color pickers in `renderBlockSettings()` | ✅ PASS |
| REQ-VP22 | Deep merge supports `blocks.{key}.colors` level | ✅ PASS |
| REQ-VP23 | Commit-on-blur for color picker (input→preview, change→save) | ✅ PASS |

### Warnings
1. **Missing success toast on `saveSettings()`** — Regular save does not call `CC.showToast()`, so "Toast appears 'Settings saved'" per Scenario 1 is not satisfied. Status bar correctly shows "Saved ✓".
2. **Missing URL display in preview toolbar** — No DOM element showing current iframe `src` URL exists in the preview toolbar per REQ-VP9.

### Success Gates
- ✅ ZERO `alert()` calls remain in `admin-pro.js`
- ✅ Every save operation shows visible feedback (status bar)
- ✅ Per-block color changes produce scoped CSS vars on frontend
- ✅ Global `:root` CSS vars unchanged
- ✅ postMessage bridge sends messages, iframe receives and injects CSS
- ✅ postMessage degradation: `supportsPostMessage = false` triggers full reload
- ✅ Loading overlay appears on iframe load, disappears on `load` event
- ✅ Responsive presets constrain iframe width correctly
- ✅ Empty states display CTAs, not raw "No data" text
- ✅ Menu Editor CRUD works identically
- ✅ Old profile settings (without `colors` sub-object) load without error
- ✅ All 14 regression areas pass
- ⚠️ Toast does not appear on regular save (status bar shows)
- ⚠️ No URL display element in preview toolbar

---

## Lessons Learned

1. **`vbb_origin` query param is critical for postMessage security**: The origin check in the iframe script validates `event.origin` against this parameter derived from `home_url()`. On multisite, if `home_url()` differs from the admin domain, postMessage will fail gracefully and fall back to full reload. This is the correct behavior — security over convenience.

2. **CSS specificity for block-scoped variables**: Block-scoped vars use `.vbb-section-{type}` selectors which naturally beat `:root` declarations via the CSS cascade — no `!important` hacks needed. Per-page overrides use `.page-id-{id} .vbb-section-{type}` for even higher specificity.

3. **`input` vs `change` event split for color pickers**: The `input` event fires on every color slider movement (up to 60fps). Sending XHR for each would DDoS the server. The commit-on-blur pattern (`input` → preview via postMessage, `change` → XHR) is essential. However, the `change` event for color inputs fires on blur in most browsers, not on every release — keyboard Enter on color inputs may not fire `change` in all browsers.

4. **Full CSS var rebuild vs delta updates**: Sending the complete CSS vars string via `vbb:css-vars` is simpler and more reliable than maintaining per-field delta logic. The `styleTag.textContent = data.styleTag` replacement is atomic and eliminates race conditions from partial updates.

5. **Block format backward compatibility**: Existing blocks stored as booleans (`"hero": true`) are converted to objects (`{ enabled: true, colors: {} }`) in sanitization. Old `colors[]` flat format is still migrated to `palettes` format. The sanitization must handle both paths independently and in the correct order.

6. **Inter-font loading**: The font-family stack references `'Inter'` first but there's no `wp_enqueue_style()` to load it. System fallbacks ensure clean rendering. Loading Inter via CDN would improve consistency but requires an additional HTTP request — appropriate as a future enhancement.

7. **postMessage protocol design**: Defining a full 6-message protocol (even if only 2 are immediately implemented) provides a clear extension path. The `vbb:` prefix effectively namespaces messages and prevents collisions with other JS on the preview page.

8. **`_doing_it_wrong()` for unknown section types**: The fallback in `vbb_pro_section_class_for_block()` uses `_doing_it_wrong()` in debug mode, which helps developers discover missing mappings during development without breaking production behavior.

---

## Files Changed (Final)

| File | Action | Lines (net) | What Changed |
|------|--------|-------------|--------------|
| `assets/js/admin-pro.js` | Modified | ~+240 | Stage 1: `showStatus()`, `showToast()`, `showConfirmToast()`, `_dismissToast()`, `_flashChangedField()`, `_showSkeletons()`, `_showCopyTooltip()`. Replaced all alert/confirm. Enhanced `renderColorGroups()` with hex/copy. Empty states. Removed `#vbb-cc-menu-status`. Stage 2: `postMessage()`, `buildCssVars()`, `_handleColorInput()`, `_setNested()`, `_blockKeyToSectionClass()`, `_onPresetChange()`, `_showPreviewOverlay()`, `_hidePreviewOverlay()`. Extended `renderBlockSettings()` with per-block color pickers. Split color event handlers in `bindCardEvents()`. Updated `refreshPreview()` with overlay and `vbb_origin`. Updated `saveSettings()` for postMessage. Updated `onPageChange()` for overlay. |
| `assets/css/admin-pro.css` | Modified | ~+100 | Stage 1: Typography, card polish, toast, skeleton, transitions, empty states, color UX, save flash. Stage 2: Preview overlay, responsive presets, injected CSS fallbacks. |
| `inc/pro-admin.php` | Modified | ~+50 | Stage 1: `#vbb-cc-toast-container`, `#vbb-cc-status-bar`. Stage 2: `previewOrigin` in `vbbCommandCenterData`, `vbb_origin` param, preview toolbar with presets + refresh, preview viewport wrapper with loading overlay, `vbb_pro_inject_preview_script()` hook. |
| `inc/pro-settings.php` | Modified | ~+40 | `vbb_pro_block_color_keys()`, default blocks as objects with `{ enabled, colors }`, per-block color sanitization in `vbb_pro_sanitize_settings()`. |
| `inc/pro-css-vars.php` | Modified | ~+75 | `vbb_pro_section_class_for_block()` with explicit mapping, `vbb_pro_block_scoped_css_vars()`, per-page `.page-id-{id}` overrides. |

## Task Completion

- **Total tasks**: 14
- **Completed**: 14
- **Stages**: 2 (Stage 1: 7 tasks, Stage 2: 7 tasks)
- **Delivery**: force-chained, stacked-to-main (PR 1 → PR 2)

No remaining tasks. All implementation and verification tasks are complete.
