# Apply Progress: Builder Completion — Phase 2

**Change**: builder-completion
**Phase**: 2 — Baker Token Mapping
**Mode**: Standard
**Status**: Completed

## Completed Tasks

### Phase 1 (from prior batch)
- [x] 1.1 Add `vbb_get_vertical_page_by_id($page_id)` helper in `inc/page-blueprint.php`
- [x] 1.2 Add `vbb_bake_page_content($page_id)` in `inc/block-baker.php`
- [x] 1.3 Fix `vbb_bake_process()` loop bug (lines 351–367)
- [x] 1.4 Add page selector DOM container in `inc/pro-admin.php`
- [x] 1.5 Wire `CC.el.pageSelector` in `admin-pro.js`
- [x] 1.6 Update `inc/test-block-baker.php` with new tests

### Phase 2 (this batch)
- [x] 2.1 Insert `{{vbb_*}}` tokens in single-value baker functions (hero-centered, cta-final, contact-section)
- [x] 2.2 Add `{{vbb_*_heading}}` tokens for all 8 repeatable section bakers
- [x] 2.3 Expand `vbb_pro_replace_dynamic_content()` map to include all 20 tokens
- [x] 2.4 Update tests for token assertions + add token resolution test

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `orkestone-theme/inc/block-baker.php` | Modified | **Task 2.1**: `vbb_bake_hero_centered()` — replaced `vbb_esc_text()` on title/tagline with `{{vbb_hero_centered_title}}` / `{{vbb_hero_centered_tagline}}`. `vbb_bake_cta_final()` — replaced text/buttonText/buttonUrl with `{{vbb_cta_final_*}}` placeholders. `vbb_bake_contact_section()` — replaced email/phone with `{{vbb_contact_email}}` / `{{vbb_contact_phone}}`. **Task 2.2**: All 8 repeatable section bakers (services-grid, benefits, process, testimonials, faq, logo-cloud, pricing, team) — replaced `vbb_esc_text($data['heading'])` + fallback `__(...)` with `{{vbb_{section}_heading}}` tokens. |
| `orkestone-theme/inc/pro-settings.php` | Modified | **Task 2.3**: Expanded `vbb_pro_replace_dynamic_content()` map from 5 entries to 20. Added hero-centered (2), cta-final (3), contact (2), and 8 repeatable section heading tokens. Each resolves via `$settings['blocks'][$block_key][$field]`. Tagline falls back to `subtitle` for backwards compat. Hero-centered title shares hero's `title` field. |
| `orkestone-theme/inc/test-block-baker.php` | Modified | **Task 2.4**: Updated all 16 existing heading/text assertions to check for `{{vbb_*}}` tokens instead of raw text values. Fixed cta-no-button test (button now always emitted as placeholder). Added tests for logo-cloud (3), pricing (4), team (4) sections. Added comprehensive token resolution test (11 assertions) that simulates `vbb_pro_replace_dynamic_content()` with mock settings and verifies every token resolves correctly. Test count grew from 74 to 98. |

## Token Map (20 tokens total)

| Token | Setting Path | Baker Function |
|-------|-------------|----------------|
| `{{vbb_hero_title}}` | `blocks.hero.title` | hero (existing) |
| `{{vbb_hero_subtitle}}` | `blocks.hero.subtitle` | hero (existing) |
| `{{vbb_hero_eyebrow}}` | `blocks.hero.eyebrow` | hero (existing) |
| `{{vbb_hero_cta_text}}` | `blocks.hero.primaryCta` | hero (existing) |
| `{{vbb_hero_cta_url}}` | `blocks.hero.primaryUrl` | hero (existing) |
| `{{vbb_hero_centered_title}}` | `blocks.hero.title` | hero-centered (new) |
| `{{vbb_hero_centered_tagline}}` | `blocks.hero.tagline` → `blocks.hero.subtitle` | hero-centered (new) |
| `{{vbb_cta_final_text}}` | `blocks.ctaFinal.text` | cta-final (new) |
| `{{vbb_cta_final_button_text}}` | `blocks.ctaFinal.buttonText` | cta-final (new) |
| `{{vbb_cta_final_button_url}}` | `blocks.ctaFinal.buttonUrl` | cta-final (new) |
| `{{vbb_contact_email}}` | `blocks.contact.email` | contact (new) |
| `{{vbb_contact_phone}}` | `blocks.contact.phone` | contact (new) |
| `{{vbb_services_heading}}` | `blocks.servicesGrid.heading` | services-grid (new) |
| `{{vbb_benefits_heading}}` | `blocks.benefits.heading` | benefits (new) |
| `{{vbb_testimonials_heading}}` | `blocks.testimonials.heading` | testimonials (new) |
| `{{vbb_faq_heading}}` | `blocks.faq.heading` | faq (new) |
| `{{vbb_process_heading}}` | `blocks.process.heading` | process (new) |
| `{{vbb_pricing_heading}}` | `blocks.pricing.heading` | pricing (new) |
| `{{vbb_team_heading}}` | `blocks.team.heading` | team (new) |
| `{{vbb_logo_cloud_heading}}` | `blocks.logoCloud.heading` | logo-cloud (new) |

## Design Decision: Repeatable Items

Follows **Design Option C**: repeatable items (services, benefits, testimonials, FAQ, process steps, pricing plans, team members, logo items) are baked **directly from merged settings** at bake time. No placeholders inside repeatable loops. Single-value fields (headings for repeatable sections, plus hero/cta/contact single-value fields) use `{{vbb_*}}` tokens. This is stable because the number of repeatable items is variable — placeholders inside loops can't map 1:1.

## Deviations from Design

None — implementation matches design exactly.

Tasks 2.1-2.4 follow the exact token mapping table and static-field rules from design.md Phase 2.

## Issues Found

None.

## Testing Results

- **Standalone test run**: 98/98 passed, 0 failed (up from 74 in Phase 1).
- Every baker function output was verified to contain the expected `{{vbb_*}}` token instead of raw text values.
- Token resolution test proves all 20 tokens can be resolved from mock settings, and no `{{vbb_` tokens remain after resolution.
- New sections (logo-cloud, pricing-tables, team-section) get basic smoke tests confirming heading token, structural markup, and key content fields.

## Bake Behavior Changes

- `vbb_bake_hero_centered()`: Title/tagline blocks are **always emitted** (previously conditional on data). This matches the hero baker pattern.
- `vbb_bake_cta_final()`: Button block is **always emitted** (previously conditional on both buttonText and buttonUrl being non-empty). The URL defaults to `#` when settings are empty at resolution time.
- `vbb_bake_contact_section()`: Email/phone blocks are **always emitted** (previously conditional on data). The heading remains a raw i18n string (not tokenized, by design).
- All repeatable section heading blocks are **always emitted** with `{{vbb_*_heading}}` tokens.

## Remaining Tasks (Future Phases)

- [ ] Phase 3: Page CRUD API (tasks 3.1-3.4)
- [ ] Phase 4: Menu data model (tasks 4.1-4.4)
- [ ] Phase 5: Backfill & polish (tasks 5.1-5.3)

## Workload / PR Boundary

- **Mode**: feature-branch-chain (PR 2 of 5)
- **Current work unit**: 2 — Baker token mapping + replacement map
- **Boundary**: This batch covers all Phase 2 tasks. Next batch is Phase 3 (Page CRUD API).
- **Estimated review budget impact**: ~300 lines changed (token insertions, map expansion, test updates).

## Status

10/10 tasks complete across Phase 1 (6) + Phase 2 (4). Ready for verify.
