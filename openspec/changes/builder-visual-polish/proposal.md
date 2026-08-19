# Proposal: Builder Visual Polish (UI/UX)

## Intent

Elevate the Command Center from a functional tool to a professional-grade UX — instant feedback on every action, seamless live preview without full-page flashes, and granular per-block color control. Every interaction must communicate its state: saving, saved, or error. No more `alert()` dialogs, no more silent saves, no more white-flash preview reloads.

## Scope

### In Scope
1. **Visual Feedback System** — Global status bar, toast notifications (replace `alert()`), save indicators on fields, loading skeletons.
2. **Live Preview Enhancements** — `postMessage` bridge between CC and iframe, CSS variable injection (no full reload), loading overlay, responsive presets (desktop/tablet/mobile).
3. **Per-Block Color Overrides** — Data model extension (`blocks.{key}.colors`), block-scoped CSS vars in `pro-css-vars.php`, per-block color pickers in UI.
4. **General UI Polish** — Custom admin font stack, refined card design (border-radius, shadows, left-border accent), enhanced color picker UX (hex display, copy, contrast), smooth transitions, preview iframe resize handle, refresh button, empty states.

### Out of Scope
- New REST API endpoints (existing endpoint is sufficient)
- Frontend (public) CSS changes beyond generated CSS variables
- New block types or block content fields
- Multi-user collaboration features
- Analytics/tracking of color usage
- Dark mode toggle for the admin UI itself (only block palette dark/light)

## Capabilities

### New Capabilities
- `builder-visual-feedback`: Save status bar, toast notifications, field-level save indicators, skeleton loading states.
- `builder-live-preview`: postMessage bridge for cross-frame communication, CSS variable injection without full reload, responsive preview presets, loading overlay.
- `builder-per-block-colors`: Per-block color override data model, scoped CSS variable generation, UI color pickers per block.

### Modified Capabilities
- `builder-command-center`: Enhance existing capability with visual feedback integration, updated preview behavior, and per-block color UI. Requirements scope expands from "functional save/preview" to "stateful save/preview with instant feedback."

## Approach

Two-stage delivery (2 chained PRs, 800-line review budget):

**Stage 1 — Feedback & UI Polish** (lower risk, immediate UX lift)
- Toast component + replace `alert()` calls
- Global save status bar (replaces menu-specific indicator)
- Field-level save feedback (green flash animation)
- Loading skeletons
- Font stack, card polish, transitions, color picker UX, empty states
- Preview iframe controls (resize, refresh, URL display)

**Stage 2 — Advanced Preview & Per-Block Colors** (architectural shift, higher value)
- `postMessage` bridge with origin security checks
- CSS variable injection into iframe (eliminate full reload)
- Responsive preview presets
- Data model: `blocks.{key}.colors` in global + per-page settings
- Block-scoped CSS var generation in `pro-css-vars.php`
- Per-block color pickers in `renderBlockSettings()`

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `assets/js/admin-pro.js` | Modified | Save feedback, toast, postMessage bridge, per-block color picker, preview controls |
| `assets/css/admin-pro.css` | Modified | Typography, card polish, skeleton, toast, transitions, loading overlay |
| `inc/pro-admin.php` | Modified | Status bar element, toast container, preview size controls |
| `inc/pro-settings.php` | Modified | Block colors data model, sanitization, merge logic |
| `inc/pro-css-vars.php` | Modified | Block-scoped CSS variable generation |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `postMessage` fails cross-origin or blocked | Med | Strict origin check + fallback to full iframe reload; detect capability via `CC.supportsPostMessage` |
| CSS specificity conflicts with per-block colors | Low | Block vars scoped to `.vbb-section-{type}` — CSS cascade ensures overrides beat `:root` naturally |
| Preview/DB state divergence (unsaved changes in preview) | Med | "Commit on blur" pattern: color picker drag updates preview only, field blur triggers save |
| Duplicate save indicators (menu + global status) | Low | Remove menu-specific `#vbb-cc-menu-status`, unify under global status bar |
| Backward compatibility of block colors data model | Low | `vbb_pro_sanitize_settings()` already normalizes blocks; old profiles without `colors` sub-object work unchanged |

## Rollback Plan

**Per-stage rollback**:
- **Stage 1**: Revert `admin-pro.js`, `admin-pro.css`, `pro-admin.php` to pre-change state. Toast/status bar are purely additive CSS + JS — no data impact.
- **Stage 2**: Revert `pro-settings.php` and `pro-css-vars.php` to remove `blocks.{key}.colors` schema extension. Block color pickers in JS fall back gracefully if `colors` sub-object is absent.
- **Full rollback**: `git revert` the merged PRs in reverse order (Stage 2 first, then Stage 1). No data migration needed — `colors` sub-object is optional in all paths.

## Dependencies

- None external. All changes are within the existing plugin architecture.

## Success Criteria

- [ ] **Zero full-page reloads**: Color changes update the preview via CSS variable injection, not iframe reload
- [ ] **Visible save states**: Every save operation shows a toast or status indicator (saving → saved → fade)
- [ ] **No `alert()` calls**: All error/success feedback uses toast notifications
- [ ] **Per-block colors applied**: Setting `hero.background` changes only the hero section, not global `--vbb-pro-background`
- [ ] **Responsive preview**: Tablet (768px) and mobile (375px) presets render the iframe at correct widths
- [ ] **Skeleton loading**: Initial load shows card-shaped shimmer placeholders, replaced by real cards
