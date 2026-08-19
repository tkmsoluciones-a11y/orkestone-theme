# EVIDENCE — Admin JSON Import Panel

## Version

- Theme: `vertical-block-base`
- Version: `0.2.0`
- Date: `2026-05-19`

## Objective

Add a real WordPress admin panel for importing and activating future vertical JSON files without manually editing the theme files.

## Files added

- `inc/vertical-storage.php`
- `inc/vertical-importer.php`
- `inc/admin-verticals.php`
- `info/EVIDENCE_ADMIN_JSON_IMPORT.md`

## Files modified

- `functions.php`
- `inc/vertical-loader.php`
- `style.css`
- `README.md`

## Implemented capabilities

1. Admin page under `Appearance > Verticales JSON`.
2. JSON upload and validation.
3. Storage of imported verticals in `wp-content/uploads/vertical-block-base/verticals/`.
4. Active vertical persisted in WordPress option `vbb_active_vertical`.
5. Loader priority: imported verticals first, bundled theme verticals second.
6. Page generation from `pages[]`.
7. Gutenberg navigation generation from `navigation.primary`.
8. Front page assignment from `importOptions.homepageKey`.
9. Media side-loading from graphics/media arrays.
10. Extra WP-CLI commands for automation.

## Safety decisions

- JSON is parsed as data only; it is never executed as PHP or JavaScript.
- The importer validates minimum required fields before saving.
- The vertical key is sanitized with `sanitize_key()` before file writing.
- Imported JSON files are stored in uploads to avoid requiring theme folder write access.
- Existing page slugs are skipped to avoid duplicate content.
- Media importing is limited per execution to reduce timeout risk.

## Known limitations

- Media import depends on the WordPress server being able to access the source URLs.
- Navigation generation creates/updates a `wp_navigation` entity; the user may still need to select it in the Site Editor depending on the active header block instance.
- This is not yet a full visual vertical builder; it is an import/activation/admin operations panel.

## Current task

Admin JSON import panel created.

## Next task

Test in a real WordPress installation and record activation/admin screenshots or error logs.
