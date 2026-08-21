# Design: json-image-sideloading

## Technical Approach

Extend the existing vertical import pipeline (`vertical-importer.php`) with a source→local URL map built during sideload, then apply that map as a pure-string pass on baked block content before `wp_insert_post()`. No new storage format, no new dependencies — the map lives only for the duration of a single `vbb_import_vertical_full()` call and is surfaced in the return report.

## Architecture Decisions

### Decision: `strtr()` for content remap

**Choice**: Use `strtr($content, $url_map)` inside a new `vbb_remap_block_urls()` helper. This is an O(n) pass with no regex overhead and deterministic single-replacement semantics per key.

**Alternatives considered**: `preg_replace()` with delimited URL patterns; `str_replace()` on pairs.

**Rationale**: `strtr()` takes a flat `string → string` map and performs simultaneous replacement — no double-replacement risk from overlapping keys. Regex adds backtracking cost for full URLs and introduces escaping complexity. `str_replace()` in a loop would be O(n×m) and risks cascading replacements. `strtr()` is the correct primitive here.

### Decision: Optional `$url_map` parameter on `vbb_generate_vertical_pages_from_baked()`

**Choice**: Add `$url_map = []` as the third parameter. Callers outside the full-import pipeline pass nothing and get pre-change behavior.

**Alternatives considered**: Content filter (`the_content`) or a separate remap pass after page insertion.

**Rationale**: A default-empty parameter is the narrowest backward-compatible surface. Post-insert remap (`post_content` filter or `wp_update_post`) would require re-querying every inserted page and risks double-writes if the filter fires again on render. Remapping before `wp_insert_post()`/`wp_update_post()` ensures the stored content is correct on the first write.

### Decision: Remap occurs in the caller, not in `vbb_build_page_content_from_baked()`

**Choice**: `vbb_generate_vertical_pages_from_baked()` remaps after calling `vbb_build_page_content_from_baked()` and before `wp_insert_post()`. `page-blueprint.php` is not modified.

**Alternatives considered**: Pass `$url_map` into `vbb_build_page_content_from_baked()` and remap inside it.

**Rationale**: `vbb_build_page_content_from_baked()` is a pure baker — it has no knowledge of media state or sideload outcomes. Adding a media concern to it violates the existing layering. Keeping remap in `vertical-importer.php` (the orchestrator of the full pipeline) preserves separation of concerns and means `page-blueprint.php` touches nothing for this change.

### Decision: Placeholder URLs enter the map under the original remote URL key

**Choice**: When `vbb_create_placeholder_attachment()` is called, its `wp_get_attachment_url()` is stored in the map under the original `$url` key — same as a successful sideload.

**Alternatives considered**: Track placeholders separately with a `remap_skipped` flag baked into the content.

**Rationale**: The downstream remap pass is a blind `strtr()` — it has no context about which attachments are placeholders. Merging placeholder URLs into the same map keeps the remap helper stateless. Gravel risk of filename collision (`vbb-placeholder-{slug}.svg`) is zero: sideloaded images preserve their original filenames.

## Data Flow

```
vertical JSON
    │
    ▼
vbb_get_vertical_media_items()        ← extract remote URLs
    │
    ▼
vbb_import_vertical_media_with_placeholders( 0, $report )
    │                                         │
    │  per URL:                                │
    │  - vbb_find_attachment_by_source_url()   │  dedup (existing)
    │  - media_sideload_image()                │  sideload or
    │  - vbb_create_placeholder_attachment()   │  placeholder fallback
    │  - wp_get_attachment_url()               │  ← populates $url_map
    │                                         │
    ▼
returns $summary + $url_map (source_url → local_url)
    │
    ▼ (in vbb_import_vertical_full)
vbb_generate_vertical_pages_from_baked( $config, $sections, $url_map )
    │
    │  per page:
    │    baked = vbb_build_page_content_from_baked(...)
    │    baked = vbb_remap_block_urls( baked, $url_map )   ← strtr pass
    │    wp_insert_post( baked )
    ▼
report enriched: urls_remapped_count, remap_skipped, remap_replacements
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `orkestone-theme/inc/vertical-importer.php` | **Modify** | 1) `vbb_import_vertical_media_with_placeholders()` returns augmented summary with `url_map`. 2) New `vbb_remap_block_urls()` helper added. 3) `vbb_generate_vertical_pages_from_baked()` accepts optional `$url_map` and calls remap before insert. 4) Report accumulator gains `urls_remapped_count`, `remap_skipped`, `remap_replacements`. |
| `orkestone-theme/inc/page-blueprint.php` | **No change** | `vbb_build_page_content_from_baked()` is left untouched; remapping is the orchestrator's responsibility per existing layering. |
| `openspec/specs/agency-hub/spec.md` | **Modify** | REQ-AH20 delta adds `urls_remapped_count` to the activation response; existing fields unchanged. |

### Key signatures after change

```php
// Before: returns array{imported, skipped, errors}
// After:  returns array{imported, skipped, errors, url_map}
function vbb_import_vertical_media_with_placeholders( int $limit = 25, array &$report = [] ): array

// New helper — lives in vertical-importer.php
function vbb_remap_block_urls( string $content, array $url_map ): string

// Before: vbb_generate_vertical_pages_from_baked( $config, $sections )
// After:  vbb_generate_vertical_pages_from_baked( $config, $sections, $url_map = [] )
function vbb_generate_vertical_pages_from_baked( array $config, array $sections = [], array $url_map = [] ): array
```

### `$url_map` shape

```php
// [ 'https://agency.example.com/hero.jpg' => 'https://client.com/wp-content/uploads/2026/08/hero.jpg', ... ]
array<string, string>
```

## Interfaces / Contracts

**`vbb_import_vertical_media_with_placeholders()` return shape (augmented)**

| Key | Type | Description |
|-----|------|-------------|
| `imported` | `array[]` | Each: `{url, id, is_placeholder?}` |
| `skipped` | `array[]` | Each: `{url, id}` — already sideloaded |
| `errors` | `array[]` | Each: `{url, error, fallback?}` |
| `url_map` | `array<string,string>` | **NEW.** Source URL → `wp_get_attachment_url()`. Includes successful sideloads, placeholders, and deduplicated existing attachments. |

**`vbb_remap_block_urls()` contract**

| Param | Type | Notes |
|-------|------|-------|
| `$content` | `string` | Raw Gutenberg block markup |
| `$url_map` | `array<string,string>` | Source→local URL map |

Returns: `string` — remapped content. Unmapped source URLs are left unchanged. Caller records `remap_skipped` by diffing the map keys against found URLs.

**Report accumulator additions (in `vbb_import_vertical_full()`)**

| Key | Type | Source |
|-----|------|--------|
| `urls_remapped_count` | `int` | Count of successful `strtr()` substitutions applied to page content |
| `remap_skipped` | `string[]` | Source URLs with no map entry (not found in content, or map empty) |
| `remap_replacements` | `int` | Total individual replacements across all pages |

## Testing Strategy

| Layer | What to test | Approach |
|-------|-------------|----------|
| Manual | Full import with remote URLs | Run `vbb_import_vertical_full()` from WP-CLI against a vertical containing known remote image URLs. Verify: (1) Media Library entries created with `_vbb_source_url` meta; (2) Page `post_content` contains zero remote URLs from the source set; (3) Placeholder pages render a local SVG, not a broken image; (4) Report shows `urls_remapped_count` matching sideloaded items. |
| Manual | Re-import idempotency | Re-run `vbb_import_vertical_full()` for same vertical. Verify no duplicate attachments (dedup via `vbb_find_attachment_by_source_url()`); report sideloaded count stays flat. |
| Manual | Partial sideload | Corrupt one remote URL to force placeholder. Verify map entry points to placeholder URL; remap substitutes correctly; report `failed` count correct. |

**No unit test scaffolding changes required.** Existing test infrastructure uses standalone PHP scripts with WP function stubs; `strtr()` and the new helper are trivial enough that manual E2E verification against a real WordPress install is the higher-fidelity signal. The agency-hub delta spec provides the acceptance criteria (`urls_remapped_count` in the REST response).

## Migration / Rollout

No migration required. The `$url_map` parameter defaults to `[]`, so all existing callers (WP-CLI `vbb import-all`, `vbb import-full`, any direct callers of `vbb_generate_vertical_pages_from_baked()`) continue to behave identically without code changes.

**Rollback**: remove the `$url_map` argument from the single call site in `vbb_import_vertical_full()` (line ~632). The default-empty parameter absorbs all other callers automatically. No database cleanup needed — `_vbb_source_url` meta is a permanent asset identifier.

## Open Questions

- [ ] **Report field name alignment**: the proposal uses `media_remapped_count`; the delta spec and agency-hub spec use `urls_remapped_count`. The implementation should follow the delta spec / agency-hub spec naming (`urls_remapped_count`) as the source-of-truth for the REST response, but `media_remapped` also appears in the local `$report` accumulator. Resolve: use `urls_remapped_count` as the canonical field (matches REST contract), and drop `media_remapped` from internal report to avoid split naming.