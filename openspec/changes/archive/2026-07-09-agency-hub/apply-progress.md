# Apply Progress: Orkestone Agency Hub — All Phases

**Change**: agency-hub
**Phase**: 1, 2 & 3 — Infrastructure, Briefing Engine, Payments & Delivery
**Status**: ✅ COMPLETE — All 12 tasks implemented
**Date**: 2026-07-09
**Mode**: Standard (no Strict TDD)

---

## Summary

### Phase 1 — Infrastructure & Asset Manager (Complete)
- Plugin bootstrap with autoloader, activation/deactivation hooks
- `orke_configuration` CPT with all meta fields (`_orke_vertical_key`, `_orke_payment_*`, `_orke_delivery_token`, `_orke_token_*`)
- `orke_asset` CPT with SVG/PNG/JPEG upload support, role tagging via `_vbb_media_role`
- Namespaced admin CSS (`.orke-hub-*`)

### Phase 2 — Briefing Engine & Budgeting (Complete)
- 4-tab briefing form (Branding, Pages & Sections, Content & Models, Navigation & SEO)
- Client-side form validation + dynamic add/remove fields
- `Orkestone_JSON_Builder` — maps form data to vertical JSON matching `default.json` schema
- `Orkestone_Pricing` — itemized budget calculator with filterable constants

### Phase 3 — Payments & Delivery (Complete — this phase)
1. **Payment Gateway (TASK-AH3.1, 3.2)** — Stripe Checkout integration with `create_checkout_session()`, webhook handler with signature verification, manual "Mark as Paid" override, PayPal IPN support
2. **Token Delivery System (TASK-AH3.3)** — UUID v4 token generation on publish, 24h expiry via post meta, revocation and regeneration, daily cleanup cron
3. **Hub REST API (TASK-AH3.4)** — `GET /orke-hub/v1/config/{token}`, `POST /orke-hub/v1/validate-token`, Stripe/PayPal webhook endpoints, rate limiting (10 req/min/IP), origin validation
4. **Theme Connector (TASK-AH3.5)** — `vbb_get_schema_version()` in `vertical-storage.php`, `POST /orkestone/v1/activate` route in `pro-rest-api.php` with full activation flow and SemVer schema compatibility check

---

## Completed Tasks

| Task | Status | Files Created / Modified |
|------|--------|--------------------------|
| **1.1** Plugin Bootstrap + Configuration CPT | ✅ Done | `orkestone-agency-hub.php`, `includes/class-configuration-cpt.php` |
| **1.2** Asset Library | ✅ Done | `includes/class-asset-library.php` |
| **1.3** Admin CSS | ✅ Done | `assets/css/admin.css` |
| **2.1** Briefing Form UI | ✅ Done | `includes/class-briefing-form.php`, `templates/admin-briefing-form.php` |
| **2.2** Form Interaction JS | ✅ Done | `assets/js/briefing-form.js` |
| **2.3** JSON Builder | ✅ Done | `includes/class-json-builder.php` |
| **2.4** Pricing Calculator | ✅ Done | `includes/class-pricing.php` |
| **3.1** Payment Gateway (Stripe Checkout + Webhook) | ✅ Done | `includes/class-payment-gateway.php` |
| **3.2** Manual Override + PayPal IPN | ✅ Done | `includes/class-payment-gateway.php` (same file) |
| **3.3** Token Delivery System | ✅ Done | `includes/class-delivery.php` |
| **3.4** Hub REST API | ✅ Done | `includes/class-hub-rest-api.php` |
| **3.5** Theme Connector | ✅ Done | `orkestone-theme/inc/vertical-storage.php`, `orkestone-theme/inc/pro-rest-api.php` |

---

## Files Created / Modified in Phase 3

| File | Lines | Action | Description |
|------|-------|--------|-------------|
| `orkestone-agency-hub/includes/class-payment-gateway.php` | ~460 | Created | Stripe Checkout session creation, webhook handler w/ signature verification, manual "Mark as Paid" override, PayPal IPN verification + processing |
| `orkestone-agency-hub/includes/class-delivery.php` | ~430 | Created | UUID v4 token generation on `orke_configuration_paid` hook, token validation (expiry + revocation), token revocation/regeneration admin actions, token meta box on edit screen, daily expired token cleanup |
| `orkestone-agency-hub/includes/class-hub-rest-api.php` | ~370 | Created | `GET /orke-hub/v1/config/{token}` (returns JSON for valid tokens), `POST /orke-hub/v1/validate-token` (returns `{valid,verticalKey}`), Stripe/PayPal webhook endpoints, UUID validation, rate limiting via transient (10 req/min/IP), origin validation |
| `orkestone-agency-hub/orkestone-agency-hub.php` | ~320 | Modified | Added 3 new dependencies to autoloader, registered hooks for all Phase 3 classes, added Settings page for Stripe API keys with full form + webhook URL display |
| `orkestone-theme/inc/vertical-storage.php` | ~165 | Modified | Added `vbb_get_schema_version()` returning `"1.0.0"` with filterable hook (`vbb_schema_version`) |
| `orkestone-theme/inc/pro-rest-api.php` | ~880 | Modified | Added `vbb_rest_activate_config()` callback with full activation flow (token → Hub fetch → validate → schema check → save → import → report), `vbb_check_schema_compatibility()` SemVer checker, registered `POST /orkestone/v1/activate` route |

---

## Plugin Structure (Final)

```
orkestone-agency-hub/
├── orkestone-agency-hub.php              # Bootstrap (updated: Phase 3 deps + Settings page)
├── includes/
│   ├── class-configuration-cpt.php       # orke_configuration CPT
│   ├── class-asset-library.php           # orke_asset CPT
│   ├── class-json-builder.php            # Form → vertical JSON mapper
│   ├── class-pricing.php                 # Budget calculator
│   ├── class-briefing-form.php           # 4-tab form admin page
│   ├── class-payment-gateway.php         # Stripe/PayPal/manual payments [NEW]
│   ├── class-delivery.php                # Token generation/lifecycle  [NEW]
│   └── class-hub-rest-api.php            # Hub REST endpoints          [NEW]
├── assets/
│   ├── css/
│   │   └── admin.css                     # Namespaced admin styles
│   └── js/
│       └── briefing-form.js              # Tab nav, validation, dynamic fields
└── templates/
    └── admin-briefing-form.php           # 4-tab form template
```

---

## Phase 3 Component Details

### `Orkestone_Payment_Gateway` (`includes/class-payment-gateway.php`)
- **`create_checkout_session($post_id, $budget)`** — Maps budget items to Stripe line items via `wp_remote_post()` (no SDK dependency). Total sent as USD cents. Metadata includes `post_id` and `vertical_key` for webhook correlation. Stores `_orke_payment_id` (session ID) and `_orke_payment_status='pending'`.
- **`handle_webhook($payload, $signature)`** — Verifies Stripe signature using HMAC-SHA256 against `_orke_webhook_secret`. Processes `checkout.session.completed`: finds post by metadata → transitions draft→publish → stores `_orke_payment_status='completed'`. Idempotent: if already published, logs and returns success (Scenario 3).
- **`handle_mark_as_paid()`** — Admin POST handler, requires `manage_options`. Transitions post to publish, stores `_orke_payment_id='manual-{user_id}-{timestamp}'`, `_orke_payment_status='manual'`. Confirmation dialog prevents accidental clicks.
- **`handle_paypal_ipn($ipn_data)`** — Verifies IPN via PayPal's `ipnpb.paypal.com` endpoint. Processes completed payments: `_orke_payment_status='paypal-completed'`. Supports sandbox via `ORKE_PAYPAL_SANDBOX` constant.
- **SSL warning**: Admin notice shown when `!is_ssl()` on Hub admin pages (G7/AD8).
- **Stripe API communication**: Custom `build_form_data()` handles Stripe's nested parameter format (e.g., `line_items[0][price_data][currency]=usd`).
- **Settings**: Stripe secret key, publishable key, and webhook secret stored as WP options. Configurable via Settings → Agency Hub submenu.

### `Orkestone_Delivery` (`includes/class-delivery.php`)
- **`generate_token($post_id)`** — Hooked to `orke_configuration_paid`. Generates `wp_generate_uuid4()` token, stores `_orke_delivery_token` and `_orke_token_expires_at` (24h default, filterable via `orke_token_ttl`). Multi-use (AD7): if existing valid token, returns it without regeneration.
- **`validate_token($token)`** — Queries `orke_configuration` posts by token meta. Checks: not revoked (doesn't start with `revoked-`), not expired (current time ≤ expires_at). Auto-revokes expired tokens. Returns post ID or false.
- **`revoke_token($post_id)`** — Sets token meta to `revoked-{timestamp}-user-{user_id}`. Logs the action. Idempotent — re-revoking already revoked token is a no-op.
- **`regenerate_token($post_id)`** — Revokes current token, then generates a new one.
- **Token meta box** — Renders in sidebar of configuration edit screen. Shows: payment status badge (pending/completed/manual/paypal-completed), post status, payment ID, current token (or "Revoked"), expiry time, action buttons (Revoke/Regen/Mark as Paid with confirmation dialogs).
- **Daily cleanup** — WP Cron event `orke_daily_token_cleanup` auto-revokes expired tokens across all configurations.
- **`validate_origin($token, $origin)`** — Compares request `Origin` header against `_orke_token_allowed_origin`. Host-level comparison. Empty origin or empty allowed_origin = allowed (backward compat).

### `Orkestone_Hub_REST_API` (`includes/class-hub-rest-api.php`)
- **`GET /orke-hub/v1/config/{token}`** — Validates UUID format (400 if malformed), checks rate limit, validates origin, validates token (expired/revoked → 404 w/o information leakage), returns `{success:true, config:{...}}` with full vertical JSON. Filterable via `orke_hub_config_response`.
- **`POST /orke-hub/v1/validate-token`** — Returns `{valid:bool, verticalKey:string}`. Never exposes config JSON (REQ-AH17). Same rate limiting.
- **`POST /orke-hub/v1/webhook/stripe`** — Passes raw payload + `stripe-signature` header to `Orkestone_Payment_Gateway::handle_webhook()`.
- **`POST /orke-hub/v1/webhook/paypal`** — Passes POST params to `Orkestone_Payment_Gateway::handle_paypal_ipn()`.
- **Rate limiting** — Transient `orke_rate_limit_{md5(ip)}` with 60s window, max 10 requests. Returns 429 on limit. Respects Cloudflare/X-Forwarded-For headers.
- **UUID validation** — `preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $uuid)`.

### Theme Connector — `vertical-storage.php`
- **`vbb_get_schema_version()`** — Returns `"1.0.0"` (SemVer). Filterable via `vbb_schema_version` for child theme overrides. No side effects (R1 safe).

### Theme Connector — `pro-rest-api.php`
- **`POST /orkestone/v1/activate`** — Registered with `vbb_rest_command_center_permission()` (R12: requires `manage_options`). Callback flow:
  1. Validates `{token: string}` present (400)
  2. Resolves Hub URL: `ORKE_HUB_URL` constant → `get_option('orke_hub_url')` fallback (502 if empty)
  3. `wp_remote_get("$hub_url/orke-hub/v1/config/$token", ['timeout' => 30])` (502 on failure)
  4. Checks 200 + `success:true` + `config` present (400 if invalid)
  5. `vbb_validate_vertical_config($config)` — structural validation (400)
  6. SemVer schema check via `vbb_check_schema_compatibility()` (409 on major mismatch)
  7. `vbb_save_imported_vertical_config($config)` — persists to uploads (500 on failure)
  8. `vbb_import_vertical_full($vertical_key)` — full import pipeline
  9. Returns: `{success:true, pagesCreated:N, mediaImported:M, report:{...}}`
- **`vbb_check_schema_compatibility($hub_version, $theme_version)`** — SemVer comparison:
  - Hub major > Theme major → 409 (breaking changes)
  - Hub major == Theme major → allowed (any minor/patch)
  - Hub major < Theme major → allowed (theme is newer)
  - Hub pre-1.0 (0.x.y) + Theme ≥ 1.0 → rejected
  - Returns `true` or a descriptive error message string.

### Bootstrap Updates (`orkestone-agency-hub.php`)
- **Dependencies**: Added `class-payment-gateway.php`, `class-delivery.php`, `class-hub-rest-api.php` to autoloader.
- **Hooks**: `Orkestone_Payment_Gateway::register_hooks()`, `Orkestone_Delivery::register_hooks()`, `Orkestone_Hub_REST_API::register_hooks()`.
- **Settings page**: Added submenu "Settings" under `orke_configuration` CPT. Full form for Stripe secret key, publishable key, and webhook secret. Displays webhook URL for Stripe dashboard configuration.

---

## Deviation from Design

- **Token meta box added**: Design does not explicitly mention a meta box for token/payment status. Added `Orkestone_Delivery::add_token_meta_box()` to provide a visual admin interface for token actions (Mark as Paid, Revoke, Regenerate) rather than requiring users to navigate elsewhere. This improves UX and matches the design intent of "admin should be able to manage tokens from the edit screen."
- **Settings page added**: Design says "warning banner if `!is_ssl()`" and mentions Stripe API keys but doesn't specify a settings UI. Added a full settings page under the CPT menu for configuring Stripe keys, with clear field labels and the webhook URL displayed for Stripe dashboard setup.
- **Rate limiting implementation**: Design mentions "custom transient" and the spec says "10 req/min/IP". Implemented via `orke_rate_limit_{md5(ip)}` transient with IP detection from proxy headers. Design was prescriptive enough that implementation matches.
- **Schema version compatibility**: Design says "major mismatch → 409". Implemented as `vbb_check_schema_compatibility()` with full SemVer logic including pre-1.0 rejection and detailed error messages matching the spec examples.

All other implementation matches design decisions (AD1–AD11) and spec requirements (REQ-AH1–AH22).

## Issues Found

None during Phase 3 implementation.

---

## Spec-Req Coverage

| Requirement | Implemented | Location |
|-------------|-------------|----------|
| **REQ-AH11** Stripe Checkout Session | ✅ | `Orkestone_Payment_Gateway::create_checkout_session()` |
| **REQ-AH12** Webhook handler + signature verification | ✅ | `Orkestone_Payment_Gateway::handle_webhook()` + `verify_stripe_signature()` |
| **REQ-AH13** Manual "Mark as Paid" override | ✅ | `Orkestone_Payment_Gateway::handle_mark_as_paid()` + meta box button |
| **REQ-AH14** PayPal IPN webhook | ✅ | `Orkestone_Payment_Gateway::handle_paypal_ipn()` → `POST /orke-hub/v1/webhook/paypal` |
| **REQ-AH15** UUID token generation on publish | ✅ | `Orkestone_Delivery::generate_token()` → `wp_generate_uuid4()` |
| **REQ-AH16** `GET /orke-hub/v1/config/{token}` | ✅ | `Orkestone_Hub_REST_API::get_config()` |
| **REQ-AH17** `POST /orke-hub/v1/validate-token` | ✅ | `Orkestone_Hub_REST_API::validate_token_endpoint()` |
| **REQ-AH18** Token security (expiry, origin, rate limit, revocation) | ✅ | `Orkestone_Delivery::validate_token()` + `validate_origin()` + `Orkestone_Hub_REST_API::check_rate_limit()` |
| **REQ-AH19** Admin token revocation | ✅ | `Orkestone_Delivery::revoke_token()` + `handle_revoke_token()` + meta box |
| **REQ-AH20** `POST /orkestone/v1/activate` | ✅ | `vbb_rest_activate_config()` in `pro-rest-api.php` |
| **REQ-AH21** `vbb_get_schema_version()` | ✅ | `vertical-storage.php` |
| **REQ-AH22** Schema compatibility check | ✅ | `vbb_check_schema_compatibility()` SemVer comparison |

---

## Workload / PR Boundary

- **Mode**: force-chained (PR 3 / Phase 3)
- **Current work unit**: Phase 3 — Payments, Token System, Theme Connector
- **Boundary**: Payment Gateway → Delivery → Hub REST API → Theme modifications
- **Chain strategy**: feature-branch-chain (PR 3 targets PR 2 branch)
- **Estimated review budget impact**: ~1,260 lines new code across 3 new files + 3 modified files

---

## Verification Notes

The following manual checks are needed on a real WordPress instance:

### Phase 3 Specific
1. Navigate to Hub → Settings → configure Stripe test keys
2. Create a configuration via briefing form → verify draft is created
3. Navigate to configuration edit screen → verify "Mark as Paid" button visible
4. Click "Mark as Paid" → verify confirmation dialog → confirm → post transitions to publish
5. Verify token is generated and displayed in meta box (UUID format)
6. Test `GET /orke-hub/v1/config/{token}` with valid token → 200 + JSON
7. Test with revoked token → 404
8. Test with malformed UUID → 400
9. Test rate limit: 11 requests in 1 minute → 429 on 11th
10. Test `POST /orke-hub/v1/validate-token` → `{valid:true, verticalKey:"..."}`
11. Click "Revoke Token" → verify confirmation → token marked as revoked
12. Verify token endpoint returns 404 for revoked token
13. Click "Regen Token" → verify old token revoked, new one generated
14. On theme side: configure `orke_hub_url` option
15. Test `POST /orkestone/v1/activate {token}` with valid token → 200 + import report
16. Test with invalid token → 400
17. Test with Hub unreachable → 502
18. Verify `vbb_get_schema_version()` returns `"1.0.0"`
19. Test Stripe webhook: send mock `checkout.session.completed` with valid signature → post transitions to publish
20. Test Stripe webhook: invalid signature → 401
21. Test PayPal IPN: valid IPN → `_orke_payment_status='paypal-completed'`

### Regression Checks
22. Existing Command Center endpoints still work (GET/POST settings, page CRUD, menu CRUD)
23. Manual vertical import via Command Center still works
24. No REST route collisions between `orke-hub/v1` and `orkestone/v1`
25. Plugin deactivation doesn't break theme functionality

---

## Next Steps

| Step | Description |
|------|-------------|
| **sdd-verify** | Verify all 3 phases against spec requirements REQ-AH1–AH22, all 5 scenarios, and all 12 regression areas |
