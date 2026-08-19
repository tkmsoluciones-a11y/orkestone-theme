# Design: Visual Enhancements (Top 5)

## Technical Approach

Five independent CSS/JS additions to `pro-admin.php`, `admin-pro.css`, and `admin-pro.js`. No new files, no DB schemas, no PHP endpoints. Each enhancement maps to additive CSS classes and toolbar/card UI — independently revertible. The spec's proposed class `.vbb-cc-preview-scrollable` does NOT exist in the codebase; the actual wrapper is `.vbb-cc-preview-viewport`. All design decisions below account for actual code, not speculative markup.

---

## Architecture Decisions

### 1. Zoom X2 — transform on viewport, not iframe

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `scale(2)` on `.vbb-cc-preview-viewport` | Reuses existing overflow/max-width. Viewport has `overflow:hidden` and `transition:max-width` for responsive presets — zoom must not conflict. | **Adopted**. Scale viewport, not iframe. Add `.vbb-cc-preview-viewport--zoomed` class. Reset iframe width to 50% with negative margins to compensate for parent scaling. |
| `zoom` CSS property | Non-standard, deprecated. | Rejected. |
| Resize iframe dimensions | Breaks responsive preset `max-width` logic. | Rejected. |

**Implementation**: Toggle button in preview toolbar, one JS handler (20 lines), one CSS class block (15 lines). State lives in memory only — no localStorage per spec.

### 2. Smooth Transitions — CSS custom properties

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `--vbb-cc-transition-{fast,normal,slow}` on `.vbb-command-center` | Follows existing `--vbb-admin-*` custom property pattern. CSS-only, no JS. | **Adopted**. Three durations: 0.15s, 0.3s, 0.5s. Utility classes `.vbb-cc-timing-*` for direct element use. |
| Hardcoded values everywhere | Current state. Fragile, inconsistent. | Rejected. |

**Font dropdown fix**: The font list uses `display:none` → `display:block` (lines 504-505 of admin-pro.css). Refactor to `max-height: 0` → `max-height: 280px` with `opacity` + `overflow:hidden` transition to eliminate flicker. This is the highest-risk item because changing display behavior affects existing open/close logic in `initFontDropdowns()`.

### 3. Onboarding Animation — staggered card reveal

| Option | Tradeoff | Decision |
|--------|----------|----------|
| JS assigns `animation-delay` in `renderCards()` | Dynamic — works for any card count. CSS-only fallback (no animation rendered) handles no-JS. | **Adopted**. After `CC.el.cards.innerHTML = html`, loop `.vbb-cc-card` and set `style.animationDelay = (i * 80) + 'ms'`. |
| `:nth-child` CSS rules | Fixed at 9 cards. Breaks if card count changes. | Rejected. |
| CSS `@container` queries | Insufficient browser support. | Rejected. |

**Keyframe**: `@keyframes vbb-cc-card-reveal` — opacity 0→1, translateY(16px)→0, ease-out. Duration tied to `--vbb-cc-transition-normal`. The animation fires on every `init()` because `renderCards()` replaces innerHTML, re-triggering the animation.

### 4. Preset Selector — new card, reuses existing PHP

| Option | Tradeoff | Decision |
|--------|----------|----------|
| New `.vbb-cc-card` rendered in `renderCards()` | Follows existing card pattern (Colors, Typography, Layout). Reuses `vbb_pro_get_preset_settings()` + XHR. | **Adopted**. Card contains `<select>` listing presets from PHP + "Apply" button. On select, XHR POST to `vertical-settings` endpoint with merged settings. No page reload. |
| Add to preview toolbar | Already crowded (responsive presets, refresh, URL). | Rejected. |

**Spec gap**: Spec says "preset MUST apply global theme settings only and MUST NOT overwrite page-specific overrides." The existing `saveSettings()` already handles this because the XHR payload merges via PHP's `vbb_pro_deep_merge()` — per-page overrides are separate in the data model. No additional work needed.

### 5. Dark Mode Preview — postMessage, no persistence

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `vbb:dark-preview` postMessage type | Extends existing protocol. Existing receiver checks `data.type.indexOf('vbb:') === 0` — `vbb:dark-preview` passes. | **Adopted**. Toggle in preview toolbar sends `{ type: 'vbb:dark-preview', enabled: true }`. Iframe receiver injects dark palette CSS vars into `#vbb-pro-injected-css`. No localStorage, resets on refresh. |
| Reload iframe with `?vbb_dark=1` param | Full reload, slow UX. | Rejected. |

**Spec gap**: The spec example payload uses `vbb-cc-dark-mode-preview` (dash-separated), but the existing receiver validates `data.type.indexOf('vbb:') === 0`. Using `vbb-cc-dark-mode-preview` would NOT match `vbb:` — the type would be silently dropped. Design uses `vbb:dark-preview` to match the established protocol convention.

---

## Data Flow

```
User clicks toggle → JS handler → CSS class toggle / postMessage
                                      │
                    ┌──────────────────┴──────────────────┐
                    │                                     │
            CSS class toggle                     postMessage to iframe
            (Zoom, Transitions,                  (Dark Preview)
             Onboarding, Preset)
                    │                                     │
            DOM updates immediately              Receiver injects
            (no XHR, no reload)                  dark CSS vars into
                                                 #vbb-pro-injected-css
```

- Zoom: `click` → `viewport.classList.toggle()` — no XHR, no storage
- Transitions: CSS-only, applied on next paint
- Onboarding: `renderCards()` → loop delay assignment → CSS animation
- Preset: `select` + `click` → `onFieldChange` → `debouncedSave()` → XHR POST
- Dark Preview: `change` → `postMessage({ type: 'vbb:dark-preview', enabled })` — no XHR, no storage

---

## File Changes

| File | Action | Lines | Description |
|------|--------|-------|-------------|
| `assets/css/admin-pro.css` | Modify | +160 | Zoom class, transition vars, card animation keyframes, preset card UI, dark preview toggle |
| `assets/js/admin-pro.js` | Modify | +130 | Zoom toggle handler, animation-delay assignment, preset apply, dark preview postMessage |
| `inc/pro-admin.php` | Modify | +25 | Zoom button in preview toolbar, dark preview toggle HTML, iframe receiver handler for `vbb:dark-preview` |
| `inc/pro-presets.php` | None | — | Reuses existing `vbb_pro_get_preset_settings()` — no changes needed |

---

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Visual | Zoom X2 at 375px/768px/desktop | Manual — toggle zoom, verify scrollbars appear, no layout shift in grid |
| Visual | Smooth transitions on card hover, dropdown expand | Manual — inspect computed styles use `--vbb-cc-transition-*` vars |
| Visual | Stagger animation on every `init()` | Manual — reload CC, verify staggered reveal. Disable JS → cards visible immediately |
| Visual | Dark Preview toggle | Manual — toggle ON → iframe switches to dark. Refresh → resets to light |
| Integration | Preset selector | Select preset → XHR fires → settings update without page reload. Page-specific overrides preserved |

---

## Migration / Rollback

No migration required. Revert all three files to undo. Each enhancement is independently revertible — no coupling between them.

---

## Open Questions

- [ ] **Font dropdown `max-height` transition**: The dropdown's `display:none` → `display:block` toggle won't animate. Refactoring to `max-height` requires changing `initFontDropdowns()` open/close logic (lines 796-907). Need to confirm the `max-height` approach works with the existing search filter and custom input toggle. This is the highest-risk change in the batch.

---

## Next Recommended Action

`tasks` — break down design into implementation tasks
