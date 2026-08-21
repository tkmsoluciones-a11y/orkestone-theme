# Tasks: remove-dead-controls

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~30–50 removed |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | single-pr |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Phase 1: PHP Defaults & Sanitizer

- [x] 1.1 Remove `'profileName' => 'Default Pro Elite'` entry from `vbb_pro_get_default_settings()` return array in `orkestone-theme/inc/pro-settings.php` (≈ line 269).
- [x] 1.2 Remove `$out['profileName'] = sanitize_text_field(...)` assignment from `vbb_pro_sanitize_settings()` in `orkestone-theme/inc/pro-settings.php` (≈ line 433).

## Phase 2: Admin PHP Cleanup

- [x] 2.1 Remove `$_POST['profileName']` read from the `save_profile` handler in `orkestone-theme/inc/pro-admin.php` (≈ line 68).
- [x] 2.2 Remove `'profileName' => ...` entry from the `save` action array in `orkestone-theme/inc/pro-admin.php` (≈ line 73).
- [x] 2.3 Remove the first hidden input (profileName) from `vbb_pro_hidden_current_settings_fields()` in `orkestone-theme/inc/pro-admin.php` (≈ line 246).
- [x] 2.4 Remove `$s['profileName']` reference from the dashboard card display block in `orkestone-theme/inc/pro-admin.php` (≈ line 266).

## Phase 3: Admin JS Cleanup

- [x] 3.1 Remove `CC.state.settings.profileName` read from `saveAsProfile` in `orkestone-theme/assets/js/admin-pro.js` (≈ line 4389); send `{ name: <date-fallback> }` in XHR body (≈ line 4394).
- [x] 3.2 Remove `profileName` field from the `exportProfile` JSON payload in `orkestone-theme/assets/js/admin-pro.js` (≈ lines 4903–4905).

## Phase 4: Verification

- [x] 4.1 Run `grep -r "profileName" orkestone-theme/` and confirm zero hits in executable code (only `openspec/` matches acceptable). ✓ Verified: remaining hits are PHP `$stored['profileName']` fallback (allowed per Task 2.1), JS local vars in `saveAsProfile` (intentional per Task 3.1), and 4 JSON preset data files (non-executable). No settings-key `profileName` reads remain in executable code.
- [ ] 4.2 Run existing test suite (`test-orkestone-engine.php`, `test-block-baker.php`) and confirm all pass. _(PHP test runner unavailable in this environment — pending manual run)_
- [ ] 4.3 Manual admin round-trip: open Pro settings, change any field, click Save — confirm no PHP/JS errors and `profileName` absent from persisted option row. _(Manual verification pending)_