# Tasks: Orkestone Agency Hub — Business & Distribution

**Change**: agency-hub
**Delivery Strategy**: force-chained (3 phases)
**Review Budget**: 800 lines per PR

---

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1,780 total (3 PRs × ~600 avg) |
| 400-line budget risk | Medium |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Phase 1) → PR 2 (Phase 2) → PR 3 (Phase 3) |
| Delivery strategy | force-chained |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Plugin bootstrap, CPTs, Asset Library | PR 1 | Base: feature/tracker. ~390 lines. |
| 2 | Briefing Form, JSON Builder, Pricing | PR 2 | Base: PR 1 branch. ~800 lines (tight). |
| 3 | Payments, Tokens, Theme Connector | PR 3 | Base: PR 2 branch. ~590 lines. |

## Phase 1: Infrastructure & Asset Manager

- [x] **1.1** Create `orkestone-agency-hub.php` bootstrap (activation/deactivation hooks, file loader) + `includes/class-configuration-cpt.php` with `orke_configuration` CPT, all post meta fields. **Verify**: CPT registered on activation; meta fields (`_orke_vertical_key`, `_orke_payment_*`, `_orke_delivery_token`, `_orke_token_*`) writable.
- [x] **1.2** Create `includes/class-asset-library.php`: `orke_asset` CPT, `_vbb_media_role` meta, SVG/PNG/JPEG MIME filter, public URL generation via `apply_filters('orke_asset_base_url', site_url())`. **Verify**: Upload SVG → stored in Media Library; URL reachable; role tag writable.
- [x] **1.3** Create `assets/css/admin.css` with `.orke-hub-*` namespaced styles. **Verify**: CSS enqueued on Hub admin pages; no selector collision with Command Center.

## Phase 2: Briefing Engine & Budgeting

- [x] **2.1** Create `includes/class-briefing-form.php` + `templates/admin-briefing-form.php` with 4-tab admin page (Branding, Pages & Sections, Content & Models, Navigation & SEO). **Verify**: 4 tabs render with all fields per REQ-AH3.
- [x] **2.2** Create `assets/js/briefing-form.js`: tab navigation, field validation, JSON preview as read-only `<pre>`. **Verify**: Empty site name → error; all valid → JSON builder called.
- [x] **2.3** Create `includes/class-json-builder.php`: `build()` maps form data → vertical JSON array; `validate()` checks required keys; `get_json()` outputs `wp_json_encode()` with flags; sets `schemaVersion` from theme function or `"1.0.0"` fallback; `verticalKey` as sanitized site name slug. **Verify**: Output passes `vbb_validate_vertical_config()`; all 7 required top-level keys present.
- [x] **2.4** Create `includes/class-pricing.php`: `Total = BASE_PRICE + (PAGE_PRICE × pages) + Σ(section_complexity) + (ITEM_PRICE × items) + premium_surcharge`. All constants filterable via `apply_filters('orke_agency_pricing')`. Returns itemized array. **Verify**: 1 page, no sections → BASE_PRICE + PAGE_PRICE; 0 pages → BASE_PRICE only (AD10); filter changes output.

## Phase 3: Payments & Delivery

- [x] **3.1** Create `includes/class-payment-gateway.php`: `create_checkout_session()` maps budget to Stripe line items and creates Checkout Session; `handle_webhook()` verifies Stripe signature, processes `checkout.session.completed`, transitions `orke_configuration` draft→publish, stores `_orke_payment_id` and `_orke_payment_status='completed'`. **Verify**: Stripe API called with correct line items; webhook with valid signature transitions post; invalid signature → 401.
- [x] **3.2** Add manual "Mark as Paid" override button (requires `manage_options` capability, confirmation dialog) + PayPal IPN webhook at `POST /orke-hub/v1/webhook/paypal`. **Verify**: Manual override transitions post to publish; `_orke_payment_status='manual'`; PayPal IPN sets `_orke_payment_status='paypal-completed'`.
- [x] **3.3** Create `includes/class-delivery.php`: token generation hook on publish transition via `wp_generate_uuid()`; stores `_orke_delivery_token` + `_orke_token_expires_at` (24h); token revocation (sets meta to `revoked-{timestamp}`); token regeneration (marks old as revoked). **Verify**: UUID generated on publish; revoked token returns 404 in endpoint.
- [x] **3.4** Create `includes/class-hub-rest-api.php`: `GET /orke-hub/v1/config/{token}` (returns JSON for valid tokens), `POST /orke-hub/v1/validate-token` (returns `{valid,verticalKey}`), rate limiting via transient (10 req/min/IP), origin validation against `_orke_token_allowed_origin`. **Verify**: Valid token → 200 + JSON; expired → 404; malformed UUID → 400; rate limit → 429; wrong origin → rejected.
- [x] **3.5** Modify `orkestone-theme/inc/vertical-storage.php` — add `vbb_get_schema_version()` returning `"1.0.0"`. Modify `orkestone-theme/inc/pro-rest-api.php` — add `POST /orkestone/v1/activate` route with `vbb_rest_activate_config()` callback: validate token → fetch JSON from Hub → `vbb_validate_vertical_config()` → SemVer schema check → `vbb_save_imported_vertical_config()` → `vbb_import_vertical_full()`. Hub URL from `get_option('orke_hub_url')` or `ORKE_HUB_URL` constant. **Verify**: Valid token → 200 + import report; schema mismatch → 409; Hub unreachable → 502.
