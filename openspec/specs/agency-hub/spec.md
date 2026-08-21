# Delta Spec: Orkestone Agency Hub — Navigation & SEO Tab + JSON Builder

**Change**: json-menu-sync
**Base spec**: `openspec/specs/agency-hub/spec.md`
**Affected requirements**: REQ-AH3, REQ-AH5

---

## MODIFIED Requirements

### Requirement: REQ-AH3 — Briefing Form Navigation & SEO Tab Field Set

The briefing form MUST render a 4-tab interface inside the WordPress admin with tabs: Branding, Pages & Sections, Content & Models, Navigation & SEO. The Navigation & SEO tab MUST display each primary navigation item row with the fields `label`, `url`, and an optional `url_slug`. The `url_slug` field is rendered as a text input prefilled from `url_slug` when present in the loaded JSON; agencies leave it blank for external/custom URLs. The tab MUST continue to display SEO fields (title pattern, meta description, robots). (Previously: Navigation & SEO tab showed only `label` and `url` per menu item.)

#### Scenario: Tab 4 renders url_slug input per nav item row

- GIVEN the 4-tab form is rendered
- WHEN the user navigates to Tab 4 "Navigation & SEO"
- THEN each primary nav item row displays inputs for `label`, `url`, and `url_slug`

#### Scenario: url_slug prefilled when loaded from stored JSON

- GIVEN a saved configuration whose `navigation.primary[0]` contains `url_slug: "about-us"`
- WHEN the Navigation tab renders for that configuration
- THEN the `url_slug` input for that item is prefilled with `about-us`

#### Scenario: Blank url_slug produces no key in stored data

- GIVEN a nav item row where the `url_slug` field is empty
- WHEN the form saves without entering a slug
- THEN the stored nav item has no `url_slug` key

#### Scenario: SEO fields continue to render

- GIVEN the Navigation & SEO tab is rendered with ≥ 1 nav item
- WHEN the tab scrolls below the menu items
- THEN the SEO fields (title pattern, meta description, robots) are visible and functional

#### Scenario: Tab 4 shows menu items (label + URL) — unchanged behavior

- GIVEN a valid configuration with 4 menu items (Home, Services, About, Contact)
- WHEN Tab 4 renders
- THEN each row shows the item label and URL as before

---

## MODIFIED Requirements

### Requirement: REQ-AH5 — JSON Builder Output Includes Optional url_slug

The JSON Builder MUST map form data to the existing `default.json` vertical schema, including `schemaVersion`, `verticalKey`, `name`, `brand`, `navigation`, `pages`, `contentModels`, `graphics`, and `seoDefaults`. Each item in `navigation.primary` MUST include `label` and `url`. When `url_slug` is non-empty for an item, it MUST be included in that item's output object. Existing keys (`kind`, `id`) are passed through if set. When `url_slug` is empty, it MUST be omitted from that item's JSON object. (Previously: JSON Builder output for navigation items included only `label` and `url`.)

#### Scenario: url_slug appears in generated JSON when filled

- GIVEN a nav item with label "About", url "", and page slug "about-us"
- WHEN `JSON_Builder::build_navigation()` produces output
- THEN `navigation.primary[0]` contains `{"label":"About","url":"","url_slug":"about-us"}`

#### Scenario: url_slug omitted from JSON when blank

- GIVEN a nav item with label "Contact" and an empty `url_slug` field
- WHEN `JSON_Builder::build_navigation()` processes that item
- THEN the output object contains `label` and `url` but no `url_slug` key

#### Scenario: Partial population across items

- GIVEN 3 nav items: items 1–2 have `url_slug` values, item 3 has none
- WHEN the JSON builder runs
- THEN items 1 and 2 include `url_slug`; item 3 omits it

#### Scenario: Existing JSON passes vbb_validate_vertical_config() — unchanged

- GIVEN a `default.json` produced before this change with no `url_slug` keys
- WHEN `vbb_validate_vertical_config()` validates the JSON
- THEN validation passes without error

#### Scenario: kind and id passed through unchanged

- GIVEN a nav item where `kind: 'post-type'` and `id: 42` are pre-set
- WHEN `build_navigation()` processes the item with `url_slug: "about-us"`
- THEN the output includes `kind`, `id`, and `url_slug` — no existing keys are dropped