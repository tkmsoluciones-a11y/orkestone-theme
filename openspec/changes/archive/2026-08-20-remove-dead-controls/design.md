# Design: remove-dead-controls

## Technical Approach

Strip the orphan `profileName` setting from three touchpoints: PHP defaults (`vbb_pro_default_settings`), sanitization (`vbb_pro_sanitize_settings`), and admin JS state/export logic. The key has no frontend consumer or rendering path; deletions are purely subtractive with no replacement logic required. Legacy `profileName` rows in `wp_options` are harmless orphans that survive the merge path via `vbb_pro_deep_merge` and are dropped on next save.

## Architecture Decisions

### Decision: Delete `profileName` from defaults and sanitizer, do not re-map

**Choice**: Remove the key entirely from `vbb_pro_default_settings()` and `vbb_pro_sanitize_settings()`.
**Alternatives considered**: (a) Keep the field as a no-op, (b) re-map to an existing key.
**Rationale**: The spec explicitly requires zero `profileName` references. Mapping to another key would re-introduce state coupling with no consumer. Simplest correct path is pure removal.

### Decision: Strip `profileName` from PHP save handlers and hidden fields

**Choice**: Remove the `profileName` entry from the `save` action array and from `vbb_pro_hidden_current_settings_fields()`.
**Alternatives considered**: Keep hidden input as a no-op passthrough.
**Rationale**: The hidden input drives the `save` POST payload. Leaving it in would cause the sanitizer to receive a key it no longer handles; removing it prevents PHP notices and keeps the HTML output aligned with the new settings schema.

### Decision: Update `saveAsProfile` and `exportProfile` to ignore `profileName`

**Choice**: Remove the `CC.state.settings.profileName` read from `saveAsProfile`; use the date-based fallback string directly. Drop the `profileName` field from the `exportProfile` JSON payload.
**Alternatives considered**: Keep `profileName` in export for backward compatibility.
**Rationale**: Export consumers parse the JSON themselves; removing one key that no consumer reads is safe and matches the "zero references" success criterion.

### Decision: No database migration

**Choice**: Leave existing `vbb_pro_settings` rows containing `profileName` as-is; they degrade to harmless orphans through `vbb_pro_deep_merge`.
**Alternatives considered**: Run a one-time `DELETE` or `unset` on stored options.
**Rationale**: Adds operational risk for zero benefit. Orphan keys are ignored by the new code paths and are overwritten on the next save cycle.

## Data Flow

    ┌────────────────────┐      ┌──────────────────────┐      ┌─────────────────┐
    │  admin-pro.js      │ POST │  pro-admin.php       │  in  │ pro-settings.php│
    │  CC.state.settings │ ────▶│  vbb_pro_handle_     │─────▶│  vbb_pro_       │
    │  (no profileName)  │      │  admin_actions()     │      │  sanitize_      │
    │                    │      │  save array (no key) │      │  settings()     │
    └────────────────────┘      └──────────────────────┘      └─────────────────┘
                                                            │  defaults array  │
                                                            │  (no profileName)│
                                                            └─────────────────┘

    Export path (saveAsProfile):
    CC.state.settings ──▶ XHR POST { name: <date-fallback> } ──▶ vbb_pro_save_profile()

    Export path (exportProfile):
    CC.state.settings ──▶ JSON blob (no profileName field) ──▶ download

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `orkestone-theme/inc/pro-settings.php` | Modify | Remove `'profileName' => 'Default Pro Elite'` from the return array (line 269). Remove `$out['profileName'] = sanitize_text_field(...)` from sanitizer (line 433). |
| `orkestone-theme/inc/pro-admin.php` | Modify | Remove `$_POST['profileName']` read from `save_profile` handler (line 68). Remove `'profileName' => ...` entry from `save` action array (line 73). Remove first hidden input from `vbb_pro_hidden_current_settings_fields()` (line 246). Remove `$s['profileName']` from dashboard card display (line 266). |
| `orkestone-theme/assets/js/admin-pro.js` | Modify | Remove `CC.state.settings.profileName` read in `saveAsProfile` (line 4389); send only `{ name }` in XHR body (line 4394). Remove `profileName` field from `exportProfile` payload (lines 4903–4905). |

## Interfaces / Contracts

No new interfaces. The `vbb_pro_sanitize_settings()` return contract is reduced by one top-level key. The REST `vertical-settings` endpoint continues to return the same shape minus `profileName`.

Export JSON shape change (minor — backward-compatible prune):

```json
// BEFORE
{ "profileName": "...", "colorMode": "...", "palettes": {...}, ... }

// AFTER
{ "colorMode": "...", "palettes": {...}, ... }
```

`saveAsProfile` XHR payload change:

```js
// BEFORE
{ name: profileName }

// AFTER (identical shape, profileName is now always a generated fallback)
{ name: 'Profile DD/MM/YYYY' }
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Static | Zero `profileName` references in executable code | `grep -r "profileName" orkestone-theme/inc orkestone-theme/assets/js` — expect zero matches (docs/comments in `openspec/` are excluded per spec) |
| Unit | `vbb_pro_default_settings()` contract | Call the function; assert `!array_key_exists('profileName', $result)` |
| Unit | `vbb_pro_sanitize_settings()` contract | Pass input with `profileName` key; assert returned array does not contain `profileName` |
| Integration | Admin save round-trip | POST to `admin-post.php?action=vbb_pro_save` with a full settings payload; confirm no PHP warnings and stored option row excludes `profileName` |
| Manual | Command Center load + save | Open Command Center, change any field, click Save; verify no JS console errors and settings persist |
| Manual | Profile export/import | Click Export in Command Center; open JSON and confirm no `profileName` field |

## Migration / Rollout

No migration required. Existing `profileName` values in `wp_options` degrade to no-op orphans. They are silently dropped on the next settings save.

**Rollback**: Revert the three files to the pre-change commit. Orphaned `profileName` rows in `wp_options` do not affect functionality.

## Open Questions

None.