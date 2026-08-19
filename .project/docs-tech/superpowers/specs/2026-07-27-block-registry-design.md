# Block Registry & Command Center Refactor

**Date:** 2026-07-27
**Project:** Vertical Block Base (Orkestone Theme)
**Status:** Design — pending implementation

---

## Problem

The Command Center's block editing system has a fragmented architecture where:

1. **Data structures are duplicated** between JS (field definitions in `admin-pro.js` render functions) and PHP (baker functions reading ad-hoc keys). They frequently desync.
2. **Seven blocks** (`stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`) have baker functions but are not registered in `vbb_get_baker_map()`, rendering as "Unknown: {type}".
3. **Data paths mismatch**: e.g., JS saves `logoCloud` items to `items[].logo` but baker reads `logos[].url` → logos never appear.
4. **Event binding bug**: toggling a block off/on loses add/remove button listeners.
5. **Incomplete UI**: most blocks lack effects selector, media library, consistent add/remove/reorder controls.

## Solution — Block Registry

A single PHP file (`inc/block-registry.php`) that defines EVERY block's complete structure: fields, data types, baker function, styles, effects, media support. Both JS and PHP consume this as the **single source of truth**.

### Architecture

```
inc/block-registry.php        ← canonical block definitions
inc/pro-settings.php          ← defaults generated FROM registry
inc/block-baker.php           ← bakers read structure FROM registry
inc/pro-rest-api.php          ← GET /orkestone/v1/blocks endpoint
assets/js/admin-pro.js        ← generic field renderer FROM registry JSON
```

---

## Section 1 — Block Registry Structure

Each block in the registry has:

| Field | Type | Description |
|-------|------|-------------|
| `key` | string | Block key (e.g. `servicesGrid`) |
| `label` | string | Human-readable name |
| `baker` | string | PHP function name |
| `icon` | string | Dashicon CSS class |
| `styles` | string[] | Available style variants (A/B/C...) |
| `effects` | string[] | Available effects (`none`,`fade`,`slide-up`,`zoom`,`flip`) |
| `hasColors` | bool | Whether block supports per-block colors |
| `media_fields` | string[] | Field keys that use `wp.media` picker |
| `fields` | FieldDef[] | Array of field definitions |

### Field Definition

```php
[
  'key'        => 'heading',        // data path (e.g. blocks.{key}.heading)
  'label'      => 'Section Heading', // UI label
  'type'       => 'text',            // text|textarea|number|url|select|color|image|repeatable|custom
  'default'    => '',                // default value
  'isMedia'    => false,             // uses wp.media
  'options'    => [],                // for type=select: {value: label}
  'item_fields'=> [],                // for type=repeatable: FieldDef[]
]
```

### Standard field types

| type | Render |
|------|--------|
| `text` | `<input type="text">` |
| `textarea` | `<textarea>` |
| `number` | `<input type="number">` |
| `url` | `<input type="url">` |
| `select` | `<select>` with options |
| `color` | Color picker (existing pattern) |
| `image` | Thumbnail + wp.media select/clear buttons |
| `repeatable` | List of items with add/remove/drag/move. Each item renders its `item_fields[]` |
| `custom` | Custom render function for complex UIs (contact, map, newsletter) |

---

## Section 2 — All Blocks with Unified Data Structure

### Standard blocks (item-based, no special UI)

| Block key | Items key | Per-item fields | Effects |
|-----------|-----------|-----------------|---------|
| `servicesGrid` | `items[]` | `icon`(text), `title`(text), `summary`(textarea), `ctaText`(text), `ctaUrl`(url) | Sí |
| `benefits` | `items[]` | `icon`(text), `title`(text), `description`(textarea) | Sí |
| `process` | `items[]` | `number`(text), `title`(text), `description`(textarea), `icon`(text) | Sí |
| `testimonials` | `items[]` | `quote`(textarea), `author`(text), `role`(text), `avatar`(image), `rating`(number 1-5) | Sí |
| `faq` | `items[]` | `question`(text), `answer`(textarea) | No (no hay cards visuales) |
| `pricing` | `items[]` | `name`(text), `price`(text), `period`(text), `features`(textarea→array), `ctaText`(text), `ctaUrl`(url), `featured`(checkbox) | Sí |
| `team` | `items[]` | `name`(text), `role`(text), `bio`(textarea), `image`(image), `linkedin`(url), `twitter`(url), `github`(url) | Sí |
| `logoCloud` | `items[]` | `name`(text), `logo`(image), `link`(url) | Sí |
| `stats` | `items[]` | `value`(text), `label`(text), `icon`(text), `description`(textarea) | Sí |
| `gallery` | `items[]` | `image`(image), `title`(text), `category`(text), `url`(url), `description`(textarea) | Sí |

### Simple blocks (no repeatable items, no effects)

| Block key | Fields |
|-----------|--------|
| `hero` | `title`, `subtitle`, `eyebrow`, `primaryCta`, `primaryUrl`, `secondaryCta`, `secondaryUrl`, `image`(media), `style`(select A/B/C), `effect`(select) |
| `heroCentered` | Same as hero, no image. |
| `ctaFinal` | `text`, `subtitle`, `buttonText`, `buttonUrl`, `secondaryCta`, `secondaryUrl`, `style`(select A/B/C) |

### Custom blocks (special rendering)

| Block key | Fields | Notes |
|-----------|--------|-------|
| `contact` | `heading`, `email`, `phone`, `address`, `formEndpoint`, `recaptcha`, `recaptchaKey`, `recaptchaSecret`, `formFields`(repeatable) | Form builder — custom repeatable with type/label/required/options |
| `video` | `heading`, `subtitle`, `videoUrl`, `videoType`(select), `poster`(image), `ctaText`, `ctaUrl` | |
| `newsletter` | `heading`, `description`, `placeholder`, `buttonText`, `provider`(select), `listId` | |
| `map` | `heading`, `address`, `lat`, `lng`, `zoom`, `mapType`(select), `markerTitle` | |
| `comparison` | `heading`, `rows[]` with `feature`, `plan1`,`plan2`,`plan3`, `highlight` | Repeatable rows |
| `blog` | `heading`, `category`(select), `limit`(number), `layout`(select), toggles: `showExcerpt`,`showDate`,`showAuthor` | No effects |
| `divider` | `type`(select), `color`(color), `thickness`(number), `margin`(number) | No effects |

---

## Section 3 — Effects System

5 effects, stored in `blocks.{key}.effect`:

| Value | CSS class | Behavior |
|-------|-----------|----------|
| `none` | — | No animation |
| `fade` | `vbb-effect-fade` | opacity 0→1 via IntersectionObserver |
| `slide-up` | `vbb-effect-slide-up` | translateY(30px)→0 + fade |
| `zoom` | `vbb-effect-zoom` | scale(0.95)→1 + fade |
| `flip` | `vbb-effect-flip` | rotateY(90deg)→0 + fade, perspective: 800px |

Baker adds the class to the section wrapper. A lightweight frontend JS (`assets/js/vbb-effects.js`) uses IntersectionObserver to trigger animations. No external dependencies.

---

## Section 4 — Media Library Integration

Generalize the existing `wp.media` pattern from hero:

- Registry marks which fields are `type: 'image'` (or `isMedia: true`)
- Generic renderer outputs: thumbnail preview + "Select" + "Remove" buttons
- On "Select": opens `wp.media({ library: { type: 'image' } })`, sets `image_id` + `image_url`
- On "Remove": clears both `image_id` + `image_url`

Blocks gaining media picker:
- logoCloud → items[].logo
- team → items[].image
- gallery → items[].image
- testimonials → items[].avatar
- stats → items[].icon (optional)
- benefits → items[].icon (optional)

---

## Section 5 — PHP Files Changed

### NEW: `inc/block-registry.php`

- `vbb_get_block_registry()` — returns full registry array
- `vbb_get_block_def($key)` — single block definition
- `vbb_get_baker_map()` — generated from registry (replaces current hardcoded map)
- `vbb_get_block_defaults()` — default block settings from registry
- `vbb_sanitize_block_data($key, $data)` — validate+clean data against registry schema

### MODIFIED: `inc/block-baker.php`

- Add 7 missing entries to baker map: `stats`, `gallery`, `video`, `newsletter`, `map`, `comparison`, `blog`
- Update all baker functions to use registry field keys (especially `items` instead of `logos`/`plans`/`members`/`steps`)
- Add `vbb-effect-*` class output to every baker
- Fix pricing: convert features textarea string to array if needed

### MODIFIED: `inc/pro-settings.php`

- `vbb_pro_default_settings()` generates blocks defaults from registry
- `vbb_pro_sanitize_settings()` uses `vbb_sanitize_block_data()`

### MODIFIED: `inc/pro-rest-api.php`

- NEW route: `GET /orkestone/v1/blocks` → returns registry JSON
- Keep existing routes intact

---

## Section 6 — JS Changes

### MODIFIED: `assets/js/admin-pro.js`

- On load: fetch `GET /orkestone/v1/blocks` → `CC.registry = data`
- Replace all `_renderXxxCard()` functions (servicesGrid, benefits, process, testimonials, faq, pricing, team, logoCloud, stats, gallery) with a generic `_renderFromRegistry(blockKey, block)` that iterates `CC.registry[blockKey].fields`
- Keep custom renderers for: hero, heroCentered, ctaFinal, contact, video, newsletter, map, comparison, blog, divider
- Fix toggle rebinding: `_toggleBlockSettings` re-binds ALL field types (text, color, repeatable add/remove, media, effects select) using registry metadata
- Add `_renderImageField()` — reusable wp.media pattern
- Add `_renderEffectSelect()` — renders effect selector with 5 options

---

## Section 7 — Effects Frontend JS

### NEW: `assets/js/vbb-effects.js`

- IntersectionObserver-based trigger
- Reads `vbb-effect-*` classes from section wrappers
- Applies CSS transitions on viewport enter
- Respects `prefers-reduced-motion`
- Enqueued only when any block has an effect set

### NEW: `assets/css/vbb-effects.css`

- CSS transitions for each effect
- `vbb-effect-none` — no transition
- `vbb-effect-fade` — opacity 0.3s ease
- `vbb-effect-slide-up` — opacity + transform 0.5s ease
- `vbb-effect-zoom` — opacity + transform 0.4s ease
- `vbb-effect-flip` — perspective + rotateY + opacity 0.6s ease

---

## Section 8 — Implementation Order

The implementation follows dependency order so each phase is testable:

### Phase 1 — Registry + Defaults (foundation)

1. Create `inc/block-registry.php` with complete registry for ALL blocks
2. Update `vbb_pro_default_settings()` to generate from registry
3. Update `vbb_get_baker_map()` from registry
4. Update 7 bakers missing from map
5. Verify: existing settings still load, no regressions

### Phase 2 — Data alignment (match JS↔PHP)

6. Fix all baker functions to read canonical keys (`items[]` everywhere)
7. Fix pricing features array conversion
8. Fix logoCloud path mismatch
9. Update team baker (bio, social links)
10. Update process baker (number, icon)
11. Update testimonials baker (role, star rendering)
12. Verify: each block renders with existing data

### Phase 3 — Effects system

13. Create `assets/css/vbb-effects.css`
14. Create `assets/js/vbb-effects.js`
15. Add `effect` field to all block registries
16. Update bakers to add `vbb-effect-*` class
17. Verify: effects animate on scroll

### Phase 4 — Media library

18. Create generic `_renderImageField()` in JS
19. Update registry media_fields for all blocks
20. Wire wp.media picker per block
21. Verify: image selection works for logoCloud, team, gallery, testimonials

### Phase 5 — JS refactor

22. Add `GET /orkestone/v1/blocks` endpoint
23. Replace generic renderers in admin-pro.js
24. Fix toggle rebinding bug
25. Verify: add/remove works after toggle, all cards render correctly

### Phase 6 — Polish

26. Update `vbb_pro_sanitize_settings()` from registry
27. Remove old `_renderXxxCard()` functions (cleanup)
28. Test with all vertical configs

---

## Section 9 — Open Questions / Edge Cases

- **pricing features**: baker expects array, JS saves textarea string. Convert newline→array in sanitization.
- **hero-centered tagline**: the placeholder `{{vbb_hero_centered_tagline}}` exists but neither JS nor baker reads it. Add `tagline` field to heroCentered.
- **divider**: is a special block (inline separator, not a section). Keep as-is.
- **blog**: queries WP posts dynamically, the UI is a filter/config panel. Baker exists but may need a custom approach.
- **backward compat**: migrated profiles/settings with old keys (`logos[].url`, `plans[].features`) need a migration layer or accept breakage on upgrade.
