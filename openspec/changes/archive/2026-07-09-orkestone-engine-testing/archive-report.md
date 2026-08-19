# Archive Report: Orkestone Engine Testing (Closing Deferred Phase 4)

**Change**: orkestone-engine-testing
**Archived**: 2026-07-09
**Final Status**: Completed
**Artifact Store Mode**: hybrid (Engram + OpenSpec)

---

## Change Summary

Closed the deferred Phase 4 testing gap of the Orkestone Engine by adding comprehensive test coverage for the three previously uncovered functions (`vbb_reset_vertical_pages`, `vbb_update_active_vertical_config`, `vbb_import_vertical_full`) plus pipeline edge cases. The change produced:

- **Unit tests** (Reset Orchestrator + Config Management): Verified meta-query-based page trashing, JSON config file writing with temp-dir IO, empty-key guards, and write-failure error paths.
- **Integration tests** (Full Import Pipeline): Six scenario-based tests (S1–S6) exercising `vbb_import_vertical_full()` with real source functions and WP-level stubs only, covering full import, same-vertical re-import, cross-vertical switch, missing-vertical abort, and config-failure abort.
- **Manual validation protocol**: 7-step real-WP walkthrough for reset verification, re-import, cross-switch, and trash recovery.
- **Test fixture**: Minimal vertical JSON for pipeline integration.

**No source code changes were made** — all additions are test-only (3 files: test file, fixture JSON, manual protocol doc). 123 existing assertions continue to pass without regression.

## Artifact Lineage

### OpenSpec (filesystem)
| Artifact | Path | Status |
|----------|------|--------|
| Proposal | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/proposal.md` | Final |
| Spec | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/spec.md` | Final |
| Design | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/design.md` | Final |
| Tasks | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/tasks.md` | Final (18/18 tasks complete) |
| Apply Progress | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/apply-progress.md` | Final |
| Verify Report | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/verify-report.md` | Final (PASS) |
| Archive Report | `openspec/changes/archive/2026-07-09-orkestone-engine-testing/archive-report.md` | This file |

### Engram (observation IDs)
| Artifact | Topic Key | Observation ID |
|----------|-----------|----------------|
| Proposal | `sdd/orkestone-engine-testing/proposal` | #1565 |
| Spec | `sdd/orkestone-engine-testing/spec` | #1566 |
| Design | `sdd/orkestone-engine-testing/design` | #1568 |
| Tasks | `sdd/orkestone-engine-testing/tasks` | #1569 |
| Apply Progress | `sdd/orkestone-engine-testing/apply-progress` | #1572 |
| Verify Report | `sdd/orkestone-engine-testing/verify-report` | (filesystem only) |
| Archive Report | `sdd/orkestone-engine-testing/archive-report` | This save |

### No main spec merge required
- No existing main specs at `openspec/specs/`
- No delta spec subdirectory (`specs/`) existed — spec was a standalone testing spec
- The spec describes ephemeral test requirements, satisfied once tests exist and pass

## Key Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| WP_Query testability | Class stub with `class_exists` guard | Zero source changes; consistent with existing stub pattern |
| get_theme_file_path() stubbing | Temp dir via `function_exists` guard | Enables real IO verification without source changes |
| file_put_contents handling | Temp writable dir + non-writable path for failure | PHP built-in cannot be redefined; temp IO is more realistic |
| is_wp_error() fix | Redefined with `instanceof WP_Error` check | Enables proper error-flow testing for WP_Error returns |
| Test file structure | Fully standalone (`inc/test-orkestone-engine.php`) | Zero coupling; each test file runs independently |
| Pipeline integration | Load `vertical-importer.php` fully, stub only WP-level deps | More realistic tests; no source changes; reuses real sub-function logic |
| `OBJECT` constant | Added `define('OBJECT', 'OBJECT')` | Required by real `get_page_by_path()` calls in source |
| media_sideload_image stub | Configurable per-call results via `$GLOBALS` | Supports 3-sideloaded / 1-failed media count assertion |
| Fixture config | Built programmatically as PHP arrays per test | Avoids file I/O; each test self-contained |
| Delivery strategy | force-chained (2 PRs, feature-branch-chain) | ~520 lines well within 800-line review budget |

## Verification Summary

| Metric | Value |
|--------|-------|
| **Verdict** | PASS |
| **New tests passed** | 99/99 |
| **Regression tests passed** | 123/123 |
| **Requirements compliant** | 14/14 |
| **Scenarios compliant** | 6/6 |
| **Design decisions followed** | 10/10 |
| **CRITICAL issues** | 0 |
| **WARNINGS** | 0 |
| **Syntax errors** | 0 |

### Requirements Compliance

| ID | Description | Result |
|----|-------------|--------|
| REQ-R1 | Reset trashes matching pages by meta query | ✅ COMPLIANT |
| REQ-R2 | Reset trashes wp_navigation posts | ✅ COMPLIANT |
| REQ-R3 | Non-matching posts not trashed | ✅ COMPLIANT |
| REQ-R4 | Empty key returns no-op report | ✅ COMPLIANT |
| REQ-R5 | Config writes valid JSON with active/fallback | ✅ COMPLIANT |
| REQ-R6 | Empty key returns WP_Error | ✅ COMPLIANT |
| REQ-R7 | Write failure returns WP_Error | ✅ COMPLIANT |
| REQ-R8 | Full pipeline executes all steps in order | ✅ COMPLIANT |
| REQ-R9 | Same-vertical re-import skips reset | ✅ COMPLIANT |
| REQ-R10 | Missing vertical aborts with error | ✅ COMPLIANT |
| REQ-R11 | Config write failure aborts pipeline | ✅ COMPLIANT |
| REQ-R12 | Report counts pages_created/errors | ✅ COMPLIANT |
| REQ-R13 | Report counts media_sideloaded/failed | ✅ COMPLIANT |
| REQ-R14 | Reset uses wp_trash_post, NOT wp_delete_post | ✅ COMPLIANT |

## Lessons Learned

1. **`OBJECT` constant**: The real `vertical-importer.php` calls `get_page_by_path(..., OBJECT, ...)` which requires the `OBJECT` WordPress constant. Not available in standalone PHP. Fixed by adding `define('OBJECT', 'OBJECT')` in the bootstrap section.

2. **`sanitize_key(null)` deprecation**: In newer PHP versions, passing `null` to `sanitize_key()` triggers a deprecation warning. The stub needs a null-coalescing guard (`$key ?? ''`).

3. **Real source functions can run with WP-level stubs**: Pipeline tests did not need VBB-level stubs for `vbb_generate_vertical_pages_from_baked`, `vbb_import_vertical_media_with_placeholders`, etc. — these run correctly with only WP function stubs, providing far more realistic integration coverage.

4. **`media_sideload_image` stub needed configurability**: The original stub always returned `WP_Error`. To assert 3-sideloaded / 1-failed counts, it needed a configurable results mechanism via `$GLOBALS['vbb_test_media_sideload_image_results']`.

5. **`class_exists` guard timing**: The `WP_Error` class stub must be defined before `is_wp_error()` redefinition since `is_wp_error` uses `instanceof WP_Error`.

6. **Standalone test files are zero-regression**: Adding a new standalone test file (`inc/test-orkestone-engine.php`) with zero source-code coupling proved safe — all 123 existing assertions remain untouched.

7. **Assertion count grew beyond design**: The design estimated 45+ new assertions; actual implementation reached 99 assertions due to more thorough pipeline verification (report shape, step counts, error messages, globals verification).

## Files Created

| File | Description |
|------|-------------|
| `inc/test-orkestone-engine.php` | Standalone test file (~450 lines, 5 sections, 99 assertions) |
| `inc/test-fixture-minimal.json` | Minimal vertical JSON for pipeline integration |
| `docs/testing/reset-validation.md` | 7-step manual validation protocol for real-WP testing |

## Task Completion

- **Total tasks**: 18
- **Completed**: 18
- **Phases**: 4 (Foundation/Infrastructure, Unit Tests, Pipeline Integration, Manual Protocol) + 4 verification tasks

No remaining tasks. All implementation and verification tasks are complete.
