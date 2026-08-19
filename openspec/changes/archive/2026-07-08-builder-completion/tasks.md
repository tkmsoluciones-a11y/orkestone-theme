# Tasks: Builder Completion — Full No-Code Website Builder

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~700–800 |
| 800-line budget risk | Low |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Phase 1) → PR 2 (Phase 2) → PR 3 (Phase 3) → PR 4 (Phase 4) → PR 5 (Phase 5) |
| Delivery strategy | force-chained |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Base Branch |
|------|------|-----------|-------------|
| 1 | Core bake foundation + page selector UI | PR 1 | feature/builder-completion |
| 2 | Baker token mapping + replacement map | PR 2 | PR 1 branch |
| 3 | Page CRUD REST API | PR 3 | PR 2 branch |
| 4 | Menu data model + sync + editor UI | PR 4 | PR 3 branch |
| 5 | Activation backfill + admin notice | PR 5 | PR 4 branch |

## Phase 1: Core Bake Foundation

- [x] 1.1 Add `vbb_get_vertical_page_by_id($page_id)` helper in `inc/page-blueprint.php` — returns page config from vertical JSON by page ID, includes `sections` array
- [x] 1.2 Add `vbb_bake_page_content($page_id)` in `inc/block-baker.php` — loads merged settings, filters sections, loops baker functions, calls `wp_update_post`
- [x] 1.3 Fix `vbb_bake_process()` loop bug (lines 351–367) — replace broken `if(''!==$output)` guards with proper `foreach` over `$steps` setting `$step_title` / `$description`
- [x] 1.4 Add `<div id="vbb-page-selector">` container before `<div id="vbb-cc-cards">` in `vbb_pro_render_command_center()` (`inc/pro-admin.php`)
- [x] 1.5 Wire `CC.el.pageSelector = document.getElementById('vbb-page-selector')` in `admin-pro.js` `init()` — already called by `CC.renderPageSelector()` on page load
- [x] 1.6 Update `inc/test-block-baker.php` — add test for `vbb_bake_process()` with 0/1/5 steps, verify no PHP notices; add integration-like test for `vbb_bake_page_content()` when WP functions available
- **Files**: `inc/page-blueprint.php`, `inc/block-baker.php`, `inc/pro-admin.php`, `assets/js/admin-pro.js`, `inc/test-block-baker.php`

## Phase 2: Baker Token Mapping

- [x] 2.1 Insert `{{vbb_hero_centered_title}}` / `{{vbb_hero_centered_tagline}}` in `vbb_bake_hero_centered()`; `{{vbb_cta_final_text}}` / `{{vbb_cta_final_button_text}}` / `{{vbb_cta_final_button_url}}` in `vbb_bake_cta_final()`; `{{vbb_contact_email}}` / `{{vbb_contact_phone}}` in `vbb_bake_contact_section()` — replace hardcoded `vbb_esc_text()` values with `{{vbb_*}}` placeholders
- [x] 2.2 Add `{{vbb_*_heading}}` tokens for all 8 repeatable section bakers (services-grid, benefits, testimonials, faq, process, pricing, team, logo-cloud) — replace `vbb_esc_text($data['heading'])` in heading output with `{{vbb_{section}_heading}}`
- [x] 2.3 Expand `vbb_pro_replace_dynamic_content()` in `inc/pro-settings.php` — add map entries for every token from TASK-2.1 and TASK-2.2 (hero_centered ×2, cta_final ×3, contact ×2, 8 section headings = 15+ entries), resolve via `$settings['blocks'][$section_key][$field]`
- [x] 2.4 Update `inc/test-block-baker.php` — assert every baker function output contains expected `{{vbb_*}}` tokens; assert `vbb_pro_replace_dynamic_content()` resolves each token with mock settings
- **Files**: `inc/block-baker.php`, `inc/pro-settings.php`, `inc/test-block-baker.php`

## Phase 3: Page CRUD API

- [x] 3.1 Add `POST /orkestone/v1/pages` — `vbb_rest_create_page()`: `wp_insert_post`, init per-page settings `vbb_pro_page_settings[$post_id]['sections']`, return `201 {page: {id, slug}, settings}`
- [x] 3.2 Add `DELETE /orkestone/v1/pages/{id}` — `vbb_rest_delete_page()`: `wp_trash_post`, `unset(vbb_pro_page_settings[id])`, return `200 {success, page_id}`
- [x] 3.3 Expand `GET /orkestone/v1/pages` — add `slug`, `sections` (from per-page settings), `hasSettings` bool to each page entry; preserve existing `id`, `title` fields
- [x] 3.4 Add `POST /orkestone/v1/pages/{id}/regenerate` — `vbb_rest_regenerate_page()`: call `vbb_bake_page_content($id)`, return `200 {success, page_id}`
- **Files**: `inc/pro-rest-api.php`

## Phase 4: Menu Data Model

- [x] 4.1 Add `menuItems` schema sanitization in `vbb_pro_sanitize_settings()` (`inc/pro-settings.php`) — recursive sanitization for label/type/url/targetPageId/children; validate type in `['page','custom']`
- [x] 4.2 Add `vbb_pro_sync_menu_to_wp_navigation(array $menu_items)` in `inc/pro-settings.php` — build `<!-- wp:navigation-item -->` block markup, find/create `wp_navigation` post "OrkestOne Primary Navigation", `wp_update_post()` with nav content; store last-sync timestamp in `vbb_last_menu_sync` option
- [x] 4.3 Add `GET /orkestone/v1/menu` (`vbb_rest_get_menu`) returning `{menuItems: [...]}` from global settings + `PUT /orkestone/v1/menu` (`vbb_rest_update_menu`) accepting `{menuItems: [...]}`, updating settings, calling sync, returning `200 {success}` — register both routes in `vbb_register_command_center_routes()`
- [x] 4.4 Add sortable menu editor card in Command Center — PHP: render container (JS-injected via `buildCard()`); JS: add `CC.renderMenuEditor()` using existing `buildCard()` pattern, re-using `CC.debouncedSave()`; menu items stored/loaded via new GET/PUT /menu endpoints
- **Files**: `inc/pro-settings.php`, `inc/pro-rest-api.php`, `inc/pro-admin.php`, `assets/js/admin-pro.js`

## Phase 5: Backfill & Polish

- [x] 5.1 Add `vbb_pro_on_theme_activation()` in `inc/pro-settings.php` — hook `after_switch_theme`, check `vbb_baker_version` option, call `vbb_pro_regenerate_all_pages()` if `< 1.0.0`, update option; set `set_time_limit(300)` at start
- [x] 5.2 Add admin notice in `inc/pro-admin.php` — after activation, scan published pages for `{{vbb_` tokens in content; if found, show notice with link to Command Center ("Regenerate pages") and button to run `vbb_pro_regenerate_all_pages()` manually
- [x] 5.3 Write integration tests — menu sync creates `wp_navigation` post matching menuItems; page CRUD endpoints respond correctly; activation hook triggers regeneration; admin notice shows when tokens detected
- **Files**: `inc/pro-settings.php`, `inc/pro-admin.php`, `inc/test-block-baker.php`
