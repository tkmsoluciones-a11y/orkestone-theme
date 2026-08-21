# Tasks: Command Center UX Improvements

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~280 (JS +180, CSS +80, PHP +20) |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: single-pr
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | PR | Notes |
|------|------|----|-------|
| 1 | All 5 UX features + verification | PR 1 | Single self-contained feature; all changes in 3 files |

## Phase 1: Foundation — Unsaved Changes Indicator

- [x] **TASK-1.1** `assets/js/admin-pro.js`: Extend `showStatus()` — add `'unsaved'` state handler: apply `vbb-cc-status-bar--unsaved` class, set tooltip text "Unsaved changes".
- [x] **TASK-1.2** `assets/js/admin-pro.js`: In `debouncedSave()`, after `CC.state.dirty = true`, call `_updateUnsavedIndicator()` so unsaved state is visible immediately.
- [x] **TASK-1.3** `assets/js/admin-pro.js`: Add `_updateUnsavedIndicator()` helper — reads `CC.state.dirty` and save-in-progress flag, calls `showStatus('unsaved')` or `showStatus('idle')`.
- [x] **TASK-1.4** `assets/js/admin-pro.js`: Modify `_finishSave()` — after AJAX completes, reset unsaved state: success → `showStatus('saved')`, error → `showStatus('error')`.
- [x] **TASK-1.5** `assets/css/admin-pro.css`: Style `.vbb-cc-status-bar--unsaved` (yellow/orange background); add tooltip positioning for `.vbb-cc-status-bar--unsaved .vbb-cc-status-text`. CONSOLE | CSS

## Phase 2: Undo/Redo for Color Changes

- [x] **TASK-2.1** `assets/js/admin-pro.js`: Add to `CC.state`: `undoRedoStack: []`, `redoStack: []`. CONSOLE | JS
- [x] **TASK-2.2** `assets/js/admin-pro.js`: Add `undo()` method — pop last entry from `undoRedoStack`, revert setting via `saveSettings()` or direct state update, push entry to `redoStack`, refresh UI.
- [x] **TASK-2.3** `assets/js/admin-pro.js`: Add `redo()` method — pop last entry from `redoStack`, re-apply the setting, push entry to `undoRedoStack`, refresh UI.
- [x] **TASK-2.4** `assets/js/admin-pro.js`: Modify `onFieldChange()` — for paths starting with `palettes.` or `colors.`, push `{ path, oldValue, newValue }` to `undoRedoStack` (limit 5), clear `redoStack`.
- [x] **TASK-2.5** `assets/js/admin-pro.js`: Add `_bindUndoRedo()` — create Undo/Redo buttons in `.vbb-cc-actions-row`, bind click handlers, update disabled state when stacks are empty.
- [x] **TASK-2.6** `assets/css/admin-pro.css`: Style undo/redo buttons following `.vbb-cc-preset-btn` pattern; add `.vbb-cc-undo-btn--disabled` / `.vbb-cc-redo-btn--disabled` for disabled state. CONSOLE | CSS

## Phase 3: Before/After Comparator

- [x] **TASK-3.1** `assets/js/admin-pro.js`: Add to `CC.state`: `comparisonMode: 'after'` (default). CONSOLE | JS
- [x] **TASK-3.2** `assets/js/admin-pro.js`: Add `toggleComparisonMode()` — toggle between `'after'` and `'before'`, persist choice to `localStorage['vbb-cc-comparison-mode']`.
- [x] **TASK-3.3** `assets/js/admin-pro.js`: Modify iframe `load` handler — when `comparisonMode === 'before'`, send postMessage with saved baseline CSS vars (from cached state or REST fetch).
- [x] **TASK-3.4** `assets/js/admin-pro.js`: Add `renderComparisonButton()` — create "Compare" button in `.vbb-cc-actions-row`, set active state from `comparisonMode`, bind click handler.
- [x] **TASK-3.5** `assets/js/admin-pro.js`: Call `renderComparisonButton()` in `init()` during toolbar setup.
- [x] **TASK-3.6** `assets/css/admin-pro.css`: Style Compare button in toolbar; add active state when `comparisonMode === 'before'`; tooltip "Mostrar estado guardado / Current state". CONSOLE | CSS
- [x] **TASK-3.7** `inc/pro-admin.php`: Add Compare button element in `vbb_pro_render_command_center()` — inside `.vbb-cc-preview-toolbar`, after presets section, before dark preview button. CONSOLE | PHP

## Phase 4: Export/Import Profiles as JSON

- [x] **TASK-4.1** `assets/js/admin-pro.js`: Add `exportProfile()` — collect `CC.state.settings`, build profile object (profileName, colorMode, palettes light/dark, typography, layout, blocks, buttons, exportedAt, theme, profileType, schemaVersion), create Blob, trigger download.
- [x] **TASK-4.2** `assets/js/admin-pro.js`: Add `importProfile()` — handle file input `change` event, read file, call `_validateProfileJSON()`, apply settings via existing import endpoint.
- [x] **TASK-4.3** `assets/js/admin-pro.js`: Add `_validateProfileJSON(data)` helper — check required fields (profileName, colorMode, palettes, typography, etc.) against existing schema; return `{ valid, errors }`.
- [x] **TASK-4.4** `assets/js/admin-pro.js`: Modify `init()` — add event listeners for Export Profile button and Import Profile file input; wire to `exportProfile` / `importProfile`.
- [x] **TASK-4.5** `assets/css/admin-pro.css`: Style export/import buttons in toolbar following existing `.vbb-cc-preset-btn` pattern. CONSOLE | CSS
- [x] **TASK-4.6** `inc/pro-admin.php`: Add "Export Profile" button and "Import Profile" file input to Command Center toolbar in `vbb_pro_render_command_center()`. CONSOLE | PHP
- [x] **TASK-4.7** `inc/pro-admin.php`: Add `admin_post` action `vbb_pro_export_profile` — return current settings as JSON download with appropriate headers. CONSOLE | PHP

## Phase 5: Keyboard Shortcuts

- [x] **TASK-5.1** `assets/js/admin-pro.js`: Modify `_bindKeyboardShortcuts()` — add Ctrl+S handler: call `CC.saveSettings()`, `preventDefault()`, let `showStatus` display 'saving'/'saved'/'error'.
- [x] **TASK-5.2** `assets/js/admin-pro.js`: Add Ctrl+Z handler in `_bindKeyboardShortcuts()` — if `undoRedoStack` non-empty, call `CC.undo()`, `preventDefault()`, show toast "Undo performed"; else show toast "No actions to undo".
- [x] **TASK-5.3** `assets/js/admin-pro.js`: Add `_isInputFocused()` helper — returns `true` if `document.activeElement` is a color input, text input, textarea, or select; used by keyboard shortcut handlers to skip when input is focused.
- [x] **TASK-5.4** `assets/js/admin-pro.js`: Add shortcut indicator text in `.vbb-cc-status-bar` (or toolbar) — "Ctrl+S Save · Ctrl+Z Undo".
- [x] **TASK-5.5** `assets/css/admin-pro.css`: Optional styling for keyboard shortcut indicator — small muted text, aligns with existing status bar typography. CONSOLE | CSS

## Phase 6: Verification & Cross-Cutting Checks

- [x] **TASK-V1** Unit: `undo()` / `redo()` correctly manipulate stacks; stack limited to 5 entries; new color change clears redo stack.
- [x] **TASK-V2** Integration: Modify color → unsaved indicator appears → save → indicator clears → undo → color reverts → redo → color reapplies.
- [x] **TASK-V3** Integration: Comparison mode — toggle Compare → iframe shows saved baseline → toggle back → shows current changes → persists via localStorage after reload.
- [x] **TASK-V4** Integration: Export → JSON file downloads with all required fields → Import valid JSON → settings applied → toast success.
- [x] **TASK-V5** Integration: Import invalid JSON → error toast "El JSON no es válido.", no settings changed.
- [x] **TASK-V6** Keyboard: Ctrl+S saves (status bar transitions saving→saved/error); Ctrl+Z undos; shortcuts disabled when input focused; indicator visible in UI.
- [x] **TASK-V7** Regression: Existing Command Center features — color picker, debounce, iframe refresh, profile save/reset — unchanged.
- [x] **TASK-V8** PHP syntax: `php -l inc/pro-admin.php` (file modified in TASK-4.6 and 4.7).
- [x] **TASK-V9** JS parse: Run acorn parse on `assets/js/admin-pro.js` to confirm no syntax errors introduced.
- [x] **TASK-V10** CSS balance: brace matching on `assets/css/admin-pro.css` — confirm no unclosed selectors or blocks.

---

## Archive Reconciliation Note

Stale checkbox reconciliation applied 2026-08-20 before archive.

All 40 tasks above were checked manually from persisted evidence:
- Engram #3236 — apply phase: all 40 tasks complete, ~280 lines added across admin-pro.js/admin-pro.css/pro-admin.php
- Engram #3160 — implementation summary: 5 UX features implemented
- Engram #3162 — verification PASS: all 5 features confirmed working

`apply-progress.md` documents the reconciliation and lists Engram observation IDs.