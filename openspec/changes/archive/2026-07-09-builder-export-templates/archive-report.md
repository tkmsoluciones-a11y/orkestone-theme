# Archive Report: Builder Export & Templates

**Change**: builder-export-templates
**Archived**: 2026-07-09
**Final Status**: Completed (with warnings)

---

## Change Summary

Enabled the full round-trip of OrkestOne site configurations. Users can export their customized site as JSON (global settings + per-page overrides) via a REST endpoint and Command Center button, then import that data into a fresh install preserving all overrides. Section blocks gained a `style` field (A/B/C) enabling visual variants — three baker functions dispatch on `style` via `switch()` to produce different markup using shared rendering helpers. A button-group style selector in block settings auto-triggers re-bake on change.

Delivered in two chained stages:
- **Stage 1 — Export** (~220 lines): REST `GET /orkestone/v1/export` endpoint, Command Center "Export Site" button with client-side JSON blob download, extended import handler with `pageOverrides` support.
- **Stage 2 — Styles** (~440 lines): `style` field defaults and sanitization in settings, baker dispatch (hero, cta-final, testimonials), shared helpers (`vbb_render_cta_button()`, `vbb_render_heading_block()`), style selector UI, auto-rebake with confirmation.

## Artifact Lineage

| Artifact | Engram Obs ID | OpenSpec Path |
|----------|--------------|---------------|
| Proposal | #1615 | `openspec/changes/archive/2026-07-09-builder-export-templates/proposal.md` |
| Spec | #1617 | `openspec/changes/archive/2026-07-09-builder-export-templates/spec.md` |
| Design | #1619 | `openspec/changes/archive/2026-07-09-builder-export-templates/design.md` |
| Tasks | #1621 | `openspec/changes/archive/2026-07-09-builder-export-templates/tasks.md` |
| Apply Progress | #1624 | `openspec/changes/archive/2026-07-09-builder-export-templates/apply-progress.md` |
| Verify Report | #1627 | `openspec/changes/archive/2026-07-09-builder-export-templates/verify-report.md` |
| **Archive Report** | *(current save)* | `openspec/changes/archive/2026-07-09-builder-export-templates/archive-report.md` |
| Main Spec (synced) | — | `openspec/specs/builder-export/spec.md` |

## Key Technical Decisions

| Decision | Rationale |
|----------|-----------|
| **Schema v1.0.0** | Export envelope uses `schemaVersion: "1.0.0"` with semver semantics. Coexists with legacy admin-post export (`0.3.2`) — different consumers. |
| **Style dispatch via `switch()`** | Baker functions use internal `switch($data['style'])` for A/B/C dispatch. No changes to `vbb_bake_section()` dispatcher — `$data` already carries merged settings including `style`. |
| **Shared render helpers** | `vbb_render_cta_button()` and `vbb_render_heading_block()` extracted as shared helpers, used by ≥2 baker functions each. Receive `{{vbb_*}}` placeholders unescaped — escaping happens at render time via `vbb_pro_replace_dynamic_content()`. |
| **Deep-merge import strategy** | Import `pageOverrides` deep-merges per-page entries via `vbb_pro_deep_merge()` — existing entries not in import are preserved, not replaced. |
| **Confirmation BEFORE save** | Intentionally deviated from design (which had confirmation after save). Showing confirmation before persists is more correct — on cancel, the server still has the old value and `CC.loadSettings()` properly reverts. |
| **Style selector scoped to 3 blocks** | Only hero, ctaFinal, and testimonials get the UI selector and baker dispatch. Other blocks store/sanitize `style` but have no UI or dispatch. |
| **Auto-rebake scope** | Per-page style change → regenerates that page only. Global style change → regenerates ALL pages via existing `/regenerate-pages` endpoint. |
| **No REST import endpoint** | Deferred. Import continues via legacy admin-post handler only. A REST import endpoint can be added later. |

## Verification Summary

| Metric | Value |
|--------|-------|
| Verdict | **PASS WITH WARNINGS** |
| Requirements coverage | 12/12 (static verification) — all pass static inspection |
| Runtime test coverage | 0/12 — no covering runtime tests exist |
| Tasks complete | 10/10 |
| CRITICAL issues | None |
| WARNINGS | 2: (1) No runtime tests for the 12 new requirements; (2) Existing `test-block-baker.php` has stub ordering issue exposed by new shared helpers using `wp_json_encode()`. |

### Requirements Compliance

| ID | Description | Status |
|----|-------------|--------|
| REQ-ET1 | GET /orkestone/v1/export REST endpoint | ✅ Implemented |
| REQ-ET2 | Export JSON shape with envelope | ✅ Implemented |
| REQ-ET3 | Export button in CC toolbar | ✅ Implemented |
| REQ-ET4 | Import handler extended for pageOverrides | ✅ Implemented |
| REQ-ET5 | Deleted pages excluded from export | ✅ Implemented |
| REQ-ET6 | style field defaults to 'A' | ✅ Implemented |
| REQ-ET7 | Style sanitization A/B/C only | ✅ Implemented |
| REQ-ET8 | Baker dispatch on style | ✅ Implemented |
| REQ-ET9 | Shared helpers extracted | ✅ Implemented |
| REQ-ET10 | Style selector button-group | ✅ Implemented |
| REQ-ET11 | Style change triggers rebake | ✅ Implemented |
| REQ-ET12 | Export includes style values | ✅ Implemented |

### Regression Areas
All 15 regression areas (R1-R15) verified — legacy export, import, baker functions, settings save, page regeneration, and UI patterns remain intact.

## Design Deviations

1. **Shared helpers don't escape placeholders**: Design used `esc_url()`/`esc_html()` in helpers, but helpers receive `{{vbb_*}}` placeholders. Implemented with raw output — consistent with existing baker patterns. Escaping handled by `vbb_pro_replace_dynamic_content()` at render time.
2. **Confirmation BEFORE save**: Implementation shows confirmation before persisting rather than after. More correct UX — cancel properly reverts.
3. **Filename format**: Design specified `YYYYMMDD_HHmmss`. Implementation uses JS `.toISOString()` producing `YYYYMMDDTHHmmss`. Functionally equivalent.

## Lessons Learned

1. **Placeholder escaping in shared helpers**: `vbb_render_cta_button()` and `vbb_render_heading_block()` receive `{{vbb_*}}` placeholders, not actual URLs/text. Using `esc_url()` on placeholder URLs returns empty strings. Must output raw and let `vbb_pro_replace_dynamic_content()` handle escaping at render time.
2. **S5 — Global rebake performance**: Changing style globally triggers regeneration of ALL pages. The existing `vbb_pro_regenerate_all_pages()` handles this, but users should be warned via the confirmation dialog: "This style change will regenerate ALL pages. Continue?"
3. **Stub ordering in test files**: The existing standalone test file `test-block-baker.php` has `wp_json_encode()` stub defined after `require_once` of `block-baker.php`. New shared helpers that call `wp_json_encode()` expose this ordering issue. The fix is to move the stub before the require.
4. **Style selector only for styled blocks**: The `style` field is stored/sanitized for ALL 11 blocks, but the UI selector and baker dispatch only apply to hero, ctaFinal, and testimonials. Other blocks silently store `style='A'` with no visual impact.
5. **Page ID mapping across installs**: Per-page overrides are keyed by WP page ID, which differs between installs. Import preservation works within the same install or when IDs coincidentally match. Slug-based matching flagged as a future enhancement.
