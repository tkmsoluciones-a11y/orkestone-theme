# Verification Report: json-image-sideloading (Spec 2)

**Change**: json-image-sideloading  
**Version**: N/A  
**Mode**: Standard (no Strict TDD; design explicitly disclaims unit test scaffolding, manual E2E is the prescribed signal)

---

### Completeness

| Metric | Value |
|--------|-------|
| Tasks total | 10 |
| Tasks complete | 7 |
| Tasks incomplete | 3 |

Implementation tasks (Phases 1–3) are all marked `[x]`. Verification tasks (Phase 4) remain unchecked:

| Task | Status | Note |
|------|--------|------|
| 1.1 Build `$url_map` in `vbb_import_vertical_media_with_placeholders()` | ☑ Done (static) | Signature confirms three ref params: `&$report`, `&$url_map` |
| 1.2 Add `vbb_remap_block_urls()` with `strtr()` | ☑ Done (static) | Lines 360–366 |
| 2.1 Placeholder URL added to `$url_map` | ☑ Done (static) | Line 441 |
| 3.1 `vbb_generate_vertical_pages_from_baked()` accepts optional `$url_map` | ☑ Done (static) | Lines 811–890 |
| 3.2 Caller wires `$url_map` through | ☑ Done (static) | Lines 660, 666 |
| 3.3 Report accumulator fields added | ☑ Done (static) | Lines 669–693 |
| 4.1 Full-import E2E (remote URLs, `_vbb_source_url`, zero remote in content, placeholder SVG, `urls_remapped_count`) | ☐ Not run | Requires live WP-CLI + WordPress environment |
| 4.2 Re-import idempotency | ☐ Not run | Requires live WordPress |
| 4.3 Partial sideload → placeholder fallback | ☐ Not run | Requires live WordPress; `remap_skipped` type already fails requirement — see CRITICAL findings |

Design.md (Testing Strategy §) explicitly states:
> No unit test scaffolding changes required. Manual E2E verification against a real WordPress install is the higher-fidelity signal.

PHP is not available in this session's execution environment; no runtime test evidence could be collected. All findings below are based on static code inspection.

---

### Build & Tests Execution

**Build / Lint**: ⚠️ Unable to run — `php` binary not found in PATH for this session.  
**Tests**: ⚠️ No PHPUnit or integration runner found in workspace; project design delegates to manual E2E.  
**Coverage**: ➖ Not applicable per design (no unit test scaffolding).

---

### Spec Compliance Matrix

**json-image-sideloading/spec.md:**

| Requirement | Scenario | Evidence | Result |
|---|---|---|---|
| Source-to-Local URL Mapping | Map populated after successful sideload | `$url_map[$url] = wp_get_attachment_url($attachment_id)` at line 467 | ✅ COMPLIANT |
| Source-to-Local URL Mapping | Map populated for existing attachments (dedup) | `$url_map[$url] = wp_get_attachment_url($existing_id)` at line 411 | ✅ COMPLIANT |
| Source-to-Local URL Mapping | Map survives round-trip through pipeline | `$url_map` threaded from `vbb_import_vertical_media_with_placeholders()` → `vbb_generate_vertical_pages_from_baked()` at lines 660, 666 | ✅ COMPLIANT |
| Block Content URL Remapping | Happy-path remap | `vbb_remap_block_urls()` calls `strtr($content, $url_map)` at line 365; replacements counted per-page at lines 840–847, accumulated at line 886, surfaced as `urls_remapped_count` at line 690 | ✅ COMPLIANT |
| Block Content URL Remapping | Unmapped URL left intact | `strtr()` is a pure map; unmapped keys are unchanged; function returns original content when map empty (line 361–363) | ✅ COMPLIANT |
| Block Content URL Remapping | Backward-compatible no-op (empty map) | Default `$url_map = []` at line 818; `empty($url_map)` short-circuits at line 842 | ✅ COMPLIANT |
| Placeholder URL Mapping | Placeholder URL stored in page content | `$url_map[$url] = wp_get_attachment_url($placeholder_id)` at line 441 | ✅ COMPLIANT |
| Placeholder URL Mapping | Placeholder collision safely impossible | Design confirms filenames differ (`vbb-placeholder-{slug}.svg` vs original filename sidloaded images); both map entries keyed on same `$original_url` so no collision | ✅ COMPLIANT |
| Import Report Enrichment | Report includes `urls_remapped_count` after full import | Line 690: `$report['urls_remapped_count'] = $urls_remapped;` | ✅ COMPLIANT |
| Import Report Enrichment | Report includes `remap_skipped` on partial failure | Line 691: `$report['remap_skipped'] = $remap_skipped;` — **type is `int`** (see CRITICAL #1) | ❌ FAILING — wrong type |

**Spec compliance summary**: 9/10 scenarios compliant (2 with warnings)

---

**agency-hub/spec.md — REQ-AH20 delta:**

| Requirement | Evidence | Result |
|---|---|---|
| `POST /orkestone/v1/activate` returns `urls_remapped_count` as non-negative integer | Delta spec: `{success: true, pagesCreated: N, mediaImported: M, urls_remapped_count: K}` (`pro-rest-api.php` line 1122–1132). **Top-level field absent** — value nested only inside `report` object. | ⚠️ PARTIAL — data present but not in required response shape |

Analysis: `vbb_rest_activate_config()` returns `report: $import_report` which does contain `urls_remapped_count`. The delta requires it at the top level in the same response envelope as `pagesCreated` and `mediaImported`. `pro-rest-api.php` was not modified by this change; no top-level `urls_remapped_count` is emitted.

---

### Correctness (Static Evidence)

| Requirement | Status | Notes |
|---|---|---|
| `vbb_import_vertical_media_with_placeholders()` signature with `&$url_map` ref param | ✅ Implemented | Line 382: function accepts `&$url_map = array()` by reference |
| `$url_map` populated for sideload success | ✅ Implemented | Line 467 |
| `$url_map` populated for dedup existing | ✅ Implemented | Line 411 |
| `$url_map` populated for placeholder fallback | ✅ Implemented | Line 441 |
| `vbb_remap_block_urls()` uses `strtr()` | ✅ Implemented | Line 365 |
| `strtr()` unmapped URLs unchanged | ✅ Implemented | Line 361 early-return on empty map |
| `vbb_generate_vertical_pages_from_baked()` optional `$url_map` | ✅ Implemented | Signature line 818 |
| Remap before `wp_insert_post()`/`wp_update_post()` | ✅ Implemented | Lines 849, 864, 867 |
| Report enriched: `urls_remapped_count` | ✅ Implemented | Line 690; type `int` correct |
| Report enriched: `remap_skipped` | ⚠️ Partial | Line 691: `$remap_skipped = max(0, count($url_map) - $urls_remapped);` — produces `int`, spec requires `string[]` |
| Report enriched: `remap_replacements` accumulator | ✅ Implemented | Line 886 |

---

### Design Coherence

| Decision | Followed? | Notes |
|---|---|---|
| `strtr()` for content remap | ✅ Yes | Line 365 — O(n) single pass, no regex |
| Optional `$url_map` param on `vbb_generate_vertical_pages_from_baked()` | ✅ Yes | Line 818 with default `[]` |
| Remap in caller (`vertical-importer.php`), not `page-blueprint.php` | ✅ Yes | Remap at line 849 inside `vertical-importer.php`; `page-blueprint.php` untouched (not in changeset) |
| Placeholder URLs enter map under original remote URL key | ✅ Yes | Line 441: `$url_map[$url] = wp_get_attachment_url($placeholder_id)` |
| `$url_map` lives only for duration of single `vbb_import_vertical_full()` call | ✅ Yes | Declared at line 620, scoped within function |

**Design deviation — remap_skipped type**:  
The design defines `remap_skipped` as `string[]` ("Source URLs with no map entry"). The implementation produces `int` at line 670–671. This is a spec-vs-implementation divergence, not a design deviation — the design contract calls for an array but a count is stored instead.

---

### Issues Found

**CRITICAL:**

- **CRITICAL-1**: `remap_skipped` type mismatch. The spec contract (`spec.md` § "Import Report Enrichment", `design.md` § "Report accumulator additions") defines `remap_skipped` as `string[]` — a list of source URLs that could not be resolved. The implementation at lines 670–671 produces an `int`:  
  ```php
  $remap_skipped = max( 0, count( $url_map ) - $urls_remapped );
  $report['remap_skipped'] = $remap_skipped;
  ```  
  This causes the partial-failure scenario test (Scenario: Report includes skipped list on partial failure) to **fail or produce incorrect data shape** — consumers expecting an array will receive an integer. Additionally, the current calculation is semantically incorrect: it subtracts total replacements from the map size, which includes successful sideloads + placeholders, but does not correspond to the set of URLs present in page content that went unmapped.

- **CRITICAL-2**: REQ-AH20 `urls_remapped_count` not at top-level in `POST /orkestone/v1/activate` response. The agency-hub delta spec requires: `{success: true, pagesCreated: N, mediaImported: M, urls_remapped_count: K}`. The actual response (pro-rest-api.php lines 1122–1132) nests `urls_remapped_count` inside the `report` object only:
  ```php
  return new WP_REST_Response(
      array(
          'success'       => true,
          'pagesCreated'  => ...,
          'mediaImported' => ...,
          'report'        => $import_report,   // urls_remapped_count is here
      ),
      200
  );
  ```  
  `pro-rest-api.php` was not modified by this change; the contract from the agency-hub delta is not yet met.

**WARNING:**

- **WARNING-1**: Phase 4 verification tasks (4.1, 4.2, 4.3) are unchecked in `tasks.md` and have not been executed. They require a live WordPress environment with WP-CLI. No runtime evidence has been collected for any spec scenario. Static inspection confirms code shape but does not substitute for runtime verification.

- **WARNING-2**: `$remap_skipped` calculation is semantically incorrect even as a count. `count($url_map) - $urls_remapped` subtracts replacement count from map size, but map entries include deduplicated existing attachments and the count represents substitutions across all pages — not URLs missing from page content. The correct implementation would collect the specific source URLs not found in any page's baked content (per spec: `urls_remapped` / the design's `remap_skipped` contract of "Source URLs with no map entry").

**SUGGESTION:**

- `pro-rest-api.php` `vbb_rest_create_page()` (lines 94–102) has a structural anomaly: the early-return block on empty title returns `$settings` (undefined in that scope) and is immediately followed by the full page-creation code at lines 228–278 which is structurally outside the function body. This is a pre-existing defect unrelated to this change but should be fixed before any response-shape change to activate is added to the same file.

---

### Verdict

**FAIL** — Two critical spec violations found in the implementation of `vertical-importer.php`:

1. `remap_skipped` is emitted as `int` in the report; the spec requires `string[]` (array of source URL strings) — this breaks the partial-failure scenario and gives consumers incorrect data shape.  
2. REQ-AH20 delta contract is not met: `urls_remapped_count` is nested inside `report` in the activate REST response, not at the top level as specified.

Both are correctable without schema migration. The remaining RAMA work (Phase 4 tasks 4.1–4.3) should execute after these issues are resolved.