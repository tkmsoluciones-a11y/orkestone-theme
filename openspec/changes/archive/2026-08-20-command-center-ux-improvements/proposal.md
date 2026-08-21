# Proposal: Command Center UX Improvements

**Change**: command-center-ux-improvements
**Author**: SDD pipeline
**Date**: 2026-08-20
**Status**: Implemented

---

## Intent

Improve the Command Center admin panel (`admin.php?page=vbb-command-center`) productivity
and user experience with five targeted UX enhancements. All changes extend existing patterns
without modifying the REST API surface or adding new dependencies.

## Scope

### In Scope

1. **Unsaved Changes Indicator** — Visual status bar state (`vbb-cc-status-bar--unsaved`) that
   lights up when settings have been modified but not yet saved.
2. **Undo/Redo for Color Changes** — Stack-based undo/redo (last 5 color/palette actions) via
   toolbar buttons, per-session state only.
3. **Before/After Comparator** — Toolbar toggle button that switches the preview iframe between
   current live state and the last-saved baseline, persisted via localStorage.
4. **Export/Import Profiles as JSON** — Download current settings as a JSON file; re-apply
   settings by uploading a previously exported JSON profile with schema validation.
5. **Keyboard Shortcuts** — Ctrl+S triggers save; Ctrl+Z triggers undo. Shortcuts are disabled
   when a form input is focused. A shortcut indicator appears in the toolbar/status bar.

### Out of Scope

- REST API changes (no new endpoints)
- PHP-side logic changes beyond minimal toolbar element additions
- Settings persistence format changes
- Multi-user or cross-session state sharing
- Mobile-specific optimizations

## Approach

All changes live in three existing files:

| File | Action | Est. Lines |
|------|--------|-----------|
| `assets/js/admin-pro.js` | Modify | +180 |
| `assets/css/admin-pro.css` | Modify | +80 |
| `inc/pro-admin.php` | Modify | +20 |

**Total: ~280 net new lines** (within 400-line budget, Low risk).

**Pattern extensions used:**
- Extend `CC.state`, `showStatus`, `onFieldChange`, `debouncedSave`, `_bindKeyboardShortcuts`
- New toolbar buttons follow `.vbb-cc-preset-btn` CSS pattern
- Comparator uses existing `postMessage` (`vbb:css-vars`) mechanism
- Export JSON built on the same `Blob` download pattern used by `exportSite`
- Import validates against the existing profile schema already used by `import_json`

## Acceptance Criteria

| Feature | Key AC |
|---------|--------|
| Unsaved Indicator | Status bar shows `vbb-cc-status-bar--unsaved` when dirty and no save in progress; reverts to idle/saved on completion |
| Undo/Redo | Stack limited to 5; buttons disabled when stacks are empty; per-session only |
| Comparator | Toggle button in toolbar; default `'after'`; mode persisted in localStorage |
| Export/Import | Export downloads valid JSON; import validates schema; invalid JSON shows error toast, no changes applied |
| Keyboard Shortcuts | Ctrl+S saves; Ctrl+Z undos; both disabled when input focused; indicator visible |

## Rollback Plan

- All changes are additive (new state fields, new methods, new CSS classes, new toolbar buttons).
- No existing JS methods are removed or have signatures changed.
- PHP changes are additive (new button elements, new `admin_post` action hook).
- Rollback: revert the three files to the pre-ux-improvements state. No database migration
  is required — all new state is held in `localStorage` or in-memory JS stacks.
- If any feature causes issues, remove the corresponding `_bind*` call in `init()` and the
  feature's CSS class to disable it without affecting other features.

## Risks

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| CSS class conflicts with future Command Center refactor | Low | All CSS classes prefixed with `vbb-cc-` |
| Undo/redo interfering with non-color settings | Low | Stack limited to `palettes.*` and `colors.*` paths only |
| Comparator showing wrong baseline after save | Low | Baseline pulled from `CC.state.settings` before `refreshPreview()` |
| Keyboard shortcut browser conflicts | Low | Shortcuts disabled when input is focused; Ctrl+S prevented via `preventDefault()` |

## Dependencies

- Parent change `command-center` must be completed first (provides `vbbCommandCenter` module,
  REST API, card UI, and iframe preview infrastructure).