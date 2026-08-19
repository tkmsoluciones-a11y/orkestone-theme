# Archive Report: Orkestone Agency Hub — Business & Distribution

**Change**: agency-hub
**Final Status**: Completed (with warnings)
**Archived At**: 2026-07-09
**Delivery Strategy**: force-chained (3 phases, 800-line review budget per PR)

---

## Change Summary

The Agency Hub plugin was launched, transforming Orkestone from a standalone theme into a professional agency distribution platform. The plugin implements a complete round-trip: **Briefing Form → JSON Builder → Pricing Calculator → Stripe Checkout → Webhook → Token Generation → Theme Activation via `POST /orkestone/v1/activate`**.

### What Was Built

| Aspect | Detail |
|--------|--------|
| **Plugin** | `orkestone-agency-hub/` — 12 new files, 2 modified theme files |
| **Phases** | 3 (Asset/Briefing → Budget/Payment → Token/Connector) |
| **Tasks** | 12/12 completed across 3 force-chained PRs |
| **Requirements** | 22 functional (REQ-AH1–AH22) — 21 compliant, 1 partial |
| **Scenarios** | 5 scenario-based tests — 3 compliant, 2 partial (no E2E runtime, origin validation unwired) |
| **Regressions** | 12/12 pass (R1–R12) |
| **Design Decisions** | 11 architecture decisions (AD1–AD11) — 10 fully followed, 1 partial |
| **Lines of Code** | ~1,780 total across 3 phases |

### Components Delivered

- **Phase 1** — `orke_asset` CPT, 4-tab briefing form (Vanilla JS), JSON builder mapping form data to vertical schema, `orke_configuration` CPT for lifecycle tracking
- **Phase 2** — Dynamic pricing calculator with filterable constants, Stripe Checkout integration via `wp_remote_post()`, webhook handler with HMAC-SHA256 signature verification, manual "Mark as Paid" override, PayPal IPN support
- **Phase 3** — UUID v4 token generation on publish with 24h expiry, Hub REST API (`GET config/{token}`, `POST validate-token`, webhook endpoints), rate limiting (10 req/min/IP), origin validation infrastructure, token revocation/regeneration, theme-side `POST /orkestone/v1/activate` endpoint with full SemVer schema compatibility check

---

## Artifact Lineage

### OpenSpec Filesystem

| Artifact | Path |
|----------|------|
| Exploration | `openspec/changes/archive/2026-07-09-agency-hub/explore.md` |
| Proposal | `openspec/changes/archive/2026-07-09-agency-hub/proposal.md` |
| Spec (Delta) | `openspec/changes/archive/2026-07-09-agency-hub/spec.md` |
| Design | `openspec/changes/archive/2026-07-09-agency-hub/design.md` |
| Tasks | `openspec/changes/archive/2026-07-09-agency-hub/tasks.md` |
| Apply Progress | `openspec/changes/archive/2026-07-09-agency-hub/apply-progress.md` |
| Verify Report | `openspec/changes/archive/2026-07-09-agency-hub/verify-report.md` |
| Archive Report | `openspec/changes/archive/2026-07-09-agency-hub/archive-report.md` |
| Main Spec (new domain) | `openspec/specs/agency-hub/spec.md` |

### Engram Persistent Memory

| Artifact | Observation ID | Topic Key |
|----------|---------------|-----------|
| Exploration | #1672 | `sdd/agency-hub/explore` |
| Proposal | #1678 | `sdd/agency-hub/proposal` |
| Spec | #1679 | `sdd/agency-hub/spec` |
| Design | #1680 | `sdd/agency-hub/design` |
| Tasks | #1681 | `sdd/agency-hub/tasks` |
| Apply Progress | #1682 | `sdd/agency-hub/apply-progress` |
| Verify Report | #1685 | `sdd/agency-hub/verify-report` |
| Archive Report | *(this save)* | `sdd/agency-hub/archive-report` |

---

## Key Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| **AD1** | Separate plugin | Other themes can use the Hub. Theme edits are minimal and guarded by `function_exists()`. |
| **AD2** | Vanilla JS tab form | Matches existing theme asset patterns. No React build step. Keeps review budget under 800 lines. |
| **AD3** | CPT `post_content` for JSON | Avoids file-permission issues. Leverages WP revision history and publish-transition hooks. |
| **AD4 / G3** | **UUID v4 + post meta expiry** (NOT JWT) | No key management. Expiry checked server-side. Simple, auditable, no crypto dependencies. Token resolves a single CPT, not a distributed auth system. |
| **AD5 / G4** | Stripe-native retries + manual override | Stripe retries webhooks for 3 days with exponential backoff. Custom retry queue is over-engineering for MVP. |
| **AD6 / G5** | WP option + constant for Hub URL | `get_option('orke_hub_url')` with `define('ORKE_HUB_URL', ...)` fallback. Admin UI for most users, `wp-config.php` for secure deployments. |
| **AD7 / G6** | Multi-use, revocable tokens | Single-use tokens break legitimate re-activation scenarios. Revocation is explicit via admin button. |
| **AD8 / G7** | SSL deployment prerequisite | Hub admin shows warning banner if `!is_ssl()`. Stripe Checkout requires HTTPS. |
| **AD9 / G8** | Filterable asset base URL | `apply_filters('orke_asset_base_url', site_url())` lets agencies migrate domains or add CDN without regenerating configs. |
| **AD10 / G1** | Pure pricing calculator | Calculator is a math engine — no form validation enforcement. Form validation runs separately before purchase. |
| **AD11 / G2** | 5MB JSON limit, 30s HTTP timeout | 5MB covers largest realistic vertical. 30s matches WP default + 5s buffer. *(Note: 5MB cap not enforced in builder — see warnings)* |

---

## Verification Summary

| Metric | Result |
|--------|--------|
| **Verdict** | **PASS WITH WARNINGS** |
| **Requirements Coverage** | **21/22 COMPLIANT**, 1 PARTIAL |
| **Scenarios** | S2 (Budget) ✅, S3 (Webhook Recovery) ✅, S5 (Schema Check) ✅ — S1 (Full Happy Path) ⚠️ Partial, S4 (Token Security) ⚠️ Partial |
| **Regressions (R1–R12)** | **12/12 PASS** |
| **Design Adherence (AD1–AD11)** | **10/11 followed**, 1 partial (AD11 — JSON size cap not enforced) |
| **Tasks** | **12/12 complete** |
| **PHP Syntax** | ✅ All 10 PHP files pass `php -n -l` |

### Warnings (from verify report)

1. **No test suite exists** — Zero PHPUnit tests, zero integration tests, zero E2E tests. All 5 spec scenarios lack automated runtime coverage. Manual verification on real WordPress instance required before production deployment.
2. **Origin validation unenforced** (REQ-AH18) — `_orke_token_allowed_origin` meta exists and `validate_origin()` checks it, but no UI or flow ever populates this field. The briefing form lacks a "Client Site URL" field, so origin validation defaults to permissive (always returns `true`).
3. **No 5MB JSON size cap** (AD11) — `Orkestone_JSON_Builder::build()` and `get_json()` don't check output size. The 30s HTTP timeout is correctly implemented, but server-side oversized JSON rejection is missing.

---

## Lessons Learned

1. **Origin writer missing in Token system** — `_orke_token_allowed_origin` post meta was defined but never populated. The briefing form lacks a "Client Site URL" field. This is a spec-to-design gap: REQ-AH18 requires origin validation but the form was never designed to collect the client URL. Fix requires adding the field to the briefing form and wiring it to token generation.

2. **Automated tests critical for payment logic** — Zero test suite exists for payment-critical flows (Stripe webhook idempotency, PayPal IPN validation, token security). Code inspection found issues (origin validation unwired) that automated tests would have caught earlier. The spec's 5 scenarios with detailed verification steps remain unautomated.

3. **Custom Stripe API via `wp_remote_post` needs careful handling** — Stripe's nested parameter format (`line_items[0][price_data][currency]=usd`) requires custom `build_form_data()` since `wp_remote_post` doesn't handle nested arrays natively. For production, consider adding the Stripe PHP SDK for built-in error handling, idempotency keys, and retry logic.

4. **Deviations documentation is valuable** — The apply-progress artifact explicitly documents 3 implementation deviations from the design (token meta box, settings page, daily cleanup cron) with rationale. This pattern should be standard — it avoids confusion between "design changed" vs "improvement made."

5. **UUID v4 regex must validate version and variant bits** — The token validation regex must match the version nibble `4` and variant `[89ab]` for proper validation, not just any UUID format.

6. **SemVer schema check needs pre-1.0 handling** — Pre-release versions (0.x.y) imply breaking changes. `vbb_check_schema_compatibility()` correctly rejects Hub schema 0.x when theme is ≥ 1.0, treating the transition to 1.0 as a breaking boundary.

---

## Archive Contents

| Item | Status |
|------|--------|
| `explore.md` | ✅ |
| `proposal.md` | ✅ |
| `spec.md` | ✅ |
| `design.md` | ✅ |
| `tasks.md` | ✅ (12/12 tasks complete) |
| `apply-progress.md` | ✅ |
| `verify-report.md` | ✅ |
| `archive-report.md` | ✅ |

## Main Spec Synced

| Domain | Action |
|--------|--------|
| `agency-hub` | Created — `openspec/specs/agency-hub/spec.md` (22 requirements, full spec) |

---

## SDD Cycle Complete

The change has been fully planned, explored, proposed, specified, designed, implemented, verified, and archived.

Ready for the next change.
