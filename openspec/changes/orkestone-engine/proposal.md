# Proposal: Site-in-a-Box Engine

## Intent

Transform the current JSON-driven dynamic theme into a **baking instantiation engine** that creates fully editable WordPress sites. Replace runtime pattern rendering (`<!-- wp:pattern -->` + PHP injection) with real Gutenberg block markup in `post_content`, making all content natively editable in the Block Editor.

## Scope

### In Scope
- Baking engine: JSON → real Gutenberg block markup → `post_content` during import
- Reset capability: wipe vertical pages/menus/media, re-import a new vertical
- OrkestOne Menu: auto-create main navigation named `OrkestOne Theme`
- Media sideloading: download images to WP Media Library, fallback placeholders, broken-link report
- WooCommerce detection: configure Catalog/Showcase mode for Store verticals
- One Vertical Policy: enforce single active vertical, purge stale content on switch

### Out of Scope
- CPT/ACF field creation (future companion plugin)
- Dynamic content sync after baking (one-shot import only)
- Multi-vertical coexistence (policy is single-active)
- Migration of existing VBB sites to the new engine

## Capabilities

### New Capabilities
- `site-instantiation`: baking engine — JSON to block markup import pipeline
- `vertical-reset`: destructive purge + re-import workflow
- `orkestone-menu`: named navigation auto-creation
- `media-import`: sideload with fallback and report generation
- `woocommerce-setup`: store mode detection + catalog configuration

### Modified Capabilities
- `vertical-loader` (**page-blueprint**): replace `<!-- wp:pattern -->` with baked block markup
- `vertical-importer` (**admin-verticals**): add reset/import-all flow, OrkestOne naming

## Approach

1. **Baking Pipeline**: Parse `pages[].sections` and `sections{}` from vertical JSON → generate real `<!-- wp:group -->`, `<!-- wp:heading -->`, `<!-- wp:image -->` etc. with content inline → `wp_insert_post` with baked `post_content`.
2. **Reset Flow**: Trash all pages + `wp_navigation` posts + detached attachments → re-run baking.
3. **Media**: `media_sideload_image()` per URL, track `_vbb_source_url` meta, substitute SVG placeholder on failure, emit report JSON.
4. **WooCommerce**: Detect `vertical.woocommerce.mode === 'catalog'` → `update_option('woocommerce_catalog_orders', 'disabled')` + set shop page.
5. **Navigation**: Generate `wp_navigation` post titled `OrkestOne Theme` with `<!-- wp:navigation-link -->` blocks from `navigation.primary`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/vertical-importer.php` | Modified | Add reset, baking, report, WooCommerce logic |
| `inc/page-blueprint.php` | Modified | Replace pattern refs with baked block builders |
| `inc/admin-verticals.php` | Modified | Add reset UI, combined import-all with media |
| `inc/vertical-loader.php` | Modified | One-vertical enforcement guard |
| New: `inc/block-baker.php` | Added | Section → block markup converters |
| New: `inc/media-report.php` | Added | Broken-link reporter |
| `config/schemas/vertical.schema.json` | Modified | Add `woocommerce` section to schema |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Data loss during reset | Medium | Confirm dialog, trash vs delete, `_vbb_backup` snapshot meta |
| Media download failures (timeout/404) | High | SVG fallback, async queue, retry with backoff |
| WooCommerce plugin missing | Medium | Graceful skip, admin notice with activation link |

## Rollback Plan

Reset writes pages to trash (not permanently deleted) for 30-day recovery. `vbb_pro_reset_to_vertical()` restores Pro Elite settings. Navigation and media are permanently replaced — export via WP Tools before reset.

## Dependencies

- WordPress `media_sideload_image()` (core)
- WooCommerce (optional — required only for Store verticals)

## Success Criteria

- [ ] Imported pages display baked block markup in Block Editor, not `<!-- wp:pattern -->`
- [ ] Reset creates zero duplicate slugs when re-importing the same vertical
- [ ] `OrkestOne Theme` navigation post exists with correct links after import
- [ ] Broken media URLs produce placeholder images and appear in the final report
- [ ] WooCommerce Store vertical sets Catalog mode without errors
- [ ] Switching verticals purges previous pages and imports new ones cleanly
