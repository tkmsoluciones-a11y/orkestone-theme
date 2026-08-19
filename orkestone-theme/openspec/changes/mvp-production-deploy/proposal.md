# Proposal: mvp-production-deploy

## Intent
Bring the Orkestone theme from dev-only state to live production on a VPS with WordPress and real users. The theme is functionally complete but has a hard fatal crash condition (unconditional `require_once` for missing Pro Elite files), a broken test suite on PHP 8.5, no security hardening, and no production packaging or deployment runbook. This change addresses those gaps without adding features.

## Scope

### In Scope
- Fix `functions.php` lines 41-45 — add `file_exists()` guards so theme loads without `inc/pro-*.php` files present (`pro-settings.php`, `pro-presets.php`, `pro-css-vars.php`, `pro-admin.php`, `pro-rest-api.php`)
- Fix or document `inc/test-block-baker.php` failure on PHP 8.5 — root-cause fix with PHP 8.5 compat test, or skip requirement with documented deprecation if fix is out of scope
- Security hardening: REST API endpoint nonces and `current_user_can()` capability checks in `inc/pro-rest-api.php`; input sanitization review in `inc/block-baker.php` (`vbb_render_cta_button` and render callbacks)
- Production readiness: add error handling / graceful degradation for missing dependencies; remove temp/dev artifacts not needed at runtime
- Deployment packaging: theme.zip with correct WordPress header, activation checklist, minimum WP/PHP version declared in `style.css`
- VPS deployment runbook: server requirements (PHP ≥ 8.1, MySQL ≥ 5.7, WP ≥ 6.4), fresh WP install steps, first vertical import flow (JSON import via admin), post-install smoke checks
- Acceptance checklist: load theme in WP admin, activate without fatal, import a vertical JSON, verify front-end renders, confirm REST API protected
- TDD compliance: every code change ships with a passing test; `inc/test-orkestone-engine.php` serves as regression suite (currently 99/99)

### Out of Scope
- New WordPress sections, block patterns, or vertical site templates
- Submission to WordPress.org theme repository
- Performance benchmarking, CDN setup, caching layers
- Visual design changes or CSS refactoring
- New features or capability expansion

## Capabilities

### Modified Capabilities
- `theme-bootstrap`: loading behavior — `functions.php` must not fatal when Pro Elite files are absent; graceful fallback documented
- `rest-api-access`: security posture — all REST endpoints must validate nonces and `edit_posts` capability before mutating state
- `block-rendering`: output sanitization — `vbb_render_cta_button` and other render callbacks must pass through `esc_url()`, `esc_attr()`, or `sanitize_text_field()` before echo

## Approach
Force-chained PRs via feature-branch-chain strategy, review budget ≤800 lines per PR slice.

| PR | Branch | Lines | Content |
|----|--------|-------|---------|
| 1 | `fix/functions-require-guards` | ~30 | `file_exists()` guards on lines 41-45 of `functions.php` + unit test for missing-file scenario |
| 2 | `fix/test-block-baker-php85` | ~150 | Root-cause PHP 8.5 compat fix in `inc/test-block-baker.php` + test pass proof; or deprecation notice if unfixable without rewrite |
| 3 | `security/rest-api-hardening` | ~200 | Nonce + `current_user_can()` checks in `inc/pro-rest-api.php`; sanitization audit in `inc/block-baker.php` |
| 4 | `chore/production-readiness` | ~100 | Error handling guards, dev-artifact audit, `style.css` version/requires header update |
| 5 | `deploy/packaging-and-runbook` | ~250 | `deploy/theme.zip` packaging script, `INSTALL.md` checklist, `deploy/vps-setup.md` runbook, smoke-test checklist |

Each PR independently testable and deployable. PR 1 unblocks all others by removing the fatal error. PR 5 can be merged last and is release gate.

## Evidence Requirements Before Phase Advance

- **PR 1 merge gate**: `php inc/test-orkestone-engine.php` still passes 99/99; manual WP activation test with Pro Elite files deliberately removed shows theme activates without fatal
- **PR 2 merge gate**: `php inc/test-block-baker.php` exits 0 on PHP 8.5; or documented decision accepted in PR description with maintainer sign-off
- **PR 3 merge gate**: `test-orkestone-engine.php` still passes; REST API `OPTIONS` request returns no unauthorized mutation routes; manual REST call without nonce returns 403
- **PR 4 merge gate**: `test-orkestone-engine.php` still passes; `functions.php` parses without PHP lint errors; no `var_dump()` / `error_log()` left in production paths
- **PR 5 merge gate**: theme zip activates cleanly on fresh WP 6.4+ install; VPS runbook tested on staging VPS; smoke checklist 100% pass

## Risks and Mitigations

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| Pro Elite files are shipped but missing guards hide a deeper dependency chain | Med | PR 3 adds error logging for skipped files; `WP_DEBUG` log review step in runbook |
| test-block-baker.php PHP 8.5 fix reveals untested edge cases in block-baker | Med | Run full `test-orkestone-engine.php` suite after PR 2; add targeted regression tests for any fixed behavior |
| REST API hardening breaks legitimate AJAX calls from front-end | Low | Audit all `admin-ajax.php` and `rest_api_init` consumers before PR 3; keep backward-compat check in place |
| VPS runbook assumes specific hosting stack (e.g., Ubuntu 22.04 + Nginx) | Low | Document tested stack explicitly; include Docker Compose alternative for reproducibility |

## Success Criteria
- Theme activates on a fresh WordPress 6.4+ install on a real VPS without PHP fatal errors
- `inc/test-orkestone-engine.php` passes 99/99 in the deployed environment
- All REST API mutation endpoints reject unauthenticated requests (HTTP 403) and validate nonces
- A vertical JSON config (e.g., `config/verticals/agencia-5.json`) imports via Command Center and produces a valid front-end page
- Theme zip, install checklist, and VPS runbook exist and are tested end-to-end
- Every PR in the chain passes lint (`php -l`) and tests before merge
