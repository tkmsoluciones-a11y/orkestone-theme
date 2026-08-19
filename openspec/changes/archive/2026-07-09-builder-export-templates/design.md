# Technical Design: Builder Export & Template Management

**Change**: builder-export-templates
**Status**: draft
**Next**: sdd-tasks
**Review Budget**: 800 lines (2 chained PRs: Stage 1 ≈ 300 lines, Stage 2 ≈ 500 lines)

---

## Executive Summary

Enable full round-trip of OrkestOne site configurations. A new REST endpoint (`GET /orkestone/v1/export`) returns global settings + per-page overrides + active profile in a schema-versioned JSON document. Section blocks gain a `style` field (default `'A'`) enabling visual variants. Three baker functions (hero, cta-final, testimonials) dispatch on `style` to produce different markup via shared helpers. A button-group style selector in each block's settings panel auto-triggers re-bake. The existing admin-post import handler is extended to consume the expanded export format.

**Delivery**: Two chained PRs. Stage 1 = Export (≈300 lines). Stage 2 = Styles (≈500 lines).

---

## Architectural Approach

### Data Flow: Export JSON Generator

The export merges three data sources:

```
                              ┌──────────────────────┐
                              │  vbb_pro_settings     │
                              │  (WP option)          │
                              └──────────┬───────────┘
                                         │ vbb_pro_get_settings()
                                         ▼
                              ┌──────────────────────┐
                              │ vbb_pro_default_     │
                              │ settings() +         │
                              │ sanitize()           │
                              └──────────┬───────────┘
                                         │
                    ┌────────────────────┼────────────────────┐
                    │                    │                    │
                    ▼                    ▼                    ▼
            ┌──────────────┐   ┌──────────────────┐   ┌──────────────┐
            │ settings     │   │ pageOverrides    │   │ activeProfile│
            │ (global)     │   │ (per-page deltas)│   │ (string|null)│
            └──────────────┘   └──────────────────┘   └──────────────┘
                    │                    │                    │
                    └────────────────────┼────────────────────┘
                                         │ wp_json_encode()
                                         ▼
                              ┌──────────────────────┐
                              │ Export document      │
                              │ { exportedAt,        │
                              │   schemaVersion,     │
                              │   theme,             │
                              │   customized: true,  │
                              │   settings,          │
                              │   pageOverrides,     │
                              │   activeProfile }    │
                              └──────────────────────┘
```

**Merge rules for export assembly**:

1. `settings` = full output of `vbb_pro_get_settings()` (global defaults + merged overrides)
2. `pageOverrides` = raw `get_option(VBB_PRO_PAGE_SETTINGS_OPTION, [])` filtered to **publish-only pages**
3. `activeProfile` = `get_option(VBB_PRO_ACTIVE_PROFILE_OPTION, null)`
4. Each block in `settings.blocks` carries a `style` field (default `'A'`)
5. `pageOverrides[pageId].blocks` may override `style` per-page

### Schema Envelope

```json
{
  "exportedAt": "2026-07-09 14:30:00",
  "schemaVersion": "1.0.0",
  "theme": "orkestOne",
  "customized": true,
  "settings": { "...": "..." },
  "pageOverrides": {
    "123": { "blocks": { "hero": { "title": "Custom", "style": "B" } } },
    "456": { "blocks": { "ctaFinal": { "text": "Different CTA" } } }
  },
  "activeProfile": "my-profile"
}
```

`schemaVersion` uses semver. The `"1.0.0"` version is the initial release. Future versions increment the minor when adding non-breaking fields, major for breaking changes. Import always proceeds regardless of version (forward-compat by ignoring unknown keys).

---

## Export REST API

### Endpoint: `GET /orkestone/v1/export`

**Route registration** (in `vbb_register_command_center_routes()`):

```php
register_rest_route('orkestone/v1', '/export', array(
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => 'vbb_rest_export_site',
    'permission_callback' => 'vbb_rest_command_center_permission',
));
```

**Callback function** `vbb_rest_export_site()`:

```php
function vbb_rest_export_site() {
    $settings = vbb_pro_get_settings();

    // Get raw per-page overrides, filter to published pages only
    $all_page_settings = get_option(VBB_PRO_PAGE_SETTINGS_OPTION, array());
    $published_ids     = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => -1,
    ));
    $page_overrides = array();
    foreach ($published_ids as $id) {
        if (isset($all_page_settings[$id])) {
            $page_overrides[(string) $id] = $all_page_settings[$id];
        }
    }

    $data = array(
        'exportedAt'    => current_time('mysql'),
        'schemaVersion' => '1.0.0',
        'theme'         => 'orkestOne',
        'customized'    => true,
        'settings'      => $settings,
        'pageOverrides' => (object) $page_overrides, // force {} when empty
        'activeProfile' => get_option(VBB_PRO_ACTIVE_PROFILE_OPTION, null),
    );

    return new WP_REST_Response($data, 200);
}
```

**Key decisions**:
- No `ob_start()` buffering needed — `WP_REST_Response` handles JSON serialization natively
- The browser download is handled entirely client-side (JS blob + `<a>` tag), NOT via PHP `Content-Disposition`
- `pageOverrides` is cast to `(object)` when empty so JSON encodes as `{}` not `[]`
- Permission callback uses the same `vbb_rest_command_center_permission()` as all other routes

### Response Headers

WordPress REST API adds JSON content-type automatically. No custom headers needed since download is client-side.

---

## Section Style Dispatch

### Data Model

Each block in `vbb_pro_default_settings()` gets a `style` field:

```php
// In vbb_pro_default_settings():
$blocks[$bk] = array(
    'enabled' => true,
    'style'   => 'A', // NEW
    'colors'  => array(),
);
```

### Sanitization

In `vbb_pro_sanitize_settings()`, after the block loop, add:

```php
// Style validation — only A, B, C allowed
$allowed_styles = array('A', 'B', 'C');
foreach ($out['blocks'] as $bk => &$block) {
    if (is_array($block)) {
        $style = isset($block['style']) ? $block['style'] : 'A';
        $block['style'] = in_array((string) $style, $allowed_styles, true) ? (string) $style : 'A';
    }
}
unset($block);
```

### Style Dispatch in `vbb_bake_section()`

The `vbb_bake_section()` dispatcher merges section data (which includes `style` from both global and per-page overrides via the existing `array_merge`). No changes needed to the dispatcher itself — the `style` field is already part of `$data`.

### Style Variant Markup Definitions (Resolving G1)

#### Hero Section

| Style | Structure | Classes | Layout |
|-------|-----------|---------|--------|
| **A** (current) | Two columns: image left, content right | `vbb-section-hero vbb-style-a` | `wp:columns` with 2 columns, eyebrow + heading + subtitle + button in right column |
| **B** (centered) | Single column centered with background pattern overlay | `vbb-section-hero vbb-style-b` | Full-width group, single centered column, heading + subtitle stacked, button below, `vbb-hero-bg` div with overlay class |
| **C** (full-bleed) | Full-bleed background image, content overlaid left | `vbb-section-hero vbb-style-c` | Full-width group with `min-height:70vh`, content column left-aligned (60% width), larger heading, minimal layout |

**Style B markup** (hero):
```html
<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero vbb-style-b","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section vbb-section-hero vbb-style-b has-accent-background-color has-background">
  <div class="vbb-hero-bg-overlay"></div>
  <!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"720px"}} -->
  <div class="wp-block-group alignwide">
    <!-- wp:paragraph {"align":"center","className":"vbb-eyebrow"} -->
    <p class="has-text-align-center vbb-eyebrow">{{vbb_hero_eyebrow}}</p>
    <!-- /wp:paragraph -->
    <!-- wp:heading {"textAlign":"center","level":1,"fontSize":"x-large"} -->
    <h1 class="wp-block-heading has-text-align-center has-x-large-font-size">{{vbb_hero_title}}</h1>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"align":"center","fontSize":"large"} -->
    <p class="has-text-align-center has-large-font-size">{{vbb_hero_subtitle}}</p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons">
      <?php echo vbb_render_cta_button('{{vbb_hero_cta_text}}', '{{vbb_hero_cta_url}}', 'primary'); ?>
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
```

**Style C markup** (hero):
```html
<!-- wp:group {"align":"full","className":"vbb-section vbb-section-hero vbb-style-c","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section vbb-section-hero vbb-style-c" style="min-height:70vh;">
  <?php if ($image_url): ?>
  <div class="vbb-hero-bg-image" style="background-image:url('<?php echo $image_url; ?>')"></div>
  <?php endif; ?>
  <div class="vbb-hero-overlay"></div>
  <!-- wp:columns {"align":"wide","verticalAlignment":"center"} -->
  <div class="wp-block-columns alignwide">
    <!-- wp:column {"width":"60%"} -->
    <div class="wp-block-column" style="flex-basis:60%;z-index:2;">
      <!-- wp:paragraph {"className":"vbb-eyebrow"} -->
      <p class="vbb-eyebrow">{{vbb_hero_eyebrow}}</p>
      <!-- /wp:paragraph -->
      <!-- wp:heading {"level":1,"fontSize":"xx-large"} -->
      <h1 class="wp-block-heading has-xx-large-font-size">{{vbb_hero_title}}</h1>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"fontSize":"large"} -->
      <p class="has-large-font-size">{{vbb_hero_subtitle}}</p>
      <!-- /wp:paragraph -->
      <!-- wp:buttons -->
      <div class="wp-block-buttons">
        <?php echo vbb_render_cta_button('{{vbb_hero_cta_text}}', '{{vbb_hero_cta_url}}', 'outline'); ?>
      </div>
      <!-- /wp:buttons -->
    </div>
    <!-- /wp:column -->
    <!-- wp:column {"width":"40%"} -->
    <div class="wp-block-column" style="flex-basis:40%;"></div>
    <!-- /wp:column -->
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
```

#### CTA-Final Section

| Style | Structure | Classes |
|-------|-----------|---------|
| **A** (current) | Full-width primary background, centered heading + button | `vbb-section-cta-final vbb-style-a` |
| **B** (split) | Two columns: heading left, button right on accent background | `vbb-section-cta-final vbb-style-b` |
| **C** (card) | Contained card with border radius, heading + description + button | `vbb-section-cta-final vbb-style-c` |

**Style B markup** (cta-final):
```html
<!-- wp:group {"align":"full","className":"vbb-section vbb-section-cta-final vbb-style-b","backgroundColor":"accent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section vbb-section-cta-final vbb-style-b has-accent-background-color has-background">
  <!-- wp:columns {"align":"wide","verticalAlignment":"center"} -->
  <div class="wp-block-columns alignwide">
    <!-- wp:column {"verticalAlignment":"center"} -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":2} -->
      <h2 class="wp-block-heading">{{vbb_cta_final_text}}</h2>
      <!-- /wp:heading -->
    </div>
    <!-- /wp:column -->
    <!-- wp:column {"verticalAlignment":"center","width":"auto"} -->
    <div class="wp-block-column" style="flex-basis:auto;">
      <?php echo vbb_render_cta_button('{{vbb_cta_final_button_text}}', '{{vbb_cta_final_button_url}}', 'secondary'); ?>
    </div>
    <!-- /wp:column -->
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
```

**Style C markup** (cta-final):
```html
<!-- wp:group {"align":"wide","className":"vbb-section vbb-section-cta-final vbb-style-c","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide vbb-section vbb-section-cta-final vbb-style-c">
  <!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px"}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"640px"}} -->
  <div class="wp-block-group has-surface-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);">
    <!-- wp:heading {"textAlign":"center","level":2} -->
    <h2 class="wp-block-heading has-text-align-center">{{vbb_cta_final_text}}</h2>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"align":"center"} -->
    <p class="has-text-align-center">{{vbb_cta_final_subtitle}}</p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons">
      <?php echo vbb_render_cta_button('{{vbb_cta_final_button_text}}', '{{vbb_cta_final_button_url}}', 'primary'); ?>
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
```

#### Testimonials Section

| Style | Structure | Classes |
|-------|-----------|---------|
| **A** (current) | Stacked `wp:quote` blocks on accent background | `vbb-section-testimonials vbb-style-a` |
| **B** (grid) | Three-column grid of card-style testimonial blocks with avatar + rating | `vbb-section-testimonials vbb-style-b` |
| **C** (featured) | Single large featured testimonial with image, smaller supporting quotes below | `vbb-section-testimonials vbb-style-c` |

**Style B markup** (testimonials):
```html
<!-- wp:group {"align":"full","className":"vbb-section vbb-section-testimonials vbb-style-b","backgroundColor":"background","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section vbb-section-testimonials vbb-style-b has-background-background-color has-background">
  <!-- wp:heading {"textAlign":"center"} -->
  <h2 class="wp-block-heading has-text-align-center">{{vbb_testimonials_heading}}</h2>
  <!-- /wp:heading -->
  <!-- wp:columns {"align":"wide"} -->
  <div class="wp-block-columns alignwide">
    <?php foreach (array_slice($items, 0, 3) as $item): ?>
    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"style":{"spacing":{"padding":"20px"},"border":{"radius":"12px"}},"backgroundColor":"surface","className":"vbb-testimonial-card"} -->
      <div class="wp-block-group vbb-testimonial-card has-surface-background-color has-background" style="border-radius:12px;padding:20px;">
        <?php if (!empty($item['avatar'])): ?>
        <!-- wp:image {"width":48,"height":48,"sizeSlug":"thumbnail","className":"vbb-testimonial-avatar"} -->
        <figure class="wp-block-image size-thumbnail is-resized vbb-testimonial-avatar"><img src="<?php echo $item['avatar']; ?>" alt="" style="width:48px;height:48px;border-radius:999px;"/></figure>
        <!-- /wp:image -->
        <?php endif; ?>
        <?php if (!empty($item['rating'])): ?>
        <!-- wp:paragraph {"className":"vbb-testimonial-stars"} -->
        <p class="vbb-testimonial-stars"><?php echo str_repeat('★', (int)$item['rating']); ?></p>
        <!-- /wp:paragraph -->
        <?php endif; ?>
        <!-- wp:paragraph -->
        <p><?php echo vbb_esc_text($item['quote']); ?></p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"fontSize":"small"} -->
        <p class="has-small-font-size"><strong><?php echo vbb_esc_text($item['author']); ?></strong></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
    <?php endforeach; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
```

**Style C markup** (testimonials) — featured + supporting:
```html
<!-- wp:group {"align":"full","className":"vbb-section vbb-section-testimonials vbb-style-c","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull vbb-section vbb-section-testimonials vbb-style-c">
  <!-- wp:heading {"textAlign":"center"} -->
  <h2 class="wp-block-heading has-text-align-center">{{vbb_testimonials_heading}}</h2>
  <!-- /wp:heading -->
  <!-- wp:columns {"align":"wide"} -->
  <div class="wp-block-columns alignwide">
    <!-- wp:column {"width":"60%"} -->
    <div class="wp-block-column" style="flex-basis:60%;">
      <!-- Large featured quote -->
      ...featured quote with larger image...
    </div>
    <!-- /wp:column -->
    <!-- wp:column {"width":"40%"} -->
    <div class="wp-block-column" style="flex-basis:40%;">
      <!-- Supporting quotes (2 smaller) -->
      ...
    </div>
    <!-- /wp:column -->
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
```

### Shared Helpers (Resolving G2)

```php
/**
 * Render a Gutenberg button block with specified style.
 *
 * @param string $text  Button label text (may contain {{vbb_*}} placeholder).
 * @param string $url   Button URL (may contain {{vbb_*}} placeholder).
 * @param string $style Button appearance: 'primary'|'secondary'|'outline'.
 * @return string Gutenberg button block markup.
 */
function vbb_render_cta_button( $text, $url, $style = 'primary' ) {
    if ('' === $text || '' === $url) {
        return '';
    }
    
    $bg_color  = 'primary' === $style ? 'primary' : ('secondary' === $style ? 'secondary' : '');
    $text_clr  = 'primary' === $style ? 'base' : ('secondary' === $style ? 'contrast' : 'primary');
    $class     = 'outline' === $style ? 'is-style-outline' : '';
    
    $attrs = array();
    if ($bg_color) {
        $attrs['backgroundColor'] = $bg_color;
    }
    if ($text_clr) {
        $attrs['textColor'] = $text_clr;
    }
    $attrs_json = !empty($attrs) ? ' ' . wp_json_encode($attrs) : '';
    
    return '<!-- wp:button' . $attrs_json . ' -->'
        . "\n" . '<div class="wp-block-button' . ($class ? ' ' . $class : '') . '">'
        . '<a class="wp-block-button__link wp-element-button' . ($bg_color ? ' has-' . $bg_color . '-background-color has-background' : '') . ($text_clr ? ' has-' . $text_clr . '-color has-text-color' : '') . '" href="' . esc_url($url) . '">' . esc_html($text) . '</a>'
        . '</div>'
        . "\n" . '<!-- /wp:button -->';
}

/**
 * Render a Gutenberg heading block.
 *
 * @param string $text  Heading text (may contain {{vbb_*}} placeholder).
 * @param int    $level Heading level (1-6, default 2).
 * @param string $align Text alignment: 'left'|'center'|'right'.
 * @return string Gutenberg heading block markup.
 */
function vbb_render_heading_block( $text, $level = 2, $align = 'left' ) {
    if ('' === $text) {
        return '';
    }
    
    $level    = max(1, min(6, (int) $level));
    $align_cl = 'center' === $align ? ' has-text-align-center' : ('right' === $align ? ' has-text-align-right' : '');
    
    return '<!-- wp:heading {"level":' . $level . ',"textAlign":"' . esc_attr($align) . '"} -->'
        . "\n" . '<h' . $level . ' class="wp-block-heading' . $align_cl . '">' . esc_html($text) . '</h' . $level . '>'
        . "\n" . '<!-- /wp:heading -->';
}
```

**Consumers**:
- `vbb_render_cta_button()` called from: `vbb_bake_hero()` (all styles), `vbb_bake_cta_final()` (all styles)
- `vbb_render_heading_block()` called from: `vbb_bake_testimonials()` (all styles), `vbb_bake_cta_final()` (style C)

---

## UI Implementation

### Style Selector Button Group

In `renderBlockSettings()` (JS), add the style selector after the heading field and before per-block colors:

```html
<div class="vbb-cc-style-selector">
  <label>Section Style</label>
  <div class="vbb-cc-style-buttons">
    <button class="vbb-cc-style-btn" data-style="A" data-path="blocks.{key}.style">A</button>
    <button class="vbb-cc-style-btn" data-style="B" data-path="blocks.{key}.style">B</button>
    <button class="vbb-cc-style-btn" data-style="C" data-path="blocks.{key}.style">C</button>
  </div>
</div>
```

**Behavior**:
- Active style button gets class `vbb-cc-style-btn--active`
- Clicking a style button:
  1. Saves current field value (preserve unsaved text)
  2. Updates `CC.state.settings.blocks.{key}.style`
  3. Shows confirmation toast: "Style change will regenerate this page. Any manual edits will be lost."
  4. On confirm → `CC.debouncedSave()` → on success → fires `POST /pages/{page_id}/regenerate`
  5. On cancel → reverts style to previous value

**Implementation in `renderBlockSettings()`** (JS addition):
```javascript
// After heading/subtitle fields, before block colors
html += '<div class="vbb-cc-style-selector">';
html += '<label>Section Style</label>';
html += '<div class="vbb-cc-style-buttons">';
var currentStyle = block.style || 'A';
['A', 'B', 'C'].forEach(function (s) {
    var active = s === currentStyle ? ' vbb-cc-style-btn--active' : '';
    html += '<button class="vbb-cc-style-btn' + active + '" data-style="' + s + '" data-path="blocks.' + key + '.style">' + s + '</button>';
});
html += '</div></div>';
```

### Export Button in Command Center

Add to the `.vbb-cc-toolbar` div (between "Save as Profile" and "Regenerate Pages"):

```html
<button class="button" id="vbb-cc-export">Export Site</button>
```

**JS handler**:
```javascript
// In CC.init() bindings:
if (CC.el.exportBtn) {
    CC.el.exportBtn.addEventListener('click', CC.exportSite);
}

// New method:
exportSite: function (e) {
    if (e) e.preventDefault();
    CC.showStatus('saving', 'Preparing export\u2026');
    CC.xhr(
        CC.state.ajaxUrl + 'export',
        'GET',
        null,
        function (data) {
            CC.showStatus('saved', 'Export ready');
            var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'orkestone-export-' + new Date().toISOString().replace(/[:.]/g, '').slice(0, 15) + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            CC.showToast('Export downloaded successfully!', 'success');
        },
        function () {
            CC.showStatus('error', 'Export failed');
            CC.showToast('Export failed. Check server logs.', 'error');
        }
    );
}
```

---

## Auto-Rebake Strategy (Resolving G4)

### Scope Decision

| Change Type | Scope | Trigger |
|-------------|-------|---------|
| **Per-page style change** (user is on a specific page) | Re-bake THAT page only | `POST /pages/{page_id}/regenerate` |
| **Global style change** (user is on "Global Settings") | Re-bake ALL pages | Show confirmation: "This style change will regenerate ALL pages. Continue?" → `POST /regenerate-pages` |

### Implementation

After a style field save succeeds via the REST API, the `saveSettings()` callback checks if the changed path includes `.style`:

```javascript
// In saveSettings callback:
if (typeof callback === 'function') {
    callback(data);
}
// After save, check if style changed
if (CC._lastChangedPath && CC._lastChangedPath.indexOf('.style') > -1) {
    if (CC.state.currentPageId) {
        // Per-page: regenerate just this page
        CC.showConfirmToast(
            'Style change will regenerate this page. Any manual edits will be lost.',
            function () {
                CC.xhr(
                    CC.state.ajaxUrl + 'pages/' + CC.state.currentPageId + '/regenerate',
                    'POST',
                    null,
                    function () {
                        CC.showToast('Page regenerated with new style.', 'success');
                        CC.refreshPreview();
                    }
                );
            },
            function () {
                // Cancel: revert style to previous value
                // (handled by re-loading settings from server)
                CC.loadSettings(CC.state.currentPageId);
            }
        );
    } else {
        // Global: regenerate all pages
        CC.showConfirmToast(
            'This style change will regenerate ALL pages. Continue?',
            function () {
                CC.xhr(
                    CC.state.ajaxUrl + 'regenerate-pages',
                    'POST',
                    null,
                    function () {
                        CC.showToast('All pages regenerated with new style.', 'success');
                        CC.refreshPreview();
                    }
                );
            },
            function () {
                CC.loadSettings(); // revert
            }
        );
    }
}
```

---

## Import Extension

### Import Handler Changes (pro-admin.php)

Extend the existing `import_json` action handler:

```php
if ('import_json' === $action && !empty($_FILES['proJson']['tmp_name'])) {
    $raw  = file_get_contents($_FILES['proJson']['tmp_name']);
    $data = json_decode($raw, true);
    if (is_array($data)) {
        // Always restore global settings
        $settings = isset($data['settings']) ? $data['settings'] : $data;
        vbb_pro_update_settings($settings);
        
        // Restore per-page overrides if present (schema >= 1.0.0)
        if (isset($data['pageOverrides']) && is_array($data['pageOverrides'])) {
            $existing = get_option(VBB_PRO_PAGE_SETTINGS_OPTION, array());
            foreach ($data['pageOverrides'] as $page_id => $overrides) {
                $page_id = (int) $page_id;
                if ($page_id < 1) {
                    continue; // skip invalid keys
                }
                // Deep-merge with existing per-page settings (G7 resolution)
                $existing[$page_id] = vbb_pro_deep_merge(
                    isset($existing[$page_id]) ? $existing[$page_id] : array(),
                    $overrides
                );
            }
            update_option(VBB_PRO_PAGE_SETTINGS_OPTION, $existing, false);
        }
        
        add_settings_error('vbb_pro_elite', 'imported', 'Configuración Pro Elite importada.', 'updated');
    } else {
        add_settings_error('vbb_pro_elite', 'import_error', 'El JSON no es válido.', 'error');
    }
}
```

### Merge Strategy for pageOverrides (Resolving G7)

**Decision: Deep merge per-page entry**.

If existing has `{blocks: {hero: {style: "B"}}}` and import has `{blocks: {hero: {title: "New"}}}` → result is `{blocks: {hero: {style: "B", title: "New"}}}`.

This preserves existing overrides not present in the import. Full replace would lose data.

---

## Conflict Resolution: All Spec Gaps

| Gap | Resolution |
|-----|------------|
| **G1: Style variants content** | Defined above — exact markup for Hero, CTA-Final, Testimonials Style B and C with classes, structure, and Gutenberg block comments. |
| **G2: Shared helper signatures** | Defined above — `vbb_render_cta_button($text, $url, $style)` and `vbb_render_heading_block($text, $level, $align)`. |
| **G3: Style confirmation UX** | Toast-based confirm using existing `CC.showConfirmToast()`. Applies to BOTH global and per-page style changes. |
| **G4: Auto-rebake scope** | Per-page style change → re-bake that page only. Global style change → re-bake ALL pages. |
| **G5: Export REST vs admin-post** | **Both coexist**. REST export (`1.0.0`) serves the Command Center "Export" button. Legacy admin-post export (`0.3.2`) unchanged. They return different schemas intentionally — this is acceptable because the admin-post export is a settings-only snapshot, while the REST export is a full-site export. |
| **G6: REST import endpoint** | **Deferred**. No REST import endpoint in Stage 1. Import continues via admin-post handler only. A REST import could be added later if round-trip from a different UI is needed. |
| **G7: pageOverrides merge strategy** | **Deep merge per-page entry**, not wholesale replace. Existing entries not in import are preserved. |
| **G8: Export filename format** | `orkestone-export-YYYYMMDD_HHmmss.json` — using `YYYYMMDD_HHmmss` format from `current_time()` or JS `toISOString()`. |
| **G9: pageOverrides key format** | JSON encodes PHP associative arrays with integer keys as string keys (`"123"`). Casting `$page_id` to `(string)` in PHP ensures clean output. No issue. |

---

## Rollback Plan

### Stage 1 (Export)

1. Remove route registration for `/export` in `pro-rest-api.php` and the `vbb_rest_export_site()` function
2. Remove "Export" button HTML and JS handler in `admin-pro.js`
3. Revert import handler changes in `pro-admin.php` to original (no `pageOverrides` parsing)
4. Result: Legacy export/import continues to work with `schemaVersion: "0.3.2"`

### Stage 2 (Styles)

1. Remove `style` field default from `vbb_pro_default_settings()` in `pro-settings.php`
2. Remove `style` sanitization from `vbb_pro_sanitize_settings()`
3. Revert baker functions to original dispatch (no `switch ($style)`, no shared helper calls)
4. Remove shared helper functions `vbb_render_cta_button()` and `vbb_render_heading_block()` from `block-baker.php`
5. Remove style selector HTML/JS from `renderBlockSettings()` in `admin-pro.js`
6. Remove auto-rebake logic from `saveSettings()` callback
7. Result: Existing profiles without `style` field merge safely (defaults to `'A'` silently ignored)

---

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| **Style field not propagated to baker** after deep-merge with per-page settings | Low | `vbb_bake_section()` already passes merged `$data` — style is part of that merge. Verified in code review. |
| **Export JSON schema drift** from vertical schema | Low | Export deliberately uses a different top-level shape (`customized`, `pageOverrides`). The `settings` object mirrors `vbb_pro_get_settings()`, not the vertical JSON. |
| **Re-bake on global style change is slow** with many pages | Medium | Show "Regenerating all pages…" status. The existing `vbb_pro_regenerate_all_pages()` already handles this. No new performance bottleneck. |
| **Style confirmation cancel doesn't fully revert** if save already persisted | Medium | On cancel, reload settings from server (`CC.loadSettings()`). This overwrites local state with server state. Style will revert to previously-saved value. |
| **Import mapping page IDs across installs** | Low | Per-page overrides are keyed by WP page ID, which WILL differ between installs. This is an accepted limitation — import targets must manually reassign overrides to matching pages. Future enhancement could add slug-based matching. |

---

## Success Gates (for verification)

- [ ] `GET /orkestone/v1/export` returns 200 with valid JSON, includes `exportedAt`, `schemaVersion: "1.0.0"`, `theme`, `customized`, `settings`, `pageOverrides`, `activeProfile`
- [ ] `pageOverrides` contains only published pages, is `{}` when empty
- [ ] Clicking "Export" in Command Center triggers JSON file download with correct filename
- [ ] Legacy export (`admin-post`) still produces `schemaVersion: "0.3.2"` format unchanged
- [ ] Import of new format restores both global settings and per-page overrides
- [ ] Import of legacy format (`0.3.2`) still works (backward compat)
- [ ] Every block in fresh install has `style: 'A'` by default
- [ ] Invalid style values sanitized to `'A'`
- [ ] Style A → B → C produces different baked markup for hero, cta-final, testimonials
- [ ] Style selector appears as button-group in `renderBlockSettings()` with A/B/C buttons
- [ ] Style change confirmation dialog appears and revert works on cancel
- [ ] Per-page style change → re-bakes one page; Global style change → re-bakes all pages
- [ ] `vbb_render_cta_button()` and `vbb_render_heading_block()` exist and are used by ≥2 baker functions
- [ ] R1-R15 regression areas pass (see spec)
