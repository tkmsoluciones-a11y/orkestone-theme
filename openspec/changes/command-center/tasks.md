# Tasks: Command Center — Interactive Theme Settings Management

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 270–400 |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | size-exception |

Decision needed before apply: Yes
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| A | REST API + functions.php + submenu | PR 1 | New `pro-rest-api.php`, wire require, submenu page |
| B | JS + CSS control panel + iframe | PR 2 | Depends on PR 1 — card UI, debounce, preview |
| C | Manual verification | PR 3 | Visit each endpoint, test flow end-to-end |

**Decision**: At ~300–400 changed lines (Medium risk), a single PR with size-exception is viable. If user prefers chain, split as shown.

## Skills to load before work

- `sdd-apply` — for implementation from these tasks
- `work-unit-commits` — for commit splitting into reviewable units

## Phase 1: REST API Foundation

- [x] 1.1 Create `inc/pro-rest-api.php` — register `orkestone/v1/vertical-settings` (GET + POST) and `vertical-config` (GET) routes
- [x] 1.2 Add `functions.php` require_once for `inc/pro-rest-api.php` after existing pro-admin include
- [x] 1.3 Add Command Center submenu page in `pro-admin.php` via `add_submenu_page('vbb-pro-elite', ...)`

## Phase 2: CSS for Card UI

- [x] 2.1 Add card grid, iframe container, and toggle switch styles to `assets/css/admin-pro.css`

## Phase 3: JavaScript Control Panel

- [x] 3.1 Build `vbbCommandCenter` module in `assets/js/admin-pro.js` — state, debounce utility (500ms), XHR helpers
- [x] 3.2 Implement `renderCards()` — generate card HTML for Colors, Typography, Layout, Blocks groups
- [x] 3.3 Wire `onFieldChange()` → `debouncedSave()` → `refreshPreview()` chain

## Phase 4: Preview & Integration

- [x] 4.1 Add iframe preview markup to Command Center page in `pro-admin.php` — clean URL with `?vbb_preview` cache bust
- [x] 4.2 Wire profile save/reset buttons — keep admin-post fallback or redirect to REST calls

## Phase 5: Manual Verification

- [ ] 5.1 Verify REST: `GET /orkestone/v1/vertical-settings` returns settings, POST saves, nonce-less returns 401
- [ ] 5.2 Verify debounce fires once per burst (browser console on `vbbCommandCenter`)
- [ ] 5.3 Verify iframe reloads with updated styles after save completes

## File Manifest

| File | Action | Est. Lines |
|------|--------|-----------|
| `inc/pro-rest-api.php` | Create | ~90–110 |
| `inc/pro-admin.php` | Modify | +25–35 |
| `assets/js/admin-pro.js` | Modify | +120–150 |
| `assets/css/admin-pro.css` | Modify | +60–80 |
| `functions.php` | Modify | +1 |
