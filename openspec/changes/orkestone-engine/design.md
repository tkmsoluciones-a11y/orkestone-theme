# Design: Orkestone Engine

## Technical Approach

Replace the current `<!-- wp:pattern -->` runtime rendering with a **static baking pipeline** that generates real Gutenberg block markup during import. The pivot point is `vbb_build_page_content_from_blueprint()` in `page-blueprint.php` — currently emits pattern slugs; we swap its `section → pattern` map for a `section → block markup` pipeline via new `inc/block-baker.php`. The orchestrator `vbb_import_vertical_full()` chains Reset → Media → Baking → Menu → WooCommerce → Report.

## Architecture Decisions

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Per-section block generators | More code, full control | ✅ Chosen — matches Gutenberg block spec |
| Reuse pattern PHP via ob_* | Coupling to runtime PHP | ❌ Baking must be static, no runtime `vbb_get_vertical_value()` |
| Section data source | Two locations (`pages[].hero` vs `sections.hero`) | ✅ Check page-level first, then `sections.{type}`, then defaults |
| Placeholder strategy | SVG inline vs external file | ✅ 1×1 transparent SVG (`data:image/svg+xml`) — zero filesystem deps |

## Data Flow

```
JSON pages[].sections[]  ──→  block-baker.php  ──→  baked block markup
         │                          │
         └── sections{} data ───────┘
                                ↓
                    wp_insert_post(post_content)
                                ↓
              pages[] + wp_navigation + options + report[]
```

**Full pipeline** (`vbb_import_vertical_full()`):
1. Read new vertical key → compare with `active-vertical.json`
2. **If different**: invoke `vbb_reset_vertical_pages( old_key )` — trash all posts with `_vbb_vertical = old_key`
3. Update `config/active-vertical.json` with new key
4. `vbb_import_vertical_media(0)` — sideload all images, placeholders on failure
5. For each page: `vbb_build_page_content_from_baked( $page )` — calls `vbb_bake_section()` per section
6. Create/update `OrkestOne Theme` `wp_navigation` from `navigation.primary` with resolved page IDs
7. If `woocommerce.mode in ['catalog','vidriera']`: configure catalog, set shop page
8. Assemble report → return

## Function Signatures

```php
// inc/block-baker.php — NEW
function vbb_bake_section( string $type, array $page, array $sections ): string
function vbb_bake_hero( array $data ): string
function vbb_bake_hero_centered( array $data ): string
function vbb_bake_services_grid( array $data ): string
function vbb_bake_benefits( array $data ): string
function vbb_bake_process( array $data ): string
function vbb_bake_testimonials( array $data ): string
function vbb_bake_faq( array $data ): string
function vbb_bake_contact_section( array $data ): string
function vbb_bake_cta_final( array $data ): string

// inc/reset-orchestrator.php — NEW
function vbb_reset_vertical_pages( string $vertical_key ): array
function vbb_update_active_vertical_config( string $new_key ): array|WP_Error

// inc/vertical-importer.php — MODIFIED
function vbb_import_vertical_full( string $vertical_key ): array
function vbb_setup_woocommerce_catalog(): array
function vbb_get_import_report(): array

// inc/page-blueprint.php — MODIFIED
function vbb_build_page_content_from_baked( array $page ): string
function vbb_generate_page_id_map(): array   // [slug => ID] after insert
```

## Key Implementation Details

**Block Baker**: Each `vbb_bake_*()` function returns a string of Gutenberg comment-delimited HTML. Data comes from `$page` (page-level section data like `$page['hero']`) merged with `$sections[$type]`. Unknown types emit `<!-- wp:paragraph --><p>Unknown: {type}</p><!-- /wp:paragraph -->`.

**Serialization**: Values are injected via `wp_kses_post()` for HTML fields, `esc_html()` for text, `esc_url()` for URLs. Image `wp:image` blocks reference placeholder URL during baking (later resolved during media import). Each block gets proper `<!-- wp:group -->...<!-- /wp:group -->` wrapping with `vbb-section` class.

**Reset**: Query posts by `_vbb_vertical` meta key matching the old slug. `wp_trash_post()` for pages + `wp_navigation`. Never `wp_delete_post()`. `vbb_update_active_vertical_config()` writes new JSON to `config/active-vertical.json`.

**Menu ID Resolution**: After page creation, store `[slug => ID]` map. When building `wp_navigation` posts, iterate `navigation.primary[]`, resolve `url_slug` (if present) to page ID via the map, build `<!-- wp:navigation-link -->` with `kind:"post-type"` using that ID. Items without `url_slug` use `kind:"custom"` with literal URL.

**Media Pipeline**: `vbb_import_vertical_media()` stays as-is. On failure, create attachment from inline SVG: `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1' height='1'/%3E`. Set `_vbb_broken=1` and `_vbb_broken_url=original_url`. Report accumulates imports, skips, errors via the `$report` array.

**WooCommerce**: Check `vertical.woocommerce.mode`. If catalog/vidriera and `class_exists('WooCommerce')`: `update_option('woocommerce_catalog_orders','disabled')`, set shop page option, add filter `woocommerce_is_purchasable__return_false`. If WC absent, admin notice — pipeline continues.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/block-baker.php` | Create | 10 section → block markup builders |
| `inc/reset-orchestrator.php` | Create | Trash + config update + WooCommerce setup |
| `inc/page-blueprint.php` | Modify | Replace `vbb_build_page_content_from_blueprint()` with baked version |
| `inc/vertical-importer.php` | Modify | Add orchestrator `vbb_import_vertical_full()`, report helpers |
| `inc/helpers.php` | Modify | Add `vbb_svg_placeholder()` generator |
| `inc/admin-verticals.php` | Modify | Import all with full pipeline action |
| `functions.php` | Modify | Add `inc/block-baker.php`, `inc/reset-orchestrator.php` to autoload |
| `config/schemas/vertical.schema.json` | Modify | Add `woocommerce` section definition |

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Unit | Each `vbb_bake_*()` function | Assert valid block markup output for known data, fallback for unknown type |
| Unit | `vbb_bake_section()` with missing data | Assert fallback paragraph emitted |
| Integration | Full import cycle | Backup DB → run `vbb_import_vertical_full()` → assert pages created with block content, menu exists, report correct |
| Integration | WooCommerce catalog | With WC active: assert options set; without WC: assert notice without fatal |
| Manual | Reset | Verify trashed pages recoverable, new import clean |

## Complexity & Risks

- **Bottleneck**: Media sideloading (HTTP per image). Mitigation: placeholder for failures, batch limit param.
- **Bottleneck**: O(n·s) page baking — safe for typical 5-20 pages. Chunk at 50+.
- **Data source ambiguity**: Section data at both `pages[].{type}` and `sections.{type}`. Baker checks page-level first, then top-level `sections{}`.
- **Navigation resolution**: Menu items that reference pages by `url_slug` must resolve AFTER page creation. Two-pass: create pages (storing IDs in map), then create nav using the map.

## Migration

No migration required. Old `<!-- wp:pattern -->` pages remain unchanged until reset/overwrite. New imports produce baked content. The existing `vbb_build_page_content_from_blueprint()` is preserved and renamed — old callers gracefully degrade.

## Open Questions

- [ ] Confirm `config/active-vertical.json` rewrite strategy: direct file write vs option-based (currently option `vbb_active_vertical` takes precedence in `vbb_get_active_vertical_settings()`)
- [ ] Should the `OrkestOne Theme` menu name be configurable?
- [ ] SVG placeholder: confirm 1×1 transparent renders acceptably in blocks
