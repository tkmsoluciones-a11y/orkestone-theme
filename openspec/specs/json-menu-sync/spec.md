# Specification: json-menu-sync — Navigation url_slug Propagation

## Purpose

Describes end-to-end propagation of an optional `url_slug` field from the Agency Hub briefing form through the JSON builder into the vertical JSON, where the theme importer resolves it to a `wp_navigation` post-type link. Closes the gap that causes `kind: 'custom'` fallback when no resolvable page reference exists.

## Requirements

### Requirement: Hub Form — Page Slug per Nav Item (REQ-JMS-1)

The Agency Hub Navigation & SEO tab (Tab 4) MUST render an optional "Page Slug" text input for each primary navigation item row. The field is always visible and prefilled from stored JSON when `url_slug` is present. Agencies leave it blank for external/custom URLs. The tab MUST continue to display SEO fields (title pattern, meta description, robots).

#### Scenario: Page slug prefilled on config load

- GIVEN stored nav data with `url_slug: "about-us"`
- WHEN the Navigation tab renders
- THEN the Page Slug input is prefilled with `about-us`

#### Scenario: Blank page slug produces no key

- GIVEN a nav item row where the Page Slug field is empty
- WHEN the form saves
- THEN the stored nav item has no `url_slug` key

### Requirement: JSON Builder — url_slug Pass-Through (REQ-JMS-2)

`JSON_Builder::build_navigation()` MUST include `url_slug` in the output object for each primary nav item when the form field is non-empty. Existing keys (`label`, `url`, `kind`, `id`) are passed through unchanged. When the field is empty, `url_slug` MUST be omitted from that item's JSON object.

#### Scenario: url_slug appears in generated JSON

- GIVEN a nav item with label "About", url "", and page slug "about-us"
- WHEN `build_navigation()` produces output
- THEN `navigation.primary[0]` contains `{"label":"About","url":"","url_slug":"about-us"}`

#### Scenario: Partial population across items

- GIVEN 3 nav items: items 1–2 have `url_slug`, item 3 has none
- WHEN the JSON builder runs
- THEN items 1 and 2 include `url_slug`; item 3 omits it

### Requirement: Vertical Schema — Optional url_slug Annotation (REQ-JMS-3)

`navigation.primary[].url_slug` is an optional string in the vertical JSON schema. Validators and documentation MUST treat it as non-breaking: JSON with or without the key is valid. No validator logic change is required.

#### Scenario: Validator accepts JSON with url_slug

- GIVEN a vertical JSON where `navigation.primary[0]` contains `url_slug: "about-us"`
- WHEN `vbb_validate_vertical_config()` runs
- THEN validation passes

#### Scenario: Validator accepts JSON without url_slug

- GIVEN a vertical JSON where `navigation.primary` items omit `url_slug`
- WHEN `vbb_validate_vertical_config()` runs
- THEN validation passes

### Requirement: Theme Importer — url_slug → Page ID Resolution (REQ-JMS-4)

`vbb_resolve_navigation_page_ids()` MUST resolve `url_slug` to a page ID via the existing `page_id_map` when `url_slug` is present on a navigation item. No code change is required in the importer. `kind: 'custom'` with the raw `url` MUST be preserved as the fallback when `url_slug` is absent or unresolved.

#### Scenario: Resolved url_slug produces post-type navigation entry

- GIVEN `page_id_map: {"about-us": 42}` and a nav item `{label: "About", url: "", url_slug: "about-us"}`
- WHEN `vbb_resolve_navigation_page_ids()` processes that item
- THEN the `wp_navigation` entry is created with `kind: 'post-type'` and `object_id: 42`

#### Scenario: Missing page slug falls back to custom URL

- GIVEN a nav item with no `url_slug` but with `url: "https://example.com/about"`
- WHEN the importer processes that item
- THEN the `wp_navigation` entry is created with `kind: 'custom'` and the raw URL preserved

#### Scenario: Unresolvable url_slug silently falls back

- GIVEN `page_id_map` does not contain the slug `"deleted-page"` and a nav item has `url_slug: "deleted-page"`
- WHEN `vbb_resolve_navigation_page_ids()` processes that item
- THEN the `wp_navigation` entry falls back to `kind: 'custom'` using the item's `url` field (no error thrown)

## Success Criteria

- [ ] Hub Navigation tab renders a page-slug picker per nav item (optional)
- [ ] Generated JSON includes `url_slug` on navigation items where a page was selected
- [ ] `vbb_validate_vertical_config()` accepts JSON with and without `url_slug`
- [ ] After import, `wp_navigation` CPT entries for nav items with `url_slug` have `kind: 'post-type'` with correct `page_id`
- [ ] Nav items without `url_slug` continue to produce `kind: 'custom'` URLs (backward compat)
- [ ] ZERO regressions on manual vertical import via Command Center