# Proposal: Builder Completion — Full No-Code Website Builder

## Intent

Complete the OrkestOne theme's Command Center into a **full no-code website builder** by closing the gaps between the Block Baker, the REST API, and the admin UI. Users should be able to create, edit, and delete pages and navigation entirely from the Command Center — without touching a single JSON config file or leaving the WordPress admin.

## Scope

### In Scope

| Capability | Description |
|------------|-------------|
| **Missing Function** | Write `vbb_bake_page_content($page_id)` — called but nonexistent, blocks all page regeneration |
| **Bugfix** | Fix `vbb_bake_process()` foreach bug (undeclared `$step_title`, `$description`) |
| **Placeholder System** | Convert 11 baker functions from static output to `{{placeholder}}` syntax + expand `vbb_pro_replace_dynamic_content()` |
| **Repeatable Items** | Services, testimonials, FAQ items, pricing plans, team members — bake from merged settings directly (no placeholders for variable-length arrays) |
| **Page CRUD API** | `POST/DELETE /orkestone/v1/pages`, expand `GET /orkestone/v1/pages`, add `POST /pages/{id}/regenerate` |
| **Menu Management** | Pro Settings–native `menuItems` schema with REST endpoints (`GET/PUT /orkestone/v1/menu`, `POST/DELETE /orkestone/v1/menu/items/{idx}`) |
| **Command Center UI** | Page selector element in DOM, menu editor card with sortable items, expand `renderBlockSettings()` for all block types |
| **Backfill** | Regenerate all pages with placeholders on activation/update to prevent raw `{{vbb_*}}` output on existing sites |

### Out of Scope

- Multi-vertical coexistence editing (single-active policy remains)
- CPT/ACF field creation from the Command Center
- Drag-and-drop visual page builder (reorder sections is a future enhancement)
- Revision history for settings changes inside the Command Center
- Frontend rendering changes — the filter-based `vbb_pro_replace_dynamic_content()` stays

## Approach — Phased Delivery

The work is split into 5 force-chained phases. Each phase produces independently reviewable changes. The chain is **strict** — Phase 1 is a hard prerequisite for all others.

```
Phase 1 (Critical Path)
  ├── Fix vbb_bake_process() bug
  ├── Write vbb_bake_page_content()
  └── Add pageSelector DOM element in Command Center
        │
        ▼
Phase 2 (Block Baker)
  ├── Convert 11 baker functions to placeholders
  ├── Expand vbb_pro_replace_dynamic_content() mapping
  └── Repeatable items: bake from merged settings
        │
        ▼
Phase 3 (Page API)
  ├── POST /orkestone/v1/pages — create
  ├── DELETE /orkestone/v1/pages/{id} — trash
  ├── Expand GET /orkestone/v1/pages — slugs + sections
  └── POST /orkestone/v1/pages/{id}/regenerate
        │
        ▼
Phase 4 (Menu Management)
  ├── menuItems schema in vbb_pro_settings
  ├── Extend vbb_pro_sanitize_settings()
  ├── REST endpoints (GET/PUT menu, POST/DELETE items)
  └── Command Center menu editor UI
        │
        ▼
Phase 5 (Backfill)
  └── Regenerate all pages on theme activation/update
```

## Key Deliverables

| Deliverable | Phase | Primary Files |
|-------------|-------|---------------|
| `vbb_bake_page_content()` function | 1 | `inc/pro-settings.php` |
| `vbb_bake_process()` fix | 1 | `inc/block-baker.php` |
| `pageSelector` DOM element | 1 | `inc/pro-admin.php`, `assets/js/admin-pro.js` |
| 11 baker functions converted to placeholders | 2 | `inc/block-baker.php` |
| Expanded `vbb_pro_replace_dynamic_content()` | 2 | `inc/pro-settings.php` |
| Page CRUD REST endpoints (4 endpoints) | 3 | `inc/pro-rest-api.php` |
| Menu `menuItems` settings schema + sanitization | 4 | `inc/pro-settings.php` |
| Menu REST endpoints (4 endpoints) | 4 | `inc/pro-rest-api.php` |
| Menu editor UI in Command Center | 4 | `assets/js/admin-pro.js` |
| Activation/update backfill routine | 5 | `inc/pro-settings.php` |

## Risks & Mitigations

| Risk | Phase | Likelihood | Mitigation |
|------|-------|------------|------------|
| **Missing function `vbb_bake_page_content()`** blocks all page regeneration | 1 | Certain | Written in Phase 1 — highest priority. Until then no page CRUD or regeneration works |
| **`vbb_bake_process()` structural bug** causes WSOD if loop data is unexpected | 1 | High | Rewrite the function entirely — not a minimal patch. Include `isset()` guards |
| **Backward compatibility**: existing pages show raw `{{vbb_*}}` placeholders after conversion | 2, 5 | High | Phase 5 backfill runs `vbb_pro_regenerate_all_pages()` automatically. Add admin notice if placeholders detected |
| **Performance**: JSON decode overhead for repeatable items on every page load | 2 | Medium | Option C mitigates this — repeatable items are baked from settings directly, no placeholder/JSON decode needed. The filter runs only simple `str_replace` |
| **Repeatable items complexity**: variable-length arrays don't map cleanly to placeholders | 2 | Medium | Use Option C: bake from merged settings directly. The baker function receives `$data` which already contains the merged global + page settings |
| **Menu data model conflict** with existing `wp_navigation` posts | 4 | Medium | Dual-write: save to `vbb_pro_settings.menuItems` and sync to `wp_navigation` post. The existing FSE nav remains functional as a render target |
| **Command Center UI regressions** when adding pageSelector + menu editor | 1, 4 | Low | Test all existing editing flows (global settings, per-page overrides) after each UI change. Keep JS changes scoped to new features |

## Success Criteria

- [ ] `vbb_bake_page_content($page_id)` exists, takes a page ID, regenerates baked block content from vertical config + merged settings
- [ ] `vbb_bake_process()` produces valid output for all step counts (0, 1, 5 items) without PHP notices
- [ ] All 11 baker functions output `{{placeholder}}` tokens instead of static text for editable fields
- [ ] `vbb_pro_replace_dynamic_content()` replaces every known `{{vbb_*}}` placeholder with the correct merged setting value
- [ ] `POST /orkestone/v1/pages` creates a WP page, initializes per-page settings, returns 201
- [ ] `DELETE /orkestone/v1/pages/{id}` trashes the page and removes its settings
- [ ] `GET /orkestone/v1/pages` returns slugs, section list, and `hasSettings` flag for every builder page
- [ ] `POST /orkestone/v1/pages/{id}/regenerate` re-bakes the page content and returns success
- [ ] `GET /orkestone/v1/menu` returns merged global+page menu items
- [ ] `PUT /orkestone/v1/menu` replaces all menu items and syncs to `wp_navigation` post
- [ ] Command Center renders a page selector dropdown that switches context between pages
- [ ] Command Center renders a menu editor card with sortable items, add/delete, and submenu nesting
- [ ] Theme activation/update triggers full page regeneration — no raw placeholders visible on the frontend
