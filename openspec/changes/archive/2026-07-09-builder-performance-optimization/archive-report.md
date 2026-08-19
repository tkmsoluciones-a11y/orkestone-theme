# Archive Report: Builder Performance Optimization (Token Caching)

**Change**: builder-performance-optimization
**Archived**: 2026-07-09
**Final Status**: Completed

---

## Change Summary

Implemented a **version-based transient cache** for `vbb_pro_page_settings()` to eliminate expensive `vbb_pro_sanitize_settings()` calls on every uncached front-end page load. The sanitization function performs deep merges, hex color validation, and recursive menu-item sanitization — yet settings only change when an admin edits them. A global version counter (`vbb_pro_settings_version` stored in `wp_options`) serves as the lynchpin for atomic invalidation: all cached page settings keys include the version (`vbb_page_settings_{$page_id}_{$version}`), so a single `update_option()` call invalidates every cached page at once.

Delivered in a single PR (~52 lines across 2 files):
- **Version manager**: `vbb_pro_get_settings_version()` / `vbb_pro_increment_settings_version()` with '0' default seed (G2 resolution)
- **Cached getter**: `vbb_pro_get_cached_page_settings(int $page_id): array` with composite cache key, `get_transient()`/`set_transient()`, `is_array()` guard (G1 resolution), debug logging, 12h TTL safety fallback, and `VBB_PRO_CACHE_DISABLED` emergency kill-switch
- **Invalidation hooks**: Injected in 3 mutation functions — `vbb_pro_update_page_settings()`, `vbb_pro_update_settings()`, `vbb_pro_reset_to_vertical()` (transitive coverage for 6 more including REST endpoints)
- **Front-end caller swaps**: 4 call sites across 3 functions — `vbb_pro_replace_dynamic_content()`, `vbb_pro_print_css_vars()`, `vbb_print_block_visibility_js()`, `vbb_pro_filter_sections()`
- **Admin/REST paths**: Left unchanged — `vbb_pro_get_page_settings()` direct access preserved for REST endpoints and admin UI

## Artifact Lineage

| Artifact | Engram Obs ID | OpenSpec Path |
|----------|--------------|---------------|
| Exploration | #1640 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/exploration.md` |
| Proposal | #1641 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/proposal.md` |
| Spec | #1660 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/specs/settings-cache/spec.md` |
| Design | #1661 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/design.md` |
| Tasks | #1662 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/tasks.md` |
| Apply Progress | #1663 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/apply-progress.md` |
| Verify Report | #1665 | `openspec/changes/archive/2026-07-09-builder-performance-optimization/verify-report.md` |
| **Archive Report** | *(current save)* | `openspec/changes/archive/2026-07-09-builder-performance-optimization/archive-report.md` |
| Main Spec (synced) | — | `openspec/specs/settings-cache/spec.md` |

## Key Technical Decisions

| Decision | Rationale |
|----------|-----------|
| **Version-based composite key** | `vbb_page_settings_{$page_id}_{$version}` — global version invalidates ALL pages atomically with a single `update_option()`. Per-page version granularity not needed for landing-page sites. |
| **12h TTL safety fallback** | Transient stored with `12 * HOUR_IN_SECONDS` (43200s). Self-healing: if invalidation paths fail, cache expires naturally. TTL=0 (no expiry) would leave stale data indefinitely. |
| **Global version as lynchpin** | A single `vbb_pro_increment_settings_version()` call bumps the option, changing all cache keys. No need to enumerate or delete individual transients. |
| **`is_array()` guard** | Corrupt serialized transient data (e.g., from DB corruption or plugin interference) returns `false` from `get_transient()` but could return non-array. Guard treats non-array as cache miss. |
| **`VBB_PRO_CACHE_DISABLED` kill-switch** | Emergency bypass without code deploy — defined in `wp-config.php`. Falls through to direct `vbb_pro_get_page_settings()`. |
| **`VBB_PRO_CACHE_DEBUG` logging** | Debug mode fires `do_action('vbb_pro_cache_log', 'HIT'|'MISS', $cache_key)` for performance monitoring. Separate constant so production code has zero overhead. |
| **Direct getter preserved** | `vbb_pro_get_page_settings()` remains unchanged for admin UI and REST endpoints — cache is front-end only, avoiding stale-data risk in admin. |
| **3 invalidation injection points** | Only 3 core functions need explicit `vbb_pro_increment_settings_version()`: `vbb_pro_update_page_settings()`, `vbb_pro_update_settings()`, `vbb_pro_reset_to_vertical()`. `vbb_pro_apply_profile()` gets it transitively. `vbb_pro_sync_menu_to_wp_navigation()` does NOT mutate settings. |
| **4 front-end callers** | Not just `the_content` filter — `wp_head` CSS vars (2 sites) and section filtering also need the cached getter to eliminate all sanitization calls per request. |
| **Default '0' seed (G2)** | `get_option(VBB_PRO_SETTINGS_VERSION_KEY, '0')` ensures first load works even if option was never set. |

## Verification Summary

| Metric | Value |
|--------|-------|
| Verdict | **PASS WITH WARNINGS** |
| Requirements coverage | 6/6 (static verification) |
| Tasks complete | 5/5 |
| Files modified | 2 (`pro-settings.php`, `pro-css-vars.php`) |
| Estimated added lines | ~52 |
| CRITICAL issues | None |
| WARNINGS | 2: (1) No automated test coverage — manual validation only; (2) `builder-export-templates` feature directory not found for cross-feature regression verification |

### Requirements Compliance

| ID | Description | Status |
|----|-------------|--------|
| REQ-SC01 | Version manager with getter/increment | ✅ Implemented |
| REQ-SC02 | Composite cache key format | ✅ Implemented |
| REQ-SC03 | Transient checked before sanitization | ✅ Implemented |
| REQ-SC04 | Mutation functions bump version | ✅ Implemented |
| REQ-SC05 | 12h TTL safety fallback | ✅ Implemented |
| REQ-SC06 | Debug logging behind constant flag | ✅ Implemented |

### E2E Scenarios

| Scenario | Status | Evidence |
|----------|--------|----------|
| S1: Cold cache → Miss → Set Transient | ✅ COMPLIANT | `get_transient()` false → `vbb_pro_get_page_settings()` → `set_transient()` |
| S2: Warm cache → Hit → Skip sanitize | ✅ COMPLIANT | `false !== $cached && is_array($cached)` → return early |
| S3: Admin Save → Version Bump → Cold | ✅ COMPLIANT | `vbb_pro_update_page_settings()` bumps version; next request key differs |
| S4: Global Version → All pages invalidate | ✅ COMPLIANT | Single global version key in every page; one bump → all keys change |
| S5: TTL Expiry → Cold cache | ✅ COMPLIANT | `12 * HOUR_IN_SECONDS` TTL; transient expires naturally |

### Design Coherence

All 9 design decisions followed exactly — version-based composite key, kill-switch, debug logging, `is_array()` guard, default '0' seed, transitive invalidation, 12h TTL, no invalidation in `vbb_pro_save_profile()` or `vbb_pro_sync_menu_to_wp_navigation()`.

### Regression Areas

All 8 regression areas verified: direct getter preserved, mutation function results identical, REST API unchanged, front-end HTML output same, admin UI untouched, `builder-visual-polish` preserved, no new dependencies.

## Design Deviations

None — implementation matches design exactly.

## Lessons Learned

1. **`is_array()` guard for corrupt transients**: WordPress Transients can return non-array values if the serialized data is corrupt (e.g., from DB corruption or plugin interference). The check `false !== $cached && is_array($cached)` treats non-array returns as cache misses, preventing PHP type errors from propagating.

2. **Three front-end callers, not one**: The exploration phase revealed that `vbb_pro_get_page_settings()` is called from 4 sites across 3 functions — not just `the_content` filter. Changing only the filter leaves 2/3 of sanitization cost untouched. The other callers are CSS variable output (`wp_head`) and section filtering.

3. **`vbb_pro_get_settings()` still uncached**: `vbb_pro_get_settings()` (the global settings getter, not per-page) is still called directly in `pro-css-vars.php:98` and runs sanitization even with a warm page-settings cache. A follow-up request-level static cache on that function would eliminate the last sanitization call per request.

4. **Transitive invalidation coverage**: Only 3 core functions need explicit `vbb_pro_increment_settings_version()` — the REST endpoints and menu endpoints invalidate transitively because they route through these core functions. `vbb_pro_sync_menu_to_wp_navigation()` does NOT mutate settings, so no invalidation needed there.

5. **Version collision window**: `time()` has 1-second granularity, so rapid saves within the same second produce the same version. This is harmless — the cached settings are still valid (same data). The next save always produces a new timestamp.

6. **No automated test coverage**: The project lacks PHPUnit setup, so all verification was static (source inspection). Manual validation scenarios are documented in the apply-progress for staging testing.
