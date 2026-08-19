# Delta Spec: Orkestone Agency Hub — Business & Distribution

**Change**: agency-hub
**Status**: draft
**Next**: sdd-design
**Delivery Strategy**: force-chained (3 phases, 800-line review budget per PR)

---

## Executive Summary

The Agency Hub plugin turns Orkestone from a standalone theme into a professional agency ecosystem. It covers 3 delivery phases: (1) client briefing form + asset library + JSON generation, (2) dynamic pricing + Stripe/PayPal payment processing, (3) activation token delivery + theme-side connector endpoint. The full round-trip is **Form → Budget → Payment → JSON → Token → Site Activation**.

All 3 phases are additive and independently shippable. Phase 1 works without payment, Phase 2 works without the token connector, Phase 3 completes the delivery loop.

---

## Requirements

### Phase 1 — Asset Manager & Briefing Form (Data Collection)

| ID | Requirement | Input → Output | Verification |
|----|-------------|---------------|--------------|
| REQ-AH1 | **The plugin MUST register an `orke_asset` custom post type** for agency media assets with role tagging via `_vbb_media_role` post meta. | Plugin activates → CPT `orke_asset` is registered with `title`, `editor`, `thumbnail` supports, and `_vbb_media_role` meta field. | 1. Run `register_post_type()` check → `orke_asset` exists with correct args<br>2. Create asset post → `_vbb_media_role` meta field is writable |
| REQ-AH2 | **The Asset Library MUST accept SVG, PNG, and JPEG uploads** and generate publicly accessible URLs stored in post meta. | Agency uploads `logo.svg` via Asset Library → File is stored in Media Library → Post meta stores absolute URL of the uploaded file. | 1. Upload `.svg` → stored in Media Library<br>2. Upload `.png` → stored<br>3. Upload `.jpg` → stored<br>4. `_vbb_asset_url` meta contains reachable URL |
| REQ-AH3 | **The briefing form MUST render a 4-tab interface** inside the WordPress admin with tabs: Branding, Pages & Sections, Content & Models, Navigation & SEO. | User navigates to Hub → "New Configuration" → 4-tab form renders with each tab showing the correct fields per the proposal schema. | 1. Tab 1 "Branding" shows: site name, tagline, logo upload, 3 color pickers, 2 font selectors<br>2. Tab 2 "Pages & Sections" shows: page list with add/remove, section pool, per-section field expansion<br>3. Tab 3 "Content & Models" shows: service/team/pricing/FAQ/testimonial sub-forms with add/remove/reorder<br>4. Tab 4 "Navigation & SEO" shows: menu items (label + URL) and SEO fields |
| REQ-AH4 | **The form MUST validate required fields before allowing JSON generation**. Minimum: site name ≥ 1 char, at least 1 page defined, menu has ≥ 1 item. | User submits form with blank site name → Validation error shown on Branding tab: "Site name is required." User adds name → validation passes. | 1. Empty site name → error message displayed<br>2. Zero pages → error on Pages tab<br>3. Zero menu items → error on Nav tab<br>4. All fields valid → JSON builder runs |
| REQ-AH5 | **The JSON Builder MUST map form data to the existing `default.json` vertical schema**, including `schemaVersion`, `verticalKey`, `name`, `brand`, `navigation`, `pages`, `contentModels`, `graphics`, `seoDefaults`. | Form data (siteName="My Agency Site", primaryColor="#102033", etc.) → `JSON_Builder::build()` → output valid JSON matching `vbb_validate_vertical_config()` requirements. | 1. Output passes `vbb_validate_vertical_config()`<br>2. All 7 required top-level keys present<br>3. `schemaVersion` is "1.0.0"<br>4. `verticalKey` is a UUID or slugified name<br>5. Graphics URLs are absolute, not relative |
| REQ-AH6 | **The generated JSON SHOULD be previewable** in the admin UI before payment, displayed as syntax-highlighted read-only text. | User clicks "Preview JSON" → Read-only `<pre>` block shows formatted JSON with syntax highlighting. | 1. Preview shows valid JSON<br>2. JSON is read-only (no edit capability)<br>3. Visual syntax highlighting applied |
| REQ-AH7 | **The plugin MUST register an `orke_configuration` custom post type** to track each client configuration through its lifecycle (draft → published). | Plugin activates → `orke_configuration` CPT registered with statuses `draft` (unpaid) and `publish` (paid). | 1. `orke_configuration` post type exists<br>2. New config created as `draft` status<br>3. JSON stored in `post_content` field |
| REQ-AH8 | **Phase 1 MUST store generated JSON** as the `post_content` of an `orke_configuration` draft post, with post meta `_orke_vertical_key` set. | JSON Builder completes → `wp_insert_post()` creates orke_configuration draft → post_content is the JSON string → `_orke_vertical_key` meta is set to the generated key. | 1. Draft post exists in `orke_configuration`<br>2. `post_content` is valid JSON<br>3. `_orke_vertical_key` is non-empty |

### Phase 2 — Budgeting & Payment (Monetization)

| ID | Requirement | Input → Output | Verification |
|----|-------------|---------------|--------------|
| REQ-AH9 | **The pricing calculator MUST compute total using the formula** `BASE_PRICE + (PAGE_PRICE × count(pages)) + Σ(section_complexity[type]) + (ITEM_PRICE × total_model_items) + premium_sections_surcharge` with all constants filterable. | Input: 5 pages, 2 premium sections, 12 model items → Output: `itemized[]` array with each line item and `total` = sum of components. | 1. Empty config → `total` === 0 (no base charge without pages)<br>2. 1 page, no sections → `total` === BASE_PRICE + PAGE_PRICE<br>3. 3 pages, 1 hero, 2 items → correct itemized breakdown<br>4. `apply_filters('orke_agency_pricing', $pricing)` modifies BASE_PRICE → output reflects change |
| REQ-AH10 | **The itemized budget MUST be displayed as an HTML table** in the Hub admin, showing each component, quantity, unit price, and subtotal, with a grand total row. | User clicks "Calculate Budget" → Admin page renders `<table>` with rows: Base, Pages (×5 @ $99), Items (×12 @ $10), Premium Sections (×2 @ $50), Total row. | 1. Table has correct number of rows based on config<br>2. Each row shows component name, qty, unit price, subtotal<br>3. Grand total row sums correctly<br>4. Zero-cost components are hidden or shown as $0 |
| REQ-AH11 | **The payment gateway MUST create a Stripe Checkout Session** with line items matching the budget components, upon clicking "Purchase Configuration". | User clicks "Purchase Configuration" → Hub calls Stripe API → Stripe Checkout Session created → Hub receives `sessionId` → User is redirected to Stripe hosted checkout page. | 1. Stripe API `checkout.sessions.create` called with correct line items<br>2. Response contains a valid `sessionId`<br>3. User is redirected via `303 See Other` to Stripe URL<br>4. Session metadata includes `_orke_vertical_key` and `post_id` |
| REQ-AH12 | **The webhook receiver MUST handle `checkout.session.completed`** and transition the `orke_configuration` post from draft → published, storing `_orke_payment_id` and `_orke_payment_status` post meta. | Stripe sends `checkout.session.completed` → Hub receives → verifies signature → finds post by `post_id` in metadata → sets post_status to 'publish' → stores `_orke_payment_id` and `_orke_payment_status='completed'`. | 1. Webhook with valid signature → post transitions to 'publish'<br>2. `_orke_payment_id` meta equals Stripe session ID<br>3. `_orke_payment_status` meta is 'completed'<br>4. Webhook with invalid signature → rejected with 401 |
| REQ-AH13 | **The Hub MUST have a manual "Mark as Paid" override button** in the admin, for invoice-based or offline payments. | Admin clicks "Mark as Paid" on a draft configuration → Confirmation dialog → On confirm, post transitions to 'publish', `_orke_payment_status='manual'`, `_orke_payment_id='manual-{user_id}-{timestamp}'`. | 1. Button visible for users with `manage_options` capability<br>2. Confirmation dialog prevents accidental clicks<br>3. Post transitions to published<br>4. Meta fields reflect manual status |
| REQ-AH14 | **The Hub SHOULD handle PayPal IPN webhook** via `POST /orke-hub/v1/webhook/paypal` as an alternative payment method. | PayPal sends IPN to Hub endpoint → Hub validates IPN via `paypal.verify_ipn()` → finds configuration → transitions to published. | 1. Valid PayPal IPN → post transitions to 'publish'<br>2. Invalid/duplicate IPN → logged but no state change<br>3. `_orke_payment_status='paypal-completed'` |

### Phase 3 — Token System & Theme Connector (Delivery)

| ID | Requirement | Input → Output | Verification |
|----|-------------|---------------|--------------|
| REQ-AH15 | **The plugin MUST generate an activation token** when an `orke_configuration` transitions to 'publish', using `wp_generate_uuid()` or JWT, stored in `_orke_delivery_token` post meta. | Post status changes to 'publish' → Hook fires → Token generated via `wp_generate_uuid()` → Stored as `_orke_delivery_token` post meta → Token returned. | 1. Token generated on publish transition<br>2. `_orke_delivery_token` meta is non-empty<br>3. Token is unique (no collisions across configs)<br>4. Token format is UUID v4 (hex with dashes) |
| REQ-AH16 | **The Hub MUST expose `GET /orke-hub/v1/config/{token}`** as an unauthenticated REST endpoint returning the full vertical JSON, but only for valid, non-revoked tokens. | `GET /orke-hub/v1/config/abc-123` with valid token → Response 200 with `{success:true, config:{...}}`. `GET /orke-hub/v1/config/invalid` → Response 404 with `{success:false, message:"Token not found or revoked"}`. | 1. Valid token → 200 + full JSON config<br>2. Revoked token → 404 (not 410)<br>3. Expired token → 404 with expiry message<br>4. Malformed UUID → 400 error<br>5. Response JSON passes `vbb_validate_vertical_config()` |
| REQ-AH17 | **The Hub MUST expose `POST /orke-hub/v1/validate-token`** returning token validity without exposing the JSON payload. | `POST /orke-hub/v1/validate-token {token:"abc-123"}` → 200 `{valid:true, verticalKey:"..."}`. Invalid token → 200 `{valid:false}`. | 1. Valid token → `valid:true` + `verticalKey`<br>2. Revoked token → `valid:false`<br>3. Response never includes `config` or JSON body |
| REQ-AH18 | **The token system MUST implement security measures**: JWT encoding with 24h expiry, origin validation (client site URL bound in claims), rate limiting (10 req/min per IP), and token revocation capability. | Client site `POST /orkestone/v1/activate {token}` → Hub validates JWT signature, checks expiry, verifies origin claim matches request `Origin` header, checks revocation list. | 1. Expired JWT (>24h) → rejected<br>2. Wrong origin → rejected<br>3. Revoked token → rejected<br>4. >10 req/min from same IP → 429 rate limit<br>5. Valid token with all checks passing → JSON returned |
| REQ-AH19 | **The Hub admin MUST support token revocation** via a "Revoke Token" button on the configuration edit screen. | Admin clicks "Revoke Token" → `_orke_delivery_token` meta set to `revoked-{timestamp}` → Token endpoint returns 404 for that token moving forward. | 1. Token endpoint returns config before revocation<br>2. After revocation, same token returns 404<br>3. Revocation is logged with user ID and timestamp |
| REQ-AH20 | **The theme MUST expose `POST /orkestone/v1/activate`** in `pro-rest-api.php`, accepting `{token:"..."}`, making an outbound HTTP request to the Hub's config endpoint, validating the response, saving via `vbb_save_imported_vertical_config()`, and triggering `vbb_import_vertical_full()`. | User pastes token in Command Center → POST to client site → Client site calls Hub's `GET /orke-hub/v1/config/{token}` → Receives JSON → Runs `vbb_save_imported_vertical_config()` → Runs `vbb_import_vertical_full()` → Returns success report. | 1. POST with valid token → 200 `{success:true, pagesCreated:N, mediaImported:M}`<br>2. POST with invalid token → 400 `{success:false, message:"Invalid token"}`<br>3. Hub unreachable → 502 `{success:false, message:"Hub unreachable"}`<br>4. Schema mismatch → 409 `{success:false, message:"Incompatible schema version"}` |
| REQ-AH21 | **The theme MUST implement `vbb_get_schema_version()`** in `vertical-storage.php` returning a SemVer string representing the supported vertical JSON schema version. | Call `vbb_get_schema_version()` → Returns `"1.0.0"` (string). | 1. Function exists in `vertical-storage.php`<br>2. Returns non-empty string matching SemVer pattern<br>3. Value is consistent across calls |
| REQ-AH22 | **The activation endpoint MUST check schema compatibility** between Hub output and theme before importing. If `Hub.schemaVersion > Theme.schemaVersion`, abort with 409. | Hub JSON has `schemaVersion: "2.0.0"`, theme returns `vbb_get_schema_version() = "1.0.0"` → 409 error: "Configuration requires schema v2.0.0 but this site supports v1.0.0. Contact your agency." | 1. Major version mismatch → rejected<br>2. Minor version mismatch (Hub 1.1.0, Theme 1.0.0) → allowed (backward compatible)<br>3. Patch mismatch → allowed<br>4. Matching versions → passes |

---

## Scenario-Based Tests

### Scenario 1: Full Happy Path — Branding to Site Activation

**Objective**: Verify the complete round-trip from an agency filling the briefing form through to a client site being automatically configured.

**Prerequisites**: Hub plugin active on Agency WordPress, client site running Orkestone theme with Command Center access.

**Steps**:
1. Agency logs into Hub → clicks "New Client Configuration"
2. Agency fills Tab 1 (Branding): "Acme Corp", tagline "Building the future", uploads `logo.svg`, primary=#1a365d, secondary=#e2e8f0, heading=Inter, body=Inter
3. Agency fills Tab 2 (Pages & Sections): adds 3 pages — Home (hero + services-grid + testimonials), About (hero-centered + process), Contact (contact-section)
4. Agency fills Tab 3 (Content & Models): adds 4 services, 3 team members, 5 FAQ items
5. Agency fills Tab 4 (Navigation & SEO): 4 menu items (Home, Services, About, Contact), title pattern = `%page% | Acme Corp`
6. Agency clicks "Calculate Budget"
7. **Expected**: Itemized table shows Base + 3 Pages + section complexity + model items + premium surcharge → Total = correct sum
8. Agency clicks "Purchase Configuration" → redirected to Stripe Checkout
9. **Expected**: Stripe Checkout shows line items matching the budget
10. Agency completes payment → Stripe webhook fires
11. **Expected**: `orke_configuration` transitions to published → JSON is generated → Token is created → Displayed in admin
12. Agency copies the token and sends it to the client
13. Client logs into their Orkestone site → Command Center → "Activate Configuration" → pastes token → clicks "Activate"
14. **Expected**: `POST /orkestone/v1/activate` fires → Hub returns JSON → Import runs → Client site shows: 3 pages created, 4 media items sideloaded, navigation configured
15. Client visits homepage → sees "Acme Corp" branding, hero section, services grid

**Edge Cases**:
- Payment fails (card declined): Webhook not received, draft stays as draft, token not generated
- Token entered with wrong origin: Origin validation rejects the request
- Hub downtime during activation: Client site shows "Hub unreachable" error, retry after 30s

---

### Scenario 2: Budget Accuracy with Complex Configurations

**Objective**: Verify the pricing formula produces correct totals across a range of configurations, and that filterable constants work.

**Steps**:
1. Agency starts a new config with 0 pages, 0 items, 0 sections
2. **Expected**: Budget calculator shows total = BASE_PRICE (or $0 if base is 0 without pages — see spec gap G1)
3. Agency adds 5 pages, each with 3 sections: hero, services-grid, testimonials
4. **Expected**: Total = BASE_PRICE + (5 × PAGE_PRICE) + (2 × hero_cost + 2 × grid_cost + testimonial_cost) + 0 items + 0 premium surcharge
5. Agency adds 15 model items (services=4, team=3, FAQ=5, pricing=3)
6. **Expected**: +15 × ITEM_PRICE added to total
7. Agency adds a premium section (pricing)
8. **Expected**: PREMIUM_SURCHARGE added to total

**Verification**: Compare computed total against manual calculation for each step.
**Edge Case**: Agency applies `add_filter('orke_agency_pricing', 'custom_pricing')` that changes BASE_PRICE to 0 → budget recalculates with 0 base.

---

### Scenario 3: Payment Webhook Failure Recovery

**Objective**: Verify that a Stripe webhook failure doesn't permanently block delivery — the agency can manually release the configuration.

**Steps**:
1. Agency completes briefing, pays via Stripe
2. Simulate Stripe webhook delivery failure (Hub server temporarily down, webhook timeout)
3. **Expected**: Configuration remains in `draft` status, no token generated
4. Agency notices the config is stuck in draft
5. Agency navigates to the configuration edit screen
6. **Expected**: "Stripe: Awaiting Webhook" badge displayed, "Mark as Paid" button visible
7. Agency clicks "Mark as Paid" → confirms
8. **Expected**: Post transitions to 'publish' → Token generated → JSON displayed → Token available for client
9. Stripe retries webhook (hours later): Hub receives it
10. **Expected**: Webhook handler detects post is already published → logs "Duplicate webhook for already-paid config" → returns 200 to Stripe (idempotent)
11. **No duplicate processing**: JSON is not regenerated, token is not re-generated

**Edge Cases**:
- "Mark as Paid" clicked but Stripe payment was actually refunded: Manual override creates `_orke_payment_status='manual'` — audit trail shows who marked it
- Webhook arrives before agency notices: Normal flow continues, token generated, "Mark as Paid" not needed
- Multiple webhook retries: Each is idempotent, no state corruption

---

### Scenario 4: Token Security and Revocation

**Objective**: Verify that token-based delivery is secure and that revoked tokens are properly rejected.

**Steps**:
1. Agency completes payment, token `abc-123-def` is generated
2. Client site successfully activates with this token
3. Agency decides to revoke access (client missed payment, project cancelled)
4. Agency navigates to configuration → clicks "Revoke Token" → confirms
5. **Expected**: `_orke_delivery_token` meta changes to `revoked-1712345678`
6. Client tries to activate again with the same token
7. **Expected**: `POST /orkestone/v1/activate` → Hub returns 404 → Client site shows "Configuration token has been revoked. Contact your agency."
8. Agency generates a new token for the same config
9. **Expected**: New token is generated, old revocation record remains

**Edge Cases**:
- Token expired (JWT > 24h): Same error as revoked — "Token not found or revoked" — no information leakage about which
- Rate limit exceeded: Client site shows "Too many activation attempts. Please wait and try again."
- Cross-origin activation attempt: Token bound to specific client URL → activation from wrong domain rejected

---

### Scenario 5: Schema Version Incompatibility

**Objective**: Verify that the activation endpoint protects against incompatible schema versions between Hub and theme.

**Prerequisites**: Hub plugin at version generating schema 2.0.0, client theme running schema 1.0.0.

**Steps**:
1. Agency completes briefing, payment, token generated (Hub schema v2.0.0)
2. Client tries to activate token on their Orkestone site (theme schema v1.0.0)
3. Client pastes token → clicks "Activate"
4. Client site calls `GET /orke-hub/v1/config/{token}` → receives JSON with `schemaVersion: "2.0.0"`
5. Client site compares `2.0.0` against `vbb_get_schema_version()` return value `"1.0.0"`
6. **Expected**: Major version mismatch detected → Import aborted → 409 response: "Configuration requires schema v2.0.0 but this site supports v1.0.0. Contact your agency."
7. Client updates theme to v2.0.0 compatible version
8. Client retries activation with same token
9. **Expected**: Schema versions match → Import proceeds → Success report returned

**Edge Cases**:
- Hub schema 1.1.0, Theme 1.0.0: Minor bump → allowed (backward compatible), import proceeds
- Hub schema 1.0.0, Theme 1.1.0: Theme is newer → always allowed
- Hub schema 0.9.0, Theme 1.0.0: Pre-1.0 versions → rejected (breaking changes presumed)

---

## Regression Areas

The following existing features MUST continue to work after the Agency Hub changes. Each area specifies what to verify and why it could break.

| Area | What to Verify | Risk if Broken |
|------|---------------|----------------|
| **R1. Manual vertical import via Command Center** | Uploading a JSON file via "Import Configuration" in Command Center still works: file upload → validation → `vbb_import_vertical_full()` → pages created. | Phase 3 adds `vbb_get_schema_version()` to `vertical-storage.php`. If this function has side effects or errors, `vbb_save_imported_vertical_config()` could break. |
| **R2. Command Center overrides functionality** | All existing REST endpoints in `pro-rest-api.php` work: GET/POST settings, page CRUD, menu CRUD, page regeneration, export. | Phase 3 adds `POST /orkestone/v1/activate` route. Route registration conflict (same namespace, same route pattern) would break existing routes. |
| **R3. `vbb_validate_vertical_config()` contract** | Validation passes for both hand-crafted and Hub-generated JSON. Required keys `schemaVersion`, `verticalKey`, `name`, `brand`, `navigation`, `pages`, `contentModels` all present. | Hub's JSON must match the exact structure the validator expects. If Hub omits a key the validator considers required, imports will fail silently. |
| **R4. `vbb_import_vertical_full()` execution** | Full import pipeline (pages → sections → content models → media → navigation → SEO) runs correctly for imported files and Hub-delivered files. | Phase 3 `vbb_rest_activate_config()` calls this function. If the JSON is malformed or the function errors on outbound HTTP, it must not have partial side effects (pages created but media not imported). |
| **R5. `vbb_resolve_image_url()` media sideloading** | Images with absolute URLs (from Hub's Asset Library) sideload correctly into client Media Library. Fallback to SVG placeholder on failure. | Hub generates absolute URLs, the theme expects `assets/img/...` relative paths. The sideloader must handle both. |
| **R6. `vbb_save_imported_vertical_config()` disk persistence** | JSON files saved to `uploads/vertical-block-base/verticals/{key}.json` remain valid and readable. | If `vbb_get_schema_version()` changes the return format (e.g., adds `v` prefix or deviates from SemVer), the stored JSON is unaffected — but any future version-gating logic could break. |
| **R7. Stripe integration in other plugins** | Any existing Stripe integration on the same WordPress (e.g., WooCommerce Stripe, GiveWP) continues to process payments independently. | Hub's `class-payment-gateway.php` registers its own webhook endpoint. if it incorrectly handles all Stripe webhooks (instead of only `checkout.session.completed`), it could interfere with other plugins' Stripe flows. |
| **R8. WP REST API availability** | All existing REST endpoints (orkestone/v1/*) respond correctly. No route conflicts with orke-hub/v1/* namespaces. | Hub uses `orke-hub/v1` namespace — distinct from `orkestone/v1` — so no collision. But if Hub accidentally registers under `orkestone/v1`, routes conflict. |
| **R9. Plugin deactivation/rollback** | Deactivating the Agency Hub plugin leaves all other theme functionality unchanged. Orphaned `orke_asset` and `orke_configuration` posts remain in the database without errors. | CPT unregistration on deactivation (if implemented) could cause PHP warnings when querying existing posts. Phase 2 payment data must not be auto-deleted. |
| **R10. `vbb_pro_get_settings()` and settings persistence** | Global settings, page settings, menu items, and profiles remain intact after Hub plugin activation, deactivation, and re-activation. | Hub does not modify any theme settings options — but if the Hub bootstrap writes to `vbb_pro_get_settings()` namespace, it could corrupt Command Center state. |
| **R11. Existing admin UI / Command Center appearance** | Command Center admin screen looks identical before and after Hub activation (no CSS conflicts). | Hub loads `assets/css/admin.css` which could override Command Center styles if selectors collide. Namespace all Hub CSS under `.orke-hub-*`. |
| **R12. Security posture** | Existing permission checks (`vbb_rest_command_center_permission()`) remain untouched. No new unauthenticated endpoints in the `orkestone/v1` namespace. | Phase 3 adds `POST /orkestone/v1/activate` to the theme — this endpoint MUST use `vbb_rest_command_center_permission()` (or equivalent) to stay protected. It must NOT be publicly writable. |

---

## Spec Gaps & Ambiguities (Risks for Design Phase)

| Gap | Description | Impact |
|-----|-------------|--------|
| **G1. Base price when no pages** | The formula says `BASE_PRICE + (PAGE_PRICE × pages)`. If there are zero pages, should the base still apply? The validator requires ≥ 1 page, but the pricing engine might be called before validation. | Design must decide: should the budget calculator enforce the same minimums as the form validator, or be a pure calculator? |
| **G2. Token JSON size limits** | The proposal mentions "reasonable JSON size limits" but doesn't specify what. A full vertical with all sections and 50+ model items could exceed 2MB. | Design must define max JSON size (e.g., 5MB), timeout settings for the theme's outbound HTTP call, and error handling for oversized payloads. |
| **G3. JWT vs UUID for tokens** | Proposal says "UUID (or JWT for production)". UUID is simpler, JWT adds built-in expiry and claims. If we use UUID, expiry must be checked via post meta. If JWT, the Hub needs a signing key. | Design must decide: UUID + separate expiry meta (simpler, no key management) vs JWT with claims (self-contained, but key rotation needed). |
| **G4. Webhook retry queue persistence** | The proposal mentions "retry queue for webhook processing" but doesn't specify storage. Should failed webhooks persist in a custom DB table, post meta, or WP Cron? | Design must choose: Transients (ephemeral), WP Cron (best-effort), or a custom DB table (reliable but over-engineered for MVP). |
| **G5. Hub URL configuration** | The theme-side `POST /orkestone/v1/activate` needs to know the Hub's URL to fetch the JSON. How does the user configure this? Via a settings field in Command Center? A constant in wp-config? | Design must decide: admin setting (user-friendly but needs a UI), or `define('ORKE_HUB_URL', 'https://...')` in wp-config (secure but technical). |
| **G6. Token-to-configuration binding** | If the same configuration is paid twice (e.g., refund → repurchase), do we generate a new token or reuse the existing one? If reused, the old client's activation still works. | Design must document whether tokens are single-use (revoked after first activation) or multi-use (valid until revoked). |
| **G7. SSL requirement for payments** | Stripe Checkout requires HTTPS on the Hub site. The spec assumes this, but it's worth noting as a deployment requirement. | If the Hub runs on HTTP, Stripe Checkout will fail with a mixed-content error. Design should document this as a deployment prerequisite. |
| **G8. Asset Library URL persistence** | Assets uploaded to the Hub's Media Library get WordPress attachment URLs. If the Hub site moves domains (dev → production), all generated JSON URLs will be broken. | Design must decide: store assets with relative URLs and resolve at token delivery time, or use a filter to override the base URL post-migration. |

---

## Success Gates (for sdd-verify)

- [ ] **REQ-AH1–AH8**: Phase 1 — Asset Library, Briefing Form, JSON Builder, Configuration CPT all work independently without payment
- [ ] **REQ-AH9–AH14**: Phase 2 — Budget calculator produces correct itemized totals; Stripe Checkout → webhook → publish lifecycle works end-to-end; "Mark as Paid" manual override works
- [ ] **REQ-AH15–AH22**: Phase 3 — Token generated on publish; `GET /orke-hub/v1/config/{token}` returns JSON for valid tokens; `POST /orkestone/v1/activate` triggers full import; schema version check prevents incompatible imports
- [ ] **Scenario 1**: Full round-trip: Form → Budget → Payment → Token → Activation → Site configured (end-to-end verified with real Stripe test mode)
- [ ] **Scenario 3**: Webhook failure recovery: Mark as Paid override unsticks a draft config; duplicate webhook is idempotent
- [ ] **Scenario 4**: Token revocation: Revoked token returns 404; rate limiting enforces 10 req/min/IP; origin validation blocks cross-origin requests
- [ ] **Scenario 5**: Schema version check: Major mismatch returns 409; minor/patch mismatch is allowed
- [ ] **R1–R12**: All 12 regression areas pass (manual vertical import, Command Center, validation, import, media sideloading, etc.)
- [ ] ZERO WordPress `rest_route` collisions between `orke-hub/v1` and `orkestone/v1` namespaces
- [ ] ZERO PHP warnings/notices on Hub activation and deactivation
