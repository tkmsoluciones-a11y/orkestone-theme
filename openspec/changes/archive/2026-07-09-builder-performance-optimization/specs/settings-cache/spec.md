# Delta for Settings Cache

## ADDED Requirements

This change introduces a new capability (`settings-cache`) with no pre-existing spec. See the full specification at `openspec/specs/settings-cache/spec.md`.

| ID | Requirement | Input → Output | Verification |
|----|-------------|----------------|--------------|
| REQ-SC01 | System MUST maintain a global settings version in `wp_options` | `get_settings_version()` → integer; `increment_settings_version()` → atomically bumps value | After increment, version returns previous + 1 |
| REQ-SC02 | Cache keys MUST follow `vbb_page_settings_{page_id}_{version}` | `get_cached_page_settings(42)` → transient key from version + page_id | Same page_id, different version = different keys |
| REQ-SC03 | Cached getter MUST check transient before sanitization | `get_cached_page_settings(42)` → resolved array | 2nd call (cache hit) skips `sanitize_settings()`, measurably faster |
| REQ-SC04 | All 4 mutation functions MUST call increment | Mutation → version bumps | After save, next page load fetches fresh data |
| REQ-SC05 | Transient TTL SHOULD be 0 with 43200s (12h) safety fallback | `set_transient(key, val, 43200)` | Transient expires after 12h if invalidation missed |
| REQ-SC06 | System MAY log hit/miss when `VBB_PRO_CACHE_DEBUG` is truthy | Constant defined → debug entries appear | Page load produces log lines with hit/miss status |

## ADDED End-to-End Scenarios

### S1: First page load (cold cache)
- GIVEN no transient exists for page 42 at version 1
- WHEN `vbb_pro_get_cached_page_settings(42)` runs
- THEN `vbb_pro_sanitize_settings()` executes, result stored in `vbb_page_settings_42_1`, and resolved array returned

### S2: Repeat page load (cache hit)
- GIVEN `vbb_page_settings_42_1` transient exists
- WHEN the same function runs again for page 42
- THEN transient value returns and `vbb_pro_sanitize_settings()` is NOT called

### S3: Admin saves a color
- GIVEN page 42 is cached at version 5
- WHEN admin edits a color via Command Center → `vbb_pro_update_page_settings()` fires → version increments to 6
- THEN next page-42 request misses (key = `vbb_page_settings_42_6`), recomputes, and caches under the new key

### S4: Global version affects all pages
- GIVEN pages 10, 20, 30 are cached at version 3
- WHEN any settings-mutating function runs (version → 4)
- THEN next requests for pages 10, 20, 30 all miss and re-cache independently

### S5: Safety TTL fallback
- GIVEN a transient stored with TTL=43200 and no version change occurs
- WHEN 12 hours pass
- THEN the transient expires naturally
- AND the next page load re-computes and re-caches settings

## Regression Areas (MUST NOT break)

- `vbb_pro_get_page_settings()` — direct (uncached) access must remain available with identical output
- All four mutation functions — `vbb_pro_update_settings`, `vbb_pro_update_page_settings`, `vbb_pro_apply_profile`, `vbb_pro_reset_to_vertical` — must produce identical results
- REST API endpoints — must still work (they route through mutation functions, no direct changes)
- `vbb_pro_replace_dynamic_content()` — must produce identical HTML (only the settings source changes)
- Admin settings UI — must remain unaffected
- External dependencies — zero new libraries or plugins
