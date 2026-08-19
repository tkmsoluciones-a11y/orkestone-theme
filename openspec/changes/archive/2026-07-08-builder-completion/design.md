# Design: Builder Completion — Full No-Code Website Builder

## Technical Approach

Five-phase implementation closing the gap between the Block Baker, REST API, and Command Center UI. Data flows: **Command Center UI → REST API → Pro Settings → Block Baker → `wp_insert_post` / `the_content` filter → WP Post Content / Frontend**.

The baker pipeline: `vbb_bake_page_content($page_id)` → loads page settings → merges global+per-page → identifies sections → `vbb_bake_section()` dispatches to baker function → baker outputs `{{vbb_*}}` placeholders → stored in `post_content` → resolved at render via `vbb_pro_replace_dynamic_content()`.

Repeatable items (services, testimonials, FAQ, process steps) are baked **directly from merged settings** — no placeholders inside repeatable loops. Single-value fields get `{{vbb_*}}` tokens. Menu data model uses **uni-directional sync**: Pro Settings → `wp_navigation` only, never reverse.

## Architecture Decisions

| Decision | Choice | Alternatives Considered | Rationale |
|----------|--------|------------------------|-----------|
| Repeatable items strategy | Option C: Bake from merged settings directly | A (placeholder per item field), B (placeholder per item block) | Repeatable items have variable count; placeholders inside loops can't map 1:1. Baking from settings produces correct N items at bake time. |
| Menu sync direction | Settings → wp_navigation only (uni-directional) | Bi-directional sync, wp_navigation as source of truth | Avoids conflicts. Settings are the Command Center's data model; wp_navigation is a render target. Reverse sync would require parsing block markup. |
| Backfill trigger | `after_switch_theme` + version check option | Cron job, manual button only | Activation hook catches theme switches; version check avoids re-baking on every page load. Admin notice for manual trigger if tokens detected. |
| Placeholder token scope | Per-section, flat namespace (`{{vbb_{section}_{field}}}`) | Hierarchical (`{{vbb.hero.title}}`), JSON-path tokens | Flat tokens are easier to str_replace, debug, and test. No nesting complexity. |
| Page selector DOM | Server-rendered `<select>` container, JS-populated via REST | Fully server-side rendered with WP_Query | Existing pattern: JS loads pages via API (`loadPages()`) then `renderPageSelector()`. Consistent with current architecture. |

## Data Flow

```
Command Center UI (admin-pro.js)
  │
  │ debouncedSave() → XHR POST
  ▼
REST API (pro-rest-api.php)
  │
  ├── /vertical-settings → vbb_pro_update_settings()
  ├── /vertical-settings/{id} → vbb_pro_update_page_settings()
  ├── /pages → vbb_rest_get/create/delete_pages()
  ├── /pages/{id}/regenerate → vbb_bake_page_content()
  └── /menu → vbb_rest_get/update_menu()
  │
  ▼
Pro Settings (pro-settings.php)
  │
  ├── vbb_pro_get_page_settings() — merges global + per-page
  ├── vbb_pro_regenerate_all_pages() — loops pages
  └── vbb_pro_replace_dynamic_content() — the_content filter
  │
  ▼
Block Baker (block-baker.php)
  │
  ├── vbb_bake_page_content()  ← NEW: entry point for single page
  ├── vbb_bake_section() → dispatches to baker function
  └── 12 baker functions → output {{vbb_*}} tokens + baked repeats
  │
  ▼
wp_posts.post_content  ──→  the_content filter  ──→  Frontend HTML
  (stored with tokens)      (tokens → setting values)   (resolved)
```

## Phase Details

### Phase 1 — Critical Path

**`vbb_bake_page_content($page_id)`** — New function in `block-baker.php`:
```
function vbb_bake_page_content( $page_id ) {
    $page_id = (int) $page_id;
    $settings = vbb_pro_get_page_settings( $page_id );
    $vertical_config = vbb_get_vertical_config();
    $sections = $vertical_config['sections'] ?? array();

    // Find this page's section list from vertical config
    $page_config = vbb_get_vertical_page_by_id( $page_id );  // NEW helper
    $section_types = $page_config['sections'] ?? array();

    // Filter enabled sections
    $section_types = vbb_pro_filter_sections( $section_types );

    $content = '';
    foreach ( $section_types as $type ) {
        $content .= vbb_bake_section( $type, $page_config, $sections ) . "\n\n";
    }

    wp_update_post( array( 'ID' => $page_id, 'post_content' => $content ) );
}
```

**`vbb_bake_process()` fix** — Rewrite the loop body (lines 351–367):
Replace broken `if ( '' !== $output )` guards with a proper `foreach` loop over `$steps`, setting `$step_title` and `$description` per iteration, wrapping each step in `<!-- wp:column -->`.

Before (broken):
```php
if ( '' !== $output ) {
    $output .= "\n\t\t" . '<!-- wp:column -->';
    // ... uses undefined $step_title, $description
}
```

After:
```php
foreach ( $steps as $step ) {
    $step_title   = isset( $step['title'] ) ? vbb_esc_text( $step['title'] ) : '';
    $description  = isset( $step['description'] ) ? vbb_esc_text( $step['description'] ) : '';
    $output .= "\n\t\t" . '<!-- wp:column -->';
    // ... rest with proper $step_title, $description
}
```

**`pageSelector` DOM** — In `vbb_pro_render_command_center()` (pro-admin.php), add container before the cards div:
```php
<div class="vbb-cc-page-selector" id="vbb-page-selector">
    <p>Loading pages…</p>
</div>
```
JS `CC.loadPages()` already calls `CC.renderPageSelector()` which targets `CC.el.pageSelector`. The `el.pageSelector` assignment in `init()` needs to be added:
```js
CC.el.pageSelector = document.getElementById('vbb-page-selector');
```

### Phase 2 — Block Baker Placeholder Mapping

| Baker Function | Placeholder Tokens | Setting Path (under `blocks.{key}`) |
|---|---|---|
| `vbb_bake_hero` (done) | `{{vbb_hero_title}}`, `{{vbb_hero_subtitle}}`, `{{vbb_hero_eyebrow}}`, `{{vbb_hero_cta_text}}`, `{{vbb_hero_cta_url}}` | `title`, `subtitle`, `eyebrow`, `primaryCta`, `primaryUrl` |
| `vbb_bake_hero_centered` | `{{vbb_hero_centered_title}}`, `{{vbb_hero_centered_tagline}}` | `title`, `tagline` |
| `vbb_bake_cta_final` | `{{vbb_cta_final_text}}`, `{{vbb_cta_final_button_text}}`, `{{vbb_cta_final_button_url}}` | `text`, `buttonText`, `buttonUrl` |
| `vbb_bake_contact_section` | `{{vbb_contact_email}}`, `{{vbb_contact_phone}}` | `email`, `phone` |
| All repeatable sections (services-grid, benefits, testimonials, faq, process, pricing, team, logo-cloud) | No placeholders inside items | Baked from merged settings directly (Option C) |

Static fields (headings for repeatable sections) use placeholders: `{{vbb_services_heading}}`, `{{vbb_benefits_heading}}`, `{{vbb_testimonials_heading}}`, `{{vbb_faq_heading}}`, `{{vbb_process_heading}}`, `{{vbb_pricing_heading}}`, `{{vbb_team_heading}}`, `{{vbb_logo_cloud_heading}}`.

**`vbb_pro_replace_dynamic_content()` expansion** — Add entries for every token above. The `$map` becomes a centralized lookup. For repeatable items (not tokens), they render correctly because they're baked from merged settings — no resolution needed.

### Phase 3 — Page API Endpoints

| Endpoint | Method | Callback | Behavior |
|---|---|---|---|
| `/orkestone/v1/pages` | POST | `vbb_rest_create_page` | `wp_insert_post` with title/slug, init `vbb_pro_page_settings[$post_id][sections]`, return 201 |
| `/orkestone/v1/pages/{id}` | DELETE | `vbb_rest_delete_page` | `wp_trash_post`, `unset(vbb_pro_page_settings[id])`, return 200 |
| `/orkestone/v1/pages` | GET | `vbb_rest_get_pages` (expand) | Add `sections` from per-page settings, `hasSettings` bool, `slug` field |
| `/orkestone/v1/pages/{id}/regenerate` | POST | `vbb_rest_regenerate_page` | Call `vbb_bake_page_content(id)`, return 200 |

Settings init on page create: `vbb_pro_page_settings[$post_id] = ['sections' => $sections]`. Sections array stored in per-page settings, separate from global block toggles.

### Phase 4 — Menu Data Model

**Settings schema** — new top-level key `menuItems` in global settings:
```json
{
  "menuItems": [
    {
      "id": "menu_1",
      "label": "Home",
      "type": "page",
      "targetPageId": 2,
      "url": "",
      "children": [
        {
          "id": "menu_1_1",
          "label": "Subpage",
          "type": "custom",
          "url": "/subpage"
        }
      ]
    },
    {
      "id": "menu_2",
      "label": "External",
      "type": "custom",
      "url": "https://example.com"
    }
  ]
}
```

**Sync logic** — `vbb_pro_sync_menu_to_wp_navigation()`:
1. Read `menuItems` from global settings
2. Build block markup: `<!-- wp:navigation-item -->` for each item
3. Find or create `wp_navigation` post titled "OrkestOne Primary Navigation"
4. `wp_update_post()` with navigation block content

**Conflict resolution**: Settings are always source of truth. The sync runs on every `PUT /orkestone/v1/menu`. Manual edits to `wp_navigation` via Site Editor are **overwritten** on next menu save. Admin notice warns: "Navigation changes made in Site Editor will be overwritten by Command Center."

**Endpoints**:
- `GET /orkestone/v1/menu` — returns `{menuItems: [...]}` merged from global settings
- `PUT /orkestone/v1/menu` — accepts `{menuItems: [...]}`, updates global settings, triggers sync to `wp_navigation`, returns `200 {success}`

**Sanitization**: Each item label sanitized with `sanitize_text_field`, type validated against `['page', 'custom']`, URL with `esc_url_raw`, targetPageId with `absint`. Recursive sanitization for children.

### Phase 5 — Backfill

**Activation hook** in `pro-settings.php`:
```php
function vbb_pro_on_theme_activation() {
    $version = get_option( 'vbb_baker_version', '0' );
    if ( version_compare( $version, '1.0.0', '<' ) ) {
        vbb_pro_regenerate_all_pages();
        update_option( 'vbb_baker_version', '1.0.0' );
    }
}
add_action( 'after_switch_theme', 'vbb_pro_on_theme_activation' );
```

**`vbb_pro_regenerate_all_pages()`** — already exists but currently calls `vbb_bake_page_content()` which doesn't exist yet. Once Phase 1 creates it, this works. The function loops all published pages, calls `vbb_bake_page_content()`, returns count.

**Performance strategy**: For sites with 50+ pages, wrap regeneration in a `wp_die()`-safe loop or use `wp_schedule_single_event()` to batch. For MVP, synchronous loop is acceptable (typical vertical has 3–10 pages). Add `set_time_limit(300)` at the start.

**Admin notice**: After activation, check if any published page content contains `{{vbb_` tokens. If found, show admin notice with "Regenerate pages" button linking to Command Center.

## Conflict Resolution — Menu Dual-Write

- **Direction**: Settings → `wp_navigation`. Always.
- **Conflict logic**: `PUT /orkestone/v1/menu` writes to settings FIRST, then syncs to `wp_navigation`. If `wp_navigation` has been manually edited, the sync overwrites it.
- **Detection**: Compare `post_modified` timestamps. If `wp_navigation` was modified after last menu sync (stored in `vbb_last_menu_sync` option), show admin warning but proceed with overwrite.
- **Rollback**: `vbb_pro_restore_menu_from_settings()` reads settings and re-syncs. Manual `wp_navigation` changes can be recovered if user exported settings before editing.

## Performance

| Risk | Strategy |
|---|---|
| Backfill on activation blocks page load | Run in `after_switch_theme` hook (admin-side, not frontend). Add `set_time_limit(300)`. For 10+ pages, consider async. |
| `vbb_pro_replace_dynamic_content()` runs on every `the_content` call | Static cache per page ID using `wp_cache_set`. Invalidate on settings save. |
| REST API payload size with 20+ menu items | Acceptable — menuItems is typically <50 items. If needed later, paginate. |
| Placeholder replacement on large content | Single `str_replace()` pass per token — O(n*m) where n=tokens, m=content length. For 20 tokens on 50KB content, negligible. |

## Rolling Back Each Phase

| Phase | Rollback |
|---|---|
| **1** (Critical) | Revert `block-baker.php` changes. Old `vbb_bake_process()` behavior is broken anyway — no regression. |
| **2** (Bakers) | Revert token insertion in baker functions. Run `vbb_pro_regenerate_all_pages()` to restore pre-token content. Old frontend will show placeholders — roll forward by running backfill. |
| **3** (Page API) | Remove new REST routes from `vbb_register_command_center_routes()`. Existing page data is unaffected. |
| **4** (Menu) | Revert settings schema change (remove `menuItems` sanitization). Restore `wp_navigation` from backup (hook `wp_insert_post` to save a copy before sync). |
| **5** (Backfill) | Remove activation hook. Option `vbb_baker_version` persists but is harmless. |

## File Changes

| File | Action | Phases |
|------|--------|--------|
| `inc/block-baker.php` | Modify | 1, 2: Add `vbb_bake_page_content()`, fix `vbb_bake_process()`, insert tokens in all baker functions |
| `inc/pro-settings.php` | Modify | 1, 2, 4, 5: Expand `vbb_pro_replace_dynamic_content()`, add menuItems schema/sanitization, add activation hook |
| `inc/pro-rest-api.php` | Modify | 3, 4: Add POST/DELETE page endpoints, GET/PUT menu endpoints, expand GET pages |
| `inc/pro-admin.php` | Modify | 1: Add `#vbb-page-selector` DOM element |
| `assets/js/admin-pro.js` | Modify | 1: Wire `CC.el.pageSelector` in `init()`, add page creation button |
| `inc/page-blueprint.php` | Modify | 1: Add `vbb_get_vertical_page_by_id()` helper |

## Interfaces

```php
// New functions
function vbb_bake_page_content( int $page_id ): void;
function vbb_get_vertical_page_by_id( int $page_id ): ?array;
function vbb_pro_sync_menu_to_wp_navigation( array $menu_items ): void;
function vbb_pro_on_theme_activation(): void;

// Expanded map
// vbb_pro_replace_dynamic_content() $map includes ALL tokens from Phase 2

// New option
const VBB_BAKER_VERSION_OPTION = 'vbb_baker_version'; // string semver
```

## Testing Strategy

| Layer | What | How |
|---|---|---|
| Unit | `vbb_bake_process()` with 0/1/5 steps | Run `php inc/test-block-baker.php` (update assertions) |
| Unit | Every baker function outputs `{{vbb_*}}` tokens | Extend `test-block-baker.php` with token assertions |
| Unit | `vbb_pro_replace_dynamic_content()` resolves all tokens | New standalone test or WP test with mock settings |
| Integration | POST/DELETE page endpoints | WP REST API test with `manage_options` capability |
| Integration | Menu sync creates `wp_navigation` post | Assert `wp_navigation` post content matches menuItems |
| E2E | Full flow: create page → edit → frontend renders | Manual: Command Center → page selector → edit → preview |
| Regression | Existing settings editing still produces same API payloads | Compare serialized request body before/after changes |
