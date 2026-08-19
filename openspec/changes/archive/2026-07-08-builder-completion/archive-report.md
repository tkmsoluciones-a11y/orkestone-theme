# Archive Report: Builder Completion

**Change**: builder-completion
**Archived**: 2026-07-08
**Final Status**: Completed

---

## Change Summary

Completed the OrkestOne theme's Command Center into a **full no-code website builder** by closing the gaps between the Block Baker, the REST API, and the admin UI. The implementation spanned 5 force-chained phases covering 21 tasks across 6 files:

- **Phase 1 (Critical Path)**: Fixed `vbb_bake_process()` foreach bug, wrote `vbb_bake_page_content()`, added page selector DOM element, wired `CC.el.pageSelector` in JS, added `vbb_get_vertical_page_by_id()` helper.
- **Phase 2 (Block Baker)**: Converted 11 baker functions to `{{vbb_*}}` placeholder token output for single-value fields. Expanded `vbb_pro_replace_dynamic_content()` mapping to resolve all 20 tokens. Repeatable items baked from merged settings directly (Option C — no placeholders inside loops).
- **Phase 3 (Page API)**: Added `POST/DELETE /orkestone/v1/pages`, expanded `GET /orkestone/v1/pages` with `slug`/`sections`/`hasSettings`, added `POST /pages/{id}/regenerate`.
- **Phase 4 (Menu Management)**: Added `menuItems` schema + sanitization, `vbb_pro_sync_menu_to_wp_navigation()` unidirectional sync, `GET/PUT /orkestone/v1/menu` endpoints, plus `POST/DELETE menu/items/{idx}` for individual operations. Sortable menu editor UI with nested items.
- **Phase 5 (Backfill)**: Activation hook (`after_switch_theme`) triggers version-gated regeneration of all pages. Admin notice with "Regenerate All Pages Now" button if unresolved tokens are detected.

## Artifact Lineage

| Artifact | Filesystem Path |
|----------|-----------------|
| Proposal | `openspec/changes/archive/2026-07-08-builder-completion/proposal.md` |
| Exploration | `openspec/changes/archive/2026-07-08-builder-completion/exploration.md` |
| Spec | `openspec/changes/archive/2026-07-08-builder-completion/spec.md` |
| Design | `openspec/changes/archive/2026-07-08-builder-completion/design.md` |
| Tasks | `openspec/changes/archive/2026-07-08-builder-completion/tasks.md` |
| Apply Progress | `openspec/changes/archive/2026-07-08-builder-completion/apply-progress.md` |
| Verify Report | `openspec/changes/archive/2026-07-08-builder-completion/verify-report.md` |
| Archive Report | `openspec/changes/archive/2026-07-08-builder-completion/archive-report.md` |

**Note**: No delta specs existed in a `specs/{domain}/` structure. The spec is a self-contained change-level `spec.md`. No main `openspec/specs/{domain}/` directory existed to merge into. All requirements are captured in the archived spec.

## Key Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **Repeatable items strategy** | Option C — bake from merged settings directly | Variable-length arrays don't map to placeholders. Baking at produce time produces correct N items per page. |
| **Menu sync direction** | Settings → `wp_navigation` only (uni-directional) | Settings are the Command Center's data model; `wp_navigation` is a render target. Avoids conflict complexity. |
| **Backfill trigger** | `after_switch_theme` + version check | Activation hook catches theme switches; version check avoids unnecessary re-baking. |
| **Placeholder token scope** | Flat namespace `{{vbb_{section}_{field}}}` | Easier to `str_replace`, debug, and test than hierarchical tokens. |
| **Page selector DOM** | Server-rendered `<select>` container, JS-populated via REST | Consistent with existing `loadPages()` / `renderPageSelector()` pattern. |
| **Menu editor UI** | JS-injected via `buildCard()` pattern (not PHP-rendered) | Follows existing Command Center card rendering pattern used by all other cards. |
| **Individual menu item endpoints** | Added `POST/DELETE /orkestone/v1/menu/items/{idx}` beyond design spec | Supports individual item operations as requested during implementation, not just full replacement via `PUT`. |

## Verification Summary

**Verdict**: PASS ✅

| Metric | Result |
|--------|--------|
| Spec compliance | 14/14 requirements compliant (REQ-1 through REQ-14) |
| Scenario coverage | 5/5 end-to-end scenarios compliant |
| Regression areas | 6/6 areas protected |
| Test execution | 123/123 passed, 0 failed, 0 skipped |
| Critical issues | 0 |
| Warnings | 0 |
| Suggestions | 4 (static cache, contact section heading, hero-centered empty-title guard, no move-down button in menu editor) |

### Spec Compliance Summary

| ID | Requirement | Result |
|----|------------|--------|
| REQ-1 | `vbb_bake_page_content($page_id)` exists and regenerates | ✅ COMPLIANT |
| REQ-2 | `vbb_bake_process()` with 0/1/5 steps, no notices | ✅ COMPLIANT |
| REQ-3 | All 11 baker functions output `{{vbb_*}}` tokens | ✅ COMPLIANT |
| REQ-4 | `vbb_pro_replace_dynamic_content()` resolves all tokens | ✅ COMPLIANT |
| REQ-5 | Repeatable items baked from merged settings | ✅ COMPLIANT |
| REQ-6 | `POST /orkestone/v1/pages` creates page, returns 201 | ✅ COMPLIANT |
| REQ-7 | `DELETE /orkestone/v1/pages/{id}` trashes + removes settings | ✅ COMPLIANT |
| REQ-8 | `GET /orkestone/v1/pages` returns all required fields | ✅ COMPLIANT |
| REQ-9 | `POST /pages/{id}/regenerate` re-bakes content | ✅ COMPLIANT |
| REQ-10 | `GET /orkestone/v1/menu` returns merged items | ✅ COMPLIANT |
| REQ-11 | `PUT /orkestone/v1/menu` replaces items + syncs | ✅ COMPLIANT |
| REQ-12 | Command Center renders page selector dropdown | ✅ COMPLIANT |
| REQ-13 | Command Center renders sortable menu editor | ✅ COMPLIANT |
| REQ-14 | Theme activation triggers regeneration | ✅ COMPLIANT |

## Lessons Learned

1. **Individual menu item endpoints beyond design**: The implementation added `POST /orkestone/v1/menu/items` and `DELETE /orkestone/v1/menu/items/{idx}` which were NOT in the original design. These were added to support individual item operations rather than requiring full replacement via `PUT`. This is a practical improvement but should be documented as a design deviation.

2. **JS-injected menu editor card**: The menu editor UI is injected via `buildCard()` pattern in JavaScript rather than a PHP-rendered container. This follows existing Command Center patterns but deviates from the design which implied a PHP-rendered `<div id="vbb-cc-menu">` container.

3. **Separate save endpoint for menu**: Menu items use `CC.saveMenu()` to `PUT /menu` (separate from general settings `POST /vertical-settings`). This is because the menu endpoint also triggers `wp_navigation` sync server-side. The design did not anticipate this separation.

4. **Flat spec structure**: The spec was stored as a single `spec.md` at the change root rather than as delta specs in a `specs/{domain}/` subdirectory. This is simpler for a self-contained change but means the spec is not organized by domain for reuse across changes.

5. **Static cache not implemented**: The design mentioned `wp_cache_set`/`wp_cache_get` for the `the_content` filter but this was listed as a future performance optimization. The current O(n*m) `str_replace` approach is acceptable for typical page sizes.

## Intentional Archive Notes

- No delta spec merge was needed — the spec.md is a self-contained change-level spec.
- No critical or blocking issues were found in verification.
- No stale unchecked tasks — all 21 tasks are marked complete in the persisted tasks.md.
- The archive was performed cleanly with full artifact set.

---

*Archived 2026-07-08 by sdd-archive agent. Audit trail complete.*
