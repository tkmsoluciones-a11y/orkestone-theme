# Manual Validation Protocol: Vertical Import & Reset

Verify the Orkestone Engine import pipeline on a live WordPress site.

## Prerequisites

- WordPress 6.0+ with a fresh database (or test site where content loss is acceptable).
- Orkestone Theme active.
- WP-CLI installed **or** admin access to the WordPress dashboard.
- At least one vertical JSON file present in the theme's `config/` directory.

---

## Step 1: Install and Activate the Theme

1. Upload the theme ZIP via **Appearance → Themes → Add New → Upload Theme**.
2. Activate the Orkestone Theme.
3. Verify the theme is active and no PHP errors appear on the front-end or in **WP_DEBUG** logs.

**Expected**: Theme activates cleanly. A default vertical is auto-imported on activation (pages, navigation, front page).

---

## Step 2: Trigger a Vertical Import

### Via WP-CLI (preferred)

```bash
wp vbb import-full <vertical-key>
```

Example:

```bash
wp vbb import-full law-firm
```

### Via Admin (fallback)

1. Navigate to **Command Center** (usually under a top-level admin menu item added by the theme).
2. Locate the vertical selector / import button.
3. Choose a vertical (e.g. "Law Firm") and click **Import**.

**Expected**:
- The CLI returns a structured JSON report with `success: true`.
- The report includes `pages_created`, `media_sideloaded`, `navigation` details.

---

## Step 3: Verify Pages in the Editor

1. Go to **Pages → All Pages**.
2. You should see the pages declared by the imported vertical (e.g. "Home", "About", "Services").
3. Open any page in the Block Editor:
   - Verify the page title matches the vertical JSON.
   - Verify the content area contains baked blocks (headings, paragraphs, columns, etc.).
   - Confirm that **no raw `{{vbb_...}}` token placeholders** are visible in the editor — all tokens should be resolved to actual content.

**Expected**: Each page from the vertical JSON exists as a published page with baked HTML content.

---

## Step 4: Verify the Menu in Appearance → Menus

1. Go to **Appearance → Menus**.
2. You should see a menu named **"OrkestOne Theme"** (or the configured menu name).
3. Open the menu and verify:
   - The menu contains the navigation items defined in the vertical JSON's `navigation.primary`.
   - Page-type items link to the correct imported pages (kind: page, with the page ID).
   - Custom URL items link to the correct external URLs.
4. Visit the front-end and confirm the navigation displays correctly.

**Expected**: A `wp_navigation` post exists with the vertical's menu items, tagged with `_vbb_source = vertical` meta.

---

## Step 5: Trigger a Second Import of the Same Vertical

Run the same import again:

```bash
wp vbb import-full law-firm
```

1. The CLI should return `success: true`.
2. Verify the `reset` field is **null** — no reset occurred because it's the same vertical.
3. Return to **Pages → All Pages**:
   - The same pages should still exist (they were updated, not duplicated).
   - Check a page's modified date — it should be updated.
4. Return to **Appearance → Menus**:
   - The "OrkestOne Theme" menu should still exist (updated, not duplicated).
   - Menu items should match the vertical JSON.

**Expected**: Re-importing the same vertical updates existing pages and menu without trashing them.

---

## Step 6: Cross-Vertical Switch (Reset)

Import a **different** vertical:

```bash
wp vbb import-full ecommerce
```

1. The CLI should return `success: true`.
2. Verify the `reset` field shows `pages_trashed` > 0 and `navigation_trashed` > 0 — these are the old vertical's pages being trashed.
3. Go to **Pages → All Pages**:
   - The **Pages tab** should show the new vertical's pages (e.g. "Shop", "Products").
   - Check the **Trash** — the old vertical's pages (e.g. "Home", "About" from "Law Firm") should be in the trash.
4. Go to **Appearance → Menus**:
   - The menu should now reflect the new vertical's navigation items.
   - The old menu should be trashed (check **Trash** in the Menus screen if available).
5. Visit the front-end — the site should now show the new vertical's content.

**Expected**: Switching verticals trashes the old vertical's pages and navigation, then imports the new vertical's content. All trashed content is recoverable from the trash (not permanently deleted).

---

## Step 7: Recover from Trash

1. Go to **Pages → Trash**.
2. Locate a page from the old vertical (e.g. "Home" from "Law Firm").
3. Hover over the page title and click **Restore**.
4. After restoration:
   - The page should appear in **All Pages** with status "Draft" or "Published".
   - The `_vbb_vertical` meta should still be set to the old vertical key.
5. Verify that restoring trashed content does **not** break the current vertical's content — restored pages from the old vertical coexist with new vertical pages but are no longer managed by the reset orchestrator (they lack the current vertical's meta).

**Expected**: Trashed content is recoverable. Restoring does not disrupt the active vertical's imported content.

---

## Regression Checklist

| Check | Status |
|-------|--------|
| No PHP warnings or notices in **WP_DEBUG** logs during import | [ ] |
| Block editor loads without JavaScript errors after import | [ ] |
| Front-end renders all page content correctly | [ ] |
| Navigation menu links work (both page-type and custom) | [ ] |
| `wp_delete_post` is **never** called (only `wp_trash_post`) | [ ] |
| Re-importing the same vertical does not duplicate content | [ ] |
| Trashed content is visible in WordPress Trash | [ ] |
| Restored content does not break the active vertical | [ ] |

---

## Troubleshooting

| Symptom | Likely Cause | Check |
|---------|-------------|-------|
| Import returns `success: false` | Vertical JSON missing or malformed | Verify `config/<key>.json` exists and is valid JSON |
| No pages created | `vbb_generate_vertical_pages_from_baked` failed | Check `report.pages_errors` in the CLI output |
| Navigation not updated | `wp_navigation` post type requires block theme | Verify a block-based theme is active |
| `{{vbb_...}}` tokens visible | Token resolution not triggered | Run the "Regenerate All" action in Command Center |
| Content appears but menu is empty | `navigation.primary` key missing in vertical JSON | Check the vertical JSON structure |

---

*Protocol version 1.0 — Orkestone Engine Manual Validation*
