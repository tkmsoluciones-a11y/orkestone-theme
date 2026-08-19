# Design: Orkestone Agency Hub — Business & Distribution

**Change**: agency-hub
**Status**: completed
**Next**: sdd-tasks
**Delivery Strategy**: force-chained (3 phases, 800-line review budget per PR)

---

## Technical Approach

The Agency Hub is a new WordPress plugin (`orkestone-agency-hub/`) that transforms Orkestone from a standalone theme into a professional agency distribution platform. It implements a **token-based delivery model (Model B)** where the Hub stores vertical JSON configs as CPT posts, exposes them via a public REST endpoint, and the client theme fetches and imports them using an activation token.

The end-to-end flow: **Briefing Form → JSON Builder → Pricing Calculator → Stripe Checkout → Webhook → Token Generation → Theme Activation via `POST /orkestone/v1/activate`**.

---

## Architecture Decisions

| # | Decision | Choice | Tradeoff / Rationale |
|---|----------|--------|----------------------|
| **AD1** | Plugin vs theme extension | **Separate plugin** | Other themes can use it. The 2 theme file edits (pro-rest-api.php, vertical-storage.php) are minimal and guarded by `function_exists()`. |
| **AD2** | Briefing form UI | **Vanilla JS tab form** | Matches existing theme asset patterns. No React build step. Keeps review budget under 800 lines. |
| **AD3** | JSON storage | **CPT post_content** | Avoids file-permission issues of `file_put_contents()`. Leverages WP revision history. The publish transition hooks are already CPT-native. |
| **AD4** | Token format | **UUID v4** (`wp_generate_uuid()`) + post meta for expiry | G3 resolution. JWT adds key management overhead with no benefit: tokens resolve a single CPT, not a distributed system. Expiry stored as `_orke_token_expires_at` (timestamp, 24h from generation). |
| **AD5** | Webhook retry | **Stripe-native retries + manual override** | G4 resolution. Stripe retries webhooks for 3 days with exponential backoff. A custom queue (WP Cron/DB table) is over-engineering for MVP. "Mark as Paid" handles edge cases. |
| **AD6** | Hub URL config | **WP option `orke_hub_url`** + `define('ORKE_HUB_URL', ...)` fallback | G5 resolution. Admin setting in Command Center (user-friendly), constant override for secure/headless deployments. |
| **AD7** | Token lifecycle | **Multi-use, revocable** | G6 resolution. Single-use tokens break legitimate re-activation scenarios (client re-imports after reset). Revocation is explicit via admin button. |
| **AD8** | SSL for payments | **Deployment prerequisite** | G7 resolution. Hub admin shows warning banner if `is_ssl()` returns false. Stripe Checkout requires HTTPS. |
| **AD9** | Asset URL persistence | **Filterable base URL** | G8 resolution. `apply_filters('orke_asset_base_url', site_url())` lets agencies migrate domains or add a CDN layer without regenerating configs. |
| **AD10** | Pricing engine vs validation | **Pure calculator, no enforcement** | G1 resolution. The calculator is a math engine — it computes BASE_PRICE even with 0 pages. Form validation (≥1 page, ≥1 menu item) runs separately before purchase. |
| **AD11** | Max JSON size | **5MB limit, 30s HTTP timeout** | G2 resolution. Hub rejects oversized JSON at generation time. Theme-side HTTP timeout is configurable via `wp_remote_get()` `timeout` arg. |

---

## Data Flow

```
AGENCY (Hub Plugin)                          CLIENT SITE (Orkestone Theme)
──────────────────────                       ─────────────────────────────

  Briefing Form (4 tabs)
        │
        ▼
  Orkestone_JSON_Builder::build()
        │
        ▼
  orke_configuration CPT (draft)
  post_content = JSON string
        │
        ▼
  Pricing Engine → itemized budget
        │
        ▼
  Stripe Checkout Session
        │
  ◄─── checkout.session.completed ─── Stripe
        │
        ▼
  Webhook → post_status → publish
        │
        ├── _orke_delivery_token (UUID)
        ├── _orke_token_expires_at (now + 24h)
        ├── _orke_payment_id
        └── _orke_payment_status = 'completed'
              │
              ▼
  GET /orke-hub/v1/config/{token}
  ──► 200 {success:true, config:{...}}
              ▲
              │  POST /orkestone/v1/activate {token}
              │  ───────────────────────────────►
                                                    │
                                                    ▼
                                              vbb_rest_activate_config()
                                                    │
                                                    ├── GET /orke-hub/v1/config/{token}
                                                    ├── vbb_validate_vertical_config()
                                                    ├── vbb_get_schema_version() check
                                                    ├── vbb_save_imported_vertical_config()
                                                    └── vbb_import_vertical_full($key)
                                                          │
                                                          ├── Pages created
                                                          ├── Media sideloaded
                                                          ├── Navigation set
                                                          ├── Front page applied
                                                          └── 200 {success:true, report:{...}}
```

---

## Component Design

### Phase 1 — Asset Library & Briefing Form (Data Collection)

#### orkestone-agency-hub.php (plugin bootstrap)
- Plugin header with `Plugin Name: Orkestone Agency Hub`
- `register_activation_hook()`, `register_deactivation_hook()`
- Loads `includes/*.php` files
- Registers CPTs on `init`
- Registers Hub REST routes on `rest_api_init`

#### class-configuration-cpt.php
- `register_post_type('orke_configuration', ...)` — `show_in_rest => true`, `supports => ['title', 'editor']`
- Post meta registration: `_orke_vertical_key`, `_orke_payment_id`, `_orke_payment_status`, `_orke_delivery_token`, `_orke_token_expires_at`, `_orke_token_allowed_origin`, `_orke_asset_base_url`
- Token generation hook: `on publish transition` → `wp_generate_uuid()` → store in `_orke_delivery_token`

#### class-asset-library.php
- `register_post_type('orke_asset', ...)` — `show_in_rest => true`
- `_vbb_media_role` post meta for role tagging (`logo`, `hero-main`, `about-image`, etc.)
- Filters `upload_mimes` to allow SVG
- Generates public asset URLs, filterable via `orke_asset_base_url`

#### class-briefing-form.php
- Admin page rendering: 4-tab form UI (Vanilla JS)
- Validation: site name ≥ 1 char, ≥ 1 page, ≥ 1 menu item
- Tabs: (1) Branding, (2) Pages & Sections, (3) Content & Models, (4) Navigation & SEO

#### class-json-builder.php
```php
class Orkestone_JSON_Builder {
    public function build(array $form_data): array;
    public function validate(array $config): bool;
    public function get_json(array $config): string;
}
```
- Maps form data → vertical JSON matching `default.json` schema
- Sets `schemaVersion` to value from `vbb_get_schema_version()` (available via theme, fallback to `"1.0.0"` if function doesn't exist)
- Generates `verticalKey` as sanitized site name slug
- Resolves asset URLs through `apply_filters('orke_asset_base_url', site_url())`
- Output: `wp_json_encode()` with `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`

#### Files created
| File | Purpose |
|------|---------|
| `orkestone-agency-hub.php` | Plugin bootstrap, hook registrations |
| `includes/class-configuration-cpt.php` | `orke_configuration` CPT + token lifecycle hooks |
| `includes/class-asset-library.php` | `orke_asset` CPT + upload handling |
| `includes/class-briefing-form.php` | 4-tab form UI, validation logic |
| `includes/class-json-builder.php` | Form data → vertical JSON mapper |
| `assets/js/briefing-form.js` | Tab navigation, field validation, JSON preview |
| `assets/css/admin.css` | Namespaced styles (`.orke-hub-*`) |
| `templates/admin-briefing-form.php` | Admin page template |

### Phase 2 — Budgeting & Payment (Monetization)

#### class-pricing.php
- Formula: `Total = BASE_PRICE + (PAGE_PRICE × pages) + Σ(section_complexity[type]) + (ITEM_PRICE × total_model_items) + premium_sections_surcharge`
- Default constants (all filterable via `apply_filters('orke_agency_pricing', $pricing)`):
  - `BASE_PRICE = 499`
  - `PAGE_PRICE = 99`
  - `ITEM_PRICE = 10`
  - `PREMIUM_SURCHARGE = 50`
  - `section_complexity` per type map
- Returns `itemized[]` array with component, qty, unit price, subtotal
- Pure calculator — does NOT validate form completeness (AD10)

#### class-payment-gateway.php
- `create_checkout_session($post_id, $budget)` — calls Stripe API `checkout.sessions.create`
  - Line items from budget components
  - Metadata: `post_id`, `_orke_vertical_key`
  - Success URL: Hub admin configuration page
  - Cancel URL: Hub budget page
- `handle_webhook($payload, $signature)` — verifies Stripe signature, processes `checkout.session.completed`
- `mark_as_paid($post_id, $user_id)` — manual override, stores `_orke_payment_status='manual'`
- PayPal IPN support via `POST /orke-hub/v1/webhook/paypal` (secondary)

#### Files created
| File | Purpose |
|------|---------|
| `includes/class-pricing.php` | Budget calculator with filterable constants |
| `includes/class-payment-gateway.php` | Stripe/PayPal abstraction, webhooks, manual override |

### Phase 3 — Token System & Theme Connector (Delivery)

#### class-delivery.php
- Token generated on `orke_configuration` publish transition via `wp_generate_uuid()`
- Stored in `_orke_delivery_token` + `_orke_token_expires_at` (24h from publish)
- Token revocation: sets `_orke_delivery_token` to `revoked-{timestamp}`, logs user ID
- Token regeneration (replaces old token, marks old as revoked)

#### class-hub-rest-api.php
| Method | Route | Permission | Purpose |
|--------|-------|------------|---------|
| GET | `/orke-hub/v1/config/{token}` | None (public) | Returns full vertical JSON for valid, non-revoked, non-expired token. 404 for invalid/revoked/expired. |
| POST | `/orke-hub/v1/validate-token` | None (public) | Returns `{valid:bool, verticalKey:string}`. Never exposes JSON. |
| POST | `/orke-hub/v1/webhook/stripe` | None (Stripe-signed) | Receives Stripe webhooks, verifies signature, processes payment completion. |
| POST | `/orke-hub/v1/webhook/paypal` | None (PayPal IPN) | Receives PayPal IPN, validates, processes payment. |

**Token security** (REQ-AH18):
- UUID + expiry meta (AD4) — validated server-side
- Rate limiting: `WP_Rate_Limit` or custom transient `orke_rate_limit_{ip}` with 10 req/min/IP
- Origin validation: `_orke_token_allowed_origin` meta set during token generation, compared to `Origin` header on activation requests
- Revocation: checked before expiry in token lookup

#### Theme modifications (2 files)

**`orkestone-theme/inc/pro-rest-api.php`** — Add route + callback:

```php
register_rest_route('orkestone/v1', '/activate', array(
    'methods'             => WP_REST_Server::CREATABLE,
    'callback'            => 'vbb_rest_activate_config',
    'permission_callback' => 'vbb_rest_command_center_permission',
));
```

`vbb_rest_activate_config()` callback flow:
1. Validate input: `{token: "..."}` present and string
2. Fetch Hub URL from `get_option('orke_hub_url')` or `ORKE_HUB_URL` constant
3. `wp_remote_get("$hub_url/orke-hub/v1/config/{token}", ['timeout' => 30])`
4. Validate response: 200 + `success:true` + `config` object
5. Run `vbb_validate_vertical_config($config)` — if invalid, return 400
6. Schema check: compare `$config['schemaVersion']` with `vbb_get_schema_version()` using SemVer comparison (major mismatch → 409)
7. `vbb_save_imported_vertical_config($config)` — persist to uploads
8. `vbb_import_vertical_full($config['verticalKey'])` — full import pipeline
9. Return 200 with import report

**`orkestone-theme/inc/vertical-storage.php`** — Add:

```php
function vbb_get_schema_version(): string {
    return '1.0.0'; // Bump this when vertical JSON schema changes
}
```

#### Files modified
| File | Change | Risk |
|------|--------|------|
| `orkestone-theme/inc/pro-rest-api.php` | Add `/activate` route + callback | R2 — guarded by `function_exists()` on new functions. No route collision with existing `orkestone/v1/*` routes. |
| `orkestone-theme/inc/vertical-storage.php` | Add `vbb_get_schema_version()` | R1 — no side effects, pure return. |

---

## Spec Gap Resolutions

| Gap | Resolution | Rationale |
|-----|-----------|-----------|
| **G1** | Pricing calculator is a pure math engine — does not enforce page minimums | Form validation handles business rules. Calculator must work for edge cases (showing base price with 0 pages is valid display). |
| **G2** | Max 5MB JSON, 30s HTTP timeout | 5MB covers the largest realistic vertical. Timeout matches WP default + 5s buffer. Oversized JSON returns 413 from Hub. |
| **G3** | UUID v4 + post meta expiry, NOT JWT | No key management. Expiry checked server-side. Simple, auditable, no crypto dependencies. |
| **G4** | Stripe-native retries (3 days exponential backoff) + manual "Mark as Paid" override | Custom retry queue is premature. Stripe's built-in retry + admin override covers the failure case. |
| **G5** | WP option `orke_hub_url` (admin UI) + `define('ORKE_HUB_URL', ...)` fallback | Command Center settings page for most users, `wp-config.php` for secure/automated setups. |
| **G6** | Multi-use tokens, revocable explicitly | Single-use breaks legitimate re-activation. Explicit revocation gives the agency control without blocking valid workflows. |
| **G7** | Warning banner on HTTP sites, documented deployment prerequisite | Hub admin shows "Stripe requires HTTPS" notice via `admin_notices` when `!is_ssl()`. |
| **G8** | Filterable base URL via `orke_asset_base_url` filter, defaulting to `site_url()` | Lets agencies migrate to CDN or new domain without regenerating configs. URLs resolved at JSON build time. |

---

## Performance: Asset Delivery Strategy

**Sideloading** is the primary delivery mechanism (matching existing theme pattern). The theme's `vbb_import_vertical_media_with_placeholders()` already handles:

- `media_sideload_image()` for remote URLs
- `vbb_create_placeholder_attachment()` as SVG fallback
- `_vbb_source_url` deduplication via post meta

**CDN readiness**: The `orke_asset_base_url` filter lets agencies plug in Cloudflare R2, AWS CloudFront, or any CDN at the Hub level. The generated JSON always uses absolute URLs, so the sideloader fetches from whatever URL the filter returns.

No CDN integration in MVP. If needed later, it's a filter change — no theme modifications required.

---

## Rollback Plan

| Phase | Rollback | Side Effects |
|-------|----------|--------------|
| **1** | Deactivate plugin | `orke_asset` and `orke_configuration` posts remain in DB (soft cleanup on re-activation). JSON is post_content, not files — no orphaned files. |
| **2** | Deactivate plugin | Stripe webhooks queue at Stripe side for 3 days. Re-enable plugin within window recovers pending. Manual "Mark as Paid" overrides any missed webhooks. |
| **3** | Deactivate plugin + `git revert` on pro-rest-api.php & vertical-storage.php | Existing activated sites keep their config. Tokens become unresolvable. Hub-side tokens remain in DB — re-enable plugin restores endpoint. |

**Payment failure recovery**: If Stripe payment succeeds but webhook never arrives (rare, Stripe retries 3 days):
1. Agency notices config stuck in `draft`
2. Clicks "Mark as Paid" — confirms dialog
3. Post transitions to `publish`, token generated
4. Stripe retry arrives later → webhook handler detects post already published → logs duplicate → returns 200 (idempotent)

---

## File Change Summary

| Action | File | Phase |
|--------|------|-------|
| Create | `orkestone-agency-hub/orkestone-agency-hub.php` | 1 |
| Create | `orkestone-agency-hub/includes/class-configuration-cpt.php` | 1 |
| Create | `orkestone-agency-hub/includes/class-asset-library.php` | 1 |
| Create | `orkestone-agency-hub/includes/class-briefing-form.php` | 1 |
| Create | `orkestone-agency-hub/includes/class-json-builder.php` | 1 |
| Create | `orkestone-agency-hub/includes/class-pricing.php` | 2 |
| Create | `orkestone-agency-hub/includes/class-payment-gateway.php` | 2 |
| Create | `orkestone-agency-hub/includes/class-delivery.php` | 3 |
| Create | `orkestone-agency-hub/includes/class-hub-rest-api.php` | 3 |
| Create | `orkestone-agency-hub/assets/js/briefing-form.js` | 1 |
| Create | `orkestone-agency-hub/assets/css/admin.css` | 1 |
| Create | `orkestone-agency-hub/templates/admin-briefing-form.php` | 1 |
| Modify | `orkestone-theme/inc/pro-rest-api.php` | 3 |
| Modify | `orkestone-theme/inc/vertical-storage.php` | 3 |

**Total: 12 new files, 2 modified files.** Within 800-line review budget per phase.

---

## Testing Strategy

| Layer | What | How |
|-------|------|-----|
| **Unit** | `Orkestone_JSON_Builder::build()` — map all tabs, missing fields, empty data | PHPUnit with mocked form data, assert output passes `vbb_validate_vertical_config()` |
| **Unit** | `class-pricing.php` — formula accuracy, filterability, zero-page edge case | PHPUnit with controlled page/section/item counts, `apply_filters` mock |
| **Unit** | Token validation — expired, revoked, valid, malformed | Unit test token lookup against known DB state (fixtures) |
| **Integration** | Stripe webhook → CPT publish transition | WP Test Suite with Stripe test mode, mock webhook payload |
| **Integration** | `POST /orkestone/v1/activate` — full round-trip with mock Hub response | Theme-side test with `wp_remote_get` intercepted via filter |
| **E2E** | Scenario 1 (full happy path) | Manual test with real Stripe test mode + two WordPress installs |
| **Regression** | R1-R12 from spec | Automated via existing test suite + manual verification of Command Center, import pipeline, navigation, media sideloading |

---

## Open Questions

- None resolved by design. All 8 spec gaps (G1-G8) closed.
- Implementation should verify that `vbb_validate_vertical_config()` from `vertical-validator.php` is the single source of truth for JSON shape — no second schema definition in the Hub.
- Confirm that `wp_generate_uuid()` is available (WP 5.7+ target — yes, available since WP 4.7 via `wp_generate_uuid4()`).
