# Exploration Report: Orkestone Agency Hub — Business & Distribution

**Status**: completed
**Date**: 2026-07-09
**Change**: agency-hub

---

## Executive Summary

The Orkestone Agency Hub is a WordPress Plugin that acts as the **Factory** (generator) and **Store** (distributor) for Orkestone Theme vertical JSON configurations. Currently, the Orkestone Theme is a pure "receiver" — it reads JSON files from `config/verticals/` or the uploads directory, validates them, and instantiates pages/blocks. The Agency Hub inverts this: it provides a UI for agencies to capture client requirements via a tab-based briefing form, calculates pricing dynamically, processes payments, and outputs a valid vertical JSON that can be delivered to the client's Orkestone-powered site.

The end-to-end flow is: **Client Brief → Form → Budget → Payment → JSON Generation → Delivery → Site Instantiation**.

---

## Technical Findings

### 1. Asset & Graphic Management

**Current State in Theme:**
- The theme reads `graphics.images` array from vertical JSON: each entry has `url`, `title`, `alt`, `role`.
- `vbb_import_vertical_media_with_placeholders()` sideloads these URLs into the WordPress Media Library via `media_sideload_image()`.
- Tracked with `_vbb_source_url` post meta to prevent duplication.
- Resolved via `vbb_resolve_image_url()` which checks attachment ID first, then falls back to remote URL, then SVG placeholder.
- `vbb_get_vertical_media_items()` scans paths: `graphics.images`, `graphics.themeAssets`, `graficos.imagenes`, `graficos.assetsDelThemeOriginal`.

**Proposed Hub Architecture:**
- **Centralized Asset Library**: The Hub plugin needs a custom post type or media library integration where agencies upload images/SVGs that will be referenced in client vertical JSONs.
- **Storage Strategy**: Store assets in the Hub's own WordPress Media Library (or a dedicated cloud bucket for production). The vertical JSON will reference these by absolute URL.
- **Sideload Contract**: The Hub MUST generate URLs that the theme's `media_sideload_image()` can fetch — publicly accessible URLs. For security, use signed URLs or token-based access if assets are private.
- **SVG Handling**: The theme already handles SVG failures via `vbb_create_placeholder_attachment()`. The Hub should prefer SVG for logos/icons (small footprint) and fallback to PNG/JPEG for photos.
- **Image Role System**: Extend the existing `_vbb_media_role` meta system. The Hub should tag each asset with a role key (e.g., `hero-main`, `about-image`) that maps to specific fields in the form.

**Key Insight**: The Hub must NOT assume the client site's domain. Asset URLs in the generated JSON must be absolute, publicly reachable URLs that the import pipeline can download and sideload into the client's Media Library.

### 2. Client Briefing & JSON Generation

**Form Structure Required:**

The existing vertical JSON schema (`default.json`) reveals these required fields:

| JSON Path | Type | Form Tab |
|-----------|------|----------|
| `brand.siteName` | string | Branding |
| `brand.tagline` | string | Branding |
| `brand.logo` | url (asset) | Branding |
| `brand.primaryColor` | hex | Styles |
| `brand.secondaryColor` | hex | Styles |
| `brand.accentColor` | hex | Styles |
| `brand.fontHeading` | string | Styles |
| `brand.fontBody` | string | Styles |
| `navigation.primary[]` | [{label, url}] | Navigation |
| `pages[]` | [{key, title, slug, template, sections}] | Pages |
| `pages[].{section}.{fields}` | varies | Per-section |
| `sections.{type}.items[]` | varies | Content |
| `contentModels.{key}.items[]` | varies | Content |
| `graphics.images[]` | [{url, title, role}] | Assets |
| `seoDefaults` | object | SEO |

**Tab-based Form Proposal (4 Tabs):**

1. **Branding Tab**: Site name, tagline, logo upload, color pickers (primary/secondary/accent font), font family selectors (Google Fonts API integration).
2. **Pages & Sections Tab**: Page builder — add/remove pages, assign template (front-page vs page), drag sections onto each page from a pool of available section types (hero, services-grid, benefits, process, testimonials, faq, contact-section, cta-final, logoCloud, pricing, team). Each section expands to show data fields (e.g., hero: style A/B/C, eyebrow, title, subtitle, CTA).
3. **Content & Models Tab**: Content model items (services, team members, pricing plans, FAQ items, testimonials). Each model type has a dedicated sub-form with add/remove/reorder.
4. **Navigation & SEO Tab**: Menu items (label + URL/page reference), SEO title pattern, meta description.

**Mapping Mechanism:**
- A `Orkestone_JSON_Builder` class that collects all form data into an associative array.
- Applies the same schema as `default.json`.
- Validates against `vbb_validate_vertical_config()` logic (required fields: `schemaVersion`, `verticalKey`, `name`, `brand`, `navigation`, `pages`, `contentModels`).
- Outputs the final JSON via `wp_json_encode()` with `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
- The generated JSON MUST use placeholders `{{vbb_*}}` for dynamic content that the theme replaces at runtime — consistent with `block-baker.php` patterns.

### 3. Budgeting & Monetization

**Pricing Model:**

| Component | Basis | Example |
|-----------|-------|---------|
| Base Price | Fixed per project | $499 |
| Per Page | $X per declared page | $99 × 5 pages = $495 |
| Section Complexity | Per section type (weighted) | Hero=$50, Grid=$30, Custom=$100 |
| Content Model Items | Per model item | $10 × 12 items = $120 |
| Premium Sections | logoCloud, pricing, team | +$50 each |
| Total | Sum of components | $499 + $495 + $120 + $150 = $1,264 |

**Formula:**
```
Total = BASE_PRICE 
      + (PAGE_PRICE × count(pages)) 
      + Σ(section_complexity[section_type]) 
      + (ITEM_PRICE × total_model_items) 
      + premium_sections_surcharge
```

All pricing constants should be filterable via WordPress `apply_filters('orke_agency_pricing', $pricing)` so agencies can customize.

**Payment Integration Recommendations:**

| Gateway | Pros | Cons | Recommendation |
|---------|------|------|---------------|
| **Stripe** | WP integration mature (WP Simple Pay), webhooks, subscriptions, 195+ countries | Requires SSL, somewhat complex setup | **Primary choice** |
| PayPal | Widely known, PayPal Standard easy | Fewer features, outdated UX | Secondary fallback |

**Payment Flow:**
1. User completes briefing form → sees calculated budget
2. User clicks "Purchase Configuration" → redirected to Stripe Checkout
3. Stripe webhook `checkout.session.completed` → triggers JSON generation
4. JSON is generated and made available for download/delivery

**Release Mechanism:**
- The generated JSON is stored as a WordPress Custom Post Type (`orke_configuration`).
- Post status: `draft` while unpaid, `publish` after payment confirmed.
- Post meta stores: `_orke_payment_id`, `_orke_payment_status`, `_orke_vertical_key`, `_orke_delivery_token`.

### 4. Distribution & Delivery Architecture

| Criterion | Model A (Manual) | Model B (Token) | Model C (Cloud Sync) |
|-----------|-----------------|-----------------|---------------------|
| **Description** | Agency generates JSON → emails it → client uploads via Command Center | Hub generates activation token → client enters in Command Center → site pulls JSON | Centralized DB, API key auth, auto-sync |
| **Technical Complexity** | Low (no API needed) | Medium (custom REST endpoint + token auth) | High (webhook receivers, bidirectional sync) |
| **User Experience** | Manual, error-prone | Semi-automated, good | Fully automated, best |
| **Security** | Low (email attachment) | Good (signed tokens, expiring) | High (API keys, HMAC) |
| **Scalability** | None (manual per client) | Good | Excellent |
| **Maintenance** | None | Low | High (server, DB, VPN) |
| **Offline Support** | Full | Partial (needs token endpoint) | Full (cached JSON) |

**Recommendation: Model B (Token-Based) — with Model A as fallback.**

**Rationale:**
- Model C is over-engineering for an agency servicing 20-50 clients. The cost of maintaining a cloud sync infrastructure and handling conflicts (client edits JSON, then agency pushes update) makes it premature.
- Model A is unprofessional — emailing JSONs is fragile, hard to version, and feels cheap.
- Model B hits the sweet spot: the agency Hub generates a signed JWT or UUID token, stores it with the configuration post, and exposes a public REST endpoint `GET /orke-hub/v1/config/{token}`. The client's Command Center has a "Activate Configuration" field where they paste the token. The Command Center calls the Hub's endpoint, receives the JSON, and runs the standard `vbb_import_vertical_full()` pipeline.

**Token Delivery Architecture:**
```
Client Action                    Hub                              Client Site
─────────────                    ───                              ───────────
Briefing Form →                  Save as draft CPT                  
Payment →                        Mark as published
                                 Generate token (wp_generate_uuid())
                                 Store token in _orke_delivery_token
                                 "Configuration Ready" screen
                                   ↓
Copy activation token ──────────────────────────────────────────────→ Command Center "Activate" field
                                                                     User pastes token
                                                                     POST /orkestone/v1/activate (on client site)
                                                                       → calls Hub's GET /orke-hub/v1/config/{token}
                                                                       → receives JSON
                                                                       → runs vbb_import_vertical_full(verticalKey)
                                                                     Returns success/error report
```

---

## Proposed Architecture: Agency Hub Plugin

### Files & Structure

```
orkestone-agency-hub/
├── orkestone-agency-hub.php           # Plugin bootstrap
├── includes/
│   ├── class-asset-library.php        # CPT for assets, upload handling
│   ├── class-briefing-form.php        # Tabbed form UI, validation
│   ├── class-json-builder.php         # Form data → vertical JSON mapper
│   ├── class-pricing.php              # Budget calculator
│   ├── class-payment-gateway.php      # Stripe/PayPal abstraction
│   ├── class-delivery.php             # Token generation & REST endpoint
│   ├── class-configuration-cpt.php    # orke_configuration CPT
│   └── class-hub-rest-api.php         # Hub-side REST endpoints
├── assets/
│   ├── js/briefing-form.js            # React/Vanilla JS tab form
│   └── css/admin.css
└── templates/
    └── admin-briefing-form.php        # Form page template
```

### WordPress Hooks & API Endpoints

**Hub-Side Endpoints (registered on Agency's WordPress):**
| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/orke-hub/v1/config/{token}` | Public — returns JSON for a valid token |
| POST | `/orke-hub/v1/validate-token` | Validates token without returning JSON |
| POST | `/orke-hub/v1/webhook/stripe` | Stripe payment confirmation |
| POST | `/orke-hub/v1/webhook/paypal` | PayPal IPN |

**Theme-Side Hooks (already exist, Hub must be compatible):**
| Hook/Endpoint | File | Purpose |
|---------------|------|---------|
| `POST /orkestone/v1/vertical-config` | `pro-rest-api.php` | **NEW** — Theme accepts uploaded JSON |
| `vbb_validate_vertical_config()` | `vertical-validator.php` | Validates Hub output |
| `vbb_import_vertical_full()` | `vertical-importer.php` | Full import pipeline |
| `vbb_save_imported_vertical_config()` | `vertical-storage.php` | Persists JSON to uploads |
| `vbb_resolve_image_url()` | `helpers.php` | Asset resolution |
| `vbb_get_vertical_media_items()` | `vertical-importer.php` | Media sideloader |

**New Theme Endpoint Needed:**
```php
// In pro-rest-api.php — ADD:
register_rest_route('orkestone/v1', '/activate', array(
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'vbb_rest_activate_config',
    'permission_callback' => 'vbb_rest_command_center_permission',
));
```

`vbb_rest_activate_config()` receives `{token: "uuid-or-jwt"}`, fetches the Hub's `/orke-hub/v1/config/{token}`, validates the response, saves the JSON via `vbb_save_imported_vertical_config()`, and triggers `vbb_import_vertical_full()`.

### End-to-End User Journey

```
1. AGENCY: Logs into their WordPress (Hub)
2. AGENCY: Clicks "New Client Configuration"
3. AGENCY: Fills Tab 1 — Branding (site name, colors, fonts, logo)
4. AGENCY: Fills Tab 2 — Pages (adds 4 pages, assigns sections)
5. AGENCY: Fills Tab 3 — Content (adds services, team, FAQ, testimonials)
6. AGENCY: Fills Tab 4 — Navigation & SEO (menu items, meta)
7. AGENCY: Clicks "Calculate Budget"
8. HUB: Displays itemized pricing table
9. AGENCY/CLIENT: Clicks "Proceed to Payment"
10. HUB: Redirects to Stripe Checkout
11. STRIPE: Processes payment → webhook to Hub
12. HUB: Generates vertical JSON from form data
13. HUB: Stores as orke_configuration CPT (published)
14. HUB: Generates activation token
15. HUB: Displays "Configuration Ready" screen with token
16. AGENCY: Sends token to client (email, portal, etc.)
17. CLIENT: Logs into their Orkestone-powered site
18. CLIENT: Opens Command Center → "Activate Configuration"
19. CLIENT: Pastes token → Clicks "Activate"
20. CLIENT SITE: POST /orkestone/v1/activate {token}
21. CLIENT SITE: Fetches JSON from Hub's /orke-hub/v1/config/{token}
22. CLIENT SITE: Runs vbb_import_vertical_full()
23. CLIENT SITE: Pages created, media sideloaded, navigation set, front page applied
24. CLIENT SITE: Returns success report → "Site configured successfully!"
```

---

## Proposed Improvements

1. **JSON Schema Versioning**: The Hub should support `schemaVersion` negotiation. If the theme has a higher schema version than the Hub, the Hub cannot generate compatible JSON. Add a `vbb_get_schema_version()` function.
2. **Partial Re-activation**: After initial activation, clients may customize via Command Center. If the agency pushes updates (same token, re-generated JSON), the import pipeline should MERGE rather than RESET — preserving client overrides. This requires a two-way diff strategy.
3. **Template Presets**: Instead of starting from blank form, offer vertical templates (E-commerce, Legal, Agency, SaaS) that pre-populate the form with industry-appropriate pages, sections, and pricing.
4. **Nonce-Secured Public Endpoint**: The `/orke-hub/v1/config/{token}` endpoint is public by design (the client site's WordPress needs to call it without being logged into the Hub). Secure it with:
   - Short-lived JWT (24h expiry)
   - Rate limiting
   - IP allowlisting (optional)
   - Token revocation capability in Hub admin
5. **Asset CDN Layer**: For production agencies, serve assets through a CDN (Cloudflare R2, AWS CloudFront) with signed URLs so the theme's `media_sideload_image()` can fetch them reliably.

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| **Payment webhook failure** | Client paid but JSON never released | Implement retry queue + manual release button in Hub admin |
| **Token interception** | Unauthorized site activates config | JWT with client site URL bound in claims; validate origin |
| **Asset URL breakage** | Images fail to sideload on client | Hub should generate assets with long-lived public URLs; fallback to SVG placeholder in theme |
| **Cross-version incompatibility** | Hub generates JSON for schema v2 but client theme only supports v1 | Hub checks schema version on activation endpoint; reject if incompatible |
| **Large JSON payload** | Timeout on fetch or import | Chunk media import (theme already has `$limit` param); set reasonable JSON size limits |
| **No conflict resolution** | Agency pushes update → overwrites client edits | Import pipeline should diff pages (match by slug) and update only if `_vbb_source == 'vertical'` |
| **Stripe/PayPal dependency** | Payment gateway outage blocks all sales | Implement manual invoice + "Mark as Paid" override in Hub admin |

---

## Ready for Proposal

**Yes** — the exploration is complete and the architecture is well-understood.

The `sdd-propose` phase should focus on:
- Defining the exact scope for MVP (Minimum Viable Product) vs. v2 features
- Choosing the right front-end approach for the briefing form (React SPA embedded in WP admin, or plain vanilla JS with tabs)
- Selecting the payment gateway to integrate first (Stripe recommended)
- Determining whether to add the `/orkestone/v1/activate` endpoint to the theme (breaking change) or as a separate companion plugin

---

## Affected Areas

- `orkestone-theme/inc/vertical-storage.php` — Add `vbb_get_schema_version()` for Hub compatibility check
- `orkestone-theme/inc/pro-rest-api.php` — Add `/orkestone/v1/activate` endpoint
- `orkestone-theme/inc/vertical-importer.php` — Import pipeline already robust; minor adjustments for merge semantics
- `orkestone-theme/inc/vertical-validator.php` — Already compatible; no changes needed
- `orkestone-theme/inc/block-baker.php` — Already compatible; Hub generates JSON that feeds this pipeline
- `orkestone-theme/inc/helpers.php` — `vbb_resolve_image_url()` already handles remote URLs; no changes needed
