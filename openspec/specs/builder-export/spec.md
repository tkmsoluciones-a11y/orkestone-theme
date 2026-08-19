# Delta Spec: Builder Export & Template Management

**Change**: builder-export-templates
**Status**: draft
**Next**: sdd-design
**Review Budget**: 800 lines (2 chained PRs)

---

## Executive Summary

Enable the round-trip of site configurations. Users can export their full customized site as JSON (global settings + per-page overrides) via a REST endpoint in the Command Center, and import that data into a fresh install preserving all overrides. Section blocks gain a `style` field (default `'A'`) enabling visual variants — baker functions dispatch on style to produce different markup. A button-group style selector in `renderBlockSettings()` auto-triggers re-bake on change. Delivered in two stages: **Stage 1 — Export** (REST endpoint + Command Center Export button + import extension) and **Stage 2 — Styles** (data model + baker dispatch + shared helpers + UI selector).

---

## Requirements

### Stage 1 — Export

| ID | Requirement | Input → Output | Verification |
|----|------------|----------------|--------------|
| REQ-ET1 | **The system MUST provide a `GET /orkestone/v1/export` REST endpoint** returning a full-site JSON document with global settings, per-page overrides, and active profile. | Authenticated GET request → JSON response with envelope `{ exportedAt, schemaVersion: "1.0.0", theme: "orkestOne", settings: {...}, pageOverrides: {...}, activeProfile: "..." }`. | 1. `curl -H "X-WP-Nonce: ..." -X GET /wp-json/orkestone/v1/export` returns 200<br>2. Response has `exportedAt` (ISO datetime)<br>3. Response has `schemaVersion: "1.0.0"`<br>4. `settings` matches `vbb_pro_get_settings()` output<br>5. `pageOverrides` is an object keyed by page ID, each value is that page's deltas (from `vbb_pro_page_settings` option)<br>6. `activeProfile` is the active profile key or `null` |
| REQ-ET2 | **The export JSON MUST use the same top-level shape as vertical JSON** plus a `"customized": true` flag and a `pageOverrides` key. | Export document structure: `{ schemaVersion, theme, customized: true, settings: {...}, pageOverrides: { [pageId]: {...} }, activeProfile: "..." }`. | 1. `customized` is `true`<br>2. `settings` contains ALL keys from `vbb_pro_default_settings()`<br>3. `pageOverrides` contains ONLY pages with non-default settings<br>4. Deleted pages are NOT present in `pageOverrides` |
| REQ-ET3 | **The Command Center MUST have an "Export" button** in the toolbar that fetches the export blob and triggers a browser download. | User clicks "Export" → JS fetches `GET /orkestone/v1/export` → receives JSON → creates `<a download="orkestone-export-{timestamp}.json">` → triggers click. | 1. "Export" button is visible in the `vbb-cc-toolbar` div<br>2. Clicking it triggers a network request to `/orkestone/v1/export`<br>3. A JSON file is downloaded with filename `orkestone-export-*.json`<br>4. The downloaded file is valid JSON matching REQ-ET2 schema |
| REQ-ET4 | **The existing import handler MUST accept the expanded export format** parsing `pageOverrides` in addition to `settings`. | Import receives JSON with `{ settings, pageOverrides }` → `settings` restored via `vbb_pro_update_settings()`, each `pageOverrides[pageId]` restored via `vbb_pro_update_page_settings()`. | 1. Import a legacy file (without `pageOverrides`) → global settings restored, `pageOverrides` option unchanged<br>2. Import a new export file (with `pageOverrides`) → global settings AND per-page overrides restored<br>3. Import with `pageOverrides` for a deleted page → orphaned entry is skipped silently<br>4. Existing `vbb_pro_page_settings` entries NOT in import are preserved (merge, not replace) |
| REQ-ET5 | **The export MUST exclude deleted pages** from `pageOverrides`. | Build export → query all pages with `post_status = 'publish'` → filter `pageOverrides` to only include published page IDs. | 1. Delete a page → re-export → that page ID is absent from `pageOverrides`<br>2. Add a page with overrides → export → that page ID IS present in `pageOverrides` |

### Stage 2 — Styles

| ID | Requirement | Input → Output | Verification |
|----|------------|----------------|--------------|
| REQ-ET6 | **Each block in `vbb_pro_default_settings()` MUST have a `style` field** defaulting to `'A'`. | `vbb_pro_default_settings()` returns `blocks.hero = { enabled: true, colors: {}, style: 'A' }`. | 1. Fresh install → `vbb_pro_get_settings()` shows `style: 'A'` for every block<br>2. Old profile without `style` field → merge fills `'A'` as default |
| REQ-ET7 | **`vbb_pro_sanitize_settings()` MUST validate the `style` field** accepting only `'A'`, `'B'`, or `'C'`, defaulting to `'A'` on invalid values. | Sanitization loop over `settings.blocks` → if `style` is set and not in `['A','B','C']`, set to `'A'`. | 1. Submit `style: 'B'` → stored as `'B'`<br>2. Submit `style: 'X'` → stored as `'A'`<br>3. Submit `style: ''` → stored as `'A'`<br>4. Submit no `style` key → stored as `'A'` |
| REQ-ET8 | **Baker functions for hero, cta-final, and testimonials MUST dispatch on `$data['style']`** producing different markup per style variant. | `vbb_bake_hero($data)` with `$data['style'] === 'B'` → different layout/structure than style `'A'`. | 1. Regenerate page with `hero.style: 'A'` → hero markup shows layout A<br>2. Change to `hero.style: 'B'` → re-bake produces layout B with different class names/structure<br>3. Unknown style value → fallback to style `'A'` output |
| REQ-ET9 | **Shared rendering helpers MUST be extracted** for common sub-patterns: `vbb_render_cta_button()`, `vbb_render_heading_block()`. | `vbb_bake_cta_final()` calls `vbb_render_cta_button($text, $url, $style)` instead of inline button HTML. Called from both cta-final baker and hero baker (when style B uses a button). | 1. `vbb_render_cta_button()` exists and returns valid Gutenberg block markup<br>2. `vbb_render_heading_block()` exists and returns valid heading markup<br>3. At least 2 baker functions use at least one shared helper |
| REQ-ET10 | **The style selector MUST appear as a button-group control in `renderBlockSettings()`** for each block. | In `renderBlockSettings(key, block)` → after existing color section, add `<div class="vbb-cc-style-selector">` with buttons for A, B, C. Active style is highlighted. | 1. Open any block's expanded settings → "Section Style" label visible<br>2. Three buttons labeled "A", "B", "C"<br>3. Current style button has active class<br>4. Clicking a different style updates `state.settings.blocks.{key}.style` |
| REQ-ET11 | **Changing a block's style MUST trigger an auto-rebake** of the current page via the existing regenerate endpoint. | User clicks style "B" → `CC.onFieldChange('blocks.hero.style', 'B')` → debounced save → on success, XHR to `/pages/{pageId}/regenerate` → preview refreshes. | 1. Change hero style from A to B → XHR to `regenerate` is fired<br>2. Preview reloads showing new style B markup<br>3. Confirmation dialog appears if page has unsaved changes: "Style change will regenerate this page. Any manual edits will be lost."<br>4. Cancelling the dialog reverts the style selection to previous value |
| REQ-ET12 | **The export format MUST include `style` values** per block in both `settings.blocks` and per-page override blocks. | Export document `settings.blocks.hero.style` reflects the saved value. Per-page override blocks also carry `style` if overridden. | 1. Export with `hero.style: 'B'` → export JSON has `"settings":{"blocks":{"hero":{"style":"B"}}}`<br>2. Import that export → restored hero has `style: 'B'` |

---

## Scenario-Based Tests

### Scenario 1: Full Export → Import Round-Trip (Happy Path)

**Objective**: Verify that a user can export a fully customized site and import it into a fresh install, preserving all global settings and per-page overrides.

**Steps**:
1. User opens Command Center → changes `primary` color to `#ff0000` → changes `siteTitle` to "My Custom Site"
2. User switches to page "About" via page selector → changes hero title to "About Us Custom" → changes hero `style` to `'B'`
3. User clicks "Export" button in toolbar
4. **Expected**: Download starts with `orkestone-export-*.json`
5. User opens the downloaded file
6. **Expected**: JSON has `exportedAt`, `schemaVersion: "1.0.0"`, `theme: "orkestOne"`, `customized: true`
7. **Expected**: `settings.blocks.hero.style` is `"B"` (from per-page — but actually the global might still be 'A' if only overridden per-page... wait — the style change was per-page, so global should have `"A"`)
8. User switches to a fresh WP install with the theme activated
9. User opens Export/Import page → selects the downloaded file → clicks "Importar configuración"
10. **Expected**: Global settings imported → `primary` is `#ff0000`, `siteTitle` is "My Custom Site"
11. **Expected**: Page "About" has hero title "About Us Custom" and hero `style` `'B'`
12. User opens Command Center → verifies all settings match

**Edge cases**:
- Export with NO per-page overrides → `pageOverrides` is `{}`
- Export with 1 page having overrides → `pageOverrides` contains exactly 1 entry
- Imported file has `pageOverrides` for pages that don't exist → silently skipped
- Imported file has `schemaVersion` field but it's not `"1.0.0"` → import still proceeds (future-compat)
- Export with a deleted page → that page not in `pageOverrides`

---

### Scenario 2: Style Variant Bake with Different Output

**Objective**: Verify that changing a block's style produces visually and structurally different baked markup on the frontend.

**Steps**:
1. User opens Command Center → Blocks card → expands hero block
2. **Expected**: "Section Style" button-group shows A, B, C with A active
3. User clicks "B"
4. **Expected**: Confirmation dialog: "Style change will regenerate this page. Any manual edits will be lost." with [Confirm] [Cancel]
5. User clicks "Confirm"
6. **Expected**: Status bar shows "Saving…" → XHR to save settings → XHR to `/regenerate` endpoint → Preview reloads
7. User inspects the preview iframe source or the frontend page
8. **Expected**: Hero markup differs from style A — different CSS classes, different HTML structure
9. User clicks "C"
10. **Expected**: Another confirmation → re-bake → markup differs again
11. User switches back to "A"
12. **Expected**: Re-bake restores style A markup
13. User exports → inspects JSON
14. **Expected**: `blocks.hero.style` is `"A"` (or if per-page, overridden)

**Edge cases**:
- Style changed but user clicks "Cancel" on confirmation → style reverts to previous value
- Invalid style value submitted via non-UI (curl) → sanitizer defaults to `'A'`
- Style change on a block that is NOT hero/cta-final/testimonials → no dispatch impact, style stored but ignored by baker

---

### Scenario 3: Per-Page Style Override with Export Preservation

**Objective**: Verify that a per-page style override survives export and import, and that a different page retains the global default.

**Steps**:
1. Open Command Center → global settings → hero style = `'A'`
2. Switch to page "Home" → hero style = `'B'`
3. Switch to page "About" → hero style = `'C'`
4. Export
5. **Expected**: `pageOverrides["{home_id}"].blocks.hero.style` = `"B"`, `pageOverrides["{about_id}"].blocks.hero.style` = `"C"`, `settings.blocks.hero.style` = `"A"`
6. Import into fresh install
7. Open Command Center → Global settings → hero style = `'A'`
8. Switch to page "Home" → hero style = `'B'` (from per-page override)
9. Switch to page "About" → hero style = `'C'` (from per-page override)
10. Bake each page → inspect frontend markup
11. **Expected**: Home page hero has style B markup, About page hero has style C markup, any other page has style A markup

**Edge cases**:
- Per-page style override where the global block has `style: 'B'` → per-page `'A'` overrides globally
- Multiple pages with the same override value → all restored correctly
- Page deleted before export → no orphaned overrides

---

### Scenario 4: Import Format Compatibility

**Objective**: Verify backward compatibility of the import handler with legacy export files (schema v0.3.2) and the new expanded format.

**Steps**:
1. Take a legacy export JSON (from existing `vbb_pro_export_settings()` — format `{ exportedAt, theme, profileType, schemaVersion: "0.3.2", settings }`)
2. Import it via the admin Import page
3. **Expected**: Global settings restored correctly, no errors
4. **Expected**: `pageOverrides` option unchanged (legacy format doesn't have it)
5. Take the new export format (with `pageOverrides`)
6. Import it
7. **Expected**: Global settings AND per-page overrides restored
8. Take a partially-modified new export (manually remove `pageOverrides` key)
9. Import it
10. **Expected**: Global settings restored, no crash, `pageOverrides` option unchanged

**Edge cases**:
- Import file is not valid JSON → "El JSON no es válido." error message
- Import file has extra unknown keys → ignored, settings + pageOverrides still processed
- `pageOverrides` contains non-numeric keys → keys cast to int, invalid ones skipped
- `pageOverrides` is `null` or a string → ignored gracefully

---

### Scenario 5: REST API Auth and Error Handling

**Objective**: Verify the export REST endpoint handles auth, errors, and edge cases correctly.

**Steps**:
1. Unauthenticated request to `GET /orkestone/v1/export`
2. **Expected**: 401 response with `rest_forbidden`
3. Authenticated admin request to `GET /orkestone/v1/export`
4. **Expected**: 200 with valid JSON body
5. Check `pageOverrides` contains at most entries for published pages
6. Create a draft page with overrides → export
7. **Expected**: Draft page NOT in `pageOverrides`
8. Trash a page with overrides → export
9. **Expected**: Trashed page NOT in `pageOverrides`
10. Set `VBB_PRO_PAGE_SETTINGS_OPTION` with an entry for a non-existent page → export
11. **Expected**: Non-existent page NOT in `pageOverrides`

**Edge cases**:
- Export with hundreds of page overrides → completes within normal timeout, response is well-formed JSON
- Export with no `vbb_pro_page_settings` option at all → `pageOverrides` is `{}`
- REST route conflicts with existing `orkestone/v1` routes → no 500 errors

---

## Regression Areas

The following existing features MUST continue to work after the export/template changes. Each area includes what to verify and why it could break.

| Area | What to Verify | Risk if Broken |
|------|---------------|----------------|
| **R1. Existing admin-post export** (`admin-post.php?action=vbb_pro_export_settings`) | Clicking "Exportar JSON" on the Export/Import page still downloads the legacy format with `profileType: "pro-elite-settings"` and `schemaVersion: "0.3.2"`. | Changes to export data assembly must not break the existing server-side export handler. |
| **R2. Existing admin-post import** (`$_FILES['proJson']` handler) | Uploading a legacy JSON file on the Export/Import page restores global settings correctly. | Changes to `vbb_pro_handle_admin_actions()` to support `pageOverrides` must preserve the existing import path for legacy files. |
| **R3. Settings save/load via REST API** | Changing a text field → debounced XHR → saved on refresh. REST API returns correct merged settings. | Adding the `style` field to sanitization must not alter other block fields. |
| **R4. Page selector and per-page settings** | Switching pages loads correct settings, merges global + per-page. Per-page settings save independently. | Changes to `vbb_pro_update_page_settings()` or deep merge for style field must not break existing merge behavior. |
| **R5. Block toggle enable/disable** | Toggling a block ON shows expanded settings (including new style selector), OFF collapses. Frontend respects block visibility. | New DOM elements in `renderBlockSettings()` must be inside the existing `vbb-cc-block-settings` wrapper. |
| **R6. Baker functions for non-modified sections** | `vbb_bake_benefits()`, `vbb_bake_services_grid()`, `vbb_bake_faq()`, `vbb_bake_process()`, `vbb_bake_logo_cloud()`, `vbb_bake_pricing_tables()`, `vbb_bake_team_section()` produce identical output before and after changes. | Extracting shared helpers must not change the output of unaffected baker functions. |
| **R7. `vbb_bake_section()` dispatcher** | Section routing to correct baker function based on type key. | Changes to baker map or data merge must not break routing. |
| **R8. Page regeneration (`/regenerate-pages`)** | "Regenerate Pages" button calls correct endpoint, replaces `{{vbb_*}}` placeholders across all pages. | Style dispatch changes must not break the regeneration pipeline. |
| **R9. Per-block color overrides** | Setting `blocks.hero.colors.background = #ff0000` outputs `.vbb-section-hero { --vbb-pro-background: #ff0000 }` on frontend. | Changes to `renderBlockSettings()` or the block settings structure must preserve the `colors` sub-object handling. |
| **R10. postMessage bridge and CSS variable injection** | Color drag updates preview via postMessage. Changing a color saves and updates preview. | New style selector field must not interfere with postMessage message types — uses same `onFieldChange` mechanism. |
| **R11. `vbb_pro_sanitize_settings()` backward compatibility** | Old profiles with `colors[]` (flat) format are still converted to `palettes` format. Blocks as booleans still converted to objects. | Adding `style` validation must not break the existing backward-compat conversion logic. |
| **R12. `vbb_pro_deep_merge()` for nested blocks** | Per-page `blocks.hero.colors.background` overrides global `blocks.hero.colors.background` while keeping other colors. | The `style` field must merge correctly: per-page `blocks.hero.style` overrides global `blocks.hero.style`. |
| **R13. Legacy export schema** | Existing export files with `schemaVersion: "0.3.2"` remain importable. | New `schemaVersion: "1.0.0"` MUST NOT change the legacy export handler. |
| **R14. Confirmation dialog patterns** | All destructive actions (reset, regenerate, delete menu item) still show confirmation via `CC.showConfirmToast()`. | The new style-change confirmation must follow the same pattern. |
| **R15. Command Center toolbar buttons** | "Save as Profile", "Regenerate Pages", "Reset to Vertical Defaults" buttons remain functional in the toolbar. | Adding "Export" button must not affect existing button event bindings. |

---

## Spec Gaps & Ambiguities (Risks for Design Phase)

| Gap | Description | Impact |
|-----|-------------|--------|
| **G1. Style variants content** | The proposal says "Modify 2–3 high-impact baker functions (hero, cta-final, testimonials)" with style dispatch but doesn't specify what layout/structure changes style B and C produce. | Design must define exact markup output for each style variant per section type. Without this, implementation and verification are undefined. |
| **G2. Shared helper signature** | Proposal extracts `vbb_render_cta_button()` and `vbb_render_heading_block()` but doesn't define their signatures, what arguments they accept, or what markup they return. | Design must specify function signatures (params, return format) and which baker functions call each helper. |
| **G3. Style confirmation UX** | "Show confirmation dialog" on style change — but the proposal doesn't specify whether this applies to per-page or global style changes, or both. Also unclear if the confirmation is a toast-based confirm (like existing pattern) or a modal. | Design must decide: toast confirm or modal? Apply to global, per-page, or both? |
| **G4. Auto-rebake scope** | REQ-ET11 says style change triggers regenerate of "current page" — but if the user changes the global style (not per-page), should it regenerate ALL pages or just the current page? | Global style change logically affects all pages. Proposal mentions "current page" but this may be too narrow. Design must define scope. |
| **G5. Export REST route vs admin-post export** | The proposal says "Add GET /orkestone/v1/export" but the existing admin-post export (`vbb_pro_export_settings()`) already exports settings with a different schema. Will both coexist? If REST export replaces the admin-post one, what about the legacy Export/Import page link? | Design must decide: replace legacy export or co-exist? If co-exist, both must remain consistent. The REST export returns the new schema (`1.0.0`), the admin-post export returns `0.3.2`. This could confuse users. |
| **G6. Import handler location** | The existing import is in `pro-admin.php` (admin_post handler). The proposal says "Extend existing import handler" but doesn't specify if we also add a REST import endpoint (`POST /orkestone/v1/import`) or only extend the admin-post handler. | Without a REST import endpoint, the "Export" from Command Center can only be imported via the legacy admin page. Design should decide if a REST import endpoint is needed in Stage 1. |
| **G7. `pageOverrides` merge strategy on import** | REQ-ET4 says "Existing entries NOT in import are preserved" — but is it a full merge (deep merge per page) or a replace? If page 123 has overrides `{blocks.hero.title: "X"}` in the import and existing `{blocks.hero.style: "B"}`, what's the result? | Design must specify: import replaces per-page entry entirely, or deep-merges with existing? Proposal says "preserve" which suggests merge. |
| **G8. Export filename convention** | REQ-ET3 says `orkestone-export-{timestamp}.json` but doesn't specify the timestamp format. | Minor, but design should pick a consistent format (e.g., `YYYYMMDD_HHmmss`). |
| **G9. pageOverrides key format** | `pageOverrides` is keyed by page ID. The current `VBB_PRO_PAGE_SETTINGS_OPTION` stores `[pageId => settings]`. The export `pageOverrides` should use string keys (JSON doesn't support integer keys) — `"123": {...}` not `123: {...}`. | PHP's `json_encode` on an array with integer keys produces `{"123":...}` — string keys. This should work naturally but design should confirm. |

---

## Success Gates (for sdd-verify)

- [ ] `GET /orkestone/v1/export` returns 200 with valid JSON matching envelope schema
- [ ] Export JSON round-trips: export → import → export produces identical JSON (modulo timestamps)
- [ ] "Export" button exists in Command Center toolbar and triggers file download
- [ ] Legacy export (admin-post) still produces `schemaVersion: "0.3.2"` format
- [ ] Legacy import (admin form) still works with both old and new format files
- [ ] Every block has `style: 'A'` by default in fresh install
- [ ] Invalid style values are sanitized to `'A'`
- [ ] Changing hero style from A to B produces different baked markup
- [ ] Changing cta-final style from A to B produces different baked markup
- [ ] Changing testimonials style from A to B produces different baked markup
- [ ] Style selector button-group appears in `renderBlockSettings()` with A, B, C buttons
- [ ] Style change triggers confirmation dialog then re-bake
- [ ] Shared helper `vbb_render_cta_button()` exists and is used by at least 2 baker functions
- [ ] Shared helper `vbb_render_heading_block()` exists and is used by at least 2 baker functions
- [ ] All 15 regression areas pass automated or manual check
- [ ] No PHP warnings/notices during export, import, style change, or re-bake
