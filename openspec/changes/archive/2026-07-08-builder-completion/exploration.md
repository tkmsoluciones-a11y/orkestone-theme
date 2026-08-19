## Exploration: Builder Completion — Full No-Code Website Builder

### Current State

The OrkestOne theme has a **vertical JSON → WordPress** pipeline that generates pages and navigation from static config files. The Command Center UI (`pro-admin.php` + `admin-pro.js`) provides interactive editing for **global settings** (colors, typography, layout, block toggles) and **per-page overrides** via the `orkestone/v1` REST API.

The **Block Baker** (`block-baker.php`) generates Gutenberg block markup from vertical JSON section data. Some functions (only `vbb_bake_hero()`) already use `{{placeholder}}` syntax replaced dynamically by `vbb_pro_replace_dynamic_content()` in `pro-settings.php`. Most other baker functions still output **static text** or read from `$data` arrays without placeholder fallback.

A critical function — `vbb_bake_page_content()` — is **called but does not exist** (referenced in `vbb_pro_regenerate_all_pages()` at line 312 of `pro-settings.php`).

The navigation system uses WordPress `wp_navigation` post type created via `vbb_generate_vertical_navigation()` from `vertical-importer.php`. Menu items are defined in the vertical JSON's `navigation.primary` array. There is **no REST API** for CRUD on menu items through the Command Center.

### Affected Areas

- `inc/block-baker.php` — All 12 baker functions need placeholder conversion; `vbb_bake_process()` has a critical foreach bug (uses undeclared vars `$step_title`, `$description`)
- `inc/pro-settings.php` — `vbb_pro_replace_dynamic_content()` needs expansion to cover all section types; `vbb_pro_regenerate_all_pages()` calls nonexistent `vbb_bake_page_content()`; `vbb_pro_get_block_section_map()` needs alignment with baker function names
- `inc/pro-rest-api.php` — Missing endpoints for page CRUD and menu management; current `GET /pages` returns limited data
- `inc/vertical-importer.php` — `vbb_generate_vertical_navigation()` and `vbb_build_navigation_markup()` need to be exposed via REST
- `inc/page-blueprint.php` — `vbb_build_page_content_from_baked()` is the actual baking pipeline; page creation logic needs refactoring for per-page baking
- `assets/js/admin-pro.js` — `CC.state.pageSelector` is never assigned to a real DOM element; `renderBlockSettings()` needs expansion for all block types; missing menu editor UI
- `inc/pro-admin.php` — Command Center markup (`vbb_pro_render_command_center()`) missing pageSelector container element
- `inc/pro-css-vars.php` — `vbb_print_block_visibility_js()` uses hardcoded page index lookup; fragile when section order changes

### Block Baker: Functions to Modify

#### Group A — Already Using Placeholders (expand mapping only)
| Function | Existing Placeholders | Settings Key (blocks.*) |
|---|---|---|
| `vbb_bake_hero()` | `{{vbb_hero_title}}`, `{{vbb_hero_subtitle}}`, `{{vbb_hero_eyebrow}}`, `{{vbb_hero_cta_text}}`, `{{vbb_hero_cta_url}}` | `hero.title`, `hero.subtitle`, `hero.eyebrow`, `hero.primaryCta`, `hero.primaryUrl` |

#### Group B — Need Placeholder Conversion + expand `vbb_pro_replace_dynamic_content()`
| Function | Current Behavior | New Placeholders Needed | Settings Key |
|---|---|---|---|
| `vbb_bake_hero_centered()` | Inlines `$data['title']` and `$data['tagline']\|$data['subtitle']` directly | `{{vbb_hero_centered_title}}`, `{{vbb_hero_centered_tagline}}` | `hero.title`, `hero.subtitle` (shared with hero) |
| `vbb_bake_services_grid()` | Inlines heading; hardcoded 3-item default array | `{{vbb_services_grid_heading}}`, `{{vbb_item_1_title}}`… etc, or use JSON-based item iteration with placeholder per slot | `servicesGrid.heading` |
| `vbb_bake_benefits()` | Inlines heading; hardcoded 3-item default list | `{{vbb_benefits_heading}}`, `{{vbb_benefit_1}}`… | `benefits.heading` |
| `vbb_bake_process()` | **BUG**: uses undeclared `$step_title`, `$description` outside loop | `{{vbb_process_heading}}`, `{{vbb_step_1_title}}`, `{{vbb_step_1_desc}}`… | `process.heading` |
| `vbb_bake_testimonials()` | Inlines heading; hardcoded single-item default | `{{vbb_testimonials_heading}}`, `{{vbb_testimonial_1_quote}}`, `{{vbb_testimonial_1_author}}`… | `testimonials.heading` |
| `vbb_bake_faq()` | Inlines heading; hardcoded 2-item default | `{{vbb_faq_heading}}`, `{{vbb_faq_1_q}}`, `{{vbb_faq_1_a}}`… | `faq.heading` |
| `vbb_bake_contact_section()` | Inlines heading/email/phone directly | `{{vbb_contact_heading}}`, `{{vbb_contact_email}}`, `{{vbb_contact_phone}}` | `contact.heading`, `contact.email`, `contact.phone` |
| `vbb_bake_cta_final()` | Inlines text/buttonText/buttonUrl with static i18n fallbacks | `{{vbb_cta_text}}`, `{{vbb_cta_button_text}}`, `{{vbb_cta_url}}` | `ctaFinal.text`, `ctaFinal.buttonText`, `ctaFinal.buttonUrl` |
| `vbb_bake_logo_cloud()` | Inlines heading/subtitle; iterates logos array | Logo cloud items best represented as dynamic content from settings or preserved as vertical-json only | `logoCloud.heading` |
| `vbb_bake_pricing_tables()` | Inlines heading; iterates plans with inline data | Pricing as dynamic content or vertical-json only | `pricing.heading` |
| `vbb_bake_team_section()` | Inlines heading; iterates members with inline data | Team as vertical-json only | `team.heading` |

**Design Decision**: For repeatable items (services, benefits, steps, logos, plans, team members), the baker should emit **placeholder sequences** (e.g., `{{vbb_services_grid_items}}`) that JavaScript can hydrate into dynamic item editors, rather than hardcoding N static placeholders. The `vbb_pro_replace_dynamic_content()` should support **JSON-encoded arrays** for those fields.

### Proposed REST API Schema

#### Create Page
```
POST /orkestone/v1/pages
{
  "title": "About Us",
  "slug": "about-us",
  "sections": ["hero-centered", "benefits", "cta-final"]
}
→ 201 { "page": { "id": 42, "slug": "about-us" }, "settings": { ...initialized settings... } }
```

**Implementation**:
1. `wp_insert_post()` with `post_content` via `vbb_build_page_content_from_baked()`
2. Initialize settings in `vbb_pro_page_settings` option: `$all_page_settings[$new_id] = vbb_pro_deep_merge($global_defaults, $section_defaults)`
3. Set `_vbb_vertical` meta

#### Delete Page
```
DELETE /orkestone/v1/pages/{page_id}
→ 200 { "success": true, "page_id": 42 }
```
**Implementation**:
1. `wp_trash_post($page_id)`
2. Remove from `vbb_pro_page_settings` option: `unset($all_page_settings[$page_id])`

#### List Builder Pages
```
GET /orkestone/v1/pages
→ 200 {
  "pages": [
    { "id": 42, "title": "About Us", "slug": "about-us", "sections": [...], "hasSettings": true },
    ...
  ],
  "defaultSections": ["hero", "services-grid", ...]
}
```
**Implementation**:
- Expand existing `vbb_rest_get_pages()` to include slugs, section list from vertical JSON, and whether per-page settings exist
- Add `defaultSections` from the vertical config's first page or a configurable default template

#### Regenerate Single Page
```
POST /orkestone/v1/pages/{page_id}/regenerate
→ 200 { "success": true, "page_id": 42 }
```
- Calls `vbb_bake_page_content($page_id)` (the function that needs to be written)

### Menu Management Data Model

**Two Approaches**:

#### Approach A: Pro Settings–Native Menu Storage (Recommended)
Store menu items directly in the `vbb_pro_settings` / page-specific settings:

```php
// Under Global settings or Page settings:
'menuItems' => [
    [
        'label' => 'Inicio',
        'kind' => 'post-type',  // or 'custom'
        'id' => 42,             // if post-type
        'url' => '/',           // if custom
        'target' => '',         // _blank, etc.
        'children' => []        // nested submenu items
    ],
    ...
]
```

**REST Endpoints:**
```
GET    /orkestone/v1/menu              → current menu items (merged global + page)
PUT    /orkestone/v1/menu              → replace all menu items
POST   /orkestone/v1/menu/items        → append one item
DELETE /orkestone/v1/menu/items/{idx}  → remove by index
```

**Pro Settings Integration:**
- Store as `menuItems` array in the settings hierarchy, inheriting global→page like everything else
- The `vbb_pro_sanitize_settings()` function needs extending to validate menu items
- A page can override its own navigation via per-page `menuItems`

#### Approach B: wp_navigation CRUD Wrapper
Expose the existing `wp_navigation` post type via REST endpoints that wrap WordPress internal functions. This keeps compatibility with FSE but is harder to integrate with the hierarchical settings model.

**Recommendation**: Approach A. The entire Command Center philosophy is that settings live in `vbb_pro_settings`. Having navigation there too makes it consistent, inheritable (global→page), and editable via the same debounced-save pattern. The wp_navigation post type can remain as the **frontend render target**, synced from settings when saved.

#### Command Center UI Representation
The menu editor card in `admin-pro.js` should render:
1. A sortable list of current menu items (with drag handles)
2. Per-item fields: Label (text), Type (dropdown: Page / Custom URL), Target (dropdown: Same tab / New tab)
3. "Add Item" button that appends a blank entry
4. Delete button per item
5. Submenu nesting (indentation/children array)

### Critical Missing Function: `vbb_bake_page_content($page_id)`

This function is called by `vbb_pro_regenerate_all_pages()` but doesn't exist. It should:

1. Get the page's slug/vertical config to determine which sections it has
2. Call `vbb_build_page_content_from_baked($page, $sections_config)` for the baked content
3. `wp_update_post()` with the new content
4. Return success/failure

### Technical Risks

1. **Performance**: `vbb_pro_replace_dynamic_content()` runs on `the_content` filter for every page load. With 12+ section types each having 2-8 placeholders, str_replace cascade is still fast, but if we add JSON-encoded arrays for repeatable items, we introduce JSON decode overhead on every page load.

2. **Backward Compatibility**: Existing pages were baked with static content (no placeholders). After placeholder conversion, `vbb_pro_regenerate_all_pages()` MUST be called to re-bake all pages with placeholders. Without this, existing pages will show raw placeholders like `{{vbb_hero_title}}`.

3. **Process Section Bug**: `vbb_bake_process()` has a structural bug — the foreach loop is missing the `as $step_title => $description` binding. It references `$step_title` and `$description` as if they were extracted variables, but they're never declared. This function needs a full rewrite, not just placeholder injection.

4. **Repeatable Items Complexity**: Services, testimonials, FAQ items, pricing plans, and team members are arrays with variable length. The current placeholder approach (single values only) doesn't scale. Options:
   - **Option A**: Store repeatable items as JSON arrays in settings, emit a single `{{vbb_block_items_json}}` placeholder, decode in PHP filter → risky, mixed concerns
   - **Option B**: Emit placeholders for a fixed max count (e.g., `{{vbb_service_1_title}}` through `{{vbb_service_6_title}}`), collapse empties → limits flexibility
   - **Option C**: Bake items from settings directly in the baker function (already how it works for hero-centered, contact, etc.), no placeholder needed — the baker reads from `$data` which comes from merged settings → **Recommended**

5. **Missing `vbb_bake_page_content()` Function**: The entire page regeneration pipeline from the Command Center is blocked until this function is implemented. It's a hard dependency for both the Block Baker work and the page management API.

6. **Command Center pageSelector Element**: `CC.el.pageSelector` is used in JS but never assigned to a real DOM element. The `vbb_pro_render_command_center()` function needs an HTML container for the page selector.

### Dependencies Between Work Streams

```
Block Baker Placeholder Work
  ├── Requires: fix vbb_bake_process() bug
  ├── Requires: write vbb_bake_page_content()
  ├── Requires: expand vbb_pro_replace_dynamic_content() mapping
  └── Blocked by: none

Page Management API
  ├── Requires: write vbb_bake_page_content() (for CREATE use)
  ├── Requires: add pageSelector container to Command Center HTML
  └── Blocked by: Block Baker for the content generation

Menu Management
  ├── Requires: design settings schema for menuItems
  ├── Requires: add sanitization in vbb_pro_sanitize_settings()
  ├── Requires: new REST endpoints
  └── Blocked by: none (can work in parallel)
```

### Recommendation

1. **Phase 1 (Critical Path)**: Fix `vbb_bake_process()` bug, write `vbb_bake_page_content()`, add `pageSelector` DOM element to Command Center
2. **Phase 2**: Convert all baker functions to use placeholders + expand `vbb_pro_replace_dynamic_content()` mapping
3. **Phase 3**: Implement page CRUD REST endpoints
4. **Phase 4**: Implement menu management in Pro settings + REST endpoints + UI
5. **Phase 5**: Regenerate all pages on activation/update to ensure placeholders exist

### Ready for Proposal
Yes — the exploration is complete. The orchestrator should proceed to `sdd-design` (technical design) since the scope is now well-defined with clear dependencies and a recommended phased approach. The user should be informed that Phase 1 (critical bugfixes + missing function) is a prerequisite for all other work.
