# Tasks: Orkestone Engine Testing — Closing Deferred Phase 4

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~520 (350 test + 25 fixture + 145 doc) |
| 400-line budget risk | Low (budget is 800) |
| Chained PRs recommended | Yes (force-chained strategy) |
| Suggested split | PR 1 → PR 2 (feature-branch-chain) |
| Delivery strategy | force-chained |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Base | Lines |
|------|------|-----------|------|-------|
| 1 | Infrastructure + stubs + Reset/Config/Edge unit tests | PR 1 | feature/tracker | ~250 |
| 2 | Pipeline integration tests + manual validation doc | PR 2 | PR 1 branch | ~270 |

---

## Phase 1: Foundation & Test Infrastructure

- [x] 1.1 Create `inc/test-fixture-minimal.json` — minimal vertical JSON with 2 pages, nav, sections, importOptions for pipeline stubs (~25 lines)
- [x] 1.2 Create `inc/test-orkestone-engine.php` — WP function stubs (esc_html, __, sanitize_key, wp_json_encode, is_wp_error, get_theme_file_path, wp_trash_post, etc.) with `function_exists` guards (~60 lines)
- [x] 1.3 Add `WP_Query` + `WP_Error` class stubs with `class_exists` guards; WP_Query captures args in `$GLOBALS['vbb_test_wp_query_calls']`, returns posts from `$GLOBALS['vbb_test_wp_query_results']` (~40 lines)
- [x] 1.4 Add VBB function stubs (`vbb_load_vertical_by_key`, `vbb_get_active_vertical_key`, `vbb_get_vertical_config`, `vbb_build_page_content_from_baked`, etc.) with `function_exists` guards (~30 lines)
- [x] 1.5 `require_once` real source files: helpers.php → block-baker.php → reset-orchestrator.php → vertical-importer.php

## Phase 2: Unit Tests — Reset & Config

- [x] 2.1 Write `vbb_reset_vertical_pages()` tests: matching pages trashed, navigation trashed, non-matching pages untouched, empty key returns no-op report (REQ-R1–R4, S3)
- [x] 2.2 Write `vbb_update_active_vertical_config()` tests: writes valid JSON with active/fallback, empty key returns WP_Error, write failure returns WP_Error (REQ-R5–R7)
- [x] 2.3 Add test summary block printing pass/fail counts (consistent with existing test-block-baker.php pattern)

## Phase 3: Pipeline Integration Tests

- [x] 3.1 Write S1 (full import with valid fixture): stub full dependency chain via `vbb_import_vertical_full('test-fixture')`, assert report shape, step counts, success=true (REQ-R8, R12–R13)
- [x] 3.2 Write S2 (same-vertical re-import): stub `vbb_is_different_vertical=false`, assert `reset=null` (REQ-R9)
- [x] 3.3 Write S4 (cross-vertical switch): stub old key `'law-firm'`, assert reset called with old key (REQ-R1)
- [x] 3.4 Write S5 (pipeline abort on missing vertical): stub `vbb_load_vertical_by_key=null`, assert `success=false` (REQ-R10)
- [x] 3.5 Write S6 (config write failure abort): stub config update as WP_Error, assert `success=false`, no subsequent steps (REQ-R11)
- [x] 3.6 Verify `wp_trash_post` used (NOT `wp_delete_post`) in all reset paths (REQ-R14)

## Phase 4: Manual Verification Protocol

- [x] 4.1 Create `docs/testing/reset-validation.md` — 7-step real WP walkthrough: import vertical, verify pages, reset, verify trash, re-import, cross-switch, recover from trash

## Verification

- [x] V1 Run `php inc/test-block-baker.php` — all 123 existing assertions MUST pass (✅ 123/123)
- [x] V2 Run `php inc/test-orkestone-engine.php` — all 45+ new assertions MUST pass (✅ 99/99)
- [x] V3 Run `php -l inc/test-orkestone-engine.php` — no PHP syntax errors (✅)
- [x] V4 Run `php -l inc/test-fixture-minimal.json` — valid JSON (✅)
