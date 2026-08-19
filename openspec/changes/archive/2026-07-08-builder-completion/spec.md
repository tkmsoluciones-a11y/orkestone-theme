# Builder Completion — Full No-Code Website Builder

## Requirements

| ID | Requirement (RFC 2119) | Input → Output | Verification |
|----|----------------------|---------------|-------------|
| REQ-1 | `vbb_bake_page_content($page_id)` MUST exist and regenerate baked content | Page ID → updated `post_content` with placeholders | Call function; assert `post_content` contains `{{vbb_*}}` tokens |
| REQ-2 | `vbb_bake_process()` MUST produce valid HTML for any step count without PHP notices | 0, 1, or 5 process steps → HTML output, no `E_NOTICE` | Run with each count; assert no `undefined index` or `undefined variable` warnings |
| REQ-3 | All 11 baker functions MUST output `{{placeholder}}` tokens for editable single-value fields | Vertical config + merged settings → output with tokens | Each function's output contains `{{vbb_*}}` instead of hardcoded text |
| REQ-4 | `vbb_pro_replace_dynamic_content()` MUST resolve every known `{{vbb_*}}` token | Content with all tokens → content with merged setting values | Every token defined in baker functions has a corresponding replacement entry |
| REQ-5 | Repeatable items (services, testimonials, FAQ, etc.) MUST be baked from merged settings directly | Settings with N items → baked HTML with N items | Verify 0, 1, and 5 items produce correct count in baked output |
| REQ-6 | `POST /orkestone/v1/pages` MUST create a WP page, initialize per-page settings, return 201 | `{title, slug, sections}` → `201 {page: {id, slug}, settings}` | Page exists in `wp_posts`; settings key exists in `vbb_pro_page_settings` |
| REQ-7 | `DELETE /orkestone/v1/pages/{id}` MUST trash the page and remove its settings | Page ID → `200 {success, page_id}` | `wp_trash_post` called; page key removed from settings option |
| REQ-8 | `GET /orkestone/v1/pages` MUST return id, title, slug, sections, hasSettings for each builder page | None → `200 {pages: [...]}` | Each page entry includes all required fields |
| REQ-9 | `POST /orkestone/v1/pages/{id}/regenerate` MUST re-bake page content via `vbb_bake_page_content()` | Page ID → `200 {success, page_id}` | `post_modified` timestamp updated; content contains tokens |
| REQ-10 | `GET /orkestone/v1/menu` MUST return merged global+page menu items | None → `200 {menuItems: [...]}` | Response includes items from global settings merged with page overrides |
| REQ-11 | `PUT /orkestone/v1/menu` MUST replace all items and sync to `wp_navigation` | `{menuItems: [...]}` → `200 {success}` | Settings updated; `wp_navigation` post content matches items |
| REQ-12 | Command Center MUST render a page selector dropdown | Admin page load → populated `<select>` element | DOM element `#vbb-page-selector` exists with option per page |
| REQ-13 | Command Center MUST render a sortable menu editor card | Admin page load → card with sortable items | Drag handles, add/delete buttons, submenu nesting visible in DOM |
| REQ-14 | Theme activation/update MUST trigger full regeneration of all builder pages | Theme switch → all pages re-baked | No raw `{{vbb_*}}` tokens on frontend after activation; admin notice shown if any remain |

## End-to-End Scenarios

### Scenario 1: Create page, edit hero title, verify frontend
- GIVEN the user is in the Command Center with a vertical loaded
- WHEN they click "Add Page", enter title "About Us", select sections `[hero, services-grid]`, and save
- THEN the new page appears in the page selector dropdown (`#vbb-page-selector`)
- WHEN they select the new page and change `hero.title` to "Our Story"
- THEN the frontend preview iframe reflects "Our Story" in the hero section
- AND the actual frontend page displays "Our Story"

### Scenario 2: Delete page with per-page settings
- GIVEN a builder page with custom per-page settings exists
- WHEN the user deletes it via the Command Center
- THEN the page is moved to WordPress trash
- AND its settings key is removed from `vbb_pro_page_settings`
- AND the page no longer appears in the page selector dropdown

### Scenario 3: Manage navigation menu with submenu items
- GIVEN the user is in the Command Center menu editor card
- WHEN they add item "Services" (Page type) then add child "Web Design" under it
- THEN the menu items display with nesting/indentation
- WHEN they click Save
- THEN the frontend navigation renders "Services" with a dropdown containing "Web Design"

### Scenario 4: Regenerate all pages after theme activation
- GIVEN existing pages were baked before placeholder conversion
- WHEN the theme is deactivated and reactivated
- THEN all builder pages are regenerated via `vbb_pro_regenerate_all_pages()`
- AND no raw `{{vbb_*}}` tokens appear on any frontend page
- AND the admin notice for detected placeholders is not shown

### Scenario 5: Edit repeatable items block
- GIVEN a page with the Services Grid block containing 3 default services
- WHEN the user adds a 4th service via the Command Center settings
- THEN the frontend page renders 4 services in the grid
- AND the baked HTML for each service contains the correct icon, title, and description from settings

## Regression Areas

| Area | Risk | Guard |
|------|------|-------|
| Existing Command Center editing (colors, typography, layout, block toggles) | Placeholder conversion or new UI elements could break card rendering | All existing editing flows (global settings, per-page overrides) MUST produce same API payloads |
| Frontend rendering via `the_content` filter | Incomplete `vbb_pro_replace_dynamic_content()` mapping leaves raw `{{vbb_*}}` tokens visible | Every emitted token MUST have a corresponding replacement entry BEFORE the baker function is changed |
| Global ↔ per-page settings inheritance | Menu items in per-page settings may shadow global incorrectly | Merge logic MUST follow the same hierarchy as other per-page settings overrides |
| Vertical JSON import pipeline | Changes to baker function parameter expectations could break import | Vertical import MUST produce identical output for all section types (static values only, no placeholders needed during import) |
| Existing `GET /orkestone/v1/pages` consumers | New fields (`sections`, `hasSettings`) change response shape | Add new fields; MUST NOT remove, rename, or change type of existing fields |
| Debounced save pattern (500ms) | New endpoints must follow same nonce + sanitization pattern | All new REST endpoints MUST require `manage_options` capability and `wp_rest` nonce |
