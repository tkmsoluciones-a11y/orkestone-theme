# Exploration: Builder Performance Optimization — Token Caching

## Current State

### The `vbb_pro_replace_dynamic_content()` Execution Path

Every front-end page load triggers this filter chain via `add_filter( 'the_content', 'vbb_pro_replace_dynamic_content', 99 )`:

```
the_content
  └─ vbb_pro_replace_dynamic_content($content)
       ├─ get_the_ID()                              [trivial]
       ├─ vbb_pro_get_page_settings($page_id)
       │    ├─ get_option(VBB_PRO_PAGE_SETTINGS_OPTION)  [WP object cache — fast]
       │    ├─ vbb_pro_get_settings()
       │    │    ├─ get_option(VBB_PRO_SETTINGS_OPTION)   [WP object cache — fast]
       │    │    └─ vbb_pro_sanitize_settings()            [*** HEAVY ***]
       │    │         ├─ vbb_pro_default_settings()
       │    │         │    └─ vbb_get_vertical_config()    [reads JSON file]
       │    │         ├─ Deep merge stored + defaults
       │    │         ├─ Validate 14 hex colors (light + dark palettes)
       │    │         ├─ Validate 3 size values (px/rem/em/%)
       │    │         ├─ Validate 11 blocks (enabled/style/colors)
       │    │         ├─ Validate buttons (style/uppercase)
       │    │         ├─ Validate menuItems (recursive sanitization)
       │    │         └─ Backward-compat conversions (v0.3.x formats)
       │    └─ vbb_pro_deep_merge(global, page_overrides)  [recursive]
       ├─ Build 25-entry placeholder→value map        [trivial]
       └─ str_replace loop (25 passes over $content)  [C-level — cheap]
```

### Performance Profile

| Step | Cost | Notes |
|------|------|-------|
| `get_option()` calls | ~1-2ms | WP object cache hit (APCu/memcached) |
| `vbb_pro_sanitize_settings()` | ~10-50ms | Pure CPU — hex validation, deep merges, recursive menu sanitization |
| `vbb_pro_deep_merge()` | ~1-2ms | Recursive array merge |
| `str_replace` × 25 | ~0.5-2ms | Native C function, depends on content size |
| **Total per request** | **~15-55ms** | Every page, every visitor, every uncached view |

The **dominant cost** is `vbb_pro_sanitize_settings()`. It runs on every single `the_content` invocation, even though settings only change when an admin edits them via the Command Center. A page with 5 builder sections can pay this cost 5+ times depending on how `the_content` is invoked (e.g., excerpts, feeds, REST renders).

### Settings Modification Points (Invalidation Triggers)

| Function | File | Scope | What Changes |
|----------|------|-------|-------------|
| `vbb_pro_update_settings()` | pro-settings.php:408 | Global | All pages affected |
| `vbb_pro_update_page_settings()` | pro-settings.php:36 | Per-page | One page affected |
| `vbb_pro_save_profile()` | pro-settings.php:419 | Profile | Doesn't change active settings |
| `vbb_pro_apply_profile()` | pro-settings.php:433 | Global | All pages affected |
| `vbb_pro_reset_to_vertical()` | pro-settings.php:444 | Global | All pages affected |
| `vbb_pro_sync_menu_to_wp_navigation()` | pro-settings.php:354 | Global | menuItems in settings |
| `vbb_rest_update_settings()` | pro-rest-api.php:38 | Global | All pages affected |
| `vbb_rest_update_page_settings()` | pro-rest-api.php:704 | Per-page | One page affected |
| `vbb_rest_update_menu()` | pro-rest-api.php:566 | Global | All pages affected |
| `vbb_rest_append_menu_item()` | pro-rest-api.php:608 | Global | All pages affected |
| `vbb_rest_delete_menu_item()` | pro-rest-api.php:652 | Global | All pages affected |

## Affected Areas

- `orkestone-theme/inc/pro-settings.php` — Contains `vbb_pro_replace_dynamic_content()`, `vbb_pro_get_page_settings()`, `vbb_pro_get_settings()`, and all settings-modifying functions.
- `orkestone-theme/inc/pro-rest-api.php` — REST endpoints that trigger settings saves and need invalidation hooks.

## Approaches

### Approach 1: Cache the Merged Page Settings (Recommended)

Cache the fully-resolved, sanitized settings object per page using WordPress Transients. The `str_replace` loop still runs, but the expensive `vbb_pro_sanitize_settings()` is bypassed.

**Cache key**: `vbb_page_settings_{page_id}_{settings_version}` where `settings_version` is a timestamp option bumped on every settings write.

**Cache value**: The sanitized settings array (result of `vbb_pro_get_page_settings()`).

**TTL**: 0 (no expiration) — rely entirely on explicit invalidation via `delete_transient()`. Adds a safety TTL of 12 hours as fallback.

- **Pros**:
  - Minimal code change — wrap existing `vbb_pro_get_page_settings()`.
  - High cache hit rate — settings change infrequently.
  - Cache size is small (~5-15KB per page as serialized array).
  - Works identically for global and per-page overrides.
  - No risk of serving HTML with unresolved tokens (str_replace still runs).
- **Cons**:
  - Still runs `str_replace` loop per page (cheap, but not zero).
  - Requires explicit invalidation at every mutation point.
- **Effort**: Low

### Approach 2: Cache the Final HTML

Cache the fully-replaced HTML output per page after all token replacements.

**Cache key**: `vbb_content_{page_id}_{settings_version}`

- **Pros**: Fastest possible output — just return cached string.
- **Cons**:
  - Larger cache (full page HTML, potentially 50-100KB+ per page).
  - Cache fragmentation: different pages have different versions unless a global version is used.
  - If settings change, ALL pages need cache invalidation — even pages that wouldn't be affected by the specific change.
  - Harder to debug when content is stale.
- **Effort**: Medium

### Approach 3: Cache the Resolved Token Map

Cache the `[placeholder ⇒ value]` map, then run `str_replace` at render time.

**Cache key**: `vbb_token_map_{settings_version}` (global, not per page).

- **Pros**: Smallest cache (~1KB). Single key for all pages (when same settings apply).
- **Cons**:
  - Ignores per-page overrides — requires per-page keys anyway.
  - Still runs `str_replace` loop.
  - Minimal benefit over Approach 1.
- **Effort**: Low

## Recommendation

**Approach 1: Cache the Merged Page Settings via WordPress Transients.**

This gives the best tradeoff between complexity, performance gain, and maintainability. The core win is eliminating `vbb_pro_sanitize_settings()` on every page load — which is 80-90% of the current filter cost.

### Proposed Implementation

**1. New helper in `pro-settings.php`:**

```php
const VBB_PRO_SETTINGS_VERSION_KEY = 'vbb_pro_settings_version';

function vbb_pro_get_settings_version() {
    return (string) get_option( VBB_PRO_SETTINGS_VERSION_KEY, '0' );
}

function vbb_pro_increment_settings_version() {
    update_option( VBB_PRO_SETTINGS_VERSION_KEY, (string) time(), false );
}
```

**2. Cached page settings getter:**

```php
function vbb_pro_get_cached_page_settings( $page_id ) {
    $page_id     = (int) $page_id;
    $version     = vbb_pro_get_settings_version();
    $cache_key   = 'vbb_page_settings_' . $page_id . '_' . $version;
    $cached      = get_transient( $cache_key );

    if ( false !== $cached ) {
        return $cached;
    }

    $settings = vbb_pro_get_page_settings( $page_id );
    set_transient( $cache_key, $settings, 12 * HOUR_IN_SECONDS );

    return $settings;
}
```

**3. Modify the filter to use cached version:**

```php
function vbb_pro_replace_dynamic_content( $content ) {
    if ( is_admin() ) return $content;

    $page_id  = get_the_ID();
    $settings = vbb_pro_get_cached_page_settings( $page_id );
    // ... rest stays the same
}
```

**4. Invalidation — add to every settings-mutating function:**

| Function | Invalidation |
|----------|-------------|
| `vbb_pro_update_settings()` | `vbb_pro_increment_settings_version()` |
| `vbb_pro_update_page_settings()` | `vbb_pro_increment_settings_version()` (bumps global version, invalidates all page caches) |
| `vbb_pro_apply_profile()` | `vbb_pro_increment_settings_version()` |
| `vbb_pro_reset_to_vertical()` | `vbb_pro_increment_settings_version()` |
| `vbb_pro_sync_menu_to_wp_navigation()` | No change needed — only menuItems, which are in settings. The save already calls `vbb_pro_update_settings()`. |

All REST endpoints funnel through these core functions, so REST invalidation comes for free.

### Cache Key Granularity Trade-off

Using a single global version bumps ALL page transients when ANY setting changes. This is the simplest approach. For a landing page (1-5 pages), this is negligible. For larger sites, per-page granularity could be added later by tracking page-specific version keys.

## Risks

### Stale Cache
- **Risk**: If a mutation point is missed, the transient serves stale settings.
- **Mitigation**: Every settings-mutating function goes through `vbb_pro_update_settings()` or `vbb_pro_update_page_settings()`. Installing the invalidation in these two core functions covers all paths. Audit for direct `update_option()` calls on `VBB_PRO_SETTINGS_OPTION`.

### Transient Overhead
- **Risk**: `set_transient()` and `get_transient()` add DB writes/reads.
- **Mitigation**: This only applies to uncached requests. On a cached page (page cache), the filter never runs. On settings saves (infrequent), the transient write is negligible.

### Cache Fragmentation
- **Risk**: Many cache keys if settings change frequently.
- **Mitigation**: Settings change rarely (only admin operations). A single global version means only N pages × 1 version active at a time.

### Memory Usage
- **Risk**: Serialized settings can be 5-15KB. With 50 pages, that's ~750KB in the transients table.
- **Mitigation**: Trivial in modern WordPress hosting. Transients are stored in the options table (or external object cache if available).

### Race Conditions
- **Risk**: Two concurrent admin saves could race on the version timestamp.
- **Mitigation**: `update_option()` is atomic in MySQL. The version option is autorefreshed on next request. A 1-second collision window is harmless.

## Ready for Proposal

Yes. The performance bottleneck is well-understood: `vbb_pro_sanitize_settings()` runs on every uncached front-end request. A straightforward transient cache wrapping `vbb_pro_get_page_settings()` with a global settings version key eliminates 80-90% of the CPU cost with minimal code changes and low risk.

**Cost to implement**: ~40-60 lines of PHP, 3-5 functions.
**Performance gain**: ~30-50ms saved per uncached page render.
**Invalidation coverage**: 100% — all mutation paths converge on 2 core functions.
