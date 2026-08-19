# Proposal: Orkestone Agency Hub — Business & Distribution

**Status**: completed  
**Change**: agency-hub  
**Delivery Strategy**: force-chained (3 phases, 800-line review budget per PR)

---

## Intent

Transition Orkestone from a standalone theme to a professional **agency ecosystem**. The Agency Hub plugin lets agencies manage client briefings, calculate budgets, process payments, and deliver site configurations via activation tokens — closing the loop between requirement gathering and site instantiation.

The end-to-end flow: **Client Brief → Form → Budget → Payment → JSON Generation → Token Delivery → Site Activation**.

---

## Quick Path

1. **Phase 1** — Asset Manager & Briefing Form (data collection)
2. **Phase 2** — Budgeting & Payment (monetization)
3. **Phase 3** — Token System & Theme Connector (delivery)

Each phase is a shippable increment. No phase depends on the next to function — only to complete the full round-trip.

---

## Scope

### In Scope

| Component | Phase | Description |
|-----------|-------|-------------|
| **Briefing Engine** | 1 | 4-tab dynamic form (Branding, Pages & Sections, Content & Models, Navigation & SEO) with validation |
| **Asset Manager** | 1 | CPT for agency-owned media assets with role tagging and URL generation |
| **JSON Builder** | 1 | Form data → vertical JSON mapper, schema validation, placeholder injection |
| **Budgeting System** | 2 | Dynamic pricing calculator with filterable constants (base + per-page + complexity + surcharges) |
| **Payment Gateway** | 2 | Stripe Checkout integration with webhook receiver and payment lifecycle (draft → publish) |
| **Configuration CPT** | 2 | `orke_configuration` custom post type tracking payment status, token, and vertical key |
| **Token System** | 3 | Activation token generation (UUID/JWT), public REST endpoint `GET /orke-hub/v1/config/{token}` |
| **Theme Connector** | 3 | New `POST /orkestone/v1/activate` endpoint on theme side to receive tokens and trigger import |

### Out of Scope

- **Cloud sync** (Model C) — deferred until agency scales beyond 50+ clients
- **Template presets** (E-commerce, Legal, SaaS presets) — v2 feature
- **Partial re-activation with merge semantics** — v2 after merge strategy is designed
- **Multi-tenant Hub** — single Hub serving multiple agencies is out of scope entirely

---

## Key Deliverables

| Deliverable | Type | Phase |
|-------------|------|-------|
| `orkestone-agency-hub/` plugin directory | New plugin | All |
| `includes/class-asset-library.php` | CPT + upload handling | 1 |
| `includes/class-briefing-form.php` | Tabbed form + validation | 1 |
| `includes/class-json-builder.php` | Form → JSON mapper | 1 |
| `includes/class-configuration-cpt.php` | `orke_configuration` CPT | 1 |
| `includes/class-pricing.php` | Budget calculator | 2 |
| `includes/class-payment-gateway.php` | Stripe/PayPal abstraction | 2 |
| `includes/class-delivery.php` | Token generation + REST endpoint | 3 |
| `includes/class-hub-rest-api.php` | Hub-side REST endpoints | 3 |
| `assets/js/briefing-form.js` | Front-end tab form | 1 |
| `assets/css/admin.css` | Admin styles | 1 |
| `templates/admin-briefing-form.php` | Form page template | 1 |
| `pro-rest-api.php` update (in theme) | `POST /orkestone/v1/activate` | 3 |
| `vertical-storage.php` update (in theme) | `vbb_get_schema_version()` | 3 |

---

## Capabilities

### New Capabilities

| Capability | Class | Description |
|------------|-------|-------------|
| `asset-library` | `class-asset-library.php` | CPT for agency media assets with role tagging, URL generation, WordPress Media Library integration |
| `briefing-form` | `class-briefing-form.php` | 4-tab dynamic form with page builder, section assignment, content model sub-forms, validation |
| `budget-calculator` | `class-pricing.php` | Configurable pricing engine with filterable constants, itemized budget display |
| `payment-gateway` | `class-payment-gateway.php` | Stripe Checkout abstraction, webhook handler, payment lifecycle (draft → published) |
| `token-delivery` | `class-delivery.php` + `class-hub-rest-api.php` | Activation token generation, `orke_configuration` CPT management, public REST endpoint for token-based JSON retrieval |
| `theme-connector` | `pro-rest-api.php` (theme) | `POST /orkestone/v1/activate` endpoint that fetches JSON from Hub and triggers `vbb_import_vertical_full()` |

### Modified Capabilities

| Area | Change | Phase |
|------|--------|-------|
| `orkestone-theme/inc/pro-rest-api.php` | Add `POST /orkestone/v1/activate` endpoint with `vbb_rest_activate_config()` callback | 3 |
| `orkestone-theme/inc/vertical-storage.php` | Add `vbb_get_schema_version()` for Hub compatibility check | 3 |

---

## Approach

### Phase 1 — Asset Manager & Briefing Form

**Goal**: Data collection. Agency can capture client requirements and generate a JSON preview.

1. Create plugin bootstrap `orkestone-agency-hub.php` with CPT registration hooks
2. `class-configuration-cpt.php` — Register `orke_configuration` post type (initially draft-only)
3. `class-asset-library.php` — CPT for assets with upload handler, role tagging (`_vbb_media_role`), SVG/PNG/JPEG support
4. `class-briefing-form.php` — 4-tab React/Vanilla JS form:
   - Branding (site name, tagline, logo upload, colors, fonts)
   - Pages & Sections (page builder, section type pool, per-section fields)
   - Content & Models (services, team, pricing, FAQ, testimonials with add/remove/reorder)
   - Navigation & SEO (menu items, SEO title pattern, meta description)
5. `class-json-builder.php` — Collect form data → associative array → validate against schema → output `wp_json_encode()` with `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`
6. Save result as `orke_configuration` draft post with JSON stored in post content

**Files created**: `orkestone-agency-hub.php`, `class-asset-library.php`, `class-briefing-form.php`, `class-json-builder.php`, `class-configuration-cpt.php`, `briefing-form.js`, `admin.css`, `admin-briefing-form.php`

### Phase 2 — Budgeting & Payment

**Goal**: Monetization. Agency can calculate budgets and accept payments.

1. `class-pricing.php` — Implement pricing formula:
   ```
   Total = BASE_PRICE + (PAGE_PRICE × count(pages)) + Σ(section_complexity[type]) + (ITEM_PRICE × total_model_items) + premium_sections_surcharge
   ```
   - All constants filterable via `apply_filters('orke_agency_pricing', $pricing)`
   - Display itemized budget table in admin UI
2. `class-payment-gateway.php` — Stripe Checkout integration:
   - Create Stripe Checkout Session with line items matching budget components
   - On success: redirect to Stripe Checkout
   - Webhook handler for `checkout.session.completed`
   - Transition `orke_configuration` from draft → published on payment confirmation
   - Store `_orke_payment_id`, `_orke_payment_status`, `_orke_vertical_key` post meta
3. Manual override: "Mark as Paid" button in Hub admin for invoice-based payments

**Files created**: `class-pricing.php`, `class-payment-gateway.php`
**No changes to Phase 1 files** — purely additive.

### Phase 3 — Token System & Theme Connector

**Goal**: Delivery. Client can activate their configuration on their Orkestone site.

1. `class-delivery.php`:
   - Token generation via `wp_generate_uuid()` (or JWT for production)
   - Token stored in `_orke_delivery_token` post meta
   - Token revocation capability in Hub admin
2. `class-hub-rest-api.php`:
   - `GET /orke-hub/v1/config/{token}` — Public endpoint, returns JSON for valid token
   - `POST /orke-hub/v1/validate-token` — Validates token without returning JSON
   - `POST /orke-hub/v1/webhook/stripe` — Stripe payment confirmation
   - `POST /orke-hub/v1/webhook/paypal` — PayPal IPN
   - Security: JWT with 24h expiry, rate limiting, origin validation
3. Update `pro-rest-api.php` (theme):
   ```php
   register_rest_route('orkestone/v1', '/activate', array(
       'methods' => WP_REST_Server::CREATABLE,
       'callback' => 'vbb_rest_activate_config',
       'permission_callback' => 'vbb_rest_command_center_permission',
   ));
   ```
4. `vbb_rest_activate_config()` callback:
   - Receives `{token: "uuid-or-jwt"}`
   - Calls Hub's `GET /orke-hub/v1/config/{token}`
   - Validates response and checks `schemaVersion` compatibility
   - Saves JSON via `vbb_save_imported_vertical_config()`
   - Triggers `vbb_import_vertical_full()`
   - Returns success/error report
5. Add `vbb_get_schema_version()` to `vertical-storage.php` for Hub compatibility check

**Files created**: `class-delivery.php`, `class-hub-rest-api.php`
**Files modified**: `pro-rest-api.php`, `vertical-storage.php` (both in theme)

---

## User Journey (Full Round-Trip)

```
  AGENCY                   HUB                        CLIENT SITE
  ──────                   ───                        ───────────
     │                       │                            │
     ├─ Fill Briefing Form ──┤                            │
     │  (4 tabs)             ├─ Save draft                │
     │                       │                            │
     ├─ Click Budget ────────┤                            │
     │                       ├─ Display pricing table     │
     │                       │                            │
     ├─ Click Purchase ──────┤                            │
     │                       ├─ Redirect Stripe           │
     │                       │                            │
     │              ◄── Stripe Webhook ────               │
     │                       ├─ Generate JSON             │
     │                       ├─ Publish CPT               │
     │                       ├─ Create token              │
     │                       │                            │
     ├─ Send token ───────────────────────────────────────┤
     │                       │      (client pastes token) │
     │                       │    POST /orkestone/v1/activate
     │                       │◄───────────────────────────┤
     │                       ├─ Return JSON               │
     │                       │                            ├─ vbb_import_vertical_full()
     │                       │                            ├─ Pages created
     │                       │                            ├─ Media sideloaded
     │                       │                            ├─ Navigation set
     │                       │                            └─ Report returned
     │                       │                            │
     └─ "Site configured!" ◄┴─────────────────────────────┘
```

---

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| **Token interception** — unauthorized site activates a client's config | Low | High | Short-lived JWT (24h expiry) + origin validation (client site URL bound in claims) + rate limiting + token revocation in admin |
| **Payment/delivery desync** — client pays but JSON never releases due to webhook failure | Low | High | Retry queue for webhook processing + manual "Mark as Paid" override in Hub admin |
| **Schema drift** — Hub generates JSON for schema v2 but client theme only supports v1 | Medium | High | `vbb_get_schema_version()` check during token activation; reject if incompatible |
| **Asset URL breakage** — images fail to sideload on client site | Low | Medium | Generate long-lived public asset URLs; fallback to SVG placeholder in theme (existing `vbb_create_placeholder_attachment()`) |
| **Large JSON timeout** — fetch or import times out for complex configurations | Low | Medium | Chunked media import (theme already supports `$limit` param); set reasonable JSON size limits |
| **Stripe/PayPal outage** — payment gateway unavailable | Low | Critical | Manual invoice workflow + "Mark as Paid" override allows offline payment processing |

---

## Rollback Plan

| Phase | Rollback Action | Data Loss |
|-------|----------------|-----------|
| **1** | Deactivate plugin | Assets remain in Media Library but orphaned. No data loss. |
| **2** | Deactivate plugin | Unpaid `orke_configuration` posts remain in draft. Stripe webhooks queue briefly — re-enable within hours recovers pending. |
| **3** | Deactivate plugin + revert `pro-rest-api.php` via git | Tokens become unresolvable externally. Existing activated sites retain their config. |

Per-phase rollback is safe because each phase is **additive and independent**.

---

## Dependencies

- **Stripe account** (or PayPal Business) — required for Phase 2
- **`orkestone-engine` change** — already applied (export/import pipeline stable)
- **WordPress 5.7+** — with REST API enabled
- **WooCommerce** — optional, only if Hub handles Store verticals

---

## Success Criteria

- [ ] Agency completes the 4-tab briefing form → sees a valid JSON preview matching the `default.json` schema
- [ ] Budget calculator produces correct itemized total matching the pricing formula for any combination of pages/sections/models
- [ ] Stripe Checkout completes → webhook transitions `orke_configuration` from draft → publish → JSON is generated
- [ ] Activation token resolves the full vertical JSON via `GET /orke-hub/v1/config/{token}`
- [ ] Token entered in Command Center → client site calls `POST /orkestone/v1/activate` → fetches JSON → runs `vbb_import_vertical_full()` successfully
- [ ] Full round-trip: **form → budget → payment → token → activation → site configured** (end-to-end verified)
