# Design: Orkestone Engine Testing — Closing Deferred Phase 4

## Technical Approach

Extend the existing standalone PHP test pattern to cover the three uncovered functions (`vbb_reset_vertical_pages`, `vbb_update_active_vertical_config`, `vbb_import_vertical_full`) plus edge cases. A new standalone file `inc/test-orkestone-engine.php` uses `function_exists()`-guarded WP stubs + `class_exists`-guarded class stubs (`WP_Query`, `WP_Error`), loads real source files, and runs 45+ new assertions across 4 test sections. Pipeline tests exercise real sub-function logic with only WP-level stubs, providing realistic integration coverage without requiring a real WordPress database.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|----------|--------|-------------|-----------|
| WP_Query testability | Stub `WP_Query` as a class (`class_exists` guard) | Wrapper function in source, runkit | Zero source code changes; consistent with existing stub pattern |
| `get_theme_file_path()` stubbing | `function_exists` guard → writable temp dir + fail flag | Refactor to injectable path | No source changes; temp dir enables real IO verification |
| `file_put_contents` handling | Cannot redefine — use temp dir + non-writable path for failure | runkit, uopz | PHP built-in functions cannot be redefined; temp IO is more realistic |
| `is_wp_error()` fix | Redefine with `instanceof WP_Error` check | Global flag | Enables proper error-flow testing for `WP_Error` returns |
| Test file structure | Fully standalone (`inc/test-orkestone-engine.php`) | `require_once` existing stubs, shared stubs file | Zero coupling; each test file runs independently |
| Pipeline integration | Load `vertical-importer.php` fully, stub only WP-level deps | Extract `vbb_import_vertical_full` to own file | More realistic tests; no source changes; reuses real sub-function logic |

## Conflict/Edge Case Resolution

### WP_Query Testability Risk
- **Risk**: `vbb_reset_vertical_pages()` uses `new WP_Query(...)` directly — cannot instantiate without real WP.
- **Resolution**: Stub class with `class_exists('WP_Query')` guard. Constructor captures args in `$GLOBALS['vbb_test_wp_query_calls']` and returns posts from `$GLOBALS['vbb_test_wp_query_results'][$post_type]`. The `$posts` property is populated for iteration.
- **Verification**: After calling `vbb_reset_vertical_pages()`, assert `$GLOBALS['vbb_test_wp_query_calls']` contains expected meta_query args; assert `wp_trash_post` called for returned post IDs.

### `get_theme_file_path()` Stubbing Risk
- **Risk**: Used in `vbb_update_active_vertical_config()`; cannot write to real theme path in tests.
- **Resolution**: Stub returns `sys_get_temp_dir() . '/vbb-test-config/' . $path`. Happy path writes real JSON to temp dir (readable for assertion). Failure path: set `$GLOBALS['vbb_test_theme_file_path_fail'] = true` → stub returns `/nonexistent/vbb-test/...` → `file_put_contents` returns `false` → function returns `WP_Error`.

## Data Flow

```
test-orkestone-engine.php
  │
  ├── 1. WP stubs (function_exists guards)
  │      esc_html, __, sanitize_key, wp_json_encode, is_wp_error, ...
  │
  ├── 2. Class stubs (class_exists guards)
  │      WP_Query, WP_Error
  │
  ├── 3. VBB function stubs (function_exists guards)
  │      vbb_load_vertical_by_key, vbb_get_active_vertical_key,
  │      vbb_get_vertical_config, vbb_build_page_content_from_baked, ...
  │
  ├── 4. require_once source files
  │      helpers.php → block-baker.php → reset-orchestrator.php
  │      → vertical-importer.php
  │
  ├── 5. Test sections
  │      ├── Reset Orchestrator ──→ vbb_reset_vertical_pages()
  │      ├── Config Management  ──→ vbb_update_active_vertical_config()
  │      ├── Pipeline           ──→ vbb_import_vertical_full()
  │      └── Edge Cases         ──→ empty key, same-vertical, abort
  │
  └── 6. Summary (pass/fail counts)
```

## Test Fixture

`inc/test-fixture-minimal.json` — minimal vertical JSON for pipeline tests:

```json
{
  "schemaVersion": "1.0.0",
  "verticalKey": "test-fixture",
  "name": "Test Fixture",
  "brand": { "siteName": "Test", "tagline": "Test" },
  "navigation": { "primary": [{"label": "Home", "url": "/"}] },
  "pages": [
    {"key": "home", "title": "Home", "slug": "home", "sections": ["hero"]},
    {"key": "about", "title": "About", "slug": "about", "sections": ["hero-centered"]}
  ],
  "sections": {},
  "graphics": {"images": []},
  "importOptions": {"homepageKey": "home", "setFrontPage": false}
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit: Reset | `vbb_reset_vertical_pages()` | Stub `WP_Query` → return post IDs; stub `wp_trash_post` → capture trashed IDs; assert matching posts trashed, non-matching untouched, empty key returns empty report |
| Unit: Config | `vbb_update_active_vertical_config()` | Stub `get_theme_file_path` → temp dir; real `file_put_contents` writes to temp; read file back and assert valid JSON with `active` + `fallback`; empty key → `WP_Error`; write failure → `WP_Error` |
| Integration: Pipeline | `vbb_import_vertical_full()` | Stub loader functions; load real `vertical-importer.php`; stubs for WP-level functions only; assert report shape, pipeline step order, abort scenarios |
| Edge Cases | Empty key, same-vertical, write failure | Empty key → no-op report; same-vertical → `reset` is `null`; config write fails → `success=false` |
| Manual (doc only) | Real WP Site-in-a-Box flow | 7-step protocol: import, verify pages, reset, verify trash, re-import, cross-switch, recover from trash |

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/test-orkestone-engine.php` | Create | Standalone test file (~350 lines) with 4 test sections, WP stubs, WP_Query/WP_Error class stubs, VBB function stubs |
| `inc/test-fixture-minimal.json` | Create | Minimal vertical JSON for pipeline integration test |
| `docs/testing/reset-validation.md` | Create | Manual validation protocol (7-step real WP walkthrough) |

## Stub Inventory

### WP Stubs (redefined with `function_exists` guards)
`esc_html`, `esc_attr`, `esc_url`, `sanitize_email`, `__`, `esc_html__`, `sanitize_title`, `sanitize_key`, `sanitize_text_field`, `sanitize_html_class`, `esc_url_raw`, `wp_json_encode`, `absint`, `wp_trash_post`, `wp_delete_post`, `wp_insert_post`, `wp_update_post`, `get_post_field`, `get_posts`, `get_pages`, `get_page_by_path`, `get_page_by_title`, `get_option`, `update_option`, `delete_option`, `update_post_meta`, `get_post_meta`, `current_time`, `add_action`, `add_filter`, `do_action`, `is_wp_error`, `class_exists`, `get_theme_file_path`, `set_time_limit`, `wp_safe_redirect`, `wp_die`, `download_url`, `media_sideload_image`, `media_handle_sideload`, `wp_delete_file`, `wp_get_attachment_url`, `get_bloginfo`, `get_template_directory_uri`, `wp_parse_url`

### Class Stubs (with `class_exists` guards)
`WP_Query`, `WP_Error`

### VBB Stubs (with `function_exists` guards — for non-under-test functions)
`vbb_load_vertical_by_key`, `vbb_get_active_vertical_key`, `vbb_get_active_vertical_settings`, `vbb_get_vertical_config`, `vbb_get_vertical_pages`, `vbb_get_vertical_page`, `vbb_invalidate_vertical_cache`, `vbb_build_page_content_from_baked`, `vbb_generate_page_id_map`, `vbb_svg_placeholder`, `vbb_log_warning`

## Rollback Plan

All additions are non-destructive — no source code changes, no schema changes, no database impacts. Rollback is `git checkout` on the three new files:
1. `git checkout inc/test-orkestone-engine.php` — remove the new test file
2. `git checkout inc/test-fixture-minimal.json` — remove the fixture
3. `git checkout docs/testing/reset-validation.md` — remove the manual protocol

Existing `inc/test-block-baker.php` remains untouched and all 123 existing assertions continue passing.

## Open Questions

- None resolved in design. The two spec open questions (WP_Query, get_theme_file_path) are addressed above with concrete stub approaches.
