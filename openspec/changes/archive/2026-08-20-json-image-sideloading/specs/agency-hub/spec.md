# Delta Spec: agency-hub — REQ-AH20 Import Report Enrichment

**Change**: json-image-sideloading
**Status**: draft
**Affects**: REQ-AH20

---

## MODIFIED Requirements

### Requirement: REQ-AH20 — Activation Endpoint and Import Report

**The theme MUST expose `POST /orkestone/v1/activate`** in `pro-rest-api.php`, accepting `{token:"..."}`, making an outbound HTTP request to the Hub's config endpoint, validating the response, saving via `vbb_save_imported_vertical_config()`, triggering `vbb_import_vertical_full()`, and returning a success report that includes `urls_remapped_count`.

- GIVEN a user pastes a valid token in the Command Center and clicks "Activate"
- WHEN `POST /orkestone/v1/activate {token:"..."}` is dispatched
- THEN the theme calls the Hub's `GET /orke-hub/v1/config/{token}` endpoint
- AND receives a valid vertical JSON
- AND calls `vbb_save_imported_vertical_config()` to persist the config
- AND calls `vbb_import_vertical_full()` to import pages, media, navigation, and SEO
- AND returns HTTP 200 with `{success: true, pagesCreated: N, mediaImported: M, urls_remapped_count: K}`
- AND the response body includes `urls_remapped_count` as a non-negative integer equal to the number of remote image URLs successfully substituted with local attachment URLs in page content

- GIVEN a user submits an invalid or revoked token
- WHEN the endpoint is called
- THEN it returns HTTP 400 or 404 with `{success: false, message: "..."}`

- GIVEN the Hub is unreachable at activation time
- WHEN the outbound HTTP request fails
- THEN the endpoint returns HTTP 502 with `{success: false, message: "Hub unreachable"}`

- GIVEN the Hub JSON schema version is incompatible with the current theme
- WHEN the version check fails
- THEN the endpoint returns HTTP 409 with `{success: false, message: "Incompatible schema version"}`

- GIVEN the activation completes but some source URLs have no local attachment target
- WHEN the importer finishes without remapping those URLs
- THEN `urls_remapped_count` reflects only the URLs that were successfully substituted
- AND the report optionally includes `remap_skipped` listing source URLs that could not be resolved
- AND existing response fields (`pagesCreated`, `mediaImported`) are unchanged

(Previously: report included only `pagesCreated` and `mediaImported` counts; report shape is now extended with `urls_remapped_count` without breaking the existing response contract)