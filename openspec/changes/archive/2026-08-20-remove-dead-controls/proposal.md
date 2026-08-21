# Proposal: remove-dead-controls

## Intent

Remove the orphan `profileName` setting. It is declared in defaults, passed through sanitization, and referenced in admin templates/JS, but has no frontend consumer or rendering path. Keeping it adds dead code, confuses operators, and risks accidental reliance.

## Scope

### In Scope
- Remove `profileName` key from default settings in `inc/pro-settings.php`
- Remove `profileName` sanitization branch in `inc/pro-admin.php`
- Remove `profileName` references from admin JS state handling in `assets/js/admin-pro.js`

### Out of Scope
- `blocks.*.effect` — fully operational end-to-end; retained as active feature

## Capabilities

### New Capabilities
- `remove-dead-controls`: Removal of orphan `profileName` setting across PHP defaults, sanitization, and admin JS state

### Modified Capabilities
- None

## Approach

Strip `profileName` references cleanly across the three touchpoints. No replacement value or migration needed because the field has no persisted consumer data.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/pro-settings.php` | Removed | Drop `profileName` from `vbb_pro_default_settings()` return array |
| `inc/pro-admin.php` | Removed | Remove `profileName` case from `vbb_pro_sanitize_settings()` and any admin template references |
| `assets/js/admin-pro.js` | Removed | Remove `profileName` from initial state, save/load handlers, and UI bindings |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Undetected runtime reference to `profileName` | Low | Grep for `profileName` post-removal; verify zero hits in codebase |
| Legacy option row left in `wp_options` | Low | Harmless orphan; optional one-time DB cleanup documented in rollback |

## Rollback Plan

Revert the three files to the pre-change commit. Orphaned `profileName` rows in `wp_options` do not affect functionality.

## Dependencies

- None

## Success Criteria

- [ ] `profileName` is absent from `pro-settings.php`, `pro-admin.php`, and `admin-pro.js`
- [ ] `grep -r "profileName"` returns zero matches across the codebase
- [ ] Existing test suite (`test-orkestone-engine.php`, `test-block-baker.php`) passes
- [ ] Admin settings UI loads and saves without JS/PHP errors