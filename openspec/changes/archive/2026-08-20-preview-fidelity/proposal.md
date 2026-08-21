# Proposal: preview-fidelity (Spec 1)

## Intent

The Command Center live preview (via `postMessage` / `vbb:css-vars`) renders inconsistently against production output because `buildCssVars()` in `assets/js/admin-pro.js` emits only a partial variable set and a hardcoded rule subset. Additionally, a duplicate receiver `vbb_pro_preview_message_listener()` in PHP fights for the same message channel, and dark mode preview filters invert colors incorrectly. This unifies CSS generation across PHP and JS, removes the duplicate receiver, and fixes dark mode preview rendering — closing the gap between admin-preview and front-end output without touching any database schema or block types.

## Scope

### In Scope
- Extend `buildCssVars()` to include missing variables: `--vbb-pro-shadow`, `--vbb-pro-section-spacing`, `--vbb-pro-button-radius`, `--vbb-pro-button-shadow`, `--vbb-pro-button-padding`, `--vbb-pro-text-color`, `--vbb-pro-heading-color`, etc.
- Expand block key mapping in `_blockKeyToSectionClass()` to cover additional block types.
- Emit block-scoped and page-scoped CSS selectors from JS preview.
- Add block button styles, nav styles, and footer CSS variable bindings to JS rule output.
- Remove `vbb_pro_preview_message_listener()` from PHP; consolidate onto a single accumulation handler.
- Fix dark mode preview inversion bug in filter logic.

### Out of Scope
- Backend database schema changes.
- New block creation or block type additions.

## Capabilities

> This section is the contract between proposal and specs phases.

### New Capabilities
- `preview-fidelity`: Unified CSS variable and rule generation for Command Center live preview, covering variable parity between PHP and JS, selector scoping, single-message-receiver architecture, and correct dark mode preview filtering.

### Modified Capabilities
- None.

## Approach

Synchronize the JS `buildCssVars()` output with the PHP `pro-css-vars.php` source of truth by mirroring the full variable set and rule block set. Replace the two competing preview message listeners with one accumulation loop that handles `vbb:css-vars` end-to-end. Correct the dark mode preview filter so it derives from the preview's own CSS custom property context rather than inverting PHP-derived assumptions.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `assets/js/admin-pro.js` | Modified | Extend `buildCssVars()`, expand block key map, add selector scoping |
| `includes/pro-css-vars.php` or equivalent PHP CSS var file | Modified | Align variable names; remove `vbb_pro_preview_message_listener()` |
| `includes/pro-preview.php` or equivalent PHP preview handler | Modified | Remove duplicate receiver; keep single accumulation handler |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Variable name drift between PHP and JS reintroduced | Medium | Single canonical variable list; spec-level requirement that new vars appear in both locations |
| Dark mode regression in production output | Low | Dark mode fix is isolated to preview filter; production CSS vars unchanged |
| Large diff exceeds 400-line PR budget | Medium | Scope is narrow (2 JS files, 1–2 PHP files); confirm line count in sdd-tasks |

## Rollback Plan

Revert changes to `admin-pro.js` and the PHP preview receiver file; restore `vbb_pro_preview_message_listener()` if removed. No database or content migration to reverse.

## Dependencies

None beyond existing WordPress block editor and customizer APIs already in use.

## Success Criteria

- [ ] `buildCssVars()` output contains the same variable set as PHP `pro-css-vars.php` (verified by diff).
- [ ] Only one `vbb:css-vars` message receiver is registered on the PHP side.
- [ ] Block-scoped selectors output correct `.vbb-pro-section-*` class bindings for all mapped block types.
- [ ] Page-scoped selectors emit root-level wrappers when `pageScoped` is active.
- [ ] Dark mode preview shows correct colors (no inverted background/text in preview iframe).
- [ ] No PHP notices/warnings in preview iframe console on load.