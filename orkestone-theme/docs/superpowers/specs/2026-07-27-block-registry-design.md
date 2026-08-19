# Block Registry — Design Spec

**Date:** 2026-07-27
**Status:** Draft
**Author:** Architectural Review

## 1. Problem Statement

The Orkestone block system has grown organically, resulting in three structural problems:

1. **Duplicated block definitions** — Block schemas exist separately in PHP (`pro-settings.php` defaults, `block-baker.php` baker map) and JS (`admin-pro.js` `_sectionInfo`, `renderBlockSettings`, `renderCards`). Adding a block requires editing 4+ files manually.
2. **Missing blocks** — 5 baker functions exist but aren't registered in `vbb_get_baker_map()` (stats, gallery, video, newsletter, map). 2 blocks listed in settings have no baker at all (comparison, blog).
3. **Data misalignment** — JS saves data under different keys than PHP bakers expect (e.g., `logoCloud` saves `items[].logo` but baker reads `logos[].url`; `features` saved as string but baker expects array).

## 2. Solution: Central Block Registry

Create a single source of truth (`inc/block-registry.php`) that defines every block's schema, defaults, fields, baker function, and section mapping. Both PHP and JS consume this registry.

### 2.1 Registry Schema

```php
$vbb_block_registry = [
    'hero' => [
        'label'    => 'Hero',
        'section'  => 'header',
        'icon'     => 'dashicons-format-image',
        'defaults' => [
            'enabled'     => false,
            'title'       => '',
            'subtitle'    => '',
            'description' => '',
            'image'       => '',
            'button_text' => '',
            'button_url'  => '',
            'effect'      => 'none',
        ],
        'fields' => [
            'title'       => ['type' => 'text',       'label' => 'Title'],
            'subtitle'    => ['type' => 'text',       'label' => 'Subtitle'],
            'description' => ['type' => 'textarea',   'label' => 'Description'],
            'image'       => ['type' => 'media',      'label' => 'Image'],
            'button_text' => ['type' => 'text',       'label' => 'Button Text'],
            'button_url'  => ['type' => 'url',        'label' => 'Button URL'],
        ],
        'baker' => 'vbb_bake_hero',
    ],
    // ... 18 more blocks
];
```

### 2.2 Block List (19 blocks)

| # | Key | Section | Baker Function | Status |
|---|-----|---------|----------------|--------|
| 1 | hero | header | `vbb_bake_hero` | ✅ Exists |
| 2 | hero-centered | header | `vbb_bake_hero_centered` | ✅ Exists |
| 3 | services-grid | services | `vbb_bake_services_grid` | ✅ Exists |
| 4 | stats | services | `vbb_bake_stats` | ❌ Missing from map |
| 5 | benefits | benefits | `vbb_bake_benefits` | ✅ Exists |
| 6 | process | process | `vbb_bake_process` | ✅ Exists |
| 7 | testimonials | testimonials | `vbb_bake_testimonials` | ✅ Exists |
| 8 | faq | faq | `vbb_bake_faq` | ✅ Exists |
| 9 | contact-section | contact | `vbb_bake_contact_section` | ✅ Exists |
| 10 | cta-final | cta | `vbb_bake_cta_final` | ✅ Exists |
| 11 | logoCloud | trust | `vbb_bake_logo_cloud` | ✅ Exists (data misaligned) |
| 12 | pricing | pricing | `vbb_bake_pricing` | ✅ Exists (data misaligned) |
| 13 | team | team | `vbb_bake_team` | ✅ Exists |
| 14 | gallery | team | `vbb_bake_gallery` | ❌ Missing from map |
| 15 | video | team | `vbb_bake_video` | ❌ Missing from map |
| 16 | newsletter | footer | `vbb_bake_newsletter` | ❌ Missing from map |
| 17 | map | footer | `vbb_bake_map` | ❌ Missing from map |
| 18 | comparison | benefits | *(needs creation)* | ❌ No baker function |
| 19 | blog | footer | *(needs creation)* | ❌ No baker function |

### 2.3 REST Endpoint

`GET /orkestone/v1/blocks` — Exposes the registry as JSON so the JS admin panel can render fields generically without hardcoding block schemas.

Response shape:
```json
{
  "blocks": { "hero": { "label": "Hero", "fields": [...], "defaults": {...} }, ... },
  "sections": { "header": { "label": "Header", "blockKeys": ["hero","hero-centered"] }, ... }
}
```

## 3. Architectural Changes

### 3.1 New Files

| File | Purpose |
|------|---------|
| `inc/block-registry.php` | Central registry definition + accessor functions |
| `assets/js/vbb-effects.js` | IntersectionObserver scroll animation engine |
| `assets/css/vbb-effects.css` | CSS transitions for 5 effect types |
| `docs/superpowers/plans/2026-07-27-block-registry-plan.md` | Implementation plan |

### 3.2 Modified Files

| File | Changes |
|------|---------|
| `inc/block-baker.php` | `vbb_get_baker_map()` reads from registry; add 7 missing map entries; add comparison + blog baker functions; fix data alignment in logoCloud, pricing, benefits |
| `inc/pro-settings.php` | `vbb_pro_get_block_section_map()` can derive from registry; add `effect` to defaults |
| `inc/pro-rest-api.php` | Register `/orkestone/v1/blocks` route |
| `assets/js/admin-pro.js` | Replace hardcoded `_sectionInfo` and per-block `renderBlockSettings` with generic renderer from registry fetch; generalize media library; add effects dropdown; fix toggle rebinding |
| `assets/css/admin-pro.css` | Add effects dropdown styles, animation preview |

### 3.3 Effects System

5 animation options per block, stored as `effect` field in block data:

| Value | CSS Class | Behavior |
|-------|-----------|----------|
| `none` | *(none)* | No animation |
| `fade` | `.vbb-effect-fade` | Opacity 0→1 |
| `slide-up` | `.vbb-effect-slide-up` | TranslateY(30px)→0 + fade |
| `zoom` | `.vbb-effect-zoom` | Scale(0.95)→1 + fade |
| `flip` | `.vbb-effect-flip` | RotateX(10deg)→0 + fade |

Implementation: Vanilla JS IntersectionObserver (no jQuery dependency). Triggered once per element when it enters the viewport.

### 3.4 Media Library Integration

Generalize the existing `wp.media` pattern used in the hero block:

1. Add `data-media-button="true"` attribute to image fields
2. Single JS handler listens for clicks on `[data-media-button]`
3. Opens `wp.media` frame, returns attachment URL to the target input
4. Preview thumbnail shown next to the input

### 3.5 Backward Compatibility

`vbb_sanitize_block_data()` will include migration rules:

- `items[x].logo` → `logos[x].url` (logoCloud)
- `features` string → `features` array (explode by newline)
- `steps`/`plans`/`members` → normalized to `items` where applicable
- `effect` defaults to `'none'` for existing profiles

## 4. Data Flow

```
JS Admin Panel                    PHP Backend
==============                    ===========
GET /orkestone/v1/blocks  ──►    block-registry.php
      │                                │
      ▼                                ▼
Generic field renderer            vbb_sanitize_block_data()
reads registry schema             applies migration rules
      │                                │
      ▼                                ▼
User edits blocks                 Save to profile (JSON)
      │                                │
      ▼                                ▼
POST /orkestone/v1/save-profile   block-baker.php
                              ◄──  reads blocks from profile
                                   bakes each via registry[type]['baker']
                                   renders HTML with effect classes
```

## 5. Dependencies

- WordPress Media Library (`wp.media`) for image selection
- IntersectionObserver API (no polyfill — modern browsers only)
- Existing REST infrastructure (`register_rest_route`)

## 6. Risks

| Risk | Mitigation |
|------|------------|
| Existing profiles break on migration | `vbb_sanitize_block_data()` migration layer handles old keys |
| JS registry fetch fails (network) | Fallback to embedded defaults in `admin-pro.js` |
| IntersectionObserver unsupported (IE11) | Feature-check; effects gracefully degrade to no animation |
