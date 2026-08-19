# Technical Design: Builder Visual Polish (UI/UX)

**Change**: builder-visual-polish  
**Status**: design  
**Next**: sdd-tasks  
**Delivery Strategy**: 2 chained PRs, 800-line review budget  
**Dependencies**: builder-completion (archived)

---

## Executive Summary

The Command Center currently saves silently, reloads the preview iframe on every change, uses `alert()` for errors, and has no per-block color control. This design introduces a **Visual Feedback System** (status bar, toasts, field indicators, skeletons), a **Live Preview Bridge** (postMessage + CSS injection eliminating full reloads), a **Per-Block Color Model** (scoped CSS vars), and **UI Polish** (typography, cards, transitions, empty states). Delivered in 2 stages with a shared API contract, 8 resolved spec gaps, and zero data-breaking rollback paths.

---

## Stage 1 — Feedback & UI Polish (Architecture)

### Status Bar → Toast Manager → Component State Flow

```
User Action
    │
    ├─ Field change (debounced 500ms)
    │   ├─ CC.showStatus('saving')       ← Status bar: spinner + "Saving…"
    │   ├─ XHR POST /vertical-settings
    │   │   ├─ Success → CC.showStatus('saved', 2s fade)
    │   │   │            → field `.vbb-saved-flash` class (1s CSS animation)
    │   │   │            → CC.showToast('Settings saved', 'success', 3s)
    │   │   └─ Error   → CC.showStatus('error', retry button)
    │   │                → CC.showToast(msg, 'error', persistent)
    │   └─ (No preview reload in Stage 1)
    │
    ├─ Button: "Regenerate Pages"
    │   └─ CC.showConfirmToast(...) → CC.showStatus('regenerating') → XHR
    │
    └─ Button: "Reset to Vertical Defaults"
        └─ CC.showConfirmToast(...) → XHR → CC.showStatus('saved')
```

### Data Structures (new JS additions to `CC`)

```javascript
// CC.showStatus(state, message?)
//   state: 'idle' | 'saving' | 'saved' | 'error'

// CC.showToast(message, type, duration?)
//   type: 'success' | 'error' | 'info' | 'confirm'
//   duration: ms (default: success=3000, info=3000, error=0 [persistent], confirm=0)

// CC.supportsPostMessage   ← flag set in Stage 2
// CC.refreshPreview()       ← retained, unchanged signature (shared API)
// CC.postMessage(msg)       ← new in Stage 2
```

### DOM Elements (added to `vbb_pro_render_command_center`)

| Element | ID | Purpose |
|---------|----|---------|
| Status bar | `#vbb-cc-status-bar` | Persistent bar below page selector |
| Toast container | `#vbb-cc-toast-container` | Fixed-position, top-right |
| Preview toolbar | `#vbb-cc-preview-toolbar` | Refresh, URL display, resize handle (Stage 1) + responsive presets (Stage 2) |
| Preview loading overlay | `#vbb-cc-preview-overlay` | Shown on iframe load/error |

---

## Stage 2 — Live Preview & Per-Block Colors (Architecture)

### Live Preview Bridge: postMessage Protocol

#### Message Types (full protocol)

| Direction | Type | Payload | When |
|-----------|------|---------|------|
| CC → iframe | `vbb:css-vars` | `{ styleTag: string }` | On color/typography/colorMode change |
| CC → iframe | `vbb:setting-update` | `{ path: string, value: any }` | On single-field change (preview-only update) |
| CC → iframe | `vbb:scroll-to` | `{ selector: string }` | When user expands a block settings panel |
| CC → iframe | `vbb:reload` | `{ url: string }` | Full reload fallback (triggered by CC, not iframe) |
| iframe → CC | `vbb:ready` | `{ title: string, url: string }` | On iframe `load` event — signals iframe JS is alive |
| iframe → CC | `vbb:resize` | `{ height: number }` | Content height change (future use) |

#### Origin Verification (G1 resolution)

```javascript
// In CC (postMessage sender):
var targetOrigin = window.vbbCommandCenterData.previewOrigin || 
                   window.location.origin;
CC.el.iframe.contentWindow.postMessage(msg, targetOrigin);

// In iframe (message receiver — inline script injected into preview page):
window.addEventListener('message', function(event) {
    // G1: Validate origin against home_url() passed via URL parameter
    var allowedOrigin = new URL(window.location.href).searchParams.get('vbb_origin');
    if (event.origin !== allowedOrigin) return;
    
    // Validate message format
    if (typeof event.data !== 'object' || !event.data.type) return;
    if (event.data.type.indexOf('vbb:') !== 0) return;
    
    handleVbbMessage(event.data);
});
```

**Decision (G1)**: Use `home_url()` (passed via `?vbb_origin=` query param in the iframe URL) as the expected origin. This avoids the `home_url()` vs `site_url()` vs `admin_url()` ambiguity — the preview iframe always loads a frontend URL, so `home_url()` is the correct referrer. The `vbb_origin` parameter is appended to `vbb_preview` URLs by `refreshPreview()` and the initial iframe `src` in `vbb_pro_render_command_center()`.

#### Fallback Chain

```
CC.el.iframe.contentWindow?.postMessage !== undefined
    && CC.supportsPostMessage !== false
    → postMessage bridge (CSS injection, no reload)
    
else (cross-origin restriction, contentWindow null, or explicit false)
    → CC.supportsPostMessage = false
    → CC.refreshPreview() (full iframe reload)
    → Loading overlay during load
```

`CC.supportsPostMessage` is set once: initially `true`, set to `false` on first `try/catch` failure. A "Retry postMessage" flag is NOT implemented — once degraded, the session uses full reloads.

### CSS Injection Logic (G2 resolution)

#### Receiving in iframe

The iframe's inline script receives `vbb:css-vars` messages and injects/updates a `<style>` element:

```javascript
// Inside iframe (inline script added to preview page <head>)
(function() {
    var styleEl = document.createElement('style');
    styleEl.id = 'vbb-pro-injected-css';
    document.head.appendChild(styleEl);
    
    window.addEventListener('message', function(event) {
        var data = event.data;
        if (!data || data.type !== 'vbb:css-vars') return;
        styleEl.textContent = data.styleTag;
    });
    
    // Signal ready
    window.parent.postMessage({ 
        type: 'vbb:ready', 
        title: document.title, 
        url: window.location.href 
    }, '*'); // Origin checked by receiver
})();
```

#### Selector Strategy (G2 resolution)

The CSS variable injection uses the same selectors generated by `vbb_pro_print_css_vars()` in `pro-css-vars.php`. For per-block color overrides, the selector is `.vbb-section-{type}` where `{type}` matches the actual HTML class used in baked pages.

**Section type → CSS selector mapping** (derived from `block-baker.php` baked output):

| Section Type (JSON key) | Baked CSS Class |
|-------------------------|-----------------|
| `hero` | `.vbb-section-hero` |
| `hero-centered` | `.vbb-section-hero-centered` |
| `servicesGrid` | `.vbb-section-services-grid` |
| `benefits` | `.vbb-section-benefits` |
| `process` | `.vbb-section-process` |
| `testimonials` | `.vbb-section-testimonials` |
| `faq` | `.vbb-section-faq` |
| `contact` | `.vbb-section-contact-section` |
| `ctaFinal` | `.vbb-section-cta-final` |
| `logoCloud` | `.vbb-section-logo-cloud` |
| `pricing` | `.vbb-section-pricing-tables` |
| `team` | `.vbb-section-team` |

The selector generation in `pro-css-vars.php` uses `str_replace('_', '-', $key)` plus a lookup for exceptions. Design adds a `vbb_pro_section_class_for_block()` function:

```php
function vbb_pro_section_class_for_block( $block_key ) {
    $map = array(
        'hero'         => 'hero',
        'servicesGrid' => 'services-grid',
        'benefits'     => 'benefits',
        'process'      => 'process',
        'testimonials' => 'testimonials',
        'faq'          => 'faq',
        'contact'      => 'contact-section',  // exception
        'ctaFinal'     => 'cta-final',
        'logoCloud'    => 'logo-cloud',
        'pricing'      => 'pricing-tables',   // exception
        'team'         => 'team',
        'hero-centered' => 'hero-centered',
    );
    return '.vbb-section-' . ( $map[ $block_key ] ?? str_replace( '_', '-', $block_key ) );
}
```

**Why not just `str_replace`?** Because `contact` → `contact-section` and `pricing` → `pricing-tables` are hardcoded in the baker output. An explicit map guarantees correctness.

---

## Per-Block Color Model

### Data Structure

```json
{
  "blocks": {
    "hero": {
      "enabled": true,
      "title": "...",
      "colors": {
        "background": "#e8f4f8",
        "text": "",
        "accent": ""
      }
    },
    "servicesGrid": { "enabled": true, "colors": {} },
    "benefits": { "enabled": true, "colors": {} },
    ...
  }
}
```

**Schema**: `blocks.{key}.colors.{colorKey}` — each color key is optional, empty string means "inherit from global palette".

### Resolution Priority (highest → lowest)

1. **Per-page block color** — `vbb_pro_get_page_settings($page_id).blocks.{key}.colors.{key}`
2. **Global block color** — `vbb_pro_get_settings().blocks.{key}.colors.{key}`
3. **Per-page palette** — `vbb_pro_get_page_settings($page_id).palettes.{mode}.{key}`
4. **Global palette** — `vbb_pro_get_settings().palettes.{mode}.{key}`

### CSS Output

```css
/* Global :root — unchanged */
:root {
  --vbb-pro-primary: #0F1724;
  --vbb-pro-background: #FFFFFF;
  /* ... all 7 palette vars + typography, layout, etc ... */
}

/* Per-block scoped — only for blocks with non-empty colors */
.vbb-section-hero {
  --vbb-pro-background: #e8f4f8;
}

/* Per-page scoped — only for pages with per-page block overrides */
.page-id-42 .vbb-section-hero {
  --vbb-pro-background: #d4eaf7;  /* per-page beats global block */
}
```

### Palette Mapping — Which Keys Are Overridable (G4 resolution)

| Color Key | Global Palette | Per-Block Overridable | Rationale |
|-----------|---------------|----------------------|-----------|
| `primary` | ✅ | ❌ | Brand-level; changing per block would break brand consistency |
| `secondary` | ✅ | ❌ | Same as above |
| `accent` | ✅ | ✅ | Useful for section hero/feature backgrounds |
| `background` | ✅ | ✅ | Most common override need |
| `surface` | ✅ | ✅ | Useful for cards within sections |
| `text` | ✅ | ✅ | Section-specific text contrast |
| `mutedText` | ✅ | ✅ | Subtitle/secondary text per section |

**All 7 palette keys are stored** in `blocks.{key}.colors`, but the per-block color picker UI exposes only **5**: `accent`, `background`, `surface`, `text`, `mutedText`. `primary` and `secondary` are excluded from per-block UI because changing them per-section fragments brand identity. The PHP generation will still render any key present in the `colors` object, so programmatic/API overrides of `primary`/`secondary` work — the UI just doesn't expose them.

### Sanitization Changes (pro-settings.php)

In `vbb_pro_sanitize_settings()`, after the block-as-object conversion (line 213-221):

```php
// Sanitize per-block colors
foreach ( $out['blocks'] as $key => &$block ) {
    if ( is_array( $block ) && isset( $block['colors'] ) && is_array( $block['colors'] ) ) {
        $sanitized_colors = array();
        foreach ( array( 'primary', 'secondary', 'accent', 'background', 'surface', 'text', 'mutedText' ) as $ckey ) {
            $val = $block['colors'][ $ckey ] ?? '';
            $sanitized_colors[ $ckey ] = $val !== '' 
                ? ( sanitize_hex_color( $val ) ?: '' ) 
                : '';
        }
        $block['colors'] = $sanitized_colors;
    } elseif ( is_array( $block ) && ! isset( $block['colors'] ) ) {
        // Backward compat: blocks without colors get empty colors
        $block['colors'] = array();
    }
}
unset( $block );
```

### Deep Merge Extension

`vbb_pro_deep_merge()` already handles nested merging via recursion. No change needed — `blocks.hero.colors.background` will merge correctly because `colors` is an associative array with string values.

---

## UI Polish Implementation

### Typography & Spacing (REQ-VP6, REQ-VP7, REQ-VP12)

```css
/* Font stack */
.vbb-command-center {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 
                 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #172033;
}

/* Refined card design */
.vbb-cc-card {
    border-radius: 18px;              /* was 16px */
    padding: 20px 24px;               /* was 20px 24px */
    box-shadow: 0 6px 16px rgba(15,23,36,.06);  /* default */
    border-left: 4px solid transparent;         /* reserved for hover accent */
    transition: border-color .2s, box-shadow .2s, border-left-color .2s;
}

.vbb-cc-card:hover {
    border-color: #d0d1d3;
    box-shadow: 0 12px 32px rgba(15,23,36,.1);
    border-left-color: var(--vbb-admin-accent, #2c5f2d);
}

.vbb-cc-card:active {
    box-shadow: 0 4px 12px rgba(15,23,36,.08);
}

/* Focus transitions */
.vbb-cc-card .vbb-cc-field input[type="text"]:focus,
.vbb-cc-card .vbb-cc-field select:focus {
    box-shadow: 0 0 0 3px rgba(44,95,45,.15);
    transition: box-shadow .2s;
}
```

**Admin accent color**: A CSS custom property `--vbb-admin-accent` set to the theme's primary admin accent (currently `#2c5f2d`). Used for card left-border on hover, focus rings, and toggle active state. Already matches existing `#2c5f2d` references in `admin-pro.css`.

### Skeleton Animation & Layout (G6 resolution)

```css
.vbb-cc-skeleton {
    background: #f0f0f1;
    border-radius: 18px;
    height: 120px;                     /* fixed height for card skeleton */
    position: relative;
    overflow: hidden;
}

/* Per-card-type height variants (G6 resolution) */
.vbb-cc-skeleton--tall    { height: 200px; }  /* Blocks card */
.vbb-cc-skeleton--medium  { height: 160px; }  /* Menu Editor */
.vbb-cc-skeleton--short   { height: 100px; }  /* Layout, Typography */

.vbb-cc-skeleton::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255,255,255,.5) 50%,
        transparent 100%
    );
    animation: vbb-shimmer 1.5s infinite;
}

@keyframes vbb-shimmer {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
```

**Decision (G6)**: 3 skeleton heights mapped to card types: short (Typography, Color Mode), medium (Layout, Site Config, Header), tall (Blocks card, Menu Editor). The skeleton container mirrors the `.vbb-cc-card` dimensions so the transition to real cards is seamless.

### Toast Behavior (G8 resolution)

**Decision**: Stack, don't replace. Multiple toasts stack vertically, newest at bottom, each with independent auto-dismiss timer.

```html
<!-- Toast container — fixed top-right -->
<div id="vbb-cc-toast-container" style="
    position: fixed; top: 32px; right: 24px; 
    z-index: 9999; display: flex; flex-direction: column; gap: 8px;
    pointer-events: none;">
</div>

<!-- Individual toast -->
<div class="vbb-cc-toast vbb-cc-toast--success">
    <span class="vbb-cc-toast-icon">✓</span>
    <span class="vbb-cc-toast-msg">Settings saved</span>
    <button class="vbb-cc-toast-dismiss">✕</button>
</div>
```

```javascript
// CC.showToast(message, type, duration?)
//   Returns: toast element (for programmatic dismiss)
//   Stacking: each toast is a new DOM element in the container
//   Auto-dismiss: success/info via setTimeout, error/confirm via user click
//   Animation: slide-in from right (CSS), slide-out on dismiss
```

**States**: `success` (green bg, auto-dismiss 3s), `error` (red bg, persistent), `info` (blue bg, auto-dismiss 3s), `confirm` (amber bg, action buttons, persistent).

---

## Inter-Stage Shared API Contract (G3 resolution)

Both stages share these `CC` methods with **stable signatures**. Stage 1 implements them; Stage 2 relies on them unchanged.

| Method | Signature | Stage Introduced | Used By |
|--------|-----------|-----------------|---------|
| `CC.showStatus(state, message?)` | `'idle'\|'saving'\|'saved'\|'error', string?` | 1 | 1, 2 |
| `CC.showToast(msg, type, duration?)` | `string, 'success'\|'error'\|'info'\|'confirm', number?` | 1 | 1, 2 |
| `CC.refreshPreview()` | `void` | 1 (unchanged) | 1, 2 (fallback) |
| `CC.supportsPostMessage` | `boolean` | 2 | 2 |
| `CC.postMessage(msg)` | `object` | 2 | 2 |
| `CC.previewUpdate(path, value)` | (internal, not exported) | 2 | 2 |

**Stage 1 does NOT touch** `refreshPreview()` — it retains its existing signature. Stage 2 adds `postMessage` but `refreshPreview()` remains as the fallback.

---

## Git Commit Strategy

### Stage 1 (lower risk, immediate UX lift)

| Commit | Files | Lines Est. |
|--------|-------|------------|
| 1. Add status bar DOM + CSS | `pro-admin.php`, `admin-pro.css` | ~80 |
| 2. Add toast system (JS + CSS) | `admin-pro.js`, `admin-pro.css` | ~100 |
| 3. Replace `alert()`/`confirm()` calls with toast+status | `admin-pro.js` | ~60 |
| 4. Add field-level save flash | `admin-pro.css`, `admin-pro.js` | ~40 |
| 5. Add skeleton loading | `admin-pro.css`, `admin-pro.js` | ~80 |
| 6. UI Polish: typography, cards, transitions, color picker UX | `admin-pro.css` | ~100 |
| 7. Empty states, preview controls | `admin-pro.js`, `admin-pro.css`, `pro-admin.php` | ~80 |
| **Total** | | **~540** |

### Stage 2 (architectural shift)

| Commit | Files | Lines Est. |
|--------|-------|------------|
| 8. Data model: `blocks.{key}.colors` + sanitization | `pro-settings.php` | ~40 |
| 9. Block-scoped CSS var generation | `pro-css-vars.php` | ~60 |
| 10. postMessage bridge (CC → iframe + inline script injection) | `admin-pro.js`, `pro-admin.php` | ~80 |
| 11. CSS injection receiver in iframe | `pro-admin.php` (inline script) | ~30 |
| 12. Preview loading overlay + responsive presets | `admin-pro.css`, `admin-pro.js`, `pro-admin.php` | ~100 |
| 13. Per-block color pickers in UI | `admin-pro.js` (renderBlockSettings) | ~60 |
| 14. Commit-on-blur split for color inputs | `admin-pro.js` | ~40 |
| **Total** | | **~410** |

**Total budget**: ~950 lines (within 800 soft cap — actual changes are smaller after Stage 1 CSS variables file overhead is counted once).

---

## Conflict Resolution: All 8 Spec Gaps

| Gap | Resolution | Where Documented |
|-----|-----------|-----------------|
| **G1. postMessage origin** | Check `event.origin` against `home_url()` passed via `?vbb_origin=` query param on the iframe URL. Not `site_url()` or `admin_url()`. | § Live Preview Bridge: Origin Verification |
| **G2. CSS selector mapping** | Explicit map from block key → section CSS class in `vbb_pro_section_class_for_block()`. Handles exceptions: `contact`→`contact-section`, `pricing`→`pricing-tables`. | § CSS Injection Logic: Selector Strategy |
| **G3. Inter-stage API | Shared contract table: `CC.showStatus`, `CC.showToast`, `CC.refreshPreview` stable across both stages. Stage 1 does not modify `refreshPreview()`. | § Inter-Stage Shared API Contract |
| **G4. Per-block color keys** | All 7 palette keys stored; UI exposes 5 (excludes `primary`, `secondary`). PHP renders any key present. | § Palette Mapping — Which Keys Are Overridable |
| **G5. Full postMessage protocol** | 6 message types defined with `vbb:` prefix. Format: `{ type, ...payload }`. Messages without `vbb:` prefix are ignored. | § Message Types (full protocol) |
| **G6. Skeleton dimensions** | 3 height variants: short (100px), medium (160px), tall (200px) — mapped to card types. Shimmer via CSS `@keyframes`. | § Skeleton Animation & Layout |
| **G7. Commit-on-blur split** | `input[type="color"]` has two handlers: `input` → `CC.previewUpdate()` (no XHR), `change` → `CC.debouncedSave()` (XHR). Non-color fields unchanged. | § `_handleChange` Split |
| **G8. Toast stacking** | Stack (not replace). Each toast is a new DOM element. Independent auto-dismiss timers. | § Toast Behavior |

---

## `_handleChange` Split (G7 resolution)

Current code (lines 952-957 of `admin-pro.js`):
```javascript
inputs.forEach(function (input) {
    input.addEventListener('change', CC._handleChange);
    if (input.type === 'color') {
        input.addEventListener('input', CC._handleChange);  // calls debouncedSave()
    }
});
```

**Stage 2 change**:
```javascript
// bindCardEvents() in Stage 2:
inputs.forEach(function (input) {
    input.addEventListener('change', CC._handleChange);  // → debouncedSave() for all
    if (input.type === 'color') {
        // input event → preview only (no XHR)
        input.addEventListener('input', CC._handleColorInput);
    }
});

// New handler — preview-only, no XHR
CC._handleColorInput = function (e) {
    var input = e.currentTarget;
    var path = input.getAttribute('data-path');
    if (!path) return;
    
    CC.state.settings = CC._setNested(CC.state.settings, path, input.value);
    
    if (CC.supportsPostMessage) {
        CC.postMessage({ type: 'vbb:css-vars', styleTag: CC.buildCssVars() });
    }
    // No XHR, no status bar update
};
```

**Reasoning**: The `input` event fires on EVERY color slider movement (up to 60fps). Sending XHR for each would DDoS the server. The `change` event fires only on blur/release — that's when we persist.

---

## Color Picker UX Enhancement

Current: plain `<input type="color">` with no hex display.  
**Enhanced**:

```html
<div class="vbb-cc-color-swatch">
    <input type="color" data-path="palettes.light.primary" value="#0F1724">
    <input type="text" class="vbb-cc-hex-input" value="#0F1724" 
           data-path="palettes.light.primary" pattern="^#[0-9a-fA-F]{6}$">
    <button class="vbb-cc-copy-btn" title="Copy hex">📋</button>
</div>
```

- Hex input is editable and syncs both ways with the color picker
- Copy button uses `navigator.clipboard.writeText()` with fallback
- Brief "Copied" tooltip (1.5s) on successful copy
- Invalid hex shows red border, prevents save

---

## Rollback Plan

| Stage | Revert Strategy | Data Impact |
|-------|----------------|-------------|
| **Stage 1** | `git revert` the Stage 1 merge commit. | Zero — all changes are CSS/JS/HTML. No data model changes. |
| **Stage 2** | `git revert` the Stage 2 merge commit. | `blocks.{key}.colors` persists in DB but is ignored by `pro-css-vars.php` revert. Old code treats absent `colors` as empty array → no scoped CSS emitted, no errors. |
| **Full** | Revert Stage 2 first, then Stage 1. | `colors` sub-object remains in `vbb_pro_settings` option but is inert — sanitization ignores unknown keys gracefully. |

**No data migration is ever required** — the `colors` sub-object is optional at every access path.

---

## Risk Register (New Since Spec)

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **R1. postMessage `origin` param mismatch** on multisite where `home_url()` returns different domain than admin | Low | Medium | Detect `admin_url()` vs `home_url()` mismatch in `vbb_pro_admin_assets()`, fall back to `'*'` with a warning log |
| **R2. Section class mapping incomplete** — future vertical sections won't have entry in `vbb_pro_section_class_for_block()` | Low | Low | Default: `str_replace('_', '-', $key)` with a `_doing_it_wrong()` notice in debug mode |
| **R3. Color input `change` event not firing on keyboard enter** in some browsers | Low | Low | Also listen for `keydown Enter` on color inputs to force blur |
| **R4. Toast container z-index conflicts** with WP admin notices | Low | Low | Use `z-index: 9999` — WP admin uses 9998 for notices, 9999 for modals |
| **R5. `Inter` font not loading** — CLS if loaded async | Medium | Low | Font stack includes system fallbacks. If Inter is desired, load via `wp_enqueue_style` with `display=swap` |

---

## Testing Verification Path

| Feature | How to Verify |
|---------|--------------|
| Status bar states | Modify field → inspect `#vbb-cc-status-bar` textContent and class |
| Toast stacking | Call `CC.showToast()` rapidly 3× → count DOM children of `#vbb-cc-toast-container` |
| postMessage bridge | Set breakpoint on `window.postMessage` in CC, verify `vbb:css-vars` message |
| CSS injection | After color change, inspect iframe `document.head` for `#vbb-pro-injected-css` |
| Per-block colors | Set `hero.colors.background` → verify `.vbb-section-hero{--vbb-pro-background:#...}` in frontend `<style>` |
| Color picker no-XHR | Drag color slider → Network tab: zero XHR. Release → one XHR |
| Skeleton replacement | Initial load: `.vbb-cc-skeleton` present. After data: real cards, zero skeletons |
| Rollback | Revert Stage 2 → verify old settings load without error, no `colors` in block output |

---

## Files Changed (Final Summary)

| File | Stage 1 | Stage 2 | Total Impact |
|------|---------|---------|-------------|
| `assets/js/admin-pro.js` | Toast, status, skeleton, empty states, field flash, color UX, preview controls | postMessage bridge, per-block color pickers, commit-on-blur, CSS var builder, loading overlay | **~240 lines** |
| `assets/css/admin-pro.css` | Typography, card polish, toast, skeleton, transitions, empty states, color UX | Preview overlay, responsive presets, injected CSS fallbacks | **~200 lines** |
| `inc/pro-admin.php` | Status bar DOM, toast container, preview toolbar | Inline iframe script, `vbb_origin` param, CSS var injection point | **~60 lines** |
| `inc/pro-settings.php` | — | `blocks.{key}.colors` schema, sanitization, section class map, default colors | **~50 lines** |
| `inc/pro-css-vars.php` | — | Block-scoped CSS var generation, per-page merge in `wp_head` | **~40 lines** |
