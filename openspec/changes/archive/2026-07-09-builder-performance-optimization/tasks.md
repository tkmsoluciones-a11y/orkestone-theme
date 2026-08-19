# Tasks: Builder Performance Optimization — Version-Based Transient Cache

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~52 |
| 400-line budget risk | **Low** |
| Review budget (spec) | 800 lines |
| Chained PRs recommended | **No** |
| Suggested split | Single PR (change is ~52 lines) |
| Delivery strategy | force-chained |
| Chain strategy | single-PR (change fits in one review slice) |

**Rationale**: The entire change adds ~50 lines to `pro-settings.php` and changes 2 lines in `pro-css-vars.php`. No data model changes, no new files, no REST endpoint modifications. A single PR covers all 5 tasks cleanly. Force-chained delivery can treat this as a one-element chain.

---

## Task PO1.1 — Version Manager & Global Kill-Switch

- [x] **Description**: Add `VBB_PRO_SETTINGS_VERSION_KEY` constant, `vbb_pro_get_settings_version()` returning `get_option('vbb_pro_settings_version', '0')`, and `vbb_pro_increment_settings_version()` writing `(string) time()` via `update_option()` with `$autoload=false`. Also add `VBB_PRO_CACHE_DISABLED` constant check: the cached getter bypasses the cache entirely when the constant is defined and truthy, falling through to direct `vbb_pro_get_page_settings()`.
- **Files affected**: `orkestone-theme/inc/pro-settings.php`
- **Verification method**:
  1. Call `vbb_pro_get_settings_version()` on fresh install → returns `'0'`.
  2. Call `vbb_pro_increment_settings_version()` → `vbb_pro_get_settings_version()` returns a timestamp string > `'0'`.
  3. Define `VBB_PRO_CACHE_DISABLED` as `true` → cached getter falls through (verified in PO1.2).
- **Estimated complexity**: Low (~15 lines)

---

## Task PO1.2 — Cached Getter for Page Settings

- [x] **Description**: Implement `vbb_pro_get_cached_page_settings(int $page_id): array`. Constructs cache key as `vbb_page_settings_{$page_id}_{$version}` using `vbb_pro_get_settings_version()`. Reads via `get_transient()`. On hit with `is_array()` guard (G1 resolution), returns immediately. On miss, calls `vbb_pro_get_page_settings()`, stores via `set_transient($key, $settings, 12 * HOUR_IN_SECONDS)`, and returns. When `VBB_PRO_CACHE_DEBUG` is defined and truthy, fires `do_action('vbb_pro_cache_log', 'HIT'|'MISS', $cache_key)` on each access.
- **Files affected**: `orkestone-theme/inc/pro-settings.php`
- **Verification method**:
  1. First call for page 42 → transient `vbb_page_settings_42_0` created, `vbb_pro_sanitize_settings()` executes.
  2. Second call for page 42 → returns cached value, `vbb_pro_sanitize_settings()` NOT called.
  3. `is_array()` guard: corrupt transient data (e.g. serialized object) treats as miss.
  4. Debug mode: define `VBB_PRO_CACHE_DEBUG` → `did_action('vbb_pro_cache_log')` returns true.
- **Estimated complexity**: Medium (~20 lines)

---

## Task PO1.3 — Invalidation Hooks in Mutation Functions

- [x] **Description**: Add `vbb_pro_increment_settings_version()` call at the end of each settings-mutating function (after the option write completes):
  - `vbb_pro_update_settings()` — after `update_option(VBB_PRO_SETTINGS_OPTION, ...)` (line ~410)
  - `vbb_pro_update_page_settings()` — after `update_option(VBB_PRO_PAGE_SETTINGS_OPTION, ...)` (line ~47)
  - `vbb_pro_reset_to_vertical()` — after `delete_option(...)` calls (line ~447)
  
  **No change needed** (transitive coverage):
  - `vbb_pro_apply_profile()` calls `vbb_pro_update_settings()` → already bumped.
  - `vbb_rest_update_settings()` calls `vbb_pro_update_settings()` → already bumped.
  - `vbb_rest_update_page_settings()` calls `vbb_pro_update_page_settings()` → already bumped.
  - `vbb_rest_update_menu()`, `vbb_rest_append_menu_item()`, `vbb_rest_delete_menu_item()` all call `vbb_pro_update_settings()` → already bumped.
  - `vbb_pro_sync_menu_to_wp_navigation()` does NOT mutate settings → no invalidation needed.
- **Files affected**: `orkestone-theme/inc/pro-settings.php`
- **Verification method**:
  1. Call `vbb_pro_update_settings($s)` → version increments.
  2. Call `vbb_pro_update_page_settings(42, $s)` → version increments.
  3. Call `vbb_pro_reset_to_vertical()` → version increments.
  4. After each mutation, cached page-42 key changes (old key absent, new key populated on next request).
- **Estimated complexity**: Low (3 lines)

---

## Task PO1.4 — Front-End Caller Updates

- [x] **Description**: Swap `vbb_pro_get_page_settings()` → `vbb_pro_get_cached_page_settings()` in all three front-end call sites:
  1. `vbb_pro_replace_dynamic_content()` — line 458 in `pro-settings.php`
  2. `vbb_pro_print_css_vars()` — lines 110 and 189 in `pro-css-vars.php` (two call sites, one at line 110 for per-page block scoped CSS, one at line 189 for block visibility JS)
  3. `vbb_pro_filter_sections()` — line 605 in `pro-settings.php`
  
  **NOT changed** (keep direct calls):
  - `pro-rest-api.php:695` — REST response is admin-only; mutation already bumped version.
  - `block-baker.php:1016` — Admin-only page baking; caching adds no value.
- **Files affected**: `orkestone-theme/inc/pro-settings.php`, `orkestone-theme/inc/pro-css-vars.php`
- **Verification method**:
  1. Load any front-end page → `vbb_pro_get_cached_page_settings()` is hit (not the direct function).
  2. Transient is created on first page load.
  3. DOM output is identical to pre-change output (check hero text, CSS vars, section visibility).
- **Estimated complexity**: Low (4 lines)

---

## Task PO1.5 — Verification: Performance & Validation

- [x] **Description**: Measure and validate the caching system end-to-end. Two parts:
  
  **A. Performance measurement**:
  1. Cold cache: flush all transients, load a page, measure time via `microtime()` wrapping around `vbb_pro_get_cached_page_settings()`.
  2. Warm cache: load the same page again (cache hit), measure time.
  3. Record: cold ~15-55ms, warm ~2-7ms, savings ~13-48ms (80-90%).
  
  **B. Manual scenario validation** (from spec S1-S5):
  - S1 (cold cache): First page load computes and caches.
  - S2 (cache hit): Repeat load returns cached, no sanitization.
  - S3 (admin saves color): Version bumps → next page load misses.
  - S4 (global version): One save invalidates ALL pages.
  - S5 (safety TTL): Stale transients expire after 12h.
  
  **C. Regression check**:
  - Front-end HTML output matches pre-cache output (hero, CSS variables, section visibility).
  - REST API endpoints return identical JSON (admin settings, page settings).
  - Admin settings UI unchanged.
- **Files affected**: None (verification only; may add a temporary test script)
- **Verification method**:
  1. PHP `microtime(true)` logging before/after settings getter on cold vs warm page loads.
  2. Manual walkthrough of all 5 scenarios from spec.
  3. Visual diff of front-end HTML with/without cache.
  4. REST endpoint response comparison.
- **Estimated complexity**: Low

---

## Summary

| Task | Area | File(s) | Lines | Complexity |
|------|------|---------|-------|------------|
| PO1.1 | Version manager + kill-switch | `pro-settings.php` | ~15 | Low |
| PO1.2 | Cached getter | `pro-settings.php` | ~20 | Medium |
| PO1.3 | Invalidation hooks | `pro-settings.php` | ~3 | Low |
| PO1.4 | Front-end caller swaps | `pro-settings.php`, `pro-css-vars.php` | ~4 | Low |
| PO1.5 | Verification | N/A | 0 | Low |
| **Total** | | **2 files** | **~42** | |

## Dependency Graph

```
PO1.1 (version + kill-switch) → PO1.2 (cached getter)
                                     ↓
                              PO1.3 (invalidation)
                                     ↓
                              PO1.4 (caller swaps)
                                     ↓
                              PO1.5 (verification)
```

All tasks are strictly sequential: each depends on the previous. PO1.1 must exist before PO1.2 can use the version functions. PO1.2 must exist before PO1.3 can invalidate meaningful caches. PO1.3 must exist before PO1.4 swaps callers (otherwise cache would never invalidate). PO1.5 must run last to test the complete system.
