# Design: Command Center Admin Dashboard

## Technical Approach

Add a REST API layer (`orkestone/v1`) over the existing `vbb_pro_settings` option infrastructure, then build a Vanilla JS card-based control panel with a live preview iframe that replaces the current server-rendered `vbb-pro-elite` admin form. Debounced API calls (500ms) persist settings to the same option, and the iframe reloads to reflect them. The existing `pro-admin.php`, `admin-pro.js`, and `admin-pro.css` files are extended — no new PHP entry points.

## Architecture Decisions

| Option | Tradeoff | Decision |
|--------|----------|----------|
| REST API vs admin-post actions | REST returns JSON, supports AJAX debounce; admin-post requires full page round-trip | ✅ REST (custom `orkestone/v1` endpoints) |
| Vanilla JS vs Alpine/React | Alpine adds dependency; React needs build step (forbidden) | ✅ Vanilla JS — single file, module pattern |
| Iframe refresh vs postMessage | postMessage is faster but needs dual code (preview receiver) | ✅ Iframe src reload with cache-bust — reuses existing `wp_head` CSS vars |
| New JS file vs extend admin-pro.js | Separate file = cleaner; single file = simpler deployment | ✅ Extend `admin-pro.js` — matches user's single-file expectation |
| Tailwind vs custom CSS for admin | Tailwind needs enqueue; custom CSS is zero-dependency | ✅ Custom CSS in `admin-pro.css` — follows existing pattern |
| Full save vs partial save on each card | Full save is simpler, debounce handles frequency | ✅ Full settings object on every save — `vbb_pro_sanitize_settings()` handles it |

## Data Flow

```
User changes card field (input/select/toggle)
        │
        ▼
   Debounce timer (500ms)
        │
        ▼
  POST /orkestone/v1/vertical-settings
  Body: { entire settings object }
  Header: X-WP-Nonce
        │
        ▼
  pro-rest-api.php → vbb_pro_sanitize_settings() → update_option('vbb_pro_settings')
        │
        ▼
  Response: { success: true, settings: {...} }
        │
        ▼
  Iframe.src = window.location.href + '?vbb_preview={timestamp}'
        │
        ▼
  Frontend render with updated --vbb-pro-* CSS vars
```

```
Admin Page Layout:

┌──────────────────────────────────────────────────┐
│  OrkestOne Theme — Command Center                │
├───────────────────────┬──────────────────────────┤
│                       │                          │
│  ┌─── Card: Colors ──┐│  ┌─── Live Preview ───┐ │
│  │ Light: #__  #__ .. ││  │                     │ │
│  │ Dark:  #__  #__ .. ││  │   <iframe>          │ │
│  └────────────────────┘│  │   loads frontend    │ │
│  ┌─── Card: Typography┐│  │   with current      │ │
│  │ Heading: Inter     ││  │   CSS vars applied  │ │
│  │ Body:    Inter     ││  │                     │ │
│  └────────────────────┘│  └─────────────────────┘ │
│  ┌─── Card: Layout ───┐│                          │
│  │ Content: 1180px    ││                          │
│  │ Wide:    1440px    ││                          │
│  └────────────────────┘│                          │
│  ┌─── Card: Blocks ───┐│                          │
│  │ ☑ hero ☑ services  ││                          │
│  └────────────────────┘│                          │
│                       │                          │
│  [Save] [Save Profile] [Reset]                    │
└──────────────────────────────────────────────────┘
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/pro-rest-api.php` | Create | REST endpoint registration + handlers for `orkestone/v1` |
| `inc/pro-admin.php` | Modify | Add new `Command Center` submenu page; add iframe markup |
| `assets/js/admin-pro.js` | Modify | Rewrite from 7-line stub to full card-based control panel with debounced AJAX |
| `assets/css/admin-pro.css` | Modify | Add card grid layout, iframe container, toggle switches, responsive breakpoints |
| `functions.php` | Modify | Add `require_once` for `inc/pro-rest-api.php` |

## Interfaces / Contracts

### REST API — `orkestone/v1`

```
GET  /orkestone/v1/vertical-settings
  → Response: { settings: {...} }     (full vbb_pro_get_settings() object)
  
POST /orkestone/v1/vertical-settings
  → Body: { settings: {...} }          (full or partial settings object)
  → Response: { success: true, settings: {...} }
  → Error:   { success: false, message: "..." }
  → Auth:    manage_options + wp_rest nonce

GET /orkestone/v1/vertical-config
  → Response: { config: {...} }        (vbb_get_vertical_config() for preview context)
```

### PHP Functions (new in `pro-rest-api.php`)

```php
register_rest_route( 'orkestone/v1', '/vertical-settings', array(
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => 'vbb_rest_get_settings',
    'permission_callback' => function() { return current_user_can( 'manage_options' ); },
) );
register_rest_route( 'orkestone/v1', '/vertical-settings', array(
    'methods'             => WP_REST_Server::CREATABLE,
    'callback'            => 'vbb_rest_update_settings',
    'permission_callback' => function() { return current_user_can( 'manage_options' ); },
) );
```

### JS Module Structure (in `admin-pro.js`)

```
vbbCommandCenter = {
    state: { settings: {}, dirty: false, previewUrl: '' },
    debounceTimer: null,
    el: { cards: {}, iframe: {}, toolbar: {} },

    init(),                    // DOMContentLoaded entry
    loadSettings(),            // GET /orkestone/v1/vertical-settings
    renderCards(),             // Build card HTML from state
    onFieldChange(),           // Update state → start debounce
    debouncedSave(),           // 500ms timer → POST
    saveSettings(),            // XHR POST /orkestone/v1/vertical-settings
    refreshPreview(),          // Bump iframe src with timestamp
    saveAsProfile(),           // Existing POST form fallback
    resetSettings()            // Existing reset fallback
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit (PHP) | REST permission callback, sanitization round-trip | Manual with `wp unit` or curl against dev site |
| Integration (JS) | Debounce fires once per burst; state → API payload mapping | Browser console testing with `vbbCommandCenter` global |
| E2E | Full flow: load page → change color → iframe reflects change | Manual — navigate admin, change card, verify iframe |
| Security | Nonce rejection, unauthorized 401, XSS in settings values | Manual — curl without nonce, curl without auth, inject `<script>` |

## Migration / Rollout

No migration required. The Command Center reads/writes the same `vbb_pro_settings` option as the existing form. Both can coexist — add the new page alongside the old tabs; deprecate old forms once verified.

## Open Questions

- [ ] Should the iframe load the front page (site URL) or a dedicated preview endpoint? Front page is simplest but may include admin headers if logged in.
- [ ] Profile save/apply — rewire to REST or keep as existing admin-post form fallback?
- [ ] SVG placeholder images we created earlier — wire them into vertical config cards as visual indicators?
