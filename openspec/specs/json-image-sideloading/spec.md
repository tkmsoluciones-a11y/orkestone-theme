# Spec: json-image-sideloading

## Purpose

Defines the automated media sideloading and URL remapping pipeline for vertical JSON imports. Closes the gap where sideloaded images are stored locally but page block markup continues to reference the remote/external source URL.

## Requirements

### Requirement: Source-to-Local URL Mapping

The system MUST build a persistent `source_url → local_attachment_url` mapping dictionary during media sideloading and make it available to the page-generation phase.

#### Scenario: Map populated after successful sideload

- GIVEN a vertical JSON containing a remote image URL `https://agency.example.com/hero.jpg`
- WHEN `vbb_import_vertical_media_with_placeholders()` processes that URL and sideload succeeds
- THEN the returned map contains `"https://agency.example.com/hero.jpg" => "https://client-site.com/wp-content/uploads/2026/08/hero.jpg"`
- AND `wp_get_attachment_url()` confirms the mapped local URL resolves

#### Scenario: Map populated for existing attachments

- GIVEN a vertical JSON referencing a URL already sideloaded in a prior import, with `_vbb_source_url` meta set
- WHEN `vbb_import_vertical_media_with_placeholders()` runs the deduplication lookup via `vbb_find_attachment_by_source_url()`
- THEN the map is populated with the existing attachment's local URL — no second sideload is attempted

#### Scenario: Map survives round-trip through the import pipeline

- GIVEN `vbb_import_vertical_full()` has built the URL map
- WHEN `vbb_generate_vertical_pages_from_baked()` receives the map
- THEN the function uses the map for remap without re-querying or rebuilding it

---

### Requirement: Block Content URL Remapping

The system MUST replace every occurrence of a remote/external vertical image URL in generated page block markup with the corresponding local attachment URL before `wp_insert_post()` or `wp_update_post()` writes the content.

#### Scenario: Happy-path remap

- GIVEN a baked block content string containing `https://agency.example.com/hero.jpg` and a map entry for that URL
- WHEN `vbb_remap_block_urls($content, $url_map)` is called
- THEN every occurrence of `https://agency.example.com/hero.jpg` is replaced with the mapped local URL
- AND the count of replacements is recorded in the report's `remap_replacements` field

#### Scenario: Unmapped URL left intact

- GIVEN a block content string containing a URL not present in the URL map
- WHEN `vbb_remap_block_urls()` processes that content
- THEN the URL is left unchanged in the output
- AND `urls_remapped_count` in the report is not incremented for that URL
- AND the unmapped URL is recorded in `remap_skipped`

#### Scenario: Backward-compatible no-op

- GIVEN a caller passes an empty map (`[]`) to `vbb_generate_vertical_pages_from_baked()`
- AND no keys match remote URLs in the baked content
- WHEN the remap pass runs
- THEN content is returned unchanged
- AND the caller's behavior is identical to the pre-change state

---

### Requirement: Placeholder URL Mapping

The system MUST enter SVG placeholder attachment URLs into the source-to-local URL map when `vbb_create_placeholder_attachment()` is invoked, so generated pages reference a local placeholder rather than an unreachable external URL.

#### Scenario: Placeholder URL stored in page content

- GIVEN a remote image URL fails sideload and `vbb_create_placeholder_attachment()` creates a local SVG
- WHEN the placeholder's `wp_get_attachment_url()` is returned and added to the URL map under the original remote source URL key
- THEN remap substitutes the remote URL with the local SVG URL in all page content
- AND the page renders a visible placeholder image instead of a broken image icon

#### Scenario: Placeholder collision is safely impossible

- GIVEN a sideload produces an attachment whose original filename matches the placeholder naming pattern `vbb-placeholder-{slug}.svg`
- WHEN both the sideload and the placeholder attachment exist in the Media Library
- THEN each has a distinct attachment ID and the correct local URL is stored in the map entry for its respective source URL

---

### Requirement: Import Report Enrichment

The import report returned by `vbb_import_vertical_full()` MUST include `urls_remapped_count` (integer count of successful URL substitutions applied to page content). The report structure remains otherwise unchanged.

#### Scenario: Report includes remap counts after full import

- GIVEN a vertical JSON with 4 remote image URLs all successfully sideloaded and remapped
- WHEN `vbb_import_vertical_full()` completes
- THEN the report includes `urls_remapped_count: 4`
- AND `urls_remapped_count` is of type integer

#### Scenario: Report includes skipped list on partial failure

- GIVEN 3 of 4 remote image URLs sideload successfully; the fourth has no matching local attachment in the map
- WHEN `vbb_import_vertical_full()` completes
- THEN `urls_remapped_count` reflects the 3 successful substitutions
- AND `remap_skipped` contains the one unmapped source URL
- AND backward-compatible callers not passing a URL map see no change in report shape