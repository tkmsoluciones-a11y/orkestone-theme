# Apply Progress: Builder Visual Polish — Stage 1 & 2 Complete

**Change**: builder-visual-polish  
**Stage**: 1 & 2 — Full Implementation  
**Status**: Completed  
**Date**: 2026-07-09  

---

## Executive Summary

All 14 tasks across both stages are complete. Stage 1 implemented the visual feedback layer (status bar, toasts, skeletons, UI polish, color picker hex UX, empty states). Stage 2 implemented the architectural shift: postMessage live preview bridge, CSS variable injection eliminating full reloads, per-block color data model and scoped CSS vars, responsive preview presets, preview loading overlay, and the commit-on-blur split for color inputs.

## Completed Tasks

| Task | Description | Files Changed |
|------|-------------|--------------|
| TASK-VP1.1 | Status bar DOM + CSS (idle/saving/saved/error) | `pro-admin.php`, `admin-pro.css`, `admin-pro.js` |
| TASK-VP1.2 | Toast system (4 types, stacking, auto-dismiss) | `admin-pro.js`, `admin-pro.css`, `pro-admin.php` |
| TASK-VP1.3 | Replace ALL `alert()`/`confirm()` with toast/status | `admin-pro.js` |
| TASK-VP1.4 | Save flash animation on fields after save | `admin-pro.css`, `admin-pro.js` |
| TASK-VP1.5 | Skeleton shimmer cards loading state | `admin-pro.css`, `admin-pro.js` |
| TASK-VP1.6 | UI polish: font stack, card radius 18px, left-border accent, transitions | `admin-pro.css` |
| TASK-VP1.7 | Color picker hex display, copy button, empty states, remove menu status | `admin-pro.js`, `admin-pro.css`, `pro-admin.php` |
| TASK-VP2.1 | Data model: `blocks.{key}.colors` + sanitization | `pro-settings.php` |
| TASK-VP2.2 | Block-scoped CSS var generation | `pro-css-vars.php` |
| TASK-VP2.3 | postMessage bridge (CC → iframe) | `admin-pro.js` |
| TASK-VP2.4 | CSS injection receiver in iframe | `pro-admin.php` |
| TASK-VP2.5 | Preview loading overlay + responsive presets | `admin-pro.js`, `admin-pro.css`, `pro-admin.php` |
| TASK-VP2.6 | Per-block color pickers in UI | `admin-pro.js` |
| TASK-VP2.7 | Commit-on-blur split for color inputs | `admin-pro.js` |

## Files Changed

| File | Action | Lines (net) | What Changed |
|------|--------|-------------|--------------|
| `assets/js/admin-pro.js` | Modified | ~+240 | Stage 1: `showStatus()`, `showToast()`, `showConfirmToast()`, `_dismissToast()`, `_flashChangedField()`, `_showSkeletons()`, `_showCopyTooltip()`. Replaced all alert/confirm. Enhanced `renderColorGroups()` with hex/copy. Empty states. Removed `#vbb-cc-menu-status`. Stage 2: `postMessage()`, `buildCssVars()`, `_handleColorInput()`, `_setNested()`, `_blockKeyToSectionClass()`, `_onPresetChange()`, `_showPreviewOverlay()`, `_hidePreviewOverlay()`. Extended `renderBlockSettings()` with per-block color pickers. Split color event handlers in `bindCardEvents()`. Updated `refreshPreview()` with overlay and `vbb_origin`. Updated `saveSettings()` for postMessage. Updated `onPageChange()` for overlay. |
| `assets/css/admin-pro.css` | Modified | ~+100 | Stage 2: Preview toolbar, preset buttons, preview viewport container, preview loading overlay with spinner, overlay fade-in animation. |
| `inc/pro-admin.php` | Modified | ~+50 | Stage 1: Added `#vbb-cc-toast-container` and `#vbb-cc-status-bar`. Stage 2: Added `previewOrigin` to `vbbCommandCenterData`. Added `vbb_origin` query param to iframe URL. Added preview toolbar with presets + refresh button. Added preview viewport wrapper with loading overlay. Added `vbb_pro_inject_preview_script()` hook injecting postMessage receiver into frontend preview pages with origin verification. |
| `inc/pro-settings.php` | Modified | ~+40 | Added `vbb_pro_block_color_keys()` helper (7 color keys). Updated `vbb_pro_default_settings()` to emit blocks as objects with `{ enabled, colors }`. Added per-block color sanitization in `vbb_pro_sanitize_settings()` with `sanitize_hex_color()` validation. |
| `inc/pro-css-vars.php` | Modified | ~+75 | Added `vbb_pro_section_class_for_block()` with explicit mapping (handles `contact`→`contact-section`, `pricing`→`pricing-tables`). Added `vbb_pro_block_scoped_css_vars()` generating `.vbb-section-{type} { --vbb-pro-{key}: {val}; }` rules. Updated `vbb_pro_print_css_vars()` to emit block-scoped and per-page `.page-id-{id} .vbb-section-{type}` overrides. Fixed `vbb_pro_body_classes()` for new block object format. |

## Design Deviations

1. **Resize handle omitted**: The design mentioned a resize handle for the iframe container, but the responsive preset buttons (Desktop/Tablet/Mobile) serve the same purpose more precisely. A resize handle would add complexity without proportional benefit given the preset system.

2. **`vbb:setting-update` not used for single-field preview**: The design specified both `vbb:css-vars` (batch) and `vbb:setting-update` (single field) messages. In practice, `_handleColorInput` rebuilds the full CSS vars string via `buildCssVars()` on every input event and sends it as `vbb:css-vars`. This is simpler and avoids maintaining per-field delta logic. The iframe receiver handles both types but only processes `vbb:css-vars`.

3. **Error retry button**: The overlay error state shows a text message but the retry button is delegated to `refreshPreview()` — the existing refresh button in the toolbar serves this purpose. The overlay error text says "Preview failed" with instruction to use the refresh button.

All deviations maintain or improve the original intent and do not violate any spec requirements.

## Verifications Performed

### Stage 1
- [x] Zero `alert(` or `confirm(` calls remain in `admin-pro.js`
- [x] `CC.showStatus()` handles all 4 states (idle/saving/saved/error)
- [x] `CC.showToast()` creates DOM elements with correct type classes
- [x] Toast stacking works (each toast is independent DOM element)
- [x] Status bar grid-column: 1 / -1 spans full width
- [x] Skeleton has 3 height variants (short/medium/tall)
- [x] `_flashChangedField()` adds and removes CSS class
- [x] Clipboard API used with fallback for copy button
- [x] Hex inputs sync both ways with color inputs
- [x] Empty states show icons + CTAs (pages, blocks, menu items)
- [x] Menu editor no longer references `#vbb-cc-menu-status`

### Stage 2
- [x] `vbb_pro_block_color_keys()` returns 7 palette keys
- [x] `vbb_pro_default_settings()` emits blocks as objects with empty `colors`
- [x] Per-block color sanitization in `vbb_pro_sanitize_settings()` validates via `sanitize_hex_color()`
- [x] `vbb_pro_section_class_for_block()` maps all block keys including exceptions
- [x] `vbb_pro_block_scoped_css_vars()` generates correct `.vbb-section-{type}` selectors
- [x] Per-page overrides use `.page-id-{id} .vbb-section-{type}` selectors
- [x] `CC.postMessage()` sends messages with origin verification
- [x] `CC.supportsPostMessage` flag has try/catch fallback to full reload
- [x] `CC.buildCssVars()` generates `:root` + block-scoped CSS vars string
- [x] Inline iframe script receives `vbb:css-vars` and updates `#vbb-pro-injected-css`
- [x] Inline script validates `event.origin` against `?vbb_origin=` URL param
- [x] Inline script sends `vbb:ready` to parent on load
- [x] Preview overlay appears on iframe load and hides on `load` event
- [x] Responsive presets change viewport width and highlight active button
- [x] Per-block color pickers appear with 5 color keys (excluding primary, secondary)
- [x] Color `input` event calls `_handleColorInput` (no XHR, postMessage only)
- [x] Color `change` event (blur) calls `_handleChange` → `debouncedSave()`

## Remaining Tasks

None — all 14 tasks are complete.

## Workload

- **Mode**: force-chained (PR 1 = Stage 1, PR 2 = Stage 2)
- **Chain strategy**: stacked-to-main
- **Stage 2 estimated lines**: ~410 (within 800-line review budget combined)
- **Ready for**: sdd-verify

## Risks & Notes

- **R3 (Color input `change` event)**: The `change` event for color inputs fires on blur in most browsers, which is the intended behavior. Keyboard Enter on color inputs may not fire `change` in some browsers — this is a known edge case and does not block functionality.
- **R1 (postMessage `origin` mismatch)**: On multisite where `home_url()` differs from the admin URL, the origin check could fail. The `vbb_origin` parameter is derived from `home_url()` consistently.
- **Backward compatibility**: Old block format (booleans) is converted to objects in sanitization. Old settings without `colors` get empty `colors` arrays. No data migration required.
