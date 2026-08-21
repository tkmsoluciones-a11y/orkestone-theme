# Tasks: json-image-sideloading (Spec 2)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~100–150 across 2 files |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Chain strategy | pending |
| Delivery strategy | single-pr |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | URL map builder + remap helper + orchestrator wiring + report enrichment | PR 1 | All in `vertical-importer.php`; backward-compatible default params; single commit |

## Phase 1: URL Map Builder & Remap Utility

- [x] 1.1 Update `vbb_import_vertical_media_with_placeholders()` in `orkestone-theme/inc/vertical-importer.php` to build and return `$url_map` (source_url → `wp_get_attachment_url()`): populate for sideloaded attachments, placeholders, and deduplicated existing attachments via `vbb_find_attachment_by_source_url()`.
- [x] 1.2 Add `vbb_remap_block_urls(string $content, array $url_map): string` helper to `orkestone-theme/inc/vertical-importer.php` using a single `strtr()` pass; unmapped source URLs are left unchanged.

## Phase 2: Placeholder Integration into the URL Map

- [x] 2.1 Confirm `vbb_create_placeholder_attachment()` (already in `vertical-importer.php`) returns an attachment ID; at each placeholder call site inside `vbb_import_vertical_media_with_placeholders()`, add `wp_get_attachment_url($placeholder_id)` to `$url_map` keyed by the original remote `$url`.

## Phase 3: Full Import Orchestrator Wiring & Report Enrichment

- [x] 3.1 Extend `vbb_generate_vertical_pages_from_baked()` signature to `vbb_generate_vertical_pages_from_baked(array $config, array $sections_config = [], array $url_map = [])`; call `vbb_remap_block_urls($baked_content, $url_map)` between baking and `wp_insert_post()`/`wp_update_post()`.
- [x] 3.2 In `vbb_import_vertical_full()`: capture returned `$url_map` from `vbb_import_vertical_media_with_placeholders()` (line 626) and pass it to `vbb_generate_vertical_pages_from_baked()` (line 632).
- [x] 3.3 Enrich the `$report` accumulator in `vbb_import_vertical_full()` with `urls_remapped_count` (successful substitutions), `remap_skipped` (mapped URLs not found in content), and `remap_replacements` (total individual replacements), following the design §Report accumulator additions contract.

## Phase 4: Verification

- [x] 4.1 Run full import (`vbb import-full`) against a vertical containing known remote image URLs; confirm: (a) `_vbb_source_url` meta present on all sideloaded and placeholder attachments, (b) zero remote source URLs remain in any inserted `post_content`, (c) placeholder attachments render local SVG, (d) report includes `urls_remapped_count` matching sideloaded count.
- [x] 4.2 Re-run `vbb import-full` for the same vertical to verify idempotency: no duplicate attachments, `sideloaded` count stays flat, and `urls_remapped_count` is correct.
- [x] 4.3 Corrupt one remote URL to trigger placeholder fallback; verify map entry points to placeholder URL, remap substitutes correctly, and report `failed`/`remap_skipped` counts are accurate.