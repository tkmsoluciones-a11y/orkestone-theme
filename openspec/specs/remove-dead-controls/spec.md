# remove-dead-controls Specification

## Purpose

Define the complete removal of the orphan `profileName` theme option across PHP defaults, settings sanitization, and the admin JavaScript state layer. The option has no frontend consumer; keeping it produces dead code and risks operator confusion.

## Requirements

### Requirement: Default Settings Must Not Declare profileName

`vbb_pro_get_default_settings()` in `inc/pro-settings.php` MUST NOT include a `profileName` key in its returned settings array.

#### Scenario: Default settings loading

- GIVEN the theme is freshly activated with no stored options
- WHEN `vbb_pro_get_default_settings()` is called
- THEN the returned array MUST NOT contain the key `profileName`

#### Scenario: Existing stored option preserved

- GIVEN a site already has `profileName` persisted in `wp_options`
- WHEN defaults are merged with stored options
- THEN the stored `profileName` value MAY remain in the merged result as a no-op orphan (no error introduced)

### Requirement: Sanitization Must Not Process profileName

`vbb_pro_sanitize_settings()` in `inc/pro-admin.php` MUST NOT enter a `profileName` sanitization branch and MUST NOT include `profileName` in its returned settings array.

#### Scenario: Sanitize echo

- GIVEN an input array that contains a `profileName` key
- WHEN `vbb_pro_sanitize_settings($input)` is called
- THEN the function MUST NOT call any sanitizer on `profileName`
- AND the returned array MUST NOT contain the key `profileName`

#### Scenario: Settings save flow

- GIVEN an admin user saves Pro settings via the settings screen
- WHEN the save handler runs `vbb_pro_sanitize_settings()`
- THEN no PHP notice or warning related to `profileName` is emitted
- AND the resulting persisted option row does not include `profileName`

### Requirement: Admin UI Must Not Reference profileName

`pro-admin.php` and `assets/js/admin-pro.js` MUST NOT render, submit, or reference `profileName` in any template output, script state, or AJAX payload.

#### Scenario: Admin page render

- GIVEN an authorized admin user opens the Pro settings page
- WHEN the PHP template in `pro-admin.php` is rendered
- THEN no HTML element or data attribute carries the name `profileName`
- AND no JavaScript variable or initial state object in `admin-pro.js` is named `profileName`

#### Scenario: Settings save round-trip

- GIVEN the admin UI is loaded without `profileName` references
- WHEN the user modifies any other setting and clicks Save
- THEN the outgoing AJAX request payload MUST NOT contain a `profileName` field
- AND the UI updates without JavaScript errors

#### Scenario: Codebase grep verification

- GIVEN the three affected files have been updated
- WHEN `grep -r "profileName"` is executed across the entire codebase
- THEN zero matches are returned from `inc/` and `assets/js/`
- AND the only matches, if any, are in `openspec/` documentation or changelog notes (non-executable)