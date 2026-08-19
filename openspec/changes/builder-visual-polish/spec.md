# Delta Spec: Builder Visual Polish (UI/UX)

**Change**: builder-visual-polish  
**Status**: draft  
**Next**: sdd-design  
**Review Budget**: 800 lines (2 chained PRs)

---

## Executive Summary

Elevate the Command Center from a functional tool to a professional-grade UX — instant feedback on every action, seamless live preview without full-page flashes, and granular per-block color control. This spec covers 4 capability areas across 2 delivery stages, replacing `alert()` dialogs with toast notifications, introducing a visual feedback system (status bar + field indicators + skeletons), enhancing the live preview (postMessage bridge, CSS variable injection, responsive presets), adding per-block color overrides (data model + scoped CSS vars), and general UI polish (typography, card refinements, color picker UX).

---

## Requirements

### Stage 1 — Feedback & UI Polish

| ID | Requirement | Input → Output | Verification |
|----|------------|----------------|--------------|
| REQ-VP1 | **The Command Center MUST display a persistent save status bar** indicating saving, saved, or error state for all save operations. | User changes a field and triggers debounced save → Status bar appears below page selector showing "Saving…" with spinner, then "Saved ✓" for 2s (green), then fades to idle/hidden. On error, shows error text with retry affordance. | 1. Modify any field → "Saving…" appears within 500ms<br>2. "Saved ✓" appears and auto-dismisses<br>3. Force XHR error → error message persists until dismissed |
| REQ-VP2 | **The Command Center MUST replace all `alert()` calls with toast notifications.** | Any code path that calls `alert()` must instead call `CC.showToast(message, type)`. | 1. Click "Regenerate Pages" → toast appears (no `alert()` dialog)<br>2. Force save error → error toast appears (no `alert()` dialog)<br>3. Search `admin-pro.js`: zero occurrences of `alert(` after change |
| REQ-VP3 | **Toast notifications MUST support success, error, and info types with auto-dismiss for success.** | `CC.showToast(msg, 'success')` → green toast, auto-dismiss after 3s. `CC.showToast(msg, 'error')` → red toast, persists until dismissed. `CC.showToast(msg, 'info')` → blue toast, auto-dismiss after 3s. | 1. Fire success toast → visible for ~3s, fades out<br>2. Fire error toast → stays until manual dismiss<br>3. Dismiss button works on all types |
| REQ-VP4 | **Per-field save feedback MUST display a green flash animation on the changed field after successful save.** | User changes field → field element receives a CSS class `.vbb-saved-flash` for 1s (green border pulse). | 1. Modify text field → green border flashes on save confirmation<br>2. Modify color input → green border flashes on save confirmation |
| REQ-VP5 | **Loading skeletons MUST replace static "Loading…" text during initial data fetch.** | Page loads → skeleton shimmer cards appear. On data received → skeletons are replaced by rendered cards. | 1. Initial load shows `.vbb-cc-skeleton` elements, not text<br>2. After data loads, real cards replace skeletons<br>3. Skeleton has animated shimmer effect (CSS `@keyframes`) |
| REQ-VP6 | **The admin UI MUST use an enhanced font stack** (Inter or system fallback) scoped to `.vbb-command-center`. | CSS declares `font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif` on `.vbb-command-center`. | 1. Inspect `.vbb-command-center` → correct font-family<br>2. If Inter is loaded, it's used; otherwise system fonts display cleanly |
| REQ-VP7 | **Cards MUST have refined visual design**: increased border-radius (18px), left-border accent on hover, layered shadows. | CSS updates: `.vbb-cc-card` border-radius → 18px, hover state adds left border with primary color, shadow depth layers (default/hover/active). | 1. Card border-radius is 18px<br>2. Hover shows left accent border<br>3. Shadow deepens on hover |
| REQ-VP8 | **Color picker UX MUST display hex values** alongside each swatch with a copy-to-clipboard button. | Each `input[type="color"]` in color grid shows its hex text value, with a copy button that copies the hex to clipboard. | 1. Hex value shown next to each color swatch<br>2. Click copy → hex value copied to clipboard<br>3. Visual feedback on copy (brief "Copied" tooltip) |
| REQ-VP9 | **Preview iframe MUST have resize handle, refresh button, and URL display.** | Iframe container includes: refresh button (independent of save), URL display showing current preview URL, resize handle at bottom-right. | 1. Click refresh → iframe reloads without triggering save<br>2. URL display shows current iframe src<br>3. Resize handle adjusts iframe container height |
| REQ-VP10 | **Empty states MUST show descriptive illustrations/CTAs instead of raw "No data" text.** | **Menu Editor**: "No menu items yet. Click '+ Add Menu Item' to start." with icon. **Blocks**: "No blocks available" with icon. **Pages**: "No pages found" with "Create Page" link. | 1. Empty menu items list shows illustration + CTA<br>2. CTA buttons are functional<br>3. Empty states fade in with transition |
| REQ-VP11 | **The menu-specific save indicator `#vbb-cc-menu-status` MUST be removed** and unified under the global status bar. | Menu save operations use `CC.showStatus()` instead of the `#vbb-cc-menu-status` element. | 1. Save menu → global status bar shows status<br>2. `#vbb-cc-menu-status` element no longer exists in DOM after render<br>3. No duplicate indicators |
| REQ-VP12 | **Smooth CSS transitions MUST be applied** to focus rings, card hover, save indicators, and field focus states. | CSS transitions: focus rings `box-shadow .2s`, card hover `box-shadow .2s, border-color .2s`, save indicator `opacity .3s`. | 1. Focus a field → smooth box-shadow transition<br>2. Hover a card → smooth border-color/shadow transition<br>3. Save indicator fades smoothly |
| REQ-VP13 | **Regenerate pages MUST use confirmation toast** instead of `confirm()` dialog, with progressive action. | Click "Regenerate Pages" → confirm toast/modal appears with "This will regenerate all pages…" → on confirm, progress shown via status bar. | 1. Click "Regenerate Pages" → no `confirm()` dialog<br>2. Confirmation toast appears with action buttons<br>3. On confirm, status shows "Regenerating…" then result |

### Stage 2 — Advanced Preview & Per-Block Colors

| ID | Requirement | Input → Output | Verification |
|----|------------|----------------|--------------|
| REQ-VP14 | **A postMessage bridge MUST be established** between Command Center and preview iframe with origin security checks. | `window.postMessage` sent from CC to iframe on setting change. Inline script in preview page receives messages. Messages include `{ type: 'vbb-setting-update', path, value }`. | 1. Change a setting → `postMessage` fires to iframe<br>2. Iframe receives message with correct payload<br>3. Origin check prevents cross-origin spoofing<br>4. Falls back to full iframe reload if postMessage fails |
| REQ-VP15 | **CSS variable injection into preview iframe MUST replace full iframe reload** for color/typography changes. | On color/typography change → CC sends updated CSS vars via postMessage → iframe JS injects `<style>` element into its `<head>` with the new variable values. | 1. Change color → iframe updates without page flash<br>2. New `<style>` appears in iframe's `<head>`<br>3. If postMessage is unavailable, falls back to full reload (`CC.el.iframe.src = …`) |
| REQ-VP16 | **Preview MUST show a loading overlay** while iframe content is loading. | Iframe `load` event → overlay hidden. Iframe starts loading → overlay visible. `error` event → error state with retry button. | 1. Iframe loading → overlay with spinner appears<br>2. Iframe loaded → overlay disappears<br>3. Iframe error → error message with retry appears |
| REQ-VP17 | **Responsive preview presets MUST provide desktop, tablet (768px), and mobile (375px) viewport sizes.** | Three preset buttons in preview toolbar: Desktop, Tablet, Mobile. Clicking a preset changes the iframe's container width. | 1. Click "Tablet" → iframe width is 768px<br>2. Click "Mobile" → iframe width is 375px<br>3. Click "Desktop" → iframe width is full container<br>4. Preset buttons highlight the active choice |
| REQ-VP18 | **The settings data model MUST support `blocks.{key}.colors`** as an optional sub-object in both global and per-page settings. | Schema extension: `blocks.hero.colors = { background: '', text: '', accent: '' }`. Empty values inherit from global palette. | 1. Save settings with `colors` sub-object → persists correctly<br>2. Old settings without `colors` → works unchanged<br>3. Per-page `colors` override global `colors` at block level |
| REQ-VP19 | **`vbb_pro_sanitize_settings()` MUST preserve and sanitize the `colors` sub-object** for each block when present. | Sanitization loops `settings.blocks`, checks for `colors` array, sanitizes color values via `sanitize_hex_color()`. | 1. Submit valid hex colors → stored correctly<br>2. Submit invalid hex → falls back to empty (inherit)<br>3. Old block format (boolean) → still converted to object without `colors` |
| REQ-VP20 | **`vbb_pro_print_css_vars()` MUST emit block-scoped CSS variables** for blocks with color overrides. | For each block with non-empty `colors`, output `.vbb-section-{type}{ --vbb-pro-{colorKey}: {value}; }`. | 1. Set `hero` block `background=#ff0000` → frontend HTML shows `.vbb-section-hero{--vbb-pro-background:#ff0000}`<br>2. No color overrides → no extra CSS output<br>3. Global `:root` variables remain unchanged |
| REQ-VP21 | **Per-block color pickers MUST appear in `renderBlockSettings()`** when a block is enabled. | When block is toggled ON and has an expanded settings panel, a "Colors" section appears with color pickers for `background`, `text`, `accent`. | 1. Enable hero block → "Colors" section appears<br>2. Color pickers use same `data-path` convention: `blocks.{key}.colors.{colorName}`<br>3. Changing a per-block color triggers `debouncedSave()` and preview update |
| REQ-VP22 | **`vbb_pro_deep_merge()` MUST support `blocks.{key}.colors` level merging** for per-page block color overrides. | Per-page settings with `blocks.hero.colors.background` override the global `blocks.hero.colors.background` while keeping other colors from global. | 1. Global: `hero.colors.background=#fff`, Per-page: `hero.colors.background=#000` → preview uses `#000`<br>2. Per-page: `hero.colors` not set → falls back to global<br>3. Non-color block settings remain independent |
| REQ-VP23 | **Preview/Save decoupling MUST follow "commit on blur" pattern**: color picker drag updates preview only, blur triggers save. | Color picker `input` event → `postMessage` to iframe with new value (no XHR). `change` event (blur) → `CC.debouncedSave()` | 1. Drag color slider → preview updates without XHR call<br>2. Release/drag end → debounced save fires<br>3. Non-color fields (text, select) continue saving on change |

---

## Scenario-Based Tests

### Scenario 1: Full Color Edit Cycle (Happy Path)

**Objective**: Verify that changing a color from a field triggers instant feedback, updates the preview seamlessly, and persists across page refresh.

**Steps**:
1. User opens Command Center and selects "Global Settings"
2. User changes the `primary` color in the Light palette via the color picker
3. **Expected**: Field shows green flash animation → Status bar shows "Saving…" → CSS vars injected via postMessage into iframe (no full reload) → Status bar shows "Saved ✓" → Toast appears "Settings saved"
4. User refreshes the browser
5. **Expected**: The new `primary` color value is still set in the color picker
6. User inspects the frontend
7. **Expected**: `--vbb-pro-primary` CSS variable has the new value

**Edge cases**:
- Rapid color changes (drag): Only the final value saves, but preview updates on every `input` event
- Network failure during save: Error toast appears, status bar shows error, value reverts in preview
- Invalid hex color: Input rejects non-hex characters, no save triggered

---

### Scenario 2: Per-Block Color Isolation

**Objective**: Verify that setting a per-block color override affects ONLY that block and not the global palette.

**Steps**:
1. User opens Command Center → "Blocks" card → enables `hero` block → expands settings
2. User finds the "Colors" section and sets `hero.colors.background = #e8f4f8`
3. **Expected**: Status bar shows save feedback → Preview updates via postMessage
4. User inspects the frontend source
5. **Expected**: CSS contains `.vbb-section-hero { --vbb-pro-background: #e8f4f8; }`
6. User checks a different section (e.g., `servicesGrid`)
7. **Expected**: `.vbb-section-services-grid` does NOT override `--vbb-pro-background` — it inherits the global value
8. User sets a per-page override for hero.color.background on a specific page
9. **Expected**: That specific page shows the per-page override; other pages show the global block override

**Edge cases**:
- Block is toggled OFF: Colors section disappears, CSS not emitted for that block
- All colors left empty: No block-scoped CSS emitted, global applies
- Per-page colors set but block is toggled off globally: Block is hidden, colors irrelevant

---

### Scenario 3: postMessage Bridge Degradation

**Objective**: Verify that the live preview gracefully falls back to full iframe reload when postMessage is unavailable.

**Steps**:
1. User opens Command Center and changes a color
2. **Expected**: `CC.iframe.contentWindow.postMessage()` is called with the setting update
3. Iframe receives message and injects CSS `<style>` element
4. Simulate postMessage failure (e.g., cross-origin restriction, contentWindow null)
5. **Expected**: `CC.supportsPostMessage = false`
6. User changes another color
7. **Expected**: Instead of postMessage, `refreshPreview()` is called (full iframe reload with `?vbb_preview=` timestamp)
8. Loading overlay appears during iframe reload, disappears on `load` event

**Edge cases**:
- iframe `load` and `error` events: Loading overlay dismisses on load, shows error state on error
- Interrupted load: If user quickly changes multiple settings, only the last change triggers a full reload (debounced)
- Responsive preset change: Doesn't trigger postMessage or reload — only CSS class changes on container

---

### Scenario 4: Toast System Migration from `alert()` / `confirm()`

**Objective**: Verify all `alert()` and `confirm()` calls in the admin-pro.js are replaced with toast/status bar patterns.

**Steps**:
1. User clicks "Regenerate Pages" → confirmation toast appears: "This will regenerate all pages to apply new content structures. Continue?" with [Confirm] [Cancel] buttons
2. **Expected**: No `confirm()` dialog — toast-based confirmation with action buttons
3. User clicks "Confirm" → status bar shows "Regenerating…" with spinner → on completion, toast: "3 pages regenerated successfully"
4. **Expected**: No `alert()` — success communicated via toast
5. Save fails due to network error → error toast: "Save failed: Network error" with dismiss button
6. **Expected**: No `alert()` dialog — error communicated via toast, stays until dismissed
7. User resets settings → confirmation toast appears: "Reset all settings to vertical defaults? This cannot be undone."
8. **Expected**: No `confirm()` dialog

**Edge cases**:
- Multiple toasts: Stacked vertically, newest at bottom
- Dismiss all: Each toast has individual close button
- Rapid toasts: Auto-dismiss timers independent, each runs its own duration

---

### Scenario 5: Responsive Preview + Loading States

**Objective**: Verify responsive preview presets and loading overlay work together correctly.

**Steps**:
1. User opens Command Center → Preview sidebar is visible with iframe
2. **Expected**: Iframe starts loading → `.vbb-cc-preview-loading` overlay appears with spinner
3. Iframe finishes loading → overlay disappears
4. User clicks "Tablet" button in preview toolbar
5. **Expected**: Iframe container width changes to 768px → Button "Tablet" becomes active (highlighted)
6. User clicks "Mobile" → width 375px → "Mobile" highlighted
7. User clicks "Desktop" → width 100% of container → "Desktop" highlighted
8. User clicks the refresh button in the preview toolbar
9. **Expected**: Iframe reloads → Loading overlay appears → On load, overlay disappears
10. **No save triggered**: Refreshing preview does not trigger any XHR

**Edge cases**:
- Narrow browser window (< 900px): Preview sidebar moves to top; responsive presets still work
- Iframe error (404, network): Loading overlay transitions to error state with "Retry" button
- URL display shows correct preview URL, updates on page change

---

## Regression Areas

The following existing features MUST continue to work after the visual polish changes. Each area includes what to verify and why it could break.

| Area | What to Verify | Risk if Broken |
|------|---------------|----------------|
| **R1. Settings save/load via REST API** | Changing a text field → debounced XHR → saved on refresh. REST API returns correct merged settings. | JS restructuring of `saveSettings()` could break the XHR flow or the debounce timing. |
| **R2. Page selector and per-page settings** | Switching pages loads correct settings, merges global + per-page. Preview URL updates to `/?p={pageId}&vbb_preview=…`. | Changes to `onPageChange()` or the page selector rendering could break page switching. |
| **R3. Menu Editor CRUD** | Add, edit, delete, reorder menu items. Save menu syncs to `wp_navigation` post. Menu-specific field changes trigger debounced save. | DOM re-rendering changes (e.g., unified status bar) could break menu event bindings. |
| **R4. Block toggle enable/disable** | Toggling a block ON shows expanded settings, OFF collapses. Frontend respects block visibility (CSS + JS). | Changes to `renderBlocks()` / `renderBlockSettings()` could break toggle logic or DOM structure. |
| **R5. Global CSS variable output (`pro-css-vars.php`)** | Frontend `<style id="vbb-pro-elite-css-vars">` outputs correct `:root` variables for light/dark/auto mode. | Changes to `vbb_pro_print_css_vars()` for block-scoped vars must not alter the existing `:root` output. |
| **R6. Body classes (`vbb-block-*-on/off`, `vbb-color-mode-*`)** | Frontend `<body>` has correct class names based on settings. | Changes to `vbb_pro_body_classes()` or `vbb_pro_get_settings()` return format could break body class output. |
| **R7. Color mode toggle (Light/Dark/Auto)** | Changing color mode updates CSS vars and preview accordingly. Auto mode follows `prefers-color-scheme`. | No direct changes expected, but CSS variable injection logic must respect `colorMode`. |
| **R8. Page regeneration** | "Regenerate Pages" button calls the correct endpoint, replaces `{{vbb_*}}` placeholders. | The migration from `alert()`/`confirm()` to toast must preserve the same HTTP call and flow. |
| **R9. Reset to vertical defaults** | "Reset to Vertical Defaults" clears settings and reloads. | Changes to reset confirmation flow must preserve the underlying XHR and DOM reload sequence. |
| **R10. Save as Profile** | "Save as Profile" saves current state as a reusable profile. | Changes to `saveSettings()` callback chain must keep the hidden form submission flow intact. |
| **R11. Mobile/Responsive layout** | On screens < 900px, grid collapses to single column, preview moves to top, cards stack vertically. | CSS changes to card polishing (border-radius, shadows) must not break the existing responsive breakpoints. |
| **R12. Block settings expand/collapse animation** | Enabling a block slides down its settings panel with `vbb-slide-down` animation. | Changes to `renderBlockSettings()` must preserve the `vbb-cc-block-settings` DOM structure. |
| **R13. Deep merge of global + per-page settings** | `vbb_pro_deep_merge()` correctly merges nested settings including the new `colors` sub-object. | Changes to `vbb_pro_deep_merge()` for `colors` must not break existing merge paths (palettes, typography, etc.). |
| **R14. Backward compatibility of old profile formats** | Old profiles with `colors[]` (flat) format are still converted to `palettes` format in sanitization. | Changes to `vbb_pro_sanitize_settings()` must preserve the legacy `colors[]` → `palettes` migration path. |

---

## Spec Gaps & Ambiguities (Risks for Design Phase)

| Gap | Description | Impact |
|-----|-------------|--------|
| **G1. postMessage origin check detail** | The proposal says "strict origin check" but doesn't specify whether to check `event.origin` against `home_url()`, `site_url()`, or `admin_url()`. | If the wrong URL is used, the check could fail on multisite or when site URL differs from admin URL. Design must resolve this. |
| **G2. CSS injection selector specificity** | Block-scoped vars use `.vbb-section-{type}` but the actual section class might vary (e.g., `.wp-block-group.vbb-section-hero` or just `.vbb-section-hero`). | If the selector doesn't match the actual DOM structure in the generated pages, the CSS variable override won't apply. Design must map section types to actual selectors. |
| **G3. Interleaved Stage 1 ↔ Stage 2 dependencies** | Stage 1 removes `#vbb-cc-menu-status` and introduces global status bar. Stage 2 introduces postMessage. If Stage 1 removes `alert()` but Stage 2's postMessage fallback uses `refreshPreview()` which is retained, there's no issue — but if Stage 1 changes `refreshPreview()` signature, Stage 2 breaks. | The design must define the exact API surface (`CC.refreshPreview`, `CC.showStatus`, `CC.showToast`) that both stages share. |
| **G4. Per-block color keys** | Proposal mentions `background`, `text`, `accent` as per-block color keys, but the actual palette has 7 keys (primary, secondary, accent, background, surface, text, mutedText). | Which subset of palette colors should be overridable per block? Design must specify the exact keys per block type. |
| **G5. postMessage message format collision** | The proposal uses `{ type: 'vbb-setting-update', path, value }` but doesn't specify other message types (e.g., `vbb-iframe-ready`, `vbb-scroll-to`, `vbb-css-vars-batch`). | If the iframe preview page has other JS that uses postMessage, we need a prefix/namespace to avoid collisions. "vbb-" prefix is a good start but design should define the full protocol. |
| **G6. Skeleton dimensions** | "Card-shaped shimmer blocks" — but cards vary in height (some have few fields, some have many). | Design must decide: fixed skeleton height per card type, or a single generic skeleton? |
| **G7. Color picker "commit on blur" — what exactly triggers save?** | "Color picker drag updates preview only, field blur triggers save" — but `input` events fire continuously during drag, while `change` fires on blur. The current code already binds both events. | Design must ensure the `input` handler doesn't trigger XHR (only postMessage), and the `change` handler triggers debounced save. Current code calls `_handleChange` for both which calls `debouncedSave()`. This needs explicit split. |
| **G8. Toast stacking behavior** | Not specified how multiple overlapping toasts should be handled (queue vs stack vs replace). | Design decision: should a new toast replace the previous one, or should they stack? Common UX patterns vary. |

---

## Success Gates (for sdd-verify)

- [ ] **ZERO** `alert()` calls remain in `admin-pro.js`
- [ ] Every save operation shows visible feedback (status bar and/or toast)
- [ ] Per-block color changes produce scoped CSS variables on the frontend
- [ ] Global `:root` CSS variables are unchanged when only per-block colors are modified
- [ ] postMessage bridge: iframe receives messages and injects CSS → verify via DOM inspection
- [ ] postMessage degradation: `CC.supportsPostMessage = false` triggers full reload fallback
- [ ] Loading overlay appears on iframe load and disappears on `load` event
- [ ] Responsive presets correctly constrain iframe container width
- [ ] Empty states display CTAs, not raw "No data" text
- [ ] Menu Editor CRUD works identically before and after changes (verify add/edit/delete/save)
- [ ] Old profile settings (without `colors` sub-object) load without error
- [ ] All 14 regression areas pass automated or manual check
