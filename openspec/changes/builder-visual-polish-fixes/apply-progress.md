# Apply Progress: builder-visual-polish-fixes

## Mode
Standard — no Strict TDD (no test runner detected for this JS/PHP project).

## Completed Tasks

### Task 1: Success Toast on Save
- **File**: `assets/js/admin-pro.js`
- **What**: Added `CC.showToast('Settings saved successfully!', 'success')` in the `saveSettings()` success callback, right after `CC.showStatus('saved')`.
- **Line**: 280

### Task 2: Preview URL Display
- **File**: `inc/pro-admin.php`
- **What**: Added `<span id="vbb-cc-preview-url"></span>` inside the `.vbb-cc-preview-toolbar` div, after the refresh button.

- **File**: `assets/js/admin-pro.js`
- **What**: Added `_updatePreviewUrlDisplay()` helper method that reads the iframe's current `src` and sets it on `#vbb-cc-preview-url`.
- **What**: Added calls at the end of both `onPageChange()` and `refreshPreview()`.

### Task 3: Error Overlay Button HTML
- **File**: `assets/js/admin-pro.js`
- **What**: Changed `_showPreviewOverlay()` error state from `textContent` to `innerHTML` so the retry button renders as HTML instead of literal text.

## Files Changed
| File | Action | What Was Done |
|------|--------|---------------|
| `assets/js/admin-pro.js` | Modified | Added success toast, preview URL display logic, fixed error overlay button HTML |
| `inc/pro-admin.php` | Modified | Added preview URL span element |

## Deviations from Design
None — implementation matches the specified fixes exactly.

## Issues Found
None.

## Status
3/3 tasks complete. Ready for verify.
