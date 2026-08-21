# SDD Spec: Command Center UX Improvements

## Overview
This spec details 5 user experience improvements for the Command Center admin panel (`admin.php?page=vbb-command-center`). All changes are frontend-only (JS + CSS), extending existing patterns without REST API changes.

**Files modified:**
- `assets/js/admin-pro.js` — +180 lines
- `assets/css/admin-pro.css` — +80 lines
- `inc/pro-admin.php` — +20 lines

**Total:** +280 lines (within 400-line budget)

---

## 1. Live Preview with Visual "Unsaved Changes" Indicator

### Requirements
- Add a visual indicator in the status bar that turns yellow/orange when changes exist but haven't been saved
- When `debouncedSave` runs and `dirty` flag is true, show status bar with `vbb-cc-status-bar--unsaved` class
- When save completes, revert to idle/saved state
- Display "Unsaved changes" tooltip on status bar when dirty
- Integrate with existing `_flashChangedField` animation on modified inputs

### Scenarios
**Given** the user modifies a color/palette setting
**When** the change is detected by `onFieldChange` and `debouncedSave` is called with `visual: true`
**Then** the status bar shows `vbb-cc-status-bar--unsaved` class and tooltip "Unsaved changes"
**And** the modified input gets the `vbb-saved-flash` animation

**Given** the user clicks "Guardar configuración"
**When** `saveSettings` runs and completes successfully
**Then** the status bar reverts to `vbb-cc-status-bar--saved` with "Saved" text, auto-dismissing after 2s
**And** toast "Settings saved successfully!" appears

**Given** the user clicks "Guardar configuración" but it fails
**When** the AJAX request returns an error
**Then** the status bar shows `vbb-cc-status-bar--error` with error message
**And** toast shows the error message

### Acceptance Criteria
- [x] Status bar displays `vbb-cc-status-bar--unsaved` when `dirty === true` and no save is in progress
- [x] Status bar reverts to idle/saved after successful save
- [x] Tooltip "Unsaved changes" appears on status bar when dirty
- [x] Modified inputs get `vbb-saved-flash` animation
- [x] All existing save behaviors remain unchanged

### How It Extends Existing Patterns
- Builds on existing `showStatus` method (already has `idle`, `saving`, `saved`, `error` states)
- Extends `debouncedSave` which already sets `CC.state.dirty = true`
- Leverages existing `_flashChangedField` animation
- Uses same toast system (`showToast`) for consistency

### File Changes

**`assets/js/admin-pro.js`:**
- In `showStatus`: Add handling for `'unsaved'` state — add `vbb-cc-status-bar--unsaved` class, show tooltip
- In `debouncedSave`: After setting `CC.state.dirty = true`, ensure unsaved state is visible
- Add `_updateUnsavedIndicator()` helper method
- Modify `_finishSave` to reset unsaved state after save completes

**`assets/css/admin-pro.css`:**
- Add `.vbb-cc-status-bar--unsaved` style (yellow/orange background, tooltip positioning)
- Add tooltip styles for `.vbb-cc-status-bar--unsaved .vbb-cc-status-text`
- Keep existing status bar styles intact

**`inc/pro-admin.php`:**
- No changes needed — status bar is already rendered in `vbb_pro_render_command_center()` at line 613
- The `vbb-cc-status-bar` element exists; only CSS/JS changes needed

---

## 2. Undo/Redo for Color Changes (Last 5 Actions)

### Requirements
- Implement a stack-based undo history limited to 5 color/palette actions
- Each color change (via `onFieldChange` for `.palettes.*.*.*` or `.colors.*.*` paths) pushes to the undo stack
- "Undo" reverts the last color change and pushes to redo stack
- "Redo" re-applies the undone change
- Buttons in the preview toolbar or color card footer
- Persist undo state per session (not across sessions — local only)

### Scenarios
**Given** the user changes a color swatch via the color picker
**When** `onFieldChange` is called with a path starting with `palettes.` or `colors.`
**Then** the color value is pushed onto the undo stack (max 5 entries)
**And** the redo stack is cleared

**Given** the user clicks "Undo" button
**When** `undo()` is called and the undo stack has entries
**Then** the last color change is reverted (previous color is restored)
**And** the reverted color is pushed to the redo stack
**And** the UI reflects the undone state

**Given** the user clicks "Redo" button
**When** `redo()` is called and the redo stack has entries
**Then** the undone change is re-applied
**And** the redo stack pushes to the undo stack

**Given** the undo stack has 5 entries and a 6th change occurs
**When** the new change is pushed
**Then** the oldest entry is dropped (stack remains at 5 entries)

### Acceptance Criteria
- [x] Undo stack limited to 5 entries (color/palette changes only)
- [x] Undo button is disabled when stack is empty
- [x] Redo button is disabled when redo stack is empty
- [x] Clicking Undo reverts the last color change
- [x] Clicking Redo re-applies the undone change
- [x] Undo/Redo state is per-session only (refresh page → reset)
- [x] Non-color changes do not affect the undo stack

### How It Extends Existing Patterns
- Builds on existing `onFieldChange` method which already tracks `_lastChangedPath`
- Extends the color change detection at line 897: `if (path.indexOf('palettes.') === 0 || path.indexOf('colors.') === 0)`
- Uses same toast system for undo/redo feedback
- Follows the card-based UI pattern — undo/redo buttons in color card footer

### File Changes

**`assets/js/admin-pro.js`:**
- Add to `CC.state`: `undoRedoStack: []`, `redoStack: []`
- Add `undo()` method: pops from undo stack, reverts the setting, pushes to redo stack
- Add `redo()` method: pops from redo stack, re-applies the setting, pushes to undo stack
- Modify `onFieldChange`: for paths starting with `palettes.` or `colors.`, push to undo stack (limit 5), clear redo stack
- Add UI: Add "Undo" and "Redo" buttons in the preview toolbar (or color card footer)
- Add `_bindUndoRedo()` method to initialize buttons

**`assets/css/admin-pro.css`:**
- Style undo/redo buttons in the toolbar
- Add `.vbb-cc-undo-btn--disabled` and `.vbb-cc-redo-btn--disabled` for disabled state
- Button hover/active states following existing `.vbb-cc-preset-btn` pattern

**`inc/pro-admin.php`:**
- No changes needed — buttons are added purely in JS
- The preview toolbar already has space (`.vbb-cc-actions-row` at line 651)

---

## 3. Before/After Comparator (Toggle Saved vs Current State)

### Requirements
- Add a toolbar button "Compare" that toggles the preview between:
  - **After**: Current live preview with unsaved changes (default)
  - **Before**: Saved baseline state (what's stored in the database)
- The toggle persists via localStorage (remember last choice)
- Visual divider or split view showing "Saved" on left, "Current" on right, or a simple toggle button
- When "Before" is active, the preview iframe shows the previously-saved CSS vars state

### Scenarios
**Given** the user is on the Command Center page with saved settings
**When** the page loads
**Then** comparison mode defaults to `'after'` (current state)

**Given** the user clicks the "Compare" toolbar button
**When** the button is clicked
**Then** comparison mode toggles to `'before'` and the preview shows the saved baseline state
**And** localStorage stores `'vbb-cc-comparison-mode': 'before'`

**Given** the user clicks the "Compare" button again
**When** the button is clicked
**Then** comparison mode toggles back to `'after'` and the preview shows current live state
**And** localStorage stores `'vbb-cc-comparison-mode': 'after'`

**Given** the user switches to `'before'` comparison mode
**When** the preview iframe loads
**Then** the CSS vars from the previously-saved state are injected via postMessage

### Acceptance Criteria
- [x] Toolbar "Compare" button toggles comparison mode
- [x] Default mode is `'after'` (current live preview)
- [x] Comparison mode persists via localStorage
- [x] `'before'` mode shows saved baseline CSS vars in preview
- [x] `'after'` mode shows current changes in preview
- [x] Mode is remembered across page reloads

### How It Extends Existing Patterns
- Builds on existing postMessage mechanism (`vbb:css-vars` type) for preview updates
- Extends `initDarkMode` pattern for localStorage persistence
- Uses same `showStatus` system for visual feedback during comparison load
- Leverages existing `CC.buildCssVars()` method

### File Changes

**`assets/js/admin-pro.js`:**
- Add to `CC.state`: `comparisonMode: 'after'` (default)
- Add `_comparisonMode` localStorage key: `'vbb-cc-comparison-mode'`
- Add `toggleComparisonMode()` method: toggles between `'after'` and `'before'`, saves to localStorage
- Modify iframe `load` handler: when `comparisonMode === 'before'`, send postMessage with saved CSS vars
- Add `renderComparisonButton()` method: creates the Compare button in the toolbar
- Call `renderComparisonButton()` in `init()` after element setup

**`assets/css/admin-pro.css`:**
- Style the Compare button in the preview toolbar
- Add active state styling when comparison mode is `'before'`
- Tooltip for the button: "Mostrar estado guardado / Current state"

**`inc/pro-admin.php`:**
- Add Compare button to the preview toolbar in `vbb_pro_render_command_center()`
- The button should be in `.vbb-cc-preview-toolbar` (existing structure at line 624)
- Add it after the presets section, before the dark preview button

---

## 4. Export/Import Profiles as JSON (Extend Existing Save as Profile)

### Requirements
- Add "Export Profile" button that downloads the current settings as JSON file
- Add "Import Profile" button that opens a file input to select a JSON profile file
- Validate JSON schema on import (same schema as existing profile system)
- Show success/error toast messages
- Keep existing "Save as Profile" functionality intact

### Scenarios
**Given** the user is on the Command Center page
**When** the user clicks "Export Profile"
**Then** a JSON file downloads with the current settings including: profile name, color mode, palettes, typography, layout, blocks, buttons, exported date, theme, profile type, schema version

**Given** the user clicks "Import Profile" and selects a valid JSON file
**When** the file is validated against the profile schema
**Then** the settings are applied via the existing REST API (`orkestone/v1/vertical-settings`)
**And** success toast "Configuración Pro Elite importada." appears
**And** the page updates to reflect the new settings

**Given** the user clicks "Import Profile" and selects an invalid JSON file
**When** the JSON is invalid or fails schema validation
**Then** error toast "El JSON no es válido." appears
**And** no settings are changed

**Given** the user clicks "Import Profile" 
**When** the existing "Save as Profile" was previously used
**Then** the existing functionality remains unchanged — new export/import extends it

### Acceptance Criteria
- [x] "Export Profile" button downloads JSON with current settings
- [x] "Import Profile" button opens file input
- [x] Valid JSON profiles are applied successfully
- [x] Invalid JSON shows error toast
- [x] Existing "Save as Profile" functionality remains intact
- [x] Export includes: profileName, colorMode, palettes (light/dark), typography, layout, blocks, buttons, exportedAt, theme, profileType, schemaVersion

### How It Extends Existing Patterns
- Builds on existing `exportSite` method which already downloads JSON
- Extends the existing profile system in `pro-admin.php` (lines 127-156 handle `import_json`)
- Uses same toast system for feedback
- Follows the same JSON schema as existing profile export/import

### File Changes

**`assets/js/admin-pro.js`:**
- Add `exportProfile()` method: collects current `CC.state.settings`, sends to download endpoint
- Add `importProfile()` method: handles file input, validates JSON, applies via AJAX
- Modify `init()`: add event listeners for export/import buttons
- Add `_validateProfileJSON()` helper: checks required fields

**`assets/css/admin-pro.css`:**
- Style export/import buttons in the toolbar
- Follow existing button patterns (`.vbb-cc-preset-btn`, `.button`)

**`inc/pro-admin.php`:**
- Add "Export Profile" and "Import Profile" elements to the Command Center page
- The import handler already exists at line 127-156 (`import_json` action)
- Add export endpoint: new `admin_post` action `vbb_pro_export_profile`
- Profile name field should be pre-populated with current profile name

---

## 5. Keyboard Shortcuts: Ctrl+S for Save, Ctrl+Z for Undo

### Requirements
- **Ctrl+S**: Trigger `saveSettings()` — same as clicking "Guardar configuración" button
  - Show status bar 'saving' state
  - On success: show 'saved' state, toast "Settings saved successfully!"
  - On error: show 'error' state, toast error message
- **Ctrl+Z**: Trigger `undo()` from the undo/redo stack (if any actions available)
  - If no undo available: show info toast "No actions to undo"
  - If undo available: perform undo, show toast "Undo performed", update visual indicator
- Both shortcuts should be disabled when focus is on input fields (text inputs, color pickers) to avoid conflicts
- Display a small keyboard shortcut indicator somewhere in the UI (e.g., status bar hint or toolbar help)

### Scenarios
**Given** the user presses Ctrl+S while on the Command Center page
**When** no input field is focused
**Then** `saveSettings()` is called
**And** status bar shows 'saving' state
**And** on success: status bar shows 'saved', toast "Settings saved successfully!"
**And** on error: status bar shows 'error', toast with error message

**Given** the user presses Ctrl+Z while on the Command Center page
**When** no input field is focused and undo stack has actions
**Then** `undo()` is called
**And** toast "Undo performed" appears
**And** visual indicator updates

**Given** the user presses Ctrl+Z while on the Command Center page
**When** no input field is focused and undo stack is empty
**Then** toast "No actions to undo" appears

**Given** the user focuses on a color picker or text input
**When** Ctrl+S or Ctrl+Z is pressed
**Then** the shortcut is ignored (no action)
**And** no toast or status bar changes occur

### Acceptance Criteria
- [x] Ctrl+S triggers save, shows saving/saved/error states correctly
- [x] Ctrl+Z triggers undo when actions available, shows appropriate toast
- [x] Ctrl+Z shows "No actions to undo" when stack empty
- [x] Shortcuts disabled when focus is on input fields
- [x] Keyboard shortcut indicator displayed in UI
- [x] Default browser save behavior (Ctrl+S → save page) is prevented

### How It Extends Existing Patterns
- Builds on existing `_bindKeyboardShortcuts()` which is already called in `init()` at line 319
- Extends the existing keyboard shortcut infrastructure
- Uses same `showStatus` and `showToast` methods
- Follows the dark mode toggle pattern for opt-in feature

### File Changes

**`assets/js/admin-pro.js`:**
- Modify `_bindKeyboardShortcuts()` to add Ctrl+S and Ctrl+Z handlers
- Add keydown event listener that checks for focused inputs
- Ctrl+S: call `CC.saveSettings()`, prevent default
- Ctrl+Z: call `CC.undo()` if undo stack has entries, prevent default
- Add `_isInputFocused()` helper: checks if focus is on color inputs, text inputs, etc.
- Add keyboard shortcut indicator in the status bar or toolbar

**`assets/css/admin-pro.css`:**
- Optional: style the keyboard shortcut indicator
- No major CSS changes needed — can use existing status bar real estate

**`inc/pro-admin.php`:**
- No changes needed — keyboard shortcuts are purely frontend

---