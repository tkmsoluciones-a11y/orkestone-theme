# Proposal: json-image-sideloading

## Intent

Vertical JSON imports currently sideload images into the Media Library but do not remap the remote/external URLs used inside baked block markup after import. A site activated from a Hub-delivered vertical (or any remote JSON) ends up with pages whose block content still points to the agency server or CDN rather than the local WordPress attachment URLs. The Goal: `json-image-sideloading` closes this gap by building a source→local attachment map during sideloading and replacing all references in the final block content before pages are inserted.

## Scope

### In Scope
- Enhance `vbb_import_vertical_media_with_placeholders()` in `inc/vertical-importer.php` to build and return a `source_url → local_attachment_url` map alongside the existing sideload summary.
- Add a post-remap pass on the baked block content produced by `vbb_build_page_content_from_baked()` (in `inc/page-blueprint.php`) to replace all occurrences of remote source URLs with the corresponding local attachment URL using `wp_get_attachment_url()`.
- Retain SVG placeholder fallback behavior already present in `vbb_create_placeholder_attachment()`; the placeholder's local URL is also entered in the map so `_vbb_broken` references stay local.
- Propagate the URL map through `vbb_generate_vertical_pages_from_baked()` so it is applied before `wp_insert_post()` / `wp_update_post()` writes page content.
- Extend the import report to expose `media_remapped_count` and list any source URLs that had no matching local attachment (edge-case audit trail).

### Out of Scope
- S3, Cloudflare R2, or any third-party cloud storage direct adapters — only standard WordPress media handling (`media_sideload_image()`, `media_handle_sideload()`) is in scope.
- Background/async sideloading or offloading the sideload operation to a queue.
- Image optimization, WebP conversion, or any transcoding step beyond what WordPress core provides.

## Capabilities

### New Capabilities
- `json-image-sideloading`: Automated media sideloading and URL remapping pipeline for vertical JSON imports — builds a source-to-local attachment map during `vbb_import_vertical_media_with_placeholders()` and applies string replacement on generated block markup in `vbb_generate_vertical_pages_from_baked()`; SVG fallbacks on sideload failure; tracks `_vbb_source_url` and surfaces remap statistics in the import report.

### Modified Capabilities
- `agency-hub` (openspec/specs/agency-hub/spec.md): REQ-AH20 import report currently counts only sideloaded/failed; this change adds `media_remapped` count. No behavioral change to the endpoint or phase-3 flow — the import report shape is extended, not broken.

## Approach

1. **Map construction**: `vbb_import_vertical_media_with_placeholders()` returns its existing `summary` augmented with a `url_map` key: `[ source_url => wp_get_attachment_url( $attachment_id ) ]`. Successful sideloads, placeholders, and pre-existing attachments (via `vbb_find_attachment_by_source_url()`) all contribute entries.
2. **Content remap**: Add a new helper `vbb_remap_block_urls( $content, $url_map )` in `inc/vertical-importer.php`. It performs a `strtr()` pass (deterministic O(n), no regex overhead for full-URL replacement) substituting every source URL with its local counterpart. If a source URL has no map entry, the URL is left unchanged and recorded in the report under `remap_skipped`.
3. **Integration point**: `vbb_generate_vertical_pages_from_baked()` calls `vbb_remap_block_urls( $baked_content, $url_map )` immediately before `wp_insert_post()`/`wp_update_post()`. The function signature is extended with an optional `$url_map` parameter (default `[]` preserves backward compatibility for any callers outside the full-import pipeline).
4. **Report enrichment**: The `$report` accumulator passed by reference from `vbb_import_vertical_full()` gains `media_remapped` (count of successful substitutions), `remap_skipped` (URLs with no local target), and `remap_replacements` (total individual replacements made).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `orkestone-theme/inc/vertical-importer.php` | Modified | `vbb_import_vertical_media_with_placeholders()` returns `url_map`; new `vbb_remap_block_urls()` helper added; `vbb_generate_vertical_pages_from_baked()` signature extended |
| `orkestone-theme/inc/page-blueprint.php` | Unchanged | `vbb_build_page_content_from_baked()` itself is not modified; remapping happens in the caller that already invokes it |
| `orkestone-theme/inc/vertical-storage.php` | Unchanged | No schema or storage-format changes |
| `openspec/specs/agency-hub/spec.md` | Delta spec | REQ-AH20 report structure extended; no behavioral change |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|-----------|
| Double-sideload of the same image on re-import | Medium | `vbb_find_attachment_by_source_url()` already deduplicates; map lookup for existing IDs prevents repeated `media_sideload_image()` calls |
| Source URL with query strings or redirects mismatching stored `_vbb_source_url` | Low | `_vbb_source_url` is stored using `esc_url_raw()` — same normalization applied to map keys |
| Performance on large imports (50+ images) | Low | `strtr()` is O(total content length); map construction is O(items). No regex backtracking risk. |
| Placeholder URL filename collision with a real sideloaded image | Low | Placeholder is named `vbb-placeholder-{slug}.svg`; real sideloads preserve original filename — collision is practically impossible |

## Rollback Plan

- The `$url_map` parameter in `vbb_generate_vertical_pages_from_baked()` defaults to `[]`, so all existing callers that do not pass a map continue to work identically (no remap applied, behavior identical to pre-change).
- Disable the remap pass in production by removing the new parameter at the call site in `vbb_import_vertical_full()` — a one-line revert.
- No database schema changes; `_vbb_source_url` meta already exists, so no cleanup migration is needed on rollback.

## Dependencies

- WordPress core `media_sideload_image()` and `media_handle_sideload()` (already required by existing code).
- No new PHP extensions or external libraries.

## Success Criteria

- [ ] After `vbb_import_vertical_full()` completes, zero image URLs in any page's `post_content` reference a remote/external domain that was present in the original vertical JSON.
- [ ] `_vbb_source_url` post meta is set on every attachment created or identified during the sideload pass.
- [ ] On sideload failure, a local SVG placeholder attachment is created, its WP attachment URL appears in the page content, and the import report records `is_placeholder=true` plus `_vbb_broken=1`.
- [ ] The import report includes `media_remapped`, `remap_skipped`, and `remap_replacements` fields.
- [ ] Re-running `vbb_import_vertical_full()` for the same vertical does not duplicate attachments (idempotent sideload).
- [ ] Step-7 of Scenario 1 (sdd-spec) reads "4 media items sideloaded, 4 URLs remapped in page content" — no remote URLs remain.