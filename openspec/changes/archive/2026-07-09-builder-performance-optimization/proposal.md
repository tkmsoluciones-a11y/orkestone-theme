# Proposal: Builder Performance Optimization — Token Caching

## Intent

Page load times are inflated by `vbb_pro_sanitize_settings()` running on every uncached front-end request. This function performs expensive deep merges, hex color validation, and recursive menu-item sanitization — yet settings only change when an admin edits them. Cache the fully-resolved, sanitized settings via WordPress Transients so the sanitization path is skipped on 99%+ of page loads.

## Scope

### In Scope
- **Caching Layer**: `get_transient` / `set_transient` wrapping `vbb_pro_get_page_settings()`.
- **Cache Key Management**: Composite key of `vbb_page_settings_{page_id}_{global_version}`.
- **Invalidation Engine**: Global version counter bumped at every settings mutation point.
- **Performance Monitoring**: Optional debug logging (cache hit/miss) behind a constant flag.

### Out of Scope
- Caching the final HTML output (Approach 2 — deferred).
- Caching the token map only (Approach 3 — suboptimal without per-page support).
- Per-page version granularity (global version is sufficient for landing-page sites).
- Full-page cache or CDN caching (separate concern).

## Capabilities

### New Capabilities
- `settings-cache`: WordPress Transient-based cache for resolved page settings with version-based invalidation.

### Modified Capabilities
- None — this is a pure implementation optimization. No spec-level behavior changes.

## Approach

Single-phase delivery. Three units of work:

1. **Versioning system** — New functions `vbb_pro_get_settings_version()` and `vbb_pro_increment_settings_version()`. Stores an auto-incrementing timestamp in `wp_options`.
2. **Cached getter** — New function `vbb_pro_get_cached_page_settings($page_id)`. Checks transient before falling back to full sanitization → `set_transient()`.
3. **Invalidation hooks** — Call `vbb_pro_increment_settings_version()` inside every settings-mutating function (`vbb_pro_update_settings`, `vbb_pro_update_page_settings`, `vbb_pro_apply_profile`, `vbb_pro_reset_to_vertical`). REST endpoints invalidate automatically through these core functions.

The filter `vbb_pro_replace_dynamic_content()` changes only one line: call the cached getter instead of the direct one. `str_replace` loop still runs (cheap).

Cache TTL: 0 (explicit invalidation only) with 12-hour safety fallback.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `orkestone-theme/inc/pro-settings.php` | Modified | Add ~40 lines: version functions, cached getter, invalidation calls |
| `orkestone-theme/inc/pro-rest-api.php` | Modified | No direct changes — REST endpoints call core functions that now invalidate |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Stale cache if mutation point is missed | Low | All paths converge on 2 core functions (update/update_page_settings). Audit for direct `update_option()` on settings option. |
| Transient DB writes on every settings save | Low | Saves are admin-only, infrequent. Transient write ~1ms. |
| Race condition on version timestamp | Low | `update_option()` is atomic. 1-second collision window is harmless. |
| Memory from many transients | Low | ~5-15KB per page. With 50 pages: ~750KB total. Negligible. |

## Rollback Plan

1. Revert the one-line change in `vbb_pro_replace_dynamic_content()`.
2. Remove invalidation calls from mutation functions.
3. Run `delete_transient()` with wildcard or clear transients manually.
4. Remove version option on next deploy or leave — stale key is harmless.

## Dependencies

- None. Pure PHP/WordPress core API (`get_transient`, `set_transient`, `get_option`, `update_option`).

## Success Criteria

- [ ] Measurable page load time reduction: ≥25ms saved per uncached page render (verified via `microtime()` before/after caching).
- [ ] Cache hit rate ≥95% on repeated page views (verified via debug logging in staging).
- [ ] Zero reports of stale content after admin saves (manual QA: save settings → verify front-end reflects changes immediately).
