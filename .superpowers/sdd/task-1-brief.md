# Task 1 Brief: Create `inc/block-registry.php`

## What This Task Does

Creates the central block registry as the single source of truth for all 19 block types. This replaces the hardcoded baker map in `block-baker.php` and provides field definitions, defaults, and sanitization that both PHP bakers and JS admin will consume.

## Files to Create

### 1. CREATE: `orkestone-theme/inc/block-registry.php`

Full file with 5 functions:

**`vbb_get_block_registry(): array`** — Returns full 19-block registry array keyed by block key. Each block has: `label`, `icon`, `styles`, `effects`, `hasColors`, `fields` (array of field definitions with key/label/type/default/item_fields for repeatable).

The 19 blocks in order:
1. `servicesGrid` — Services Grid (repeatable items: icon, title, summary, ctaText, ctaUrl)
2. `benefits` — Benefits (repeatable items: icon, title, description)
3. `process` — Process (repeatable items: number, title, description, icon)
4. `testimonials` — Testimonials (repeatable items: quote, author, role, avatar, rating)
5. `faq` — FAQ (repeatable items: question, answer) — effects: only `none`
6. `pricing` — Pricing (repeatable items: name, price, period, features, ctaText, ctaUrl, featured) — effects: all 5
7. `team` — Team (repeatable items: name, role, bio, image, linkedin, twitter, github) — effects: all 5
8. `logoCloud` — Logo Cloud (repeatable items: name, logo, url) — effects: none, fade, slide-up, zoom (no flip)
9. `stats` — Stats (repeatable items: value, label, icon, description) — effects: all 5
10. `gallery` — Gallery (repeatable items: image, title, category, url, description) — effects: none, fade, slide-up, zoom (no flip)

Then simple (non-repeatable) blocks:
11. `hero` — fields: title, subtitle, eyebrow, tagline, primaryCta, primaryUrl, secondaryCta, secondaryUrl, image — effects: none, fade, slide-up, zoom (no flip)
12. `heroCentered` — fields: title, subtitle, eyebrow, tagline, primaryCta, primaryUrl, secondaryCta, secondaryUrl — effects: none, fade, slide-up, zoom (no flip)
13. `ctaFinal` — fields: text, subtitle, buttonText, buttonUrl, secondaryCta, secondaryUrl — effects: none, fade, slide-up, zoom (no flip)
14. `contact` — fields: heading, email, phone, address, formEndpoint, recaptcha (select: none/v2/v3), recaptchaKey, recaptchaSecret + repeatable formFields (type select, name, label, placeholder, required checkbox, options) — effects: none, fade, slide-up
15. `video` — fields: heading, subtitle, video_url, video_type (select: youtube/vimeo/mp4), poster, cta_text, cta_url — effects: none, fade, slide-up, zoom (no flip)
16. `newsletter` — fields: heading, description, placeholder, button_text, provider (select: custom/mailchimp/convertkit), listId — effects: none, fade, slide-up
17. `map` — fields: heading, address, lat, lng, zoom, map_type (select: roadmap/satellite/hybrid/terrain), marker_title — effects: none, fade, slide-up
18. `comparison` — Comparison Table (repeatable rows: feature, plan1, plan2, plan3, highlight checkbox) — effects: only `none`
19. `blog` — Blog Posts (fields: heading, category, limit, layout select grid/list/masonry, showExcerpt checkbox, showDate checkbox, showAuthor checkbox) — effects: only `none`, hasColors: false
20. `divider` — fields: type (select: line/space/wave/dots), color, thickness, margin — effects: only `none`, hasColors: false

**`vbb_get_block_def(string $key): ?array`** — Returns single block definition or null.

**`vbb_get_baker_map(): array`** — Maps block keys to baker function names (dynamically from registry). Key mapping:
- hero → hero
- heroCentered → hero-centered
- servicesGrid → services-grid
- benefits → benefits
- process → process
- testimonials → testimonials
- faq → faq
- contact → contact-section
- ctaFinal → cta-final
- logoCloud → logoCloud
- pricing → pricing
- team → team
- stats → stats
- gallery → gallery
- video → video
- newsletter → newsletter
- map → map
- comparison → comparison
- blog → blog
- divider → divider

Baker function names:
- hero → vbb_bake_hero
- heroCentered → vbb_bake_hero_centered
- servicesGrid → vbb_bake_services_grid
- benefits → vbb_bake_benefits
- process → vbb_bake_process
- testimonials → vbb_bake_testimonials
- faq → vbb_bake_faq
- contact → vbb_bake_contact_section
- ctaFinal → vbb_bake_cta_final
- logoCloud → vbb_bake_logo_cloud
- pricing → vbb_bake_pricing_tables
- team → vbb_bake_team_section
- stats → vbb_bake_stats
- gallery → vbb_bake_gallery
- video → vbb_bake_video
- newsletter → vbb_bake_newsletter
- map → vbb_bake_map
- comparison → vbb_bake_comparison
- blog → vbb_bake_blog
- divider → vbb_bake_divider

**`vbb_get_block_defaults(): array`** — Returns array of per-block defaults (enabled: true, style: first style, colors: [], effect: 'none', plus each field's default value).

**`vbb_sanitize_block_data(string $key, array $data): array`** — Sanitizes data against registry schema. Field type mapping:
- text, select → sanitize_text_field
- textarea → sanitize_textarea_field
- number → intval
- url → esc_url_raw
- color → sanitize_hex_color
- checkbox → !empty()
- image → array with id (intval) and url (esc_url_raw), or esc_url_raw if string
- repeatable → recurse per item_fields
- Preserves non-field keys: enabled, style, colors, effect
- Validates style against allowed styles list

## Files to Modify

### 2. MODIFY: `orkestone-theme/functions.php`

Insert `'inc/block-registry.php'` BEFORE `'inc/block-baker.php'` in the `$vertical_block_base_files` array.

### 3. For Task 2 compat: Nothing to modify in block-baker.php yet

The old `vbb_get_baker_map()` in `block-baker.php` (which maps hero → vbb_bake_hero, etc.) will conflict with the new one in block-registry.php because `block-registry.php` loads FIRST. However, **DO NOT remove the old one in this task** — that will be handled in a later cleanup task after all bakers are migrated. For now, make sure `vbb_get_baker_map()` in `block-registry.php` returns the SAME mapping plus the new blocks.

## Verification

1. Run `php -l inc/block-registry.php` — must return "No syntax errors detected"
2. Run `php -l functions.php` — must pass lint

## Exact Code

Use the code from `docs/superpowers/plans/2026-07-27-block-registry-plan.md` lines 37-717 for the registry file, and lines 719-745 for the functions.php update.

## Quality Requirements

- No syntax errors (passes `php -l`)
- All 19 blocks defined with correct field structures
- All 5 helper functions present
- Functions.php loads block-registry BEFORE block-baker
- Follow WordPress coding standards (PSR-12 compatible)
- DocBlocks on all functions
- No external dependencies
