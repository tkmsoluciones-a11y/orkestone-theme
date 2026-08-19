# Spec: Orkestone Engine Testing — Closing Deferred Phase 4

Complete the deferred Phase 4 testing by extending the standalone test suite to cover the reset orchestrator, config management, full import pipeline, and edge cases. **123 existing assertions already pass** in `inc/test-block-baker.php` — this spec adds coverage for the remaining uncovered code paths.

## Requirements

| ID | Requirement (RFC 2119) | Input → Output | Verification |
|----|----------------------|---------------|-------------|
| REQ-R1 | `vbb_reset_vertical_pages($key)` MUST trash all pages with matching `_vbb_vertical` meta | `'law-firm'` → report with `pages_trashed >= 0` | Stub `WP_Query` to return fake post IDs; verify `wp_trash_post()` called for each matching ID |
| REQ-R2 | `vbb_reset_vertical_pages($key)` MUST trash `wp_navigation` posts with `_vbb_source=vertical` meta | `'law-firm'` → report with `navigation_trashed >= 0` | Stub second `WP_Query` for `wp_navigation` type; verify `wp_trash_post()` called for each |
| REQ-R3 | `vbb_reset_vertical_pages($key)` MUST NOT trash posts with a different `_vbb_vertical` meta | Non-matching post → not trashed | Stub `WP_Query` with empty posts for page meta query; verify `wp_trash_post()` not called |
| REQ-R4 | `vbb_reset_vertical_pages('')` MUST return empty report with no side effects | `''` → `['pages_trashed'=>0,'navigation_trashed'=>0,'errors'=>[]]` | Call with empty string; verify no `WP_Query` or `wp_trash_post` calls |
| REQ-R5 | `vbb_update_active_vertical_config($key)` MUST write valid JSON with `active` + `fallback` keys | `'ecommerce'` → `['active'=>'ecommerce','fallback'=>'default','path'=>'...']` | Stub `file_put_contents()` to capture written content; JSON-decode and check keys |
| REQ-R6 | `vbb_update_active_vertical_config($key)` MUST return `WP_Error` on empty key | `''` → `WP_Error` with code `'vbb_empty_key'` | Assert `is_wp_error()` on result; check error code |
| REQ-R7 | `vbb_update_active_vertical_config($key)` MUST return `WP_Error` on write failure | `'ecommerce'` with failing stub → `WP_Error` code `'vbb_config_write_failed'` | Stub `file_put_contents` to return `false`; assert `WP_Error` |
| REQ-R8 | `vbb_import_vertical_full($key)` MUST execute pipeline: Load → (Reset if different) → Config → Media → Pages → Nav → WooCommerce → FrontPage → Report | `'test-fixture'` with stub chain → structured report | Stub each pipeline step; verify all steps called in order; verify report shape |
| REQ-R9 | `vbb_import_vertical_full($key)` MUST skip reset when importing the same vertical | Same as current active key → `reset` is `null` in result | Stub `vbb_is_different_vertical` to return `false`; verify `reset` key is `null` in pipeline result |
| REQ-R10 | `vbb_import_vertical_full($key)` MUST abort with error when vertical config not found | `'nonexistent'` → result with `success=false` and `error` string | Stub `vbb_load_vertical_by_key` to return `null`; assert `success=false` and descriptive error |
| REQ-R11 | `vbb_import_vertical_full($key)` MUST abort with error when config update fails | Valid key but config write fails → result with `success=false` and `error` | Stub `vbb_update_active_vertical_config` to return `WP_Error`; assert `success=false` |
| REQ-R12 | Pipeline MUST count `pages_created` and `pages_errors` in final report | Stubbed page generation → report counts match | Stub `vbb_generate_vertical_pages_from_baked` to return known `created`/`errors` counts |
| REQ-R13 | Pipeline MUST count `media_sideloaded` and `media_failed` in final report | Stubbed media import → report counts match | Use `$report` reference passed to `vbb_import_vertical_media_with_placeholders` |
| REQ-R14 | Reset orchestrator MUST use `wp_trash_post()` (NOT `wp_delete_post()`) | Any trashed post → uses trash, not permanent delete | Track which posts receive `wp_trash_post` vs `wp_delete_post`; assert `wp_delete_post` is NEVER called |

## Scenario-Based Tests

### S1: Full import run with valid fixture
**Setup**: Stub the full dependency chain so `vbb_import_vertical_full('test-fixture')` runs without real WordPress.
**Steps**:
1. Stub `vbb_load_vertical_by_key` returning a minimal valid vertical config with `verticalKey='test-fixture'`, 2 pages (`home`, `about`), `navigation.primary` with 2 items, `sections.hero`, `importOptions.homepageKey='home'`
2. Stub `vbb_is_different_vertical` returning `true` (simulate real switch)
3. Stub `vbb_get_active_vertical_key` returning `'previous-vertical'`
4. Stub `vbb_reset_vertical_pages` to return report (1 page trashed, 1 nav trashed)
5. Stub `vbb_update_active_vertical_config` to return success array
6. Stub `vbb_import_vertical_media_with_placeholders` via `$report` ref to yield 3 sideloaded, 1 failed
7. Stub `vbb_generate_vertical_pages_from_baked` to return 2 created, 0 errors
8. Stub `vbb_generate_page_id_map` to return `['home'=>10,'about'=>11]`
9. Stub `vbb_generate_vertical_navigation` to return success with 2 items
10. Stub `vbb_apply_vertical_front_page` to return applied with pageId=10
11. Stub `vbb_setup_woocommerce_catalog` to return not configured
**Assert**:
- Result `success` = `true`
- Result `vertical` = `'test-fixture'`
- `reset` contains 1 page trashed, 1 nav trashed
- `configUpdated` = `true`
- Result `report.pages_created` = 2
- Result `report.pages_errors` = 0
- Result `report.media_sideloaded` = 3
- Result `report.media_failed` = 1
- All pipeline stubs called in correct order

### S2: Same-vertical re-import (no reset triggered)
**Setup**: Active vertical key is `'test-fixture'` and importing `'test-fixture'` again.
**Steps**:
1. Stub `vbb_is_different_vertical` returning `false`
2. All other stubs as S1
**Assert**:
- `reset` key is `null` in the result (no reset executed)
- Pipeline still proceeds to create pages, navigation, etc.
- Final report still populated correctly

### S3: Empty reset (no-op on empty key)
**Setup**: Call `vbb_reset_vertical_pages('')` with empty string.
**Steps**:
1. Call the function directly with `''`
**Assert**:
- Returns `['pages_trashed'=>0,'navigation_trashed'=>0,'errors'=>[]]`
- No PHP notices or warnings
- `WP_Query` is NOT instantiated (can verify via stub counter)
- `wp_trash_post` is NOT called

### S4: Cross-vertical switch with reset
**Setup**: Active vertical is `'law-firm'`, importing `'ecommerce'`.
**Steps**:
1. Stub `vbb_is_different_vertical` → `true`
2. Stub `vbb_get_active_vertical_key` → `'law-firm'`
3. Stub `vbb_reset_vertical_pages('law-firm')` to trash 5 pages + 1 nav
4. Rest of pipeline stubs as S1 with `'ecommerce'` fixture
**Assert**:
- Reset called with `'law-firm'` (old vertical key)
- Reset report shows 5 pages and 1 nav trashed
- Pipeline completes with `vertical='ecommerce'`
- All new pages created under `'ecommerce'` vertical key

### S5: Pipeline abort on missing vertical
**Setup**: Call `vbb_import_vertical_full('nonexistent')`.
**Steps**:
1. Stub `vbb_load_vertical_by_key` returning `null`
**Assert**:
- Result `success` = `false`
- Result `error` contains descriptive message referencing `'nonexistent'`
- No subsequent pipeline steps are called (verify stub call counters)

### S6: Config write failure during pipeline
**Setup**: Call `vbb_import_vertical_full('ecommerce')` with failing config write.
**Steps**:
1. Stub `vbb_update_active_vertical_config` returning `new WP_Error('vbb_config_write_failed', 'message')`
2. All pipeline stubs up to config update step as S1
**Assert**:
- Result `success` = `false`
- Result `error` mentions the write failure
- Pipeline aborts — no media/pages/navigation steps called

## Regression Areas

The following existing engine features MUST NOT break. All 123 existing assertions in `inc/test-block-baker.php` must still pass after adding new code.

| Area | Covered By | Risk if Broken |
|------|-----------|---------------|
| All 9 block bakers (hero, hero_centered, services_grid, benefits, process, testimonials, faq, contact_section, cta_final, logo_cloud, pricing_tables, team_section) | Existing hero/baker tests + Phase 5 regen tests | Pages generate incorrect block markup; live preview renders wrong content |
| `vbb_bake_section()` dispatcher with known/unknown types + data fallback | Existing dispatcher tests | New section types (if added later) produce empty output |
| `vbb_bake_page_content()` with `wp_update_post` stub | Existing page content test | Page regeneration broken; activation hook produces stale content |
| Token placeholder resolution (`vbb_pro_replace_dynamic_content` pattern) | Existing token resolution tests | Front-end shows raw `{{vbb_...}}` tokens instead of resolved content |
| Phase 5 activation hook (`vbb_pro_on_theme_activation`) | Existing activation tests (fresh install, old version, current version) | Theme activation does not regenerate pages; old token placeholders persist |
| Phase 5 admin notice logic (`vbb_pro_has_unresolved_tokens`, `vbb_pro_show_regenerate_notice`) | Existing token detection tests | Admin never notified of unresolved tokens; stale content goes unnoticed |
| Phase 5 menu sync (`vbb_pro_sync_menu_to_wp_navigation`, `vbb_pro_sanitize_menu_items`, `vbb_pro_build_nav_block`) | Existing menu sync tests | Navigation blocks malformed; page-type items don't link correctly |
| Phase 5 regenerate action handler + `vbb_pro_regenerate_all_pages()` | Existing regen handler tests | Admin "Regenerate All" button does nothing |
| PHP notice/warning discipline across all functions | Existing `assert_no_notices()` tests | Undefined variables or array keys crash the engine in WP_DEBUG mode |
| Existing stub infrastructure (WP stubs for `get_option`, `wp_insert_post`, `get_posts`, `get_post_field`, `update_option`, etc.) | All existing tests | Adding new stubs must not conflict with or override existing stubs; use `function_exists()` guards |

## Delivery Notes

- All new tests go in `inc/test-orkestone-engine.php` (new file) — keep `inc/test-block-baker.php` untouched.
- The new file should `require_once` existing stubs from `test-block-baker.php` or redefine with `function_exists()` guards (prefer the latter to avoid coupling).
- **Single test fixture JSON** needed at `inc/test-fixture-minimal.json` for pipeline integration tests.
- **Manual validation protocol** at `docs/testing/reset-validation.md` documents the real-WP verification steps.
- Estimated new assertions: **≥45** across all new test sections.
- All 123 existing assertions must still pass after adding new code — run `php inc/test-block-baker.php` before marking complete.

## Open Questions

1. **`vbb_get_active_vertical_settings()`** calls `get_theme_file_path()` — is there a helper function for this or should we stub it directly? The function uses WordPress filesystem path resolution.
2. **`WP_Query` instantiation**: In the reset orchestrator, `WP_Query` is used directly. Should we wrap it in a helper for testability, or stub the class itself in tests? Proposal suggests stubbing `WP_Query` constructor or creating a test seam.
