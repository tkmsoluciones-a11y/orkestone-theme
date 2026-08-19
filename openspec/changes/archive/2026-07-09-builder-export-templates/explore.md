# Exploration: Template Management & Export

## Executive Summary

The OrkestOne theme has a functional but incomplete export system — global settings (`vbb_pro_settings`) export works via `admin-post`, but per-page overrides (`vbb_pro_page_settings`) and page section assignments are excluded. The Block Baker pipeline is entirely style-fixed: each baker function emits a single, hardcoded layout. This exploration identifies the data model extensions, pipeline changes, and new endpoints needed to support full-site JSON export and per-block layout style variants (`A | B | C`).

---

## Current State

### Data Storage (3 WordPress Options)

| Option | Key | Purpose |
|--------|-----|---------|
| `vbb_pro_settings` | Global | Full merged settings object (~40 keys: palettes, typography, layout, blocks[], buttons, menuItems, etc.) |
| `vbb_pro_page_settings` | `{page_id: {...overrides}}` | Per-page delta overrides keyed by WordPress page ID. Each entry is a partial settings object that gets deep-merged with global defaults. |
| `vbb_pro_saved_profiles` | `{profile_key: {...}}` | Named full-settings snapshots stored for reuse. |

### Export Flow (Current)

```
vbb_pro_export_settings()
  → GET /admin-post.php?action=vbb_pro_export_settings
  → Returns: { exportedAt, theme, profileType, schemaVersion: "0.3.2", settings: vbb_pro_get_settings() }
  → Security: manage_options + nonce
```

**Gap**: Only exports global settings. No per-page overrides, no section list, no page structure. The downloaded JSON does NOT match the vertical JSON schema, so it cannot be re-imported as a vertical.

### Baking Pipeline (Current)

```
vbb_bake_page_content($page_id)
  → vbb_pro_get_page_settings($page_id) → merged settings
  → vbb_get_vertical_config() → vertical JSON sections
  → vbb_get_vertical_page_by_id() → page config with sections[]
  → vbb_pro_filter_sections() → enabled only
  → foreach section: vbb_bake_section($type, $page_config, $sections)
    → vbb_get_baker_map() → { 'hero': 'vbb_bake_hero', ... }
    → call_user_func($baker, $data)
```

Each baker function (e.g., `vbb_bake_hero($data)`) emits **one fixed HTML structure**. The `$data` array carries content fields (title, subtitle, CTA) but there is no `style` field consumed by the baker.

### Command Center Data Flow

```
JS: loadSettings(pageId?) → GET /vertical-settings[/:pageId]
     → returns merged settings
     → renderCards() → buildCard('Brand & Header', ..., renderHeaderSettings(s))
     → renderBlockSettings(key, block) → per-block text fields + per-block color pickers
     → onFieldChange(path, value) → debouncedSave() → POST /vertical-settings[/:pageId]
     → postMessage('vbb:css-vars', styleTag) for preview
```

---

## Technical Findings

### 1. Export Mechanism

**Current capabilities**: `vbb_pro_export_settings()` at `inc/pro-admin.php:125-139` already generates a downloadable JSON with a schema envelope. It serializes `vbb_pro_get_settings()` only.

**Missing data for a "full site export"**:
- `vbb_pro_page_settings` — per-page delta overrides (block toggles, content strings, per-block colors)
- Page structure from `vbb_pro_page_settings` section lists (each entry can have a `sections` array)
- Active profile key (`vbb_pro_active_profile`)
- Schema versioning to allow forward-compatible imports

**Vertical JSON schema vs Export schema**: The vertical JSON (`config/verticals/*.json`) has `{ brand, navigation, pages[], sections, ... }`. The current export has `{ settings: vbb_pro_get_settings() }`. These are structurally different. A full export could produce either:
- A **settings export** (suitable for re-import into another site's Command Center)
- A **vertical JSON** (suitable for use as a new vertical definition)

**Recommendation**: The export should mirror the vertical JSON schema as closely as possible, with an additional `overrides` section for per-page deltas. This gives the dual benefit of being usable as a vertical definition and preserving all customizations.

### 2. Export Endpoint

Two access patterns already exist:

| Pattern | Location | Pros | Cons |
|---------|----------|------|------|
| `admin-post` action | `pro-admin.php:125-139` | Simple, PHP-only, uses `wp_die()` | No REST API — can't call from JS |
| REST route | `pro-rest-api.php` | Accessible from Command Center JS | Not yet implemented for export |

**Recommended approach**: Add a REST route `GET /orkestone/v1/export` that:
- Returns a JSON document with both global settings AND per-page overrides
- Uses the existing `vbb_rest_command_center_permission()` callback (manage_options)
- Can be triggered from the Command Center UI via an "Export" button
- Also keep the `admin-post` handler for direct browser downloads

**Security considerations**:
- Current `admin-post` uses `check_admin_referer()` + `current_user_can('manage_options')` — solid
- REST endpoint should use same permission callback as other routes
- File download from REST requires blob handling on JS side (createObjectURL → `<a>` click)
- No sensitive data concerns (this is site configuration, not user data)
- Large JSON: profile exports are typically <50KB even with per-page settings. No pagination needed.

### 3. Layout Styles Architecture

**The problem**: Every baker function emits one fixed layout. To support style variants without duplicating every baker, we need a variant dispatch pattern.

**Three approaches**:

| Approach | Description | Pros | Cons | Effort |
|----------|-------------|------|------|--------|
| **A. Dispatch with shared helpers** | Add `style` field to block settings. Modify each baker to switch on `$data['style']` and call smaller shared helper functions for common sub-patterns. | Minimal new code. No file duplication. Keeps all variant logic co-located. | Baker functions grow larger. Need careful parameter contracts for helpers. | **Medium** |
| **B. Variant functions** | Create `vbb_bake_hero_a()`, `vbb_bake_hero_b()`, etc. Add variant sub-map in `vbb_get_baker_map()`. | Clear separation. Easy to test each variant in isolation. | Code duplication. Many new files/functions. Naming convention must be strict. | **Medium-High** |
| **C. Template files** | Move baker markup to `.html`/`.php` template files in a `templates/` directory. Baker functions become thin renderers. | Most scalable. Designers can edit templates. Cleanest separation of concerns. | Major refactor. Every existing baker must be rewritten. Over-engineered for 2-3 variants per section. | **High** |

**Recommendation: Approach A (Dispatch with shared helpers)**

The baker functions average ~60 lines of clean PHP concatenation. Adding a `style` switch inside each baker that calls shared helpers (e.g., `vbb_bake_hero_header($style)`, `vbb_render_cta_block($text, $url, $style)`) keeps the code manageable and avoids duplication of common patterns (buttons, headings, columns).

Concrete plan for the baker map:
```php
// Current
'hero' => 'vbb_bake_hero'

// Future — unchanged! Style dispatch happens inside the baker
function vbb_bake_hero($data) {
    $style = $data['style'] ?? 'A';
    if ($style === 'A') {
        // image-left layout (current)
    } elseif ($style === 'B') {
        // centered with background pattern
    } elseif ($style === 'C') {
        // full-bleed with overlay
    }
}
```

**Data model extension**: Each block's object in settings gets an optional `style` field:
```json
{
  "blocks": {
    "hero": {
      "enabled": true,
      "style": "A",
      "colors": { ... }
    }
  }
}
```

Default value: `'A'` (current layout for backward compatibility).

Page-level overrides can set `style` per page, which takes priority over global block defaults.

### 4. UI for Styles — Command Center Integration

**Where the Style selector lives**: In the existing `renderBlockSettings()` function in `admin-pro.js` (line 741), right in the expanded block settings area. Currently it shows text fields for title/subtitle and per-block color pickers. The style selector (a dropdown or button group) should go at the top of each block's settings panel, above the content fields.

**How it triggers re-bake**:
1. User changes the style dropdown → `onFieldChange('blocks.hero.style', 'B')` fires
2. Debounced save persists to `POST /vertical-settings[/:pageId]`
3. The "Regenerate Pages" button (already present in the toolbar) triggers `POST /pages/{page_id}/regenerate`
4. OR: automatically regenerate ONLY if the change is a style switch (not just a color tweak)

**Recommended**: Add a "Re-bake on style change" auto-trigger that calls the regenerate endpoint after the style field save completes. This requires a new small REST endpoint or extending the existing `/vertical-settings` response to also return a `needsRebake: true` flag.

**UI pattern**: A button-group style selector (like the responsive presets) is better than a dropdown for visual clarity:
```
[ Style A ] [ Style B ] [ Style C ]
     ↑ active
```

---

## Proposed Improvements

### P1. Full-Site Export via REST
- Add `GET /orkestone/v1/export` route returning:
  ```json
  {
    "exportedAt": "2026-07-09 ...",
    "schemaVersion": "1.0.0",
    "theme": "orkestone",
    "settings": { ... global settings ... },
    "pageOverrides": { "123": { ... }, "456": { ... } },
    "activeProfile": "..."
  }
  ```
- Add "Export" button in Command Center toolbar (alongside "Save as Profile")
- Front-end: JS fetches, creates blob, triggers `<a>` download

### P2. Section Style Data Model
- Add `style` field to each block in `vbb_pro_default_settings()` (default `'A'`)
- Add `style` to the sanitization in `vbb_pro_sanitize_settings()`
- Extend `renderBlockSettings()` in JS to render a style selector for applicable blocks

### P3. Baker Variant Dispatching
- Modify `vbb_bake_section()` to pass the global+page merged style preference into each baker
- Modify target baker functions (start with 2-3 high-impact sections: hero, cta-final, testimonials) to support styles A/B/C via internal dispatch
- Extract shared rendering helpers: `vbb_render_section_wrapper()`, `vbb_render_cta_button()`, `vbb_render_heading_block()`
- Keep backward compatibility — missing style field defaults to `'A'`

### P4. Auto-Regenerate on Style Change
- After a style field is saved via the REST API, return `needsRebake: true` in the response
- JS checks this flag and offers a "Re-bake this page" button or auto-triggers regenerate
- Add a new lightweight `/vertical-settings/{page_id}/style` PATCH endpoint for style-only changes to avoid sending full settings on every style toggle

### P5. Import-Export Parity
- Extend the existing import handler (`vbb_pro_handle_admin_actions` for `import_json`) to accept the new expanded export format (with `pageOverrides`)
- On import, restore both global settings and per-page overrides
- If `pageOverrides` references page IDs that no longer exist, skip gracefully with a warning

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| **Large export JSON** if site has many pages with per-block colors | Usability — slow download | Limit to <1MB. Per-page settings are deltas only, not full settings — each entry is typically <2KB. A 500-page site would be ~1MB, still acceptable. |
| **WP download security** for admin-post | Unauthorized access | Already mitigated: `manage_options` + nonce. Verify the REST endpoint uses same permission. |
| **Style field ignored by baker** after save | User changes style, regenerates, but output is identical | Test coverage must assert `vbb_bake_hero(['style' => 'B'])` produces different markup than `'A'`. |
| **Backward compatibility** — existing profiles/blocks without `style` field | Fatal errors or default styles ignored | Default to `'A'` in sanitization. Old profiles merge safely. |
| **Concurrent changes** from `builder-completion` and `builder-visual-polish` changes | Merge conflicts | Both changes are archived. No active conflicts. The `builder-export-templates` change can cleanly extend the existing architecture. |
| **`vbb_pro_page_settings` page ID drift** — if page is deleted, its overrides linger in the option | Orphaned data on export/import | Filter out orphaned page IDs during export. On import, map old IDs to new page slugs. |

---

## Potential Conflicts with Other Changes

- **builder-completion** (archived): Introduced the per-page settings system (`vbb_pro_page_settings`) and the menu editor. No conflicts — this change extends both.
- **builder-visual-polish** (archived): Added per-block colors, postMessage bridge, and UI polish. The style selector will live in the same `renderBlockSettings()` area added by that change — clean extension.
- **orkestone-engine** (archived): Vertical import/reset/blueprint system. The export format should aim to be compatible with the vertical JSON schema where possible, so exports can potentially be imported as new verticals.

**No active conflicts**. All prior changes are archived.

---

## Ready for Next Phase

**Status**: ready for `sdd-propose`

The orchestrator should tell the user:
- Export mechanism is well-understood and low-risk to extend — add REST endpoint + file download
- Layout styles require a 3-part change: data model (style field) → baker dispatch (variant switch) → UI (style selector in Command Center)
- Two possible delivery strategies: single PR (export only, ~300 lines) + chained PR (styles, ~600 lines), or one combined PR (~900 lines)
- Recommend starting with Export (P1 + P5) as Phase 1, then Styles (P2 + P3 + P4) as Phase 2
