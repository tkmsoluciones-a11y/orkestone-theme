# Proposal: Visual Enhancements — Top 5

## Intent

Improve aesthetics and visual polish of the Command Center admin UI and Page Preview iframe. Five targeted frontend-only enhancements that make the tool feel more professional, responsive, and helpful during theme design sessions.

## Scope

### In Scope
- Zoom X2 toggle in preview toolbar (CSS scale + overflow)
- Smooth transitions via CSS custom property system
- Staggered card reveal animation on init()
- Preset selector card to load existing JSON preset files
- Dark mode preview toggle (localStorage override, no server persistence)

### Out of Scope
- New DB schemas or PHP entry points
- Schema changes to preset data model
- Admin Dark Mode changes (linked behavior is expected)
- Keyboard accessibility changes (existing support preserved)
- Adding new preset files

## Capabilities

### New Capabilities
- `preview-zoom`: 2x CSS scale transform toggle for iframe preview
- `smooth-transitions`: CSS custom property timing system replacing hardcoded transition values
- `onboarding-animation`: Staggered card reveal on Command Center init()
- `preset-selector`: UI card to load existing preset JSON configs
- `dark-preview`: visual-only dark mode toggle (localStorage) for preview iframe

### Modified Capabilities
None — all enhancements are additive, no existing spec-level behavior changes.

## Approach

Five independent CSS/JS additions to `admin-pro.css`, `admin-pro.js`, and `pro-admin.php`. No new files. Each enhancement is toggled via CSS class or toolbar button, isolated from existing logic. The preset selector reuses `vbb_pro_get_preset_settings()` with no schema changes. Dark preview overrides CSS vars via postMessage to iframe — visual-only, resets on refresh.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `assets/css/admin-pro.css` | Modified | +~160 lines: zoom class, CSS vars timing, card animation, preset card, dark toggle |
| `assets/js/admin-pro.js` | Modified | +~130 lines: zoom handler, animation-delay assignment, preset load, dark toggle |
| `inc/pro-admin.php` | Modified | +~23 lines: zoom button, preset card HTML, dark toggle button |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Smooth transitions perf on low-end devices | Low | CSS transforms/opacity only, GPU-composited; test on older hardware |
| Zoom breaks iframe layout on narrow viewports | Low | `overflow: auto` + `transform-origin: top left` handles overflow |
| Preset selector loads stale cached settings | Low | Hard refresh preview after load; PHP merges, doesn't replace |

## Rollback Plan

Revert `admin-pro.css`, `admin-pro.js`, and `pro-admin.php` to previous versions. Each enhancement is independently revertible — no coupling between them. All changes are additive CSS classes and toolbar buttons; removing them restores original behavior.

## Dependencies

- `vbb_pro_get_preset_settings()` PHP function (already exists)
- `buildCssVars()` JS function (already exists)
- No external libraries or packages

## Success Criteria

- [ ] Zoom X2: iframe renders at 2x scale with scrollbars when content overflows
- [ ] Transitions: all hover/focus/state changes use `--vbb-cc-transition-*` vars consistently
- [ ] Onboarding: cards animate in staggered sequence on every init()
- [ ] Preset: selecting a preset loads and applies theme settings without page reload
- [ ] Dark preview: toggle forces dark CSS vars in iframe, resets on page refresh
