# 🚀 Orkestone Theme
**The Vertical-Driven Gutenberg Engine**

Orkestone is a professional-grade WordPress Block Theme designed for rapid site instantiation. Unlike traditional themes, Orkestone uses a **Vertical-Driven Architecture**, where entire website structures, content, and styles are defined in JSON configurations and "baked" into real Gutenberg blocks.

[![Status](https://img.shields.io/badge/Status-Production--Ready-brightgreen)]()

---

## 🏗️ Core Concept: Vertical-Driven Architecture

Orkestone eliminates the manual labor of building identical site structures for different clients in the same industry.

### The Pipeline: JSON $\rightarrow$ Baking $\rightarrow$ WordPress
1. **Vertical JSON**: A configuration file defines the pages, sections (Hero, Services, FAQ, etc.), and global settings.
2. **Baking Engine**: The theme processes the JSON and generates real `wp:group`, `wp:columns`, and `wp:heading` blocks.
3. **Instantiation**: The site is "baked" into the database, creating real WordPress pages that are fully editable in the Gutenberg editor.

---

## 🕹️ The Command Center
The **Command Center** is a high-performance administrative UI that allows non-technical users to manage the site without touching the WordPress editor.

### Key Capabilities:
- **Page Management**: Create, delete, and reorganize pages via a simplified REST API.
- **Dynamic Settings**: Update colors, typography, and content across the whole site or for specific pages.
- **Visual Style Variants**: Switch between Layout Styles (A, B, or C) for key sections (Hero, CTA, Testimonials) to change the look and feel instantly.
- **Live Preview**: An integrated iframe with a `postMessage` bridge that injects CSS variables in real-time—no full page reloads required.
- **Configuration Portability**: Export the entire site state to a JSON file and import it into any other Orkestone-powered install.

---

## 🚀 Installation & Setup

### 1. Install the Theme
Upload the `orkestone-theme` folder to `/wp-content/themes/` and activate it via **Appearance $\rightarrow$ Themes**.

### 2. Import a Vertical
Navigate to **Appearance $\rightarrow$ Verticals JSON** and upload a `.json` vertical file. The engine will:
- Reset any existing vertical pages.
- Sideload required media.
- Bake all pages and navigation.
- Set the front page and WooCommerce settings.

### 3. Manage with Command Center
Open the **Command Center** to start customizing your colors, content, and page layouts.

---

## 🛠️ Developer's Guide

### Adding New Block Bakers
To add a new section type:
1. Create a baker function in `inc/block-baker.php` following the pattern: `vbb_bake_{section_type}()`.
2. The function should return valid Gutenberg block markup.
3. Add the section type to the `vbb_bake_section()` dispatcher.

### Extending Settings
Settings are managed in `inc/pro-settings.php`. To add a new global or per-page setting:
1. Add the key to the default settings array.
2. Update `vbb_pro_sanitize_settings()` to validate the input.
3. Use `vbb_pro_get_page_settings($page_id)` to retrieve the merged values.

### The Token System
Orkestone uses a placeholder system (`{{vbb_placeholder}}`) in its baked content. These are resolved at render time by `vbb_pro_replace_dynamic_content()`, allowing content to change in the Command Center without needing to re-bake the entire page.

---

## 📈 Performance & Architecture
- **Baking Pipeline**: Reduces runtime overhead by storing real block markup instead of rendering patterns on every load.
- **Transient Caching**: Implements a version-based cache for merged settings, eliminating expensive sanitization and merge operations on the critical path.
- **Scoped CSS**: Uses CSS Custom Properties (`--vbb-pro-*`) to allow instant, granular visual updates.

## 💻 Technical Stack
- **Backend**: PHP 7.4+ / WordPress 6.6+
- **Frontend**: Vanilla JavaScript (ES6+), CSS3 (Custom Properties)
- **Data**: JSON (Config), WordPress Options API (Settings)
- **Infrastructure**: REST API, WordPress Transients API

---
© 2026 Orkestone Project. Professional-grade Gutenberg Orchestration.
