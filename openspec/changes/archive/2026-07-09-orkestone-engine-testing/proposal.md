# Proposal: Orkestone Engine Testing — Closing Deferred Phase 4

Complete the deferred Phase 4 testing of the Orkestone Engine by adding unit tests for the reset orchestrator, integration tests for the full import pipeline, and a manual validation protocol for the "Site-in-a-Box" flow. **59 existing standalone test assertions already pass** — this change closes the remaining coverage gap.

## Intent

Fully validate the static baking pipeline, reset orchestration, and import flow of the Orkestone Engine so the system is production-ready.

## Scope

### In Scope

| Area | Coverage Target | Current Status |
|------|----------------|----------------|
| `vbb_bake_*()` unit tests | All 9 bakers + fallback | ✅ 9 bakers covered (hero, hero_centered, services_grid, benefits, process, testimonials, faq, contact_section, cta_final, logo_cloud, pricing_tables, team_section) |
| `vbb_bake_section()` dispatcher | Known type routing, unknown type fallback, data source fallback | ✅ Covered |
| `vbb_reset_vertical_pages()` | Trash matching posts, no-op on empty, verify meta query | ❌ Missing |
| `vbb_update_active_vertical_config()` | JSON file write, error handling, content validation | ❌ Missing |
| `vbb_import_vertical_full()` pipeline | Full end-to-end import with fixture JSON | ❌ Missing |
| Manual reset verification | Trash recoverability, clean re-import | ❌ Missing |
| Edge cases | Empty reset, same-vertical re-import, pipeline error handling | ❌ Missing |
| Test harness infrastructure | WP function stubs, helper assertions | ✅ Well-established |

### Out of Scope
- PHPUnit migration (still no WP test suite or Composer available)
- Integration tests requiring a real WordPress database
- Performance/benchmark tests for media sideloading
- WooCommerce-specific integration (covered by existing stub tests)

## Approach

### 1. Test Harness (existing — extend only)

Extend `inc/test-block-baker.php` (or create `inc/test-orkestone-engine.php` for clarity). The standalone PHP test pattern is proven — define WP function stubs at the top, load source files via `require_once`, run assertions with `assert_contains()` / `assert_no_notices()` helpers.

**Decision**: Rename to `inc/test-orkestone-engine.php` to reflect expanded scope beyond block baking. Keep existing tests, append new sections.

### 2. New Test Sections

| Test Section | Functions Under Test | Key Assertions |
|-------------|---------------------|----------------|
| Reset Orchestrator | `vbb_reset_vertical_pages()` | Posts with `_vbb_vertical=X` get `wp_trash_post()` called; posts with different key are untouched; empty key returns empty array without errors |
| Config Management | `vbb_update_active_vertical_config()` | JSON file written with correct `active` + `fallback` keys; handles write failures gracefully |
| Import Pipeline | `vbb_import_vertical_full()` | With stubbed `wp_insert_post`: verifies pipeline order (Reset → Config → Media → Pages → Nav → FrontPage → WooCommerce → Report); report structure correct; pages created with baked block content |
| Edge Cases | Reset orchestrator + pipeline | Empty vertical key; same vertical re-import (no reset triggered); pipeline abort on first failure (if configured) |

### 3. Stub Requirements (new)

| Stub | For | Implementation |
|------|-----|----------------|
| `wp_trash_post()` | `vbb_reset_vertical_pages()` | Capture post ID in `$GLOBALS['vbb_test_trashed']` |
| `wp_delete_post()` | — | Intentionally NOT stubbed (should never be called — verify absence) |
| `file_put_contents()` / `WP_Filesystem` | `vbb_update_active_vertical_config()` | Capture write target + content |
| `wp_insert_post()` | Pipeline integration | Already stubbed — extend with page ID map generation |
| `media_sideload_image()` | Pipeline integration | Return fake attachment ID or `WP_Error` depending on test scenario |
| `vbb_import_vertical_media()` | Pipeline integration | Return structured report segment |

### 4. Manual Validation Protocol

Document a step-by-step procedure for:
1. **Reset**: Import a vertical → verify pages created → run reset → confirm pages in trash (30d recoverable) → verify "Active Vertical Config" reset to defaults
2. **Re-import same vertical**: Confirm no duplicate slugs, pages overwritten cleanly
3. **Cross-vertical switch**: Import Vertical A → Import Vertical B → verify A's pages trashed, B's pages live

## Delivery Structure

| Work Unit | Files | Estimated Lines |
|-----------|-------|----------------|
| 1. Reset + Config unit tests | `inc/test-orkestone-engine.php` | ~120 |
| 2. Pipeline integration tests | `inc/test-orkestone-engine.php`, fixture JSON | ~200 |
| 3. Edge case tests | `inc/test-orkestone-engine.php` | ~80 |
| 4. Manual validation doc | `docs/testing/reset-validation.md` | ~60 |
| **Total** | | **~460** |

**Delivery Strategy**: force-chained (single PR, split into reviewable work units)
**Review Budget**: 800 lines (allocated — well within)

## Key Deliverables

1. **`inc/test-orkestone-engine.php`** — extended standalone test suite covering reset, config, and pipeline
2. **Test fixture** — minimal vertical JSON for `vbb_import_vertical_full()` integration test
3. **`docs/testing/reset-validation.md`** — manual validation protocol
4. **100% pass rate** on all critical bake and import tests

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| WP function stubs diverge from real WP behavior | Medium | Medium | Document stub assumptions inline; cross-check against real WP during manual validation |
| `file_put_contents()` stub may not match Filesystem API | Medium | Low | Use `vbb_write_config()` abstraction if it exists; otherwise stub the PHP function directly |
| Destructive reset tests stubs miss side-effects | Medium | Medium | Verify side-effect surface in source code (options deleted, caches invalidated, etc.) |
| Pipeline test fixture becomes stale | Low | Low | Keep fixture minimal; regenerate from schema as needed |
| No WP-CLI / browser test runner for integration | High | Medium | Stubs are the primary approach; manual validation is the safety net |

## Success Criteria

- [ ] **All existing 59 tests still pass** with zero regressions
- [ ] **Reset tests**: `vbb_reset_vertical_pages()` correctly trashes matching posts, ignores non-matching, no-ops on empty key
- [ ] **Config tests**: `vbb_update_active_vertical_config()` writes valid JSON with `active` + `fallback` keys
- [ ] **Pipeline tests**: `vbb_import_vertical_full()` with fixture produces report with correct `pages_created`, `pages_errors`, `media_sideloaded`, `media_failed`
- [ ] **Edge cases**: Same-vertical re-import does not trigger reset; unknown section type renders fallback paragraph
- [ ] **Manual validation**: documented procedure confirms trash recovery window and clean re-import
- [ ] **New assertion count**: ≥ 40 new assertions across all new test sections

## Dependencies

- Existing source files: `inc/block-baker.php`, `inc/reset-orchestrator.php`, `inc/vertical-importer.php`, `inc/page-blueprint.php`, `inc/helpers.php`
- Existing test infrastructure: `inc/test-block-baker.php` WP stubs + assertion helpers
- No external test framework required

## Rollback Plan

All test additions are non-destructive — no source code changes, no schema changes. Rollback is simply `git checkout` on the test file. The manual validation document has no runtime effect.
