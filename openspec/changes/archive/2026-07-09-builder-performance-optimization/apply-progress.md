# Apply Progress: Builder Performance Optimization

**Mode**: Standard
**Change**: builder-performance-optimization
**Status**: 5/5 tasks complete. Ready for verify.

---

## Completed Tasks

- [x] **PO1.1 — Version Manager & Global Kill-Switch**: Added `VBB_PRO_SETTINGS_VERSION_KEY` constant, `vbb_pro_get_settings_version()`, `vbb_pro_increment_settings_version()`, and `VBB_PRO_CACHE_DISABLED` kill-switch check in cached getter.
- [x] **PO1.2 — Cached Getter for Page Settings**: Implemented `vbb_pro_get_cached_page_settings(int $page_id): array` with composite cache key, `get_transient()`/`set_transient()`, `is_array()` guard, `VBB_PRO_CACHE_DEBUG` logging, and 12h TTL fallback.
- [x] **PO1.3 — Invalidation Hooks in Mutation Functions**: Added `vbb_pro_increment_settings_version()` call in `vbb_pro_update_page_settings()`, `vbb_pro_update_settings()`, and `vbb_pro_reset_to_vertical()`.
- [x] **PO1.4 — Front-End Caller Updates**: Swapped `vbb_pro_get_page_settings()` → `vbb_pro_get_cached_page_settings()` in `vbb_pro_replace_dynamic_content()`, `vbb_pro_print_css_vars()` (2 sites), `vbb_pro_filter_sections()`.
- [x] **PO1.5 — Verification**: Manual validation steps documented (cold/warm cache, invalidation scenarios, regression check).

---

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `orkestone-theme/inc/pro-settings.php` | Modified | +~50 lines: version functions, cached getter, invalidation hooks, caller swap |
| `orkestone-theme/inc/pro-css-vars.php` | Modified | 2 lines: caller swap in `vbb_pro_print_css_vars()` and `vbb_print_block_visibility_js()` |

### Detailed Changes in `pro-settings.php`

**New constants & functions** (lines 16-36):
- `VBB_PRO_SETTINGS_VERSION_KEY` constant
- `vbb_pro_get_settings_version(): string` — reads option, defaults to '0'
- `vbb_pro_increment_settings_version(): void` — writes `(string) time()` with `$autoload=false`

**New cached getter** (lines 62-86):
- `vbb_pro_get_cached_page_settings(int $page_id): array`
- Kill-switch: `defined('VBB_PRO_CACHE_DISABLED') && VBB_PRO_CACHE_DISABLED` → falls through to direct getter
- Cache key: `vbb_page_settings_{page_id}_{version}`
- Guard: `is_array($cached)` — non-array transient treated as miss
- Debug: `VBB_PRO_CACHE_DEBUG` fires `do_action('vbb_pro_cache_log', 'HIT'|'MISS', $cache_key)`
- TTL: `12 * HOUR_IN_SECONDS` (43200s) safety fallback

**Invalidation injections** (3 call sites):
- `vbb_pro_update_page_settings()` — after `update_option()` (line 103)
- `vbb_pro_update_settings()` — after `update_option()` (line 467)
- `vbb_pro_reset_to_vertical()` — after `delete_option()` calls (line 504)

**Caller swaps** (2 call sites):
- `vbb_pro_replace_dynamic_content()` — line 516
- `vbb_pro_filter_sections()` — line 663

### Detailed Changes in `pro-css-vars.php`

**Caller swaps** (2 call sites):
- `vbb_pro_print_css_vars()` — line 110
- `vbb_print_block_visibility_js()` — line 189

---

## Deviations from Design

None — implementation matches design exactly.

---

## Issues Found

None.

---

## Verification Guide (PO1.5)

### A. Performance Measurement
1. Cold cache: Flush all transients (`DELETE FROM wp_options WHERE option_name LIKE '_transient_vbb_page_settings_%'`), load a page. `vbb_pro_sanitize_settings()` executes once.
2. Warm cache: Load the same page again. `vbb_pro_sanitize_settings()` NOT called. Transient returned directly.
3. Expected: cold ~15-55ms, warm ~2-7ms, savings ~80-90%.

### B. Scenario Validation (from Spec S1-S5)
- **S1 (cold cache)**: First page load → `get_transient()` returns `false` → `vbb_pro_get_page_settings()` runs → `set_transient()` stores result.
- **S2 (cache hit)**: Repeat load → `get_transient()` returns array → `is_array()` passes → return cached (no sanitization).
- **S3 (admin saves color)**: Save in Command Center → `vbb_pro_update_page_settings()` → version bumps → next page load sees new key → miss → recompute.
- **S4 (global version)**: Any mutation bumps global version → all page keys change → all pages miss on next load.
- **S5 (safety TTL)**: Transient stored with TTL=43200 → expires after 12h if no mutation occurs.

### C. Regression Check
- Front-end HTML identical (same `vbb_pro_get_page_settings()` underneath on miss).
- REST API endpoints unchanged (still call core functions directly).
- Admin UI unchanged.
- Direct `vbb_pro_get_page_settings()` still available for admin/REST paths.

### How to Test
Add this to `wp-config.php` for debugging:
```php
define('VBB_PRO_CACHE_DEBUG', true);
add_action('vbb_pro_cache_log', function($status, $key) {
    error_log("[VBB Cache] $status: $key");
}, 10, 2);
```

Or use the kill-switch to bypass:
```php
define('VBB_PRO_CACHE_DISABLED', true);
```

---

## Deliverable Summary

| Aspect | Value |
|--------|-------|
| Change name | builder-performance-optimization |
| Tasks completed | 5/5 |
| Files modified | 2 |
| Estimated added lines | ~52 |
| Delivery strategy | Single PR (force-chained, one-element chain) |
| Budget check | Well within 800-line review budget |
| Next recommended | sdd-verify |
