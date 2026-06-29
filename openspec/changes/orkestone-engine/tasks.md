# Tasks: Orkestone Engine — Static Baking Pipeline

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~680 (8 files, 10 bakers, ~150 tests) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1: Foundation & Baking → PR 2: Import + Reset → PR 3: WooCommerce & Wiring |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Baking pipeline + helpers + autoload | PR 1 | Base: main. Tests for all 10 bakers included. |
| 2 | Reset orchestrator + page blueprinter + vertical importer | PR 2 | Base: main (or PR 1 branch if stacked). Tests for reset & import. |
| 3 | WooCommerce catalog + admin wiring + schema update | PR 3 | Base: main (or PR 2 branch if stacked). Manual verification of WC flow. |

## Phase 1: Foundation & Baking

- [x] 1.1 Add `vbb_svg_placeholder()` to `inc/helpers.php` returning inline 1×1 transparent SVG — Verify: `vbb_svg_placeholder()` returns string containing `data:image/svg+xml`
- [x] 1.2 Create `inc/block-baker.php` with `vbb_bake_section($type,$page,$sections)` dispatcher — Verify: dispatcher calls correct baker or fallback for unknown type
- [x] 1.3 Implement `vbb_bake_hero()` and `vbb_bake_hero_centered()` returning `wp:group`+`wp:heading`+`wp:paragraph` — Verify: output contains `<!-- wp:heading -->` with provided title
- [x] 1.4 Implement `vbb_bake_services_grid()` and `vbb_bake_benefits()` returning `wp:columns`+`wp:column` — Verify: each service item becomes a `wp:column` block
- [x] 1.5 Implement `vbb_bake_process()` and `vbb_bake_testimonials()` returning `wp:group`+`wp:paragraph` per step/review — Verify: correct item count in block markup
- [x] 1.6 Implement `vbb_bake_faq()`, `vbb_bake_contact_section()`, `vbb_bake_cta_final()` — Verify: FAQ produces `wp:details` (or similar), contact/cta have `wp:buttons`
- [x] 1.7 Wire `vbb_bake_section()` fallback: unknown type → `<!-- wp:paragraph -->Unknown: {type}<!-- /wp:paragraph -->` — Verify: unknown type returns fallback paragraph text
- [x] 1.8 Register `inc/block-baker.php` in `functions.php` autoload — Verify: `function_exists('vbb_bake_section')` returns true

## Phase 2: Import Orchestration & Reset

- [x] 2.1 Create `inc/reset-orchestrator.php` with `vbb_reset_vertical_pages($key)` trashing `_vbb_vertical` posts — Verify: posts with matching meta have `post_status='trash'`
- [x] 2.2 Implement `vbb_update_active_vertical_config($new_key)` writing JSON to `config/active-vertical.json` — Verify: file reads back `{"active":"ecommerce","fallback":"default"}`
- [x] 2.3 Add `vbb_generate_page_id_map()` to `inc/page-blueprint.php` returning `[slug=>ID]` — Verify: returns array with `['home'=>42,'about'=>43]`
- [x] 2.4 Add `vbb_build_page_content_from_baked($page)` in `inc/page-blueprint.php` using `vbb_bake_section()` per section — Verify: page gets `post_content` with real block markup, not `<!-- wp:pattern -->`
- [x] 2.5 Add `vbb_import_vertical_full($vertical_key)` to `inc/vertical-importer.php` chaining reset → media → baking → menu → frontPage → report — Verify: full run creates pages, menu, and returns report array
- [x] 2.6 Implement media sideloading with placeholder fallback: `vbb_import_vertical_media_with_placeholders()` + `vbb_create_placeholder_attachment()` — Verify: success sets `_vbb_source_url` meta, failure stores `_vbb_broken=1` and placeholder in media library
- [x] 2.7 Build `_vbb_import_report` accumulator during pipeline — Verify: report contains `media_sideloaded:N`, `media_failed:N`, `failed:[{url,reason}]`, `pages_created:N`, `pages_errors:N`

## Phase 3: Navigation, WooCommerce & Wiring

- [x] 3.1 Add two-pass navigation builder: create pages first, then `vbb_generate_page_id_map()`, then `wp_navigation` from `navigation.primary[]` — Verify: menu appears in Appearance → Menus with correct links
- [x] 3.2 Implement `vbb_setup_woocommerce_catalog()` setting `woocommerce_catalog_orders='disabled'` and shop page — Verify: `get_option('woocommerce_catalog_orders')` returns `'disabled'`
- [x] 3.3 Add WC detection: `class_exists('WooCommerce')` check with admin notice on missing — Verify: without WC active, pipeline continues and notice shown
- [x] 3.4 Wire import action in `inc/admin-verticals.php`: import button triggers `vbb_import_vertical_full()` — Verify: clicking import on admin page runs pipeline end-to-end
- [x] 3.5 Add `woocommerce` section to `config/schemas/vertical.schema.json` (mode enum, shop_page) — Verify: JSON schema validates `{woocommerce:{mode:"catalog"}}`

## Phase 4: Testing

- [ ] 4.1 Unit test each `vbb_bake_*()`: known data → valid block markup; empty/null data → graceful fallback — Verify: all tests pass, no warnings
- [ ] 4.2 Unit test `vbb_bake_section()` dispatcher: known type routes correctly, unknown type returns fallback paragraph — Verify: dispatcher coverage >= 90% type coverage
- [ ] 4.3 Unit test `vbb_reset_vertical_pages()`: asserts trash on matching meta, no-op on empty — Verify: posts with `_vbb_vertical=X` are trashed
- [ ] 4.4 Integration: `vbb_import_vertical_full()` with fixture JSON → assert pages created, nav exists, report correct — Verify: pipeline output matches spec scenarios
- [ ] 4.5 Manual: reset vertical → verify trashed pages recoverable (30d), new import produces clean content — Verify: trash has old pages, new pages have block content
