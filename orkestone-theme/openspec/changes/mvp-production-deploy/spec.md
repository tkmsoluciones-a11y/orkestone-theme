# Spec: mvp-production-deploy

## Requirements

### Requirement: Theme Bootstrap Guardrails

The theme MUST load without fatal errors when any `inc/pro-*.php` file (`pro-settings.php`, `pro-presets.php`, `pro-css-vars.php`, `pro-admin.php`, `pro-rest-api.php`) is absent. `functions.php` MUST wrap each `require_once` with a `file_exists()` check. Missing files MUST be silently skipped or logged at `WP_DEBUG` level, never fatal.

#### Scenario: Clean install without Pro Elite files
- GIVEN a fresh WordPress 6.6+ install with no `inc/pro-*.php` files present
- WHEN the theme is activated from WP Admin
- THEN the theme activates without PHP fatal error
- AND the front-end renders on a registered block template

#### Scenario: Pro files present
- GIVEN a WordPress install where `inc/pro-*.php` files exist
- WHEN the theme is activated
- THEN all Pro module features load as before
- AND no regression in existing functionality

### Requirement: REST API Security Hardening

All REST API mutation endpoints in `inc/pro-rest-api.php` MUST validate a WordPress nonce (`wp_rest`) and `current_user_can('edit_posts')` before executing. Endpoints that only read data MAY exempt the nonce check if capability check is preserved. Any input received from REST requests MUST be sanitized before use.

#### Scenario: Unauthenticated mutation request
- GIVEN a WordPress install with the theme active
- WHEN an unauthenticated HTTP POST is sent to a REST mutation endpoint
- THEN the endpoint returns HTTP 403
- AND no state is modified

#### Scenario: Authenticated request without nonce
- GIVEN a logged-in user with `edit_posts` capability
- WHEN a REST mutation request is sent without a valid nonce
- THEN the endpoint returns HTTP 403

#### Scenario: Authenticated request with valid nonce
- GIVEN a logged-in user with `edit_posts` capability and a valid nonce
- WHEN a REST mutation request is sent with sanitized input
- THEN the endpoint processes the request successfully
- AND the response contains no unsanitized raw input echoes

### Requirement: Render Callback Output Sanitization

All render callbacks in `inc/block-baker.php`, including `vbb_render_cta_button`, MUST pass URL attributes through `esc_url()`, HTML attributes through `esc_attr()`, and text content through `sanitize_text_field()` before output. No raw user-supplied or config-derived values MAY be echoed unescaped.

#### Scenario: CTA button with external URL
- GIVEN a vertical JSON defining a CTA button with `url: "https://example.com?ref=test"`
- WHEN the block renders on the front-end
- THEN the href attribute is escaped via `esc_url()`
- AND the URL is valid and clickable

#### Scenario: CTA button with malicious script payload
- GIVEN a vertical JSON defining a CTA button with `url: "javascript:alert(1)"` or `label: "<script>alert(1)</script>"`
- WHEN the block renders
- THEN the href is neutralized by `esc_url()` and contains no executable script
- AND the label is escaped by `esc_attr()` or `sanitize_text_field()`

### Requirement: Test Suite PHP 8.5 Compatibility

The test suite (`inc/test-block-baker.php`) MUST pass on PHP 8.5 without deprecation warnings treated as errors. If a root-cause fix is not viable within scope, the specific failure MUST be documented with a justified skip or deprecation notice, and the remaining tests MUST continue to pass. `inc/test-orkestone-engine.php` MUST maintain its 99/99 pass rate throughout all changes.

#### Scenario: Full test suite run on PHP 8.5
- GIVEN PHP 8.5 runtime
- WHEN `php inc/test-orkestone-engine.php` is executed
- THEN the exit code is 0
- AND all 99 assertions pass

#### Scenario: block-baker test run on PHP 8.5
- GIVEN PHP 8.5 runtime
- WHEN `php inc/test-block-baker.php` is executed
- THEN the exit code is 0
- OR a documented skip notice is emitted with a clear reason

### Requirement: Production Packaging

The theme MUST declare `Requires at least: 6.4` and `Requires PHP: 8.1` in `style.css` headers. A packaging artifact (`deploy/theme.zip`) MUST be creatable via a documented command and contain all required theme files without development-only artifacts (raw test files, debug scripts). The packaged theme MUST activate cleanly on a second WordPress instance.

#### Scenario: Theme zip installation on fresh instance
- GIVEN a second clean WordPress 6.4+ install with no prior Orkestone configuration
- WHEN the packaged `theme.zip` is uploaded and activated via WP Admin
- THEN the theme activates without fatal errors
- AND the front-end renders the default block template

### Requirement: VPS Deployment Runbook

A runbook document (`deploy/vps-setup.md`) MUST specify: tested server stack (OS, web server, PHP version), WordPress installation steps, theme activation, first vertical JSON import flow, and post-install smoke checks. A companion checklist (`INSTALL.md`) MUST cover each acceptance gate.

#### Scenario: Follow runbook on staging VPS
- GIVEN a VPS matching the documented stack
- WHEN an operator follows the runbook end-to-end
- THEN WordPress is running, theme is active, and a sample vertical JSON imports successfully
- AND all smoke checks pass
