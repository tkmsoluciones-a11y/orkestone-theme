# Design: Builder Performance Optimization — Version-Based Transient Cache

Cache fully-resolved, sanitized page settings via WordPress Transients with a global version key for instant invalidation. Eliminates the expensive `vbb_pro_sanitize_settings()` call on **every** uncached page load — saving ~30-50ms per request while keeping the `str_replace` loop (cheap) as the only runtime cost.

---

## Flow: Version-Based Transient Cache

```
Global Version (wp_options)
     │
     ▼
Page ID + Version → Composite Key → vbb_page_settings_{ID}_{VERSION}
     │
     ├── Cache HIT  ──→ Return resolved settings (skip sanitize)
     │
     └── Cache MISS ──→ vbb_pro_get_page_settings() → vbb_pro_sanitize_settings()
                          → set_transient(key, result, 43200)
                          → Return resolved settings
```

**TTL**: 0 (explicit invalidation only) with 12-hour safety fallback (`43200` seconds).

---

## Quick Path

1. **Add version functions**: ~15 lines in `pro-settings.php`
2. **Wrap `vbb_pro_get_page_settings()` in a cached overload**: ~20 lines
3. **Bump version in 3 mutation functions**: 1 line each
4. **Change front-end callers** to hit the cached overload: 3 call sites, 1 line each

---

## Details

### 1. Version Manager

Two new functions in `orkestone-theme/inc/pro-settings.php`:

**`vbb_pro_get_settings_version(): string`**
- Reads `vbb_pro_settings_version` option via `get_option()`.
- Returns `(string)` cast. Defaults to `'0'` when option is absent (fresh install).
- The version is stored as a Unix timestamp string, giving ordinal ordering and natural uniqueness.

**`vbb_pro_increment_settings_version(): void`**
- Writes `(string) time()` to the option via `update_option()` (atomic MySQL write).
- No autoload: third param `false`.

```php
const VBB_PRO_SETTINGS_VERSION_KEY = 'vbb_pro_settings_version';

function vbb_pro_get_settings_version(): string {
    return (string) get_option( VBB_PRO_SETTINGS_VERSION_KEY, '0' );
}

function vbb_pro_increment_settings_version(): void {
    update_option( VBB_PRO_SETTINGS_VERSION_KEY, (string) time(), false );
}
```

**Why `time()` instead of `+1`**: Consecutive saves in the same second produce the same timestamp — the transient key doesn't change, but the old cache was already invalidated by the first save. This is harmless and avoids race conditions on concurrent admin saves.

### 2. Cached Getter

New wrapper function replacing `vbb_pro_get_page_settings()` in front-end call sites:

```php
function vbb_pro_get_cached_page_settings( int $page_id ): array {
    $page_id   = $page_id;
    $version   = vbb_pro_get_settings_version();
    $cache_key = 'vbb_page_settings_' . $page_id . '_' . $version;
    $cached    = get_transient( $cache_key );

    if ( false !== $cached && is_array( $cached ) ) {
        if ( defined( 'VBB_PRO_CACHE_DEBUG' ) && VBB_PRO_CACHE_DEBUG ) {
            do_action( 'vbb_pro_cache_log', 'HIT', $cache_key );
        }
        return $cached;
    }

    if ( defined( 'VBB_PRO_CACHE_DEBUG' ) && VBB_PRO_CACHE_DEBUG ) {
        do_action( 'vbb_pro_cache_log', 'MISS', $cache_key );
    }

    $settings = vbb_pro_get_page_settings( $page_id );
    set_transient( $cache_key, $settings, 12 * HOUR_IN_SECONDS ); // 43200s fallback
    return $settings;
}
```

**Key decisions:**
- **`is_array($cached)` guard** (G1 resolution): If the transient returns corrupt data (e.g., serialization error), treat it as a miss rather than returning garbage.
- **`VBB_PRO_CACHE_DEBUG` constant**: Optional logging. When defined and truthy, fires `do_action('vbb_pro_cache_log', 'HIT'|'MISS', $cache_key)`. Admins can hook into this for staging diagnostics.
- **12h TTL**: Safety fallback per REQ-SC05. If all invalidation paths fail, the transient self-heals after 12 hours.

### 3. Call Sites to Change

| Caller | File | Current | Change |
|--------|------|---------|--------|
| `vbb_pro_replace_dynamic_content()` | `pro-settings.php:458` | `vbb_pro_get_page_settings($page_id)` | → `vbb_pro_get_cached_page_settings($page_id)` |
| `vbb_pro_print_css_vars()` | `pro-css-vars.php:110, 189` | `vbb_pro_get_page_settings($page_id)` (×2) | → `vbb_pro_get_cached_page_settings($page_id)` (×2) |
| `vbb_pro_filter_sections()` | `pro-settings.php:605` | `vbb_pro_get_page_settings($page_id)` | → `vbb_pro_get_cached_page_settings($page_id)` |

**Why change all three**: On every page load, CSS vars (`wp_head`), section filtering (`page-blueprint.php`), and content replacement (`the_content`) each call the settings resolver independently. Leaving two uncached means paying sanitization costs 2 out of 3 times. Changing all three gives the full ~90% reduction.

**Not changed** (keep direct calls):
- `pro-rest-api.php:695` — REST response is administrative; the mutation already bumped the version before responding.
- `block-baker.php:1016` — Admin-only page baking; caching adds no value.

### 4. Invalidation Engine

**Three direct injection points** in `pro-settings.php`:

| Function | Line | Mutation | Invalidation |
|----------|------|----------|-------------|
| `vbb_pro_update_settings()` | 408 | Writes global `VBB_PRO_SETTINGS_OPTION` | `vbb_pro_increment_settings_version()` |
| `vbb_pro_update_page_settings()` | 36 | Writes per-page `VBB_PRO_PAGE_SETTINGS_OPTION` | `vbb_pro_increment_settings_version()` |
| `vbb_pro_reset_to_vertical()` | 444 | Deletes `VBB_PRO_SETTINGS_OPTION` | `vbb_pro_increment_settings_version()` |

**Transitive coverage** (no direct change needed):

```
vbb_pro_apply_profile()
  └─ calls vbb_pro_update_settings()  ← already bumps

vbb_rest_update_settings()
  └─ calls vbb_pro_update_settings()  ← already bumps

vbb_rest_update_page_settings()
  └─ calls vbb_pro_update_page_settings()  ← already bumps

vbb_rest_update_menu()
  └─ calls vbb_pro_update_settings()  ← already bumps

vbb_rest_append_menu_item()
  └─ calls vbb_pro_update_settings()  ← already bumps

vbb_rest_delete_menu_item()
  └─ calls vbb_pro_update_settings()  ← already bumps
```

**Not invalidated** (no settings mutation):
- `vbb_pro_save_profile()` — only writes `VBB_PRO_PROFILES_OPTION`; does not change active settings.
- `vbb_pro_sync_menu_to_wp_navigation()` — only syncs to `wp_navigation` post type; settings already saved upstream.

**Why global version bumps all pages**: The global `vbb_pro_settings_version` is embedded in EVERY page's cache key. Bumping it atomically invalidates all cached pages at once. For a landing-page site (1-5 pages) this is instant and negligible. Per-page version granularity can be layered on later if needed.

### 5. Error Handling — Spec Gaps Resolved

| Gap | Issue | Resolution |
|-----|-------|-----------|
| **G1** | Transient returns corrupt data (not `false`, but non-array) | `is_array($cached)` guard in cached getter → fallback to direct fetch + re-cache |
| **G2** | Version option not seeded on fresh install | `get_option(..., '0')` provides safe default. First page load computes + caches under version `'0'`. First admin save bumps to `time()` → old key naturally misses |

**Additional edge cases:**
- **`set_transient()` fails silently** (DB write error): Settings are still computed and returned. No data loss, just a miss on next request.
- **Concurrent admin saves in same second**: Both write the same timestamp. Cache key doesn't advance between saves, but old transient was already absent → next request always recomputes. No stale data risk.
- **`get_transient()` returns serialized object instead of array**: `is_array()` guard catches this → forces re-fetch.

### 6. Performance Expectations

| Metric | Cold Cache | Warm Cache | Savings |
|--------|-----------|------------|---------|
| Page load contribution | ~15-55ms | ~2-7ms | **~13-48ms** (80-90%) |
| Sanitization calls per page | 1 (via cached getter) | 0 | **1 eliminated** |
| Transient lookup | — | ~1ms `get_transient()` | Negligible |
| Cache size (50 pages) | — | ~750KB serialized | Negligible |

**Hit rate**: >99%. Settings only change during admin saves. A page with 10,000 daily uncached views triggers only 1-2 cache misses (after saves).

**Caveat** (future optimization): `vbb_pro_get_settings()` is also called directly in `pro-css-vars.php:98`, which still runs sanitization once per page load for CSS variable output. This adds ~10-50ms even with warm page-settings cache. A follow-up could add a request-level static cache (`static $cache = null`) to `vbb_pro_get_settings()` — zero risk, ~5 lines.

### 7. Rollback Plan

**Emergency disable** (no deploy required):

```php
// Add to wp-config.php or a mu-plugin
define( 'VBB_PRO_CACHE_DISABLED', true );
```

The cached getter checks this constant at the top:

```php
function vbb_pro_get_cached_page_settings( int $page_id ): array {
    if ( defined( 'VBB_PRO_CACHE_DISABLED' ) && VBB_PRO_CACHE_DISABLED ) {
        return vbb_pro_get_page_settings( $page_id );
    }
    // ... cache logic ...
}
```

**Full rollback** (3 steps):
1. Revert the cached getter call in all 3 front-end call sites → back to `vbb_pro_get_page_settings()`.
2. Remove `vbb_pro_increment_settings_version()` calls from the 3 mutation functions.
3. Optionally clean up transients: `wp transient delete $(wp transient list --search="vbb_page_settings_*" --format=ids)`.

The `vbb_pro_settings_version` option can remain — a stale option key with no consumers is harmless (cost: one `get_option()` call per page load, ~0.5ms).

---

## Checklist

- [ ] Version functions exist with default seeding (G2 covered)
- [ ] Cached getter runs `is_array()` guard on transient return (G1 covered)
- [ ] All 3 front-end callers use cached getter (content, CSS vars, section filter)
- [ ] All 3 mutation functions call `vbb_pro_increment_settings_version()`
- [ ] `VBB_PRO_CACHE_DISABLED` constant checked before cache read
- [ ] `VBB_PRO_CACHE_DEBUG` constant gates debug logging
- [ ] Direct (uncached) `vbb_pro_get_page_settings()` remains available unchanged
- [ ] REST endpoints produce identical output (regression verified)
- [ ] TTL=43200 set as safety fallback

---

## Risks

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Missed mutation path (direct `update_option` on settings) | Low | All code paths converge on 2 core functions; audit confirms no direct `update_option(VBB_PRO_SETTINGS_OPTION)` call exists outside them. |
| `wp_head` CSS vars call `vbb_pro_get_settings()` uncached | Medium | Cached getter saves 2/3 sanitization calls. Static cache on `vbb_pro_get_settings()` noted as follow-up. |
| Transient table bloat from orphaned keys | Low | Each version change creates N new keys (one per page) but leaves old keys as orphaned. 12h TTL auto-cleans them. With 50 pages and daily saves, at most ~100 active keys at any time. |

---

## Files Changed

| File | Change Type | Lines |
|------|-----------|-------|
| `orkestone-theme/inc/pro-settings.php` | Modified | +~50 (version functions, cached getter, invalidation, section filter caller update) |
| `orkestone-theme/inc/pro-css-vars.php` | Modified | 2 lines (caller swap) |
| _(No changes to)_ `orkestone-theme/inc/pro-rest-api.php` | None | REST endpoints route through core functions → transitive invalidation |

---

## Next Step

→ **sdd-tasks**: Break into implementation tasks (version manager, cached getter, invalidation hooks, caller updates, test scenarios).
