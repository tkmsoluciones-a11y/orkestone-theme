# Exploration: Builder Visual Polish (UI/UX)

**Change**: builder-visual-polish
**Status**: completed
**Next Recommended**: sdd-propose

---

## Executive Summary

The Command Center UI is fully functional after the builder-completion SDD change — it can load pages, read/write settings via REST API, manage menu items, render a live preview iframe, and regenerate page content. However, the user experience is **strictly functional**: there are no save indicators, no loading states, the preview iframe does a full-page flash on every change, colors are entirely global with no per-block control, and the UI typography/spacing/layout lacks professional polish.

**The North Star**: A Command Center that feels like a modern single-page app — instant visual feedback on every action, a live preview that updates without full reloads, per-block color control for granular design, and a polished UI with proper typography, spacing, transitions, and visual hierarchy. Every interaction should communicate its state clearly: loading, saving, saved, error.

---

## Technical Findings

### 1. Visual Feedback — Current State

#### Save Flow (admin-pro.js:167-199)
```
onFieldChange() → debouncedSave() [500ms debounce] → saveSettings() → XHR POST
                                                                        ↓
                                                          Success: refreshPreview()
                                                          Error: alert() ❌
```

**Key observations**:
- **No visual feedback during save**: When a user changes a color picker, the `input` event fires, `onFieldChange` runs, `debouncedSave()` is called, but there is ZERO UI feedback. No spinner, no dimming, no "Saving..." text. The save happens in the background silently.
- **Error handling uses `alert()`**: If the XHR fails, `alert(msg)` is called — a native browser dialog that blocks the UI and feels unprofessional.
- **Success is invisible**: On success, `saveSettings()` only calls `refreshPreview()`. There is no toast, no checkmark, no brief "Saved!" indicator. The only visual change is the iframe reloading.
- **Menu save HAS a status indicator** (`#vbb-cc-menu-status`), but the main settings save does not. Inconsistent UX.
- **Loading states**: The initial load shows "Loading Command Center…" but individual card loading is not distinguished. There's no skeleton/shimmer pattern.
- **Regenerate action**: Uses `alert()` for both success and failure (admin-pro.js:83-97). Inconsistent with the admin notice pattern used in PHP.
- **Timing**: The 500ms debounce is reasonable, but without UI feedback the user has no idea if their change was registered.

#### Missing Feedback Points
| Action | Feedback | Issue |
|--------|----------|-------|
| Changing a field | None | No confirmation the save is queued |
| Save in progress | None | No spinner or "Saving…" indicator |
| Save success | None (menu: ✓ indicator) | No toast, no checkmark |
| Save error | `alert()` dialog | Blocks UI, browser-native |
| Loading settings | "Loading…" text | No skeleton, no shimmer |
| Regenerating pages | `alert()` dialog | Blocks UI |
| Page switch | None during XHR | No loading state on cards |

### 2. Live Preview Integration — Current State

#### Preview Flow (admin-pro.js:1003-1011)
```javascript
refreshPreview: function () {
    var baseUrl = CC.state.previewUrl.split('?')[0];
    var ts = new Date().getTime();
    CC.el.iframe.src = baseUrl + '?vbb_preview=' + ts + '&vbb_no_admin=1';
}
```

**Key observations**:
- **Full iframe reload on EVERY change**: Every debounced save triggers `refreshPreview()`, which completely reloads the iframe. This causes a white flash and reset of any JS state inside the preview.
- **Preview URL is the site homepage**: The iframe loads `home_url('/')` with `?vbb_preview={timestamp}`. This shows the ENTIRE site, not the specific page being edited. The user may be editing page-specific settings but seeing the homepage preview.
- **Page-specific preview**: `onPageChange()` correctly sets the iframe to `/?p={pageId}&vbb_preview=...` — this is better, but still a full reload.
- **No loading indicator during iframe refresh**: The iframe's `load` event is not used to show a loading state.
- **No postMessage bridge**: There's no `window.postMessage` communication between the Command Center and the preview iframe. The two are completely isolated. This means we cannot do partial updates or inform the preview of what changed.
- **No debounce on preview refresh**: Every debouncedSave() call triggers a preview refresh. If the user drags a color picker, this fires many times. The `input` event on color inputs already triggers `_handleChange`, which calls `debouncedSave()`, which calls `saveSettings()`, which calls `refreshPreview()`. So every color slider movement triggers a full save + full preview reload.

#### Preview Performance Profile
```
Color slider drag (10 events):
  → 10x onFieldChange()
  → 10x debouncedSave() (only last fires, after 500ms)
  → 1x saveSettings() XHR POST (~200-400ms)
  → 1x refreshPreview() iframe reload (~800-2000ms)
  Total perceived latency: ~1.5-3s per drag interaction
```

### 3. Per-Block Color Overrides — Current Architecture

#### Color Data Model
```
Global settings (vbb_pro_settings):
  palettes:
    light: { primary, secondary, accent, background, surface, text, mutedText }
    dark:  { primary, secondary, accent, background, surface, text, mutedText }

Per-page settings (vbb_pro_page_settings):
  [page_id]: { ... }  // Merged with global via vbb_pro_deep_merge()

CSS Variable generation (pro-css-vars.php):
  vbb_pro_print_css_vars():
    → Reads only global vbb_pro_get_settings()
    → Generates :root { --vbb-pro-primary, --vbb-pro-secondary, ... }
    → No per-page or per-block discrimination
```

**Key observations**:
- Colors are **flat global values** — one palette for the entire site
- `vbb_pro_print_css_vars()` runs in `wp_head` and outputs ONE set of CSS variables for the entire site
- Block-specific settings exist for **content** (title, subtitle, heading) but NOT for **colors**
- The per-page settings merge (`vbb_pro_get_page_settings()`) exists but is NOT used by the CSS variable generator
- `body_class` includes `vbb-block-{name}-on/off` classes per block, but no per-block color classes
- The `pro-css-vars.php` does NOT read from `vbb_pro_get_page_settings()` — only from `vbb_pro_get_settings()` (global)

#### Extension Strategy Requirements
To support per-block color overrides, we need:
1. **Data model extension**: Allow `blocks.{key}.colors.{primary,secondary,...}` in both global and per-page settings
2. **CSS variable scope change**: Either generate scoped CSS (e.g., `.vbb-section-hero { --vbb-pro-primary: ... }`) or emit block-specific CSS variables
3. **UI integration**: Add color pickers per block in the admin-pro.js card renderers
4. **Preview integration**: The preview iframe needs to receive the new CSS variables

### 4. UI Layout & Polish — Current Assessment

#### CSS Architecture (admin-pro.css, 211 lines total)
The CSS is **already quite well-structured** for a functional MVP:
- Clean BEM-like naming (`.vbb-cc-*`, `.vbb-pro-*`)
- Responsive breakpoints at 1200px, 900px, 600px
- CSS Grid layout for the main 2-column layout
- Card patterns with hover states
- Toggle switch styling
- Animated slide-down for block settings

#### Low-Hanging Fruit for Polish

| Area | Current State | Opportunity |
|------|--------------|-------------|
| **Typography** | Uses WP admin defaults (~13px system font) | Define a custom font stack for the admin UI |
| **Spacing** | Consistent but utilitarian (20-24px gaps) | Refine spacing scale, add breathing room |
| **Card shadows** | `0 6px 16px rgba(15,23,36,.06)` — subtle but flat | Layer shadows: hover → interactive → elevated |
| **Color inputs** | Plain grid, no labels with color preview | Show hex values, add contrast indicators |
| **Select inputs** | Basic WP styling | Custom dropdown styling, consistent with theme |
| **Loading states** | Text only: "Loading…" | Skeleton screens or shimmer placeholders |
| **Transitions** | Only on card hover (0.2s) | Add transitions on field focus, color change, save states |
| **Accent colors** | None in admin UI | Use the theme's primary/secondary for UI accents |
| **Preview iframe** | Fixed 520px height | Resizable, full-height option, responsive preview sizes |
| **Save indicator** | None | Toast component or persistent status bar |
| **Error states** | `alert()` dialog | Inline error messages or toast notifications |
| **Empty states** | Basic "No menu items" text | Illustrated empty states with CTAs |
| **Mobile layout** | Preview jumps to top on mobile | Better responsive card layout |
| **Scroll behavior** | Basic | Smooth scroll, sticky sidebar adjustment |

---

## Proposed Improvements

### A. Visual Feedback System

#### A1. Save Status Indicator Component
Add a persistent save status bar below the page selector (or in the toolbar):
- **States**: `idle` (hidden), `saving` (shows spinner + "Saving…"), `saved` (shows checkmark + "Saved" for 2s, then fades), `error` (shows error message with retry button)
- **Implementation**: New DOM element `<div id="vbb-cc-status-bar">` rendered in `pro-admin.php` or dynamically created in JS
- **JS**: Add `CC.showStatus(state, message)` method that manages the status bar

#### A2. Per-Field Save Feedback
Replace silent `debouncedSave()` with:
- Briefly highlight the changed field with a green border flash (1s CSS animation)
- Show a small "✓" icon next to the field after save confirmation

#### A3. Replace `alert()` with Toast Notifications
Create a `CC.showToast(message, type)` method:
- **Types**: `success` (green), `error` (red), `info` (blue)
- **Position**: Fixed top-right of the admin area
- **Auto-dismiss**: Success in 3s, error stays until dismissed
- **CSS animation**: Slide-in from right, fade-out

#### A4. Loading Skeletons
Replace "Loading Command Center…" and "Loading pages…" text with CSS-only skeleton placeholders:
- Card-shaped shimmer blocks
- Animated gradient (shimmer effect via `@keyframes`)
- One skeleton per card, same dimensions

#### A5. Debounced Preview + Save Coordination
Currently every field change triggers: save → preview. This should be:
- **Preview-only updates** for color/typography changes (faster, no DB write)
- **Full save** only after user stops interacting (current 500ms debounce) or explicit action
- This requires splitting the "apply to preview" from "persist to DB" concerns

#### Effort: Medium
| Item | Effort |
|------|--------|
| A1. Save status bar | Low (2-3 files, ~40 lines JS + CSS) |
| A2. Field feedback | Low (~30 lines JS + CSS animation) |
| A3. Toast system | Low (~1 new utility, ~30 lines CSS) |
| A4. Skeleton loading | Medium (~50 lines CSS + JS timing) |
| A5. Preview/Save split | Medium (requires architecture change) |

---

### B. Live Preview Enhancements

#### B1. postMessage Bridge
Establish two-way communication between Command Center and preview iframe:
- **Command Center → Preview**: On setting change, post a message with the changed setting path + value
- **Preview → Command Center**: On load, post the current page ID and title
- **Implementation**: `CC.iframe.contentWindow.postMessage({ type: 'vbb-setting-update', path, value }, '*')` and a `message` event listener
- **Security**: Use a specific message prefix/format to avoid collisions

#### B2. CSS Variable Injection into Preview
Instead of full reload, inject updated CSS variables into the preview iframe:
- After save, create a `<style>` tag inside the iframe's `document.head`
- Update only the changed CSS variables
- **Fallback**: If postMessage is unavailable or fails, fall back to full reload

#### B3. Preview Loading State
Use the iframe's `load` and `error` events to:
- Show a loading overlay/spinner while the iframe is loading
- Auto-dismiss when `load` fires
- Show error state if `error` fires (e.g., network issue)

#### B4. Preview Size Presets
Add responsive preview buttons:
- Desktop (full width) ← current default
- Tablet (768px viewport)
- Mobile (375px viewport)
- **Implementation**: Wrap iframe in a container, toggle `width` via CSS classes on buttons

#### B5. Active Section Highlighting
When editing a specific block's settings (e.g., hero), scroll the preview iframe to that section:
- Add `#vbb-section-{type}` anchor to section wrappers
- Post a scroll message to the iframe

#### Effort: Medium-High
| Item | Effort |
|------|--------|
| B1. postMessage bridge | Medium (~60 lines JS in both CC + inline script in preview) |
| B2. CSS injection | Medium (~40 lines JS + postMessage handling) |
| B3. Loading overlay | Low (~20 lines CSS + JS event handlers) |
| B4. Responsive presets | Low (~30 lines CSS + JS button handlers) |
| B5. Section scroll | Low (~15 lines JS) |

---

### C. Per-Block Color Overrides

#### C1. Data Model Extension
Extend the settings schema to support `blocks.{key}.colors`:

```php
// Default empty, but can be set per-block:
'blocks' => array(
    'hero' => array(
        'enabled' => true,
        'title'   => '',
        'colors'  => array(
            'background' => '',  // Empty = inherit global
            'text'       => '',
        ),
    ),
    // ...
)
```

**Changes to `pro-settings.php`**:
- `vbb_pro_default_settings()`: Add `'colors' => array()` inside each block's default
- `vbb_pro_sanitize_settings()`: Loop blocks and sanitize `colors` sub-array if present
- Backward compatibility: Keep existing behavior when `colors` is empty

#### C2. CSS Variable Generation Extension
Modify `vbb_pro_print_css_vars()` in `pro-css-vars.php` to emit block-scoped variables:

```php
// After global :root variables
// For each block with color overrides:
foreach ( $s['blocks'] as $key => $block ) {
    if ( ! empty( $block['colors'] ) ) {
        $section_class = '.vbb-section-' . sanitize_html_class( str_replace( '_', '-', $key ) );
        echo $section_class . '{';
        foreach ( $block['colors'] as $color_key => $color_value ) {
            if ( ! empty( $color_value ) ) {
                echo '--vbb-pro-' . esc_html( $color_key ) . ':' . esc_html( $color_value ) . ';';
            }
        }
        echo '}';
    }
}
```

#### C3. Per-Page Block Colors
Extend `vbb_pro_get_page_settings()` merging to include block-level color overrides:
- Per-page block colors should override global block colors
- This requires merging at the `blocks.{key}.colors` level in `vbb_pro_deep_merge()`

#### C4. UI Integration
Add per-block color pickers in `renderBlocks()` and `renderBlockSettings()`:
- When a block is enabled, show an expandable "Colors" section
- Color pickers for `background`, `text`, `accent` (subset of palette)
- Use existing color input pattern from `renderColorGroups()`
- Data path: `blocks.{key}.colors.{colorName}`

#### Effort: Medium
| Item | Effort |
|------|--------|
| C1. Data model | Low (~15 lines PHP) |
| C2. CSS vars | Low (~20 lines PHP) |
| C3. Merge logic | Low (~5 lines PHP) |
| C4. UI pickers | Medium (~60 lines JS) |

---

### D. UI Layout & Polish

#### D1. Admin Typography Enhancement
Add a dedicated font stack for the Command Center UI in `admin-pro.css`:
```css
.vbb-command-center {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 14px;
    line-height: 1.5;
    color: #172033;
}
```

#### D2. Refined Card Design
- Increase border-radius to 18px (more premium feel)
- Add a subtle left border accent (primary color) to cards on hover
- Layer shadows: default = subtle, hover = medium, active = strong
- Card header separators with accent color

#### D3. Color Picker UX Enhancement
- Show the hex value next to each color swatch (editable text input)
- Add a "copy hex" button on hover
- Show a tiny contrast indicator (e.g., "AA", "AAA" for accessibility)
- Group colors by purpose (Brand, Background, Text, Interactive)

#### D4. Improve the Page Selector
- Convert the dropdown to a searchable select or pill-style tabs for fewer pages
- Show the current page name prominently as a heading below the selector
- Indicate which pages have per-page overrides with a dot indicator

#### D5. Smooth State Transitions
- Focus rings with `transition: box-shadow .2s`
- Card expand/collapse for block settings (already has animation)
- Save indicator with opacity fade
- Toast notifications with slide-in animation

#### D6. Preview Iframe Enhancements
- Add resize handle to the preview container
- Add "Open in new tab" button for the preview URL
- Add refresh button (independent of save)
- Show the current preview URL below the iframe

#### D7. Empty States
- "No pages yet" when no pages are available (with "Create Page" CTA)
- "No settings" illustration for empty block settings
- Better empty menu items state with an illustration

#### Effort: Medium
| Item | Effort |
|------|--------|
| D1. Typography | Low (~15 lines CSS) |
| D2. Card polish | Low (~20 lines CSS) |
| D3. Color UX | Medium (~40 lines JS + CSS) |
| D4. Page selector | Medium (~30 lines JS + CSS) |
| D5. Transitions | Low (~20 lines CSS) |
| D6. Preview iframe | Low (~25 lines CSS + JS) |
| D7. Empty states | Low (~15 lines CSS + JS) |

---

## Risks & Conflicts

### Data Model Risk (Per-Block Colors)
- **Risk**: Extending the `blocks.{key}` data model from a simple object to including a `colors` sub-object could break backward compatibility if the sanitization doesn't handle old formats.
- **Mitigation**: The existing `vbb_pro_sanitize_settings()` already normalizes blocks from booleans to objects (line 213-221 of `pro-settings.php`). Extending it to preserve `colors` when present is safe. Old profiles without `colors` will continue to work.

### Performance Risk (postMessage + CSS Injection)
- **Risk**: The postMessage bridge adds complexity. If the iframe is cross-origin (unlikely, but possible if the site URL differs from the admin URL), postMessage will fail silently.
- **Mitigation**: Always fall back to full iframe reload if postMessage is unavailable. Add a `CC.supportsPostMessage` flag (try/catch on `contentWindow` access).

### Preview/DB Decoupling Risk
- **Risk**: Splitting "preview update" from "DB save" introduces a state where the preview shows unsaved changes. If the user navigates away, they lose preview-only changes.
- **Mitigation**: Keep the debounced save at 500ms but allow preview updates WITHOUT saving for UI-responsive fields (color picker drag). On blur/change, trigger the save. This is the "commit on blur" pattern.

### Conflict with builder-completion
- **Risk**: The builder-completion change restructured `pro-settings.php` to use the new blocks-as-objects format. If this visual polish change modifies the same `blocks` data model to add `colors`, there could be merge conflicts if both are active.
- **Mitigation**: The builder-completion change is already archived (completed). This visual polish change extends the format further. No active conflict.

### Menu Save vs. Settings Save Inconsistency
- **Risk**: The menu save already has a status indicator (`#vbb-cc-menu-status`). Adding a global save status bar could create visual duplication.
- **Mitigation**: Remove the menu-specific status indicator and use the global save status bar for ALL saves (settings + menu). Consistent UX.

### CSS Specificity Issues
- **Risk**: Adding block-scoped CSS variables could create specificity conflicts with global variables in the frontend.
- **Mitigation**: Block-scoped variables use the same custom property names but scoped to `.vbb-section-{type}` - CSS cascading ensures block-level overrides win over `:root` without specificity hacks.

---

## Ready for Proposal

**Yes** — the exploration is complete. The orchestrator should proceed to `sdd-propose` with the following guidance:

1. The change is **purely aesthetic + UX** — no new business logic, no new REST endpoints, no data model changes that affect existing functionality
2. The four focus areas can be implemented independently (visual feedback, live preview, per-block colors, UI polish)
3. **Priority order**: Visual feedback (highest impact for perceived quality) → UI Polish (low hanging fruit) → Per-block colors (medium complexity) → Preview enhancements (highest complexity)
4. The per-block colors feature requires **PHP + JS + CSS** changes across 5 files; it's the most impactful for designers
5. No breaking changes to existing settings — all extensions are backward compatible
6. Recommend **2 chained PRs**: PR1 = Visual feedback + UI Polish, PR2 = Live preview + Per-block colors

---

## Affected Files

| File | Why Affected |
|------|--------------|
| `assets/js/admin-pro.js` | Save feedback, toast system, postMessage bridge, color picker per-block, preview controls |
| `assets/css/admin-pro.css` | Typography, card polish, save indicator, skeleton loading, toast, transitions |
| `inc/pro-admin.php` | Status bar element, preview size buttons, toast container |
| `inc/pro-settings.php` | Block colors data model, sanitization extension, merge logic |
| `inc/pro-css-vars.php` | Block-scoped CSS variable generation for per-block colors |

---

## Testing Considerations

| Feature | Test Approach |
|---------|---------------|
| Save feedback toast | Verify toast appears on save, auto-dismisses on success, stays on error |
| Loading skeletons | Verify skeletons render during initial load, replaced by actual cards |
| postMessage bridge | Verify iframe receives messages, verify fallback to full reload |
| Per-block colors | Set block color → verify scoped CSS variable in frontend → verify global unaffected |
| Preview sizes | Verify iframe width changes on preset buttons, responsive on mobile |
| Empty states | Verify CTAs work (Create Page, Add Menu Item) |
