# Design: preview-fidelity (Spec 1)

## Technical Approach

Synchronize the JS `buildCssVars()` output with the PHP `vbb_pro_print_css_vars()` source of truth by mirroring the full variable set and rule block set. Remove the competing PHP listener `vbb_pro_preview_message_listener()` (priority 31) so that `vbb_pro_inject_preview_script()` (priority 5, `inc/pro-admin.php`) is the sole `vbb:css-vars` receiver. Fix dark mode preview by removing the `filter:invert(1) hue-rotate(180deg)` CSS rule that fights with JS dark-palette postMessage.

---

## Architecture Decisions

| Decision | Choice | Alternatives Considered | Rationale |
|---|---|---|---|
| Single message receiver | Keep `vbb_pro_inject_preview_script()` (priority 5, `inc/pro-admin.php`); remove `vbb_pro_preview_message_listener()` (priority 31, `inc/pro-css-vars.php`) | Merge both into a third function; keep both and differentiate by message subtype | Priority 5 runs first, already creates the style accumulate element, handles all vbb:* subtypes. Priority 31 duplicates `vbb:css-vars` handling and races on the same iframe style element. Removing it eliminates the race with zero behavioral loss. |
| Dark mode preview mechanism | Remove `filter:invert(1) hue-rotate(180deg)` rule entirely; rely on JS `buildCssVars('dark')` + `data-theme="dark"` attribute | Keep the filter and bypass it only when JS vars detected; invert only backgrounds | The filter inverts all colors including images/media, produces wrong output, and contradicts CSS-variable dark mode. Removing it is the smallest fix that matches production behavior. |
| Variable parity strategy | Hard-code the full variable list in both PHP and JS, connected by this spec as the source of truth | Generate JS vars from a shared JSON config; use PHP to render JS constants | Shared config requires a build step or extra REST endpoint. Explicit mirrors are simpler and match the project's no-build-step vanilla JS constraint. |
| Block key map expansion | Add the 7 missing keys directly to `_blockKeyToSectionClass()` map | Fall through to `key.replace(/_/g,'-')` for unmapped keys | The fallback produces wrong selectors for keys like `newsletter` → `.vbb-section-newsletter` (correct) vs the PHP map using the same keys. Explicit entries prevent drift and match PHP `vbb_pro_section_class_for_block()` exactly. |

---

## Data Flow

```
CC (admin-pro.js)                  Preview iframe
       │                                   │
  onFieldChange()                        wp_head → vbb_pro_inject_preview_script()
       │                                   │  (priority 5, only receiver)
       ├─► buildCssVars()                  │
       │     ├── palette vars              │
       │     ├── shadow, spacing,          │
       │     │   button-radius, glow       │
       │     ├── button + card rules       │
       │     ├── nav style rules           │
       │     ├── block scoped (expanded   │
       │     │   _blockKeyToSectionClass)  │
       │     └── footer scoped             │
       │                                   │
       └─► postMessage({                  │
             type:'vbb:css-vars',         │
             styleTag: cssString          │
           })                             │
       │─────────────────────────────────►│
                                           │ append to #vbb-pro-injected-css
                                           │ (accumulate, never replace)
       │                                   │
  toggleDarkPreview()                     │
       ├─► buildCssVars('dark')           │
       └─► postMessage({                  │
             type:'vbb:dark-preview',     │
             enabled:true/false           │
           })                             │
       │─────────────────────────────────►│
                                           │ html[data-theme="dark"]
                                           │ + CSS vars override :root
```

Single consumer (`vbb_pro_inject_preview_script`, priority 5) owns the iframe style element.
`vbb_pro_preview_message_listener` (priority 31) is removed — no second consumer.

---

## File Changes

| File | Action | Description |
|---|---|---|
| `inc/pro-css-vars.php` | Modify | Remove `vbb_pro_preview_message_listener()` function (lines 249–282) and its `add_action` hook (line 282). Remove the now-unused `<style id="vbb-dark-preview-style">` block (lines 277–279). |
| `assets/js/admin-pro.js` | Modify | Extend `buildCssVars()` to emit: `--vbb-pro-shadow`, `--vbb-pro-section-spacing`, `--vbb-pro-button-radius`, `--vbb-pro-glow-intensity`; button style rules (`.wp-block-button__link`); body/heading font family rules; content-width/wide-width on `.wp-site-blocks > *`; card rules (`.vbb-pro-card`, `.is-style-card`); nav-type and nav-style class rules. Expand `_blockKeyToSectionClass()` map with: `stats→stats`, `gallery→gallery`, `video→video`, `newsletter→newsletter`, `map→map`, `comparison→comparison`, `blog→blog`. |

---

## Interfaces / Contracts

**postMessage contract** (unchanged — receiver kept):
```
{ type: 'vbb:css-vars',    styleTag: '<style>…</style>' }
{ type: 'vbb:dark-preview', enabled:  true|false           }
{ type: 'vbb:scroll-to-section', sectionKey: 'hero'          }
```

**CSS variable contract** — JS `buildCssVars()` must cover every variable emitted by PHP `vbb_pro_print_css_vars()`:

| Variable | Source in PHP | Added to JS |
|---|---|---|
| `--vbb-pro-primary/secondary/accent/background/surface/text/mutedText` | `vbb_pro_css_palette_vars()` | ✅ exists |
| `--vbb-pro-heading-font` | line 128 | ✅ exists |
| `--vbb-pro-body-font` | line 129 | ✅ exists |
| `--vbb-pro-content-width` | line 130 | ✅ exists |
| `--vbb-pro-wide-width` | line 131 | ✅ exists |
| `--vbb-pro-radius` | line 132 | ✅ exists |
| `--vbb-pro-shadow` | line 133 | ❌ **missing** |
| `--vbb-pro-section-spacing` | line 134 | ❌ **missing** |
| `--vbb-pro-button-radius` | line 135 | ❌ **missing** |
| `--vbb-pro-glow-intensity` | line 136 | ❌ **missing** |
| `--vbb-pro-base` | line 138 | ✅ exists (inline in second :root block) |
| `--wp--preset--color--*` | lines 139–144 | ✅ exists |
| `--vbb-footer-bg/text/link/link-hover/bottom-bg` | line 237 | ✅ exists |

**Dark mode**: JS sends `buildCssVars('dark')` which computes dark palette; PHP iframe also responds to `html[data-theme="dark"]` selector (PHP line 162–171). No contract change — the fix removes the conflicting CSS filter, not the message contract.

---

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Manual visual | Palette change reflected in preview iframe | CC: change any primary/secondary/accent swatch → verify iframe updates within debounce window, no full reload |
| Manual visual | Typography change (heading/body font) | CC: switch heading font → verify `font-family` on h1–h6 in preview |
| Manual visual | Spacing/shadow change | CC: change shadow (soft→strong) and spacing (compact→wide) → verify box-shadow and section padding in preview |
| Manual visual | Navigation style change | CC: switch menu style (modern/minimal/classic/pill) → verify nav item hover/active styles match selected variant |
| Manual visual | Block color override | CC: set a per-block color on hero section → verify only `.vbb-section-hero` is affected, global vars unchanged |
| Manual visual | Page-scoped override | CC: select a specific page, override a block color → verify selector is `.page-id-{N} .vbb-section-{type}` |
| Manual visual | Dark mode preview | CC: enable dark preview toggle → verify colors are correct (no inverted images/media), no `filter:invert` artifacts |

No PHP unit tests exist for `vbb_pro_preview_message_listener()` (it is output-buffered inline script). No JS test framework is configured. All verification is manual visual. A regression guard can be added later as a Playwright snapshot test.

---

## Migration / Rollout

No migration required. No database schema changes. No content migration. No feature flags.

**Rollout steps**:
1. Remove `vbb_pro_preview_message_listener()` from `inc/pro-css-vars.php` (no deprecation period needed — `vbb_pro_inject_preview_script()` at priority 5 runs first and covers all functionality).
2. Extend `buildCssVars()` in `assets/js/admin-pro.js`; verify in dev environment across the 7 manual scenarios above.
3. Remove `vbb-dark-preview` CSS filter block from `inc/pro-css-vars.php`.

**Rollback**: Revert the two file changes; restore `vbb_pro_preview_message_listener()` if removed. No data reversal needed.

---

## Open Questions

None. All decisions are grounded in the existing codebase.