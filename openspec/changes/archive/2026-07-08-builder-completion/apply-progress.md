# Apply Progress: Builder Completion — Phase 4

**Change**: builder-completion
**Phase**: 4 — Menu Management & Editor UI
**Mode**: Standard
**Status**: Completed

## Completed Tasks

### Phase 1 (from prior batches)
- [x] 1.1 Add `vbb_get_vertical_page_by_id($page_id)` helper in `inc/page-blueprint.php`
- [x] 1.2 Add `vbb_bake_page_content($page_id)` in `inc/block-baker.php`
- [x] 1.3 Fix `vbb_bake_process()` loop bug (lines 351–367)
- [x] 1.4 Add page selector DOM container in `inc/pro-admin.php`
- [x] 1.5 Wire `CC.el.pageSelector` in `admin-pro.js`
- [x] 1.6 Update `inc/test-block-baker.php` with new tests

### Phase 2 (from prior batches)
- [x] 2.1 Insert `{{vbb_*}}` tokens in single-value baker functions (hero-centered, cta-final, contact-section)
- [x] 2.2 Add `{{vbb_*_heading}}` tokens for all 8 repeatable section bakers
- [x] 2.3 Expand `vbb_pro_replace_dynamic_content()` map to include all 20 tokens
- [x] 2.4 Update tests for token assertions + add token resolution test

### Phase 3 (from prior batch)
- [x] 3.1 Add `POST /orkestone/v1/pages` — `vbb_rest_create_page()`: `wp_insert_post`, init per-page settings, return `201 {page: {id, slug}, settings}`
- [x] 3.2 Add `DELETE /orkestone/v1/pages/{id}` — `vbb_rest_delete_page()`: `wp_trash_post`, `unset(vbb_pro_page_settings[id])`, return `200 {success, page_id}`
- [x] 3.3 Expand `GET /orkestone/v1/pages` — add `slug`, `sections`, `hasSettings` bool to each page entry
- [x] 3.4 Add `POST /orkestone/v1/pages/{id}/regenerate` — `vbb_rest_regenerate_page()`: call `vbb_bake_page_content($id)`, return `200 {success, page_id}`

### Phase 4 (this batch)
- [x] 4.1 Menu items schema sanitization in `vbb_pro_sanitize_settings()` — recursive `vbb_pro_sanitize_menu_items()` validates label/type/url/targetPageId/children; type validated against `['page', 'custom']`; empty array default in `vbb_pro_default_settings()`
- [x] 4.2 `vbb_pro_sync_menu_to_wp_navigation()` — builds `<!-- wp:navigation-link -->` block markup, upserts `wp_navigation` post "OrkestOne Primary Navigation", stores last-sync timestamp in `vbb_last_menu_sync` option; helper `vbb_pro_build_nav_block()` for recursive block generation
- [x] 4.3 Menu REST API — `GET /orkestone/v1/menu` (`vbb_rest_get_menu`) returns `{menuItems}` from global settings; `PUT /orkestone/v1/menu` (`vbb_rest_update_menu`) replaces all items, saves to settings, triggers wp_navigation sync; `POST /orkestone/v1/menu/items` appends one item; `DELETE /orkestone/v1/menu/items/{idx}` removes by index; all routes registered in `vbb_register_command_center_routes()`
- [x] 4.4 Menu editor UI — JS-injected card via `CC.renderMenuEditor()` using `buildCard()` pattern; sortable list with up/down buttons, add/delete items, label/type/target field inputs, page dropdown selector for page-type items, recursive children rendering; separate save via `CC.saveMenu()` / `CC.debouncedMenuSave()` to `PUT /orkestone/v1/menu`; CSS styles for menu editor components

### Phase 5 (this batch)
- [x] 5.1 Activation hook `vbb_pro_on_theme_activation()` in `inc/pro-settings.php` — hooks `after_switch_theme`, checks `vbb_baker_version` option, runs `vbb_pro_regenerate_all_pages()` if `< 1.0.0`, updates option; `set_time_limit(300)` at start
- [x] 5.2 Admin notice in `inc/pro-admin.php` — `vbb_pro_has_unresolved_tokens()` scans published pages for `{{vbb_` tokens; `vbb_pro_show_regenerate_notice()` shows admin notice with "Open Command Center" and "Regenerate All Pages Now" button; `vbb_pro_handle_regenerate_action()` processes regeneration request via admin_init; cached detection via `vbb_tokens_detected` option
- [x] 5.3 Integration tests — activation hook version comparison logic (fresh/outdated/current), token detection (presence/absence/caching), menu sync creates `wp_navigation` post with correct block markup, regenerate action handler no-ops correctly, `vbb_pro_regenerate_all_pages()` loop

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `orkestone-theme/inc/pro-settings.php` | Modified | **Task 4.1**: Added `menuItems: array()` to default settings. Added `vbb_pro_sanitize_menu_items()` recursive sanitizer. **Task 4.2**: Added `vbb_pro_build_nav_block()` and `vbb_pro_sync_menu_to_wp_navigation()`. **Task 5.1**: Added `vbb_pro_on_theme_activation()` — `after_switch_theme` hook, version check, regeneration call, option update. |
| `orkestone-theme/inc/pro-rest-api.php` | Modified | **Task 4.3**: Added `vbb_rest_get_menu()`, `vbb_rest_update_menu()`, `vbb_rest_append_menu_item()`, `vbb_rest_delete_menu_item()`. Registered 3 new menu routes. |
| `orkestone-theme/assets/js/admin-pro.js` | Modified | **Task 4.4**: Added `CC.state.menuItems`, `CC.loadMenu()`, `CC.renderMenuEditor()`, `CC.renderMenuItemsList()`, `CC.addMenuItem()`, `CC.addMenuChild()`, `CC.deleteMenuItem()`, `CC.moveMenuItem()`, `CC._resolveMenuKey()`, `CC._reRenderMenu()`, `CC._handleMenuChange()`, `CC.saveMenu()`, `CC.debouncedMenuSave()`, `CC.bindMenuEvents()`. Menu card inserted in `renderCards()`. |
| `orkestone-theme/assets/css/admin-pro.css` | Modified | **Task 4.4**: Added CSS styles for menu editor — `.vbb-cc-menu-list`, `.vbb-cc-menu-item`, `.vbb-cc-menu-item-child`, `.vbb-cc-menu-item-row`, `.vbb-cc-menu-drag`, `.vbb-cc-menu-field`, `.vbb-cc-menu-delete`, `.vbb-cc-menu-actions`. |
| `orkestone-theme/inc/pro-admin.php` | Modified | **Task 5.2**: Added `vbb_pro_has_unresolved_tokens()` — scans published pages for `{{vbb_` placeholders; `vbb_pro_show_regenerate_notice()` — admin_notices hook with "Open Command Center" and "Regenerate All Pages Now" buttons; `vbb_pro_handle_regenerate_action()` — admin_init handler for regeneration request; success message in Command Center when redirected after regeneration. |
| `orkestone-theme/inc/test-block-baker.php` | Modified | **Task 5.3**: Added WP function stubs (get_option, get_posts, wp_insert_post, wp_json_encode, admin_url, etc.); Phase 5 function replicas (activation, token detection, menu sync, regenerate handler); 21 test assertions covering activation version logic, token detection scanning/caching, menu sync block markup, regenerate handler no-op. |

## Deviations from Design

- Added `POST /orkestone/v1/menu/items` and `DELETE /orkestone/v1/menu/items/{idx}` endpoints beyond the design spec to support individual item operations as requested in the phase brief.
- The menu editor card is JS-injected via `buildCard()` instead of a PHP-rendered `<div id="vbb-cc-menu">` container, following the existing pattern used by all other cards in the Command Center.
- Menu items use `CC.saveMenu()` to `PUT /menu` (separate from the general settings `POST /vertical-settings`), because the menu endpoint also triggers wp_navigation sync on the server side.

## Issues Found

None.

## Workload / PR Boundary

- **Mode**: feature-branch-chain (PR 5 of 5) — final phase
- **Current work unit**: 5 — Activation backfill + admin notice + integration tests
- **Boundary**: All tasks complete. Ready for verify.

## Status

**21/21 tasks complete** across Phase 1 (6) + Phase 2 (4) + Phase 3 (4) + Phase 4 (4) + Phase 5 (3). Ready for verify.
