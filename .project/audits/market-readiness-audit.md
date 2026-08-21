# AUDITORIA DE MARKET-READINESS — ORKESTONE THEME & AGENCY HUB

**Fecha:** 20 de Agosto de 2026  
**Alcance:** Lectura completa de `orkestone-theme/` y `orkestone-agency-hub/`.  
**Objetivo:** Inventarios completos y matriz de brechas (gaps) para construir el roadmap de lanzamiento. Sin modificaciones de código.

---

## A) SUPERFICIE DE CONFIGURACION (Inventory A)

Inventario exhaustivo de cada control expuesto en el panel de administración (Command Center, vbb-pro-elite, vbb-verticals, import-export, theme.json):

| key | panel | tipo | default | donde se guarda | que escribe (CSS var / flag PHP) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `profileName` | Command Center / Pro Elite | text | "Default Pro Elite" | `vbb_pro_settings` (option) | Ninguno (Metadato de perfil) |
| `colorMode` | Command Center / Pro Elite | select | "light" (`light`, `dark`, `auto`) | `vbb_pro_settings` (option) | Clase `vbb-color-mode-{mode}` en `<body>`, selector `:root` / `@media (prefers-color-scheme: dark)` |
| `siteConfig.type` | Command Center (Page Selector) | select | "landing" (`landing`, `multi`) | `vbb_pro_settings` (option) | Estructura de navegación y selector de páginas |
| `headerConfig.siteTitle` | Command Center (Brand & Header) | text | "Mi Empresa" | `vbb_pro_settings` (option) | Filtro `pre_option_blogname` |
| `headerConfig.logoUrl` | Command Center (Brand & Header) | media / text | "" | `vbb_pro_settings` (option) | Filtro `get_custom_logo` |
| `headerConfig.menuType` | Command Center (Brand & Header) | select | "logo-title" (`logo-only`, `logo-title`, `title-only`) | `vbb_pro_settings` (option) | Clase CSS `vbb-menu-{type}` en `<body>` |
| `headerConfig.textColor` | Command Center (Brand & Header) | color | "#000000" | `vbb_pro_settings` (option) | `--vbb-pro-text` / `.wp-site-title a` |
| `headerConfig.bgColor` | Command Center (Brand & Header) | color | "#ffffff" | `vbb_pro_settings` (option) | Color de fondo en contenedor del Header |
| `menuConfig.type` | Command Center (Navigation & Menu) | select | "standard" (`standard`, `hamburger`, `sticky`) | `vbb_pro_settings` (option) | Clase `vbb-nav-{type}` en `<body>` |
| `menuConfig.style` | Command Center (Navigation & Menu) | select | "modern" (`modern`, `minimal`, `classic`, `pill`) | `vbb_pro_settings` (option) | Clase `vbb-nav-style-{style}` en `<body>` |
| `menuConfig.bgColor` | Command Center (Navigation & Menu) | color | "" | `vbb_pro_settings` (option) | Estilo inline de fondo en contenedor de navegación |
| `menuConfig.textColor` | Command Center (Navigation & Menu) | color | "" | `vbb_pro_settings` (option) | Estilo inline de color en items de navegación |
| `menuConfig.darkBtnBg` | Command Center (Navigation & Menu) | color | "" | `vbb_pro_settings` (option) | Fondo del botón de modo oscuro |
| `menuConfig.darkBtnText` | Command Center (Navigation & Menu) | color | "" | `vbb_pro_settings` (option) | Color de icono del botón de modo oscuro |
| `menuConfig.ctaButton.enabled` | Command Center (Navigation & Menu) | checkbox | false | `vbb_pro_settings` (option) | Renderiza botón CTA al final del menú |
| `menuConfig.ctaButton.text` | Command Center (Navigation & Menu) | text | "Contacto" | `vbb_pro_settings` (option) | Texto del botón CTA en navegación |
| `menuConfig.ctaButton.url` | Command Center (Navigation & Menu) | text | "/contacto" | `vbb_pro_settings` (option) | Enlace del botón CTA en navegación |
| `menuConfig.ctaButton.bgColor` | Command Center (Navigation & Menu) | color | "" | `vbb_pro_settings` (option) | Fondo del botón CTA |
| `menuConfig.ctaButton.textColor` | Command Center (Navigation & Menu) | color | "" | `vbb_pro_settings` (option) | Color de texto del botón CTA |
| `topBar.enabled` | Command Center (Navigation & Menu) | checkbox | false | `vbb_pro_settings` (option) | Renderiza la barra superior (`vbb_pro_top_bar_html`) |
| `topBar.info1.text` / `link` | Command Center (Navigation & Menu) | text | "Lun–Vie 9:00–18:00" | `vbb_pro_settings` (option) | Texto/enlace de información 1 en Top Bar |
| `topBar.info2.text` / `link` | Command Center (Navigation & Menu) | text | "hola@ejemplo.com" | `vbb_pro_settings` (option) | Texto/enlace de información 2 en Top Bar |
| `topBar.info3.text` / `link` | Command Center (Navigation & Menu) | text | "+54 11 5555-5555" | `vbb_pro_settings` (option) | Texto/enlace de información 3 en Top Bar |
| `topBar.socialFacebook` | Command Center (Navigation & Menu) | text | "" | `vbb_pro_settings` (option) | Icono y enlace social en Top Bar |
| `topBar.socialInstagram` | Command Center (Navigation & Menu) | text | "" | `vbb_pro_settings` (option) | Icono y enlace social en Top Bar |
| `topBar.socialLinkedin` | Command Center (Navigation & Menu) | text | "" | `vbb_pro_settings` (option) | Icono y enlace social en Top Bar |
| `topBar.bgColor` | Command Center (Navigation & Menu) | color | "#1a1a2e" | `vbb_pro_settings` (option) | Fondo de la Top Bar |
| `topBar.textColor` | Command Center (Navigation & Menu) | color | "#ffffff" | `vbb_pro_settings` (option) | Color de texto de la Top Bar |
| `palettes.light.*` (primary, secondary, accent, background, surface, text, mutedText) | Command Center (Colors) | color (hex) | (Definidos por vertical) | `vbb_pro_settings` (option) | Variables CSS `--vbb-pro-*` en `:root` |
| `palettes.dark.*` (primary, secondary, accent, background, surface, text, mutedText) | Command Center (Colors) | color (hex) | (Definidos por vertical) | `vbb_pro_settings` (option) | Variables CSS `--vbb-pro-*` en `html[data-theme="dark"]` |
| `typography.heading` | Command Center (Typography) | font dropdown | "Georgia..." | `vbb_pro_settings` (option) | `--vbb-pro-heading-font` / selectores `h1-h6` |
| `typography.body` | Command Center (Typography) | font dropdown | "Inter..." | `vbb_pro_settings` (option) | `--vbb-pro-body-font` / selector `body` |
| `layout.contentWidth` | Command Center (Layout) | text | "1180px" | `vbb_pro_settings` (option) | `--vbb-pro-content-width` |
| `layout.wideWidth` | Command Center (Layout) | text | "1440px" | `vbb_pro_settings` (option) | `--vbb-pro-wide-width` |
| `layout.radius` | Command Center (Layout) | text | "24px" | `vbb_pro_settings` (option) | `--vbb-pro-radius` |
| `layout.shadow` | Command Center (Layout) | select | "soft" (`none`, `soft`, `medium`, `strong`) | `vbb_pro_settings` (option) | `--vbb-pro-shadow` |
| `layout.spacingScale` | Command Center (Layout) | select | "comfortable" (`compact`, `comfortable`, `wide`) | `vbb_pro_settings` (option) | `--vbb-pro-section-spacing` |
| `buttons.style` | Command Center (Layout) | select | "pill" (`pill`, `rounded`, `square`, `outline`) | `vbb_pro_settings` (option) | `--vbb-pro-button-radius` / `.wp-block-button__link` |
| `buttons.uppercase` | Command Center (Layout) | checkbox | false | `vbb_pro_settings` (option) | `text-transform: uppercase` en botones |
| `blocks.*.enabled` | Command Center (Blocks) | checkbox | true | `vbb_pro_settings` / `vbb_pro_page_settings` | Script JS de ocultación y filtro `vbb_pro_filter_sections` |
| `blocks.*.style` | Command Center (Blocks) | select (A, B, C) | "A" | `vbb_pro_settings` | Clases CSS `vbb-style-a`, `vbb-style-b`, `vbb-style-c` |
| `blocks.*.colors.*` | Command Center (Blocks) | color | "" (hereda global) | `vbb_pro_settings` | Variables CSS scadas por bloque (`.vbb-section-{name}`) |
| `blocks.hero.*` (title, subtitle, eyebrow, primaryCta, primaryUrl, secondaryCta, secondaryUrl, image_id, image_url) | Command Center (Blocks) | text/media | (Desde vertical JSON) | `vbb_pro_settings` | Reemplazo de placeholders `{{vbb_hero_*}}` via `the_content` / `render_block` |
| `blocks.ctaFinal.*` (text, buttonText, buttonUrl, subtitle, secondaryCta, secondaryUrl) | Command Center (Blocks) | text | (Desde vertical JSON) | `vbb_pro_settings` | Reemplazo de placeholders `{{vbb_cta_final_*}}` |
| `blocks.contact.*` (email, phone, address) | Command Center (Blocks) | text | (Desde vertical JSON) | `vbb_pro_settings` | Reemplazo de placeholders `{{vbb_contact_*}}` |
| `blocks.*.heading` | Command Center (Blocks) | text | (Desde vertical JSON) | `vbb_pro_settings` | Reemplazo de placeholders `{{vbb_*_heading}}` |
| `footerConfig.column1.*` (logoUrl, description, socialFacebook, etc.) | Command Center (Footer) | text/media | "" | `vbb_pro_settings` | Renderizado en pattern de footer |
| `footerConfig.column2.*` (title, items[].text/url) | Command Center (Footer) | text repeatable | "" | `vbb_pro_settings` | Renderizado de enlaces en footer |
| `footerConfig.bottomBar.*` (copyright, button.*) | Command Center (Footer) | text | "" | `vbb_pro_settings` | Barra inferior del footer |
| `footerConfig.bgColor`, `textColor`, `linkColor`, `linkHoverColor`, `bottomBarBgColor` | Command Center (Footer) | color | "#1a1a2e" / etc. | `vbb_pro_settings` | Variables CSS scadas `.vbb-site-footer` |
| `menuItems` (Menu Editor) | Command Center (Menu Editor) | array (reorder/nested) | [] | `vbb_pro_settings` + sincronización a CPT `wp_navigation` | Sincronización automática de bloques de navegación en base de datos |

---

## B) SUPERFICIE DE RENDER (Inventory B)

Inventario de cada variable CSS y flag PHP que el frontend consume en `assets/`, `templates/`, `patterns/`, `parts/`:

| var o flag | archivo | efecto visual | quien lo escribe |
| :--- | :--- | :--- | :--- |
| `--vbb-pro-primary` | `pro-css-vars.php` (wp_head) | Color primario global del tema | `vbb_pro_print_css_vars()` desde paleta activa |
| `--vbb-pro-secondary` | `pro-css-vars.php` | Color secundario / acento de botones | `vbb_pro_print_css_vars()` |
| `--vbb-pro-accent` | `pro-css-vars.php` | Color de realce / bordes / eyebrow | `vbb_pro_print_css_vars()` |
| `--vbb-pro-background` | `pro-css-vars.php` | Color de fondo general del body y secciones | `vbb_pro_print_css_vars()` |
| `--vbb-pro-surface` | `pro-css-vars.php` | Color de superficies (tarjetas, contenedores) | `vbb_pro_print_css_vars()` |
| `--vbb-pro-text` | `pro-css-vars.php` | Color de texto principal y headings | `vbb_pro_print_css_vars()` |
| `--vbb-pro-muted-text` | `pro-css-vars.php` | Color de textos secundarios / párrafos | `vbb_pro_print_css_vars()` |
| `--vbb-pro-heading-font` | `pro-css-vars.php` | Familia tipográfica para títulos (`h1`-`h6`) | `vbb_pro_print_css_vars()` |
| `--vbb-pro-body-font` | `pro-css-vars.php` | Familia tipográfica para el cuerpo de texto | `vbb_pro_print_css_vars()` |
| `--vbb-pro-content-width` | `pro-css-vars.php` | Ancho máximo de contenido global | `vbb_pro_print_css_vars()` |
| `--vbb-pro-wide-width` | `pro-css-vars.php` | Ancho máximo de bloques anchos | `vbb_pro_print_css_vars()` |
| `--vbb-pro-radius` | `pro-css-vars.php` | Radio de bordes redondeados (`border-radius`) | `vbb_pro_print_css_vars()` |
| `--vbb-pro-shadow` | `pro-css-vars.php` | Sombra aplicada a tarjetas y elementos elevados | `vbb_pro_print_css_vars()` |
| `--vbb-pro-section-spacing` | `pro-css-vars.php` | Espaciado vertical interno de secciones | `vbb_pro_print_css_vars()` |
| `--vbb-pro-button-radius` | `pro-css-vars.php` | Radio de los botones según variante (pill/rounded/square) | `vbb_pro_print_css_vars()` |
| `--vbb-pro-{ckey}` (scoped) | `pro-css-vars.php` | Sobreescribe variables por bloque específico (`.vbb-section-{name}`) | `vbb_pro_block_scoped_css_vars()` |
| `--vbb-footer-*` | `pro-frontend.css` | Colores específicos del footer comercial | `vbb_pro_print_css_vars()` |
| `vbb-color-mode-{mode}` | `pro-settings.php` | Clase en `<body>` para alternar comportamiento dark mode | `vbb_pro_body_classes()` |
| `vbb-nav-{type}` | `pro-settings.php` | Clase en `<body>` (standard, hamburger, sticky) | `vbb_pro_body_classes()` |
| `vbb-nav-style-{style}` | `pro-settings.php` | Clase en `<body>` para efecto visual de menú (modern, minimal, classic, pill) | `vbb_pro_body_classes()` |
| `vbb-menu-{type}` | `pro-settings.php` | Oculta título o logo según `headerConfig.menuType` | `vbb_pro_apply_header_config()` |
| `vbb-style-{A/B/C}` | `patterns/*.php` | Cambia variante de diseño estructural en secciones (Hero, CTA, Testimonials) | Atributos de bloque / configuración de sección |
| `{{vbb_*}}` | `content-model.php` / `pro-settings.php` | Tokens de contenido dinámico (títulos, CTAs, contactos) | `vbb_pro_replace_dynamic_content()` |

---

## C) MATRIZ DE GAPS (Inventory C - El Corazón de la Auditoría)

### C1. Controles Muertos
Controles expuestos en el panel que escriben configuraciones o variables que el frontend nunca consume o ignora:
1. **`profileName`**: Se guarda en la opción pero no tiene ninguna representación visual ni en el frontend ni en el admin más allá de un texto estático en el Dashboard.
2. **`blocks.*.effect` (`fade`, `slide`, `zoom`)**: El panel de bloques permite configurar efectos de entrada para el Hero, pero `pro-frontend.css` y `vbb-effects.css` no aplican ninguna animación basada en este parámetro (las animaciones dependen exclusivamente de `_applyStaggerAnimation()` en JS o clases estáticas).

### C2. Puntos Ciegos
Estilos y elementos visuales del frontend que carecen por completo de control administrativo en el Command Center:
1. **Espaciados personalizados por sección (`paddingTop`, `paddingBottom`)**: El frontend renderiza espacios fijos con `--vbb-pro-section-spacing`, pero no hay control granular por bloque individual.
2. **Imágenes secundarias de servicios / equipo / galería**: Aunque existen modelos de contenido en el JSON de la vertical, el Command Center en su sección de bloques estándar no expopone selectores de medios individuales para cada ítem repetible (ej. fotos de miembros del equipo o iconos personalizados).
3. **Tipografías de elementos específicos (`h1` vs `h2` vs `h3`)**: El panel solo expone `heading` y `body`, sin permitir definir pesos de fuente (`font-weight`) o alturas de línea (`line-height`) individuales desde la UI.

### C3. Preview Mentiroso (Divergencias Preview vs Producción)
Divergencias críticas entre cómo el Command Center inyecta variables en el iframe de preview (`postMessage` con `vbb:css-vars`) y cómo el frontend real las imprime en producción (`vbb_pro_print_css_vars()`):
1. **Acumulación de estilos en Iframe**: El script receptor del preview (`vbb_pro_inject_preview_script`) acumula las reglas de CSS con `styleEl.textContent += '\n/* auto-merged */\n' + data.styleTag;`, lo que puede generar duplicación de selectores y conflictos de especificidad transitorios que no ocurren en un request real de producción donde `wp_head` emite una única etiqueta `<style>`.
2. **Filtros REST de Bloques FSE**: En el editor de bloques / FSE del Command Center, el filtro `render_block` salta en contexto administrativo (`is_admin() || wp_doing_ajax()`), mientras que en el frontend real se ejecuta mediante `the_content` (prioridad 99). Esto puede causar desincronizaciones en la renderización de tokens dinámicos si la plantilla FSE no pasa por el filtro de contenido.

### C4. Sin Modo de Customizar (Elementos Hardcodeados)
Elementos visibles en el frontend que están hardcodeados y sobre los cuales el usuario reporta que el preview "inventa" o no respeta sus cambios:
1. **Estructura de columnas en Grid de Servicios y Testimonios**: Las clases de distribución de columnas (`wp-block-columns`, `is-layout-grid`) están quemadas en el marcado PHP de los patterns (`patterns/services-grid.php`, etc.), por lo que el usuario no puede cambiar de 3 a 4 columnas desde el Command Center.
2. **Iconos de Redes Sociales en Top Bar / Footer**: Los iconos están mapeados estáticamente a letras o caracteres unicode en PHP (`'socialFacebook' => 'f'`, etc.), impidiendo cambiar el set de iconos o usar SVGs personalizados desde el panel.

---

## D) CICLO DE VIDA JSON (Inventory D)

1. **¿Qué incluye hoy el export/import (`vbb-pro-elite-import-export`) y qué deja fuera?**
   - *Incluye:* Objeto completo de configuración global (`settings`: paletas light/dark, tipografías, layout, botones, bloques, headerConfig, menuConfig, topBar, footerConfig) y sobreescrituras por página (`pageOverrides`).
   - *Deja fuera:* El CPT `wp_navigation` (menús sincronizados en base de datos), los adjuntos subidos a la Biblioteca de Medios (IDs de imágenes se rompen si las URLs absolutas cambian de entorno) y el estado de los perfiles guardados (`vbb_pro_saved_profiles`).

2. **Round-trip (Export -> Import):**
   - *¿Pierde datos?:* Sí, pierde los adjuntos físicos (IDs de la biblioteca de medios) si el dominio cambia, y los ítems del menú sincronizados en `wp_navigation` si no se reconstruyen.
   - *¿Merge vs Pisar?:* La importación global pisa `vbb_pro_settings` completamente, mientras que en `pageOverrides` hace un `vbb_pro_deep_merge` sobre las páginas existentes. Los cambios manuales post-import en ajustes globales se pierden si se vuelve a importar un JSON antiguo.

3. **Schema Versionado y Onboarding Externo:**
   - Actualmente existe una versión de esquema SemVer (`1.0.0` definida en `vbb_get_schema_version()`) y un archivo de validación JSON Schema (`config/schemas/vertical.schema.json`).
   - *Requisitos para el formulario externo de onboarding (Agency Hub):* El JSON externo que envíe un cliente desde el formulario de briefing debe cumplir estrictamente con los 7 campos raíz requeridos (`schemaVersion`, `verticalKey`, `name`, `brand`, `navigation`, `pages`, `contentModels`), asegurando que las URLs de imágenes sean absolutas y accesibles para que el pipeline de importación (`vbb_import_vertical_full`) pueda realizar el *sideloading* automático a la biblioteca de WordPress.

---

## E) UI/UX DEL ADMIN (Inventory E)

Problemas concretos detectados en el panel de administración (`admin-pro.css`, `admin-pro.js`, `pro-admin.php`):

| ID | Problema UI/UX | Ubicación | Severidad | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| **UX-01** | Desbordamiento en inputs de color y HEX | `admin-pro.css` / `admin-pro.js` | **High** | En pantallas medianas, los inputs de color nativos y los campos de texto HEX de la paleta tienden a ajustarse apretados, causando saltos de línea visuales. |
| **UX-02** | Falta de feedback inmediato en acciones largas | `admin-pro.js` (`regeneratePages`) | **Medium** | La regeneración masiva de páginas muestra un indicador genérico pero no detalla el progreso por página (útil para sitios grandes). |
| **UX-03** | Tarjetas colapsadas sin indicador visual claro | `admin-pro.js` / `admin-pro.css` | **Medium** | Aunque se implementaron minimizadas por defecto con un icono `▼`, el contraste del encabezado colapsado puede confundir al usuario sobre si es interactivo. |
| **UX-04** | Ausencia de validación en tiempo real para formato HEX | `admin-pro.js` | **Low** | Si el usuario introduce un código HEX inválido de 5 caracteres, el sistema aplica un fallback automático silencioso sin avisar visualmente al usuario. |
| **UX-05** | Comportamiento del Iframe en redimensionamiento de dispositivos | `admin-pro.js` (`_onPresetChange`) | **Low** | Los botones Desktop/Tablet/Mobile ajustan el ancho del contenedor del iframe, pero en algunos navegadores de administración de WordPress generan scroll horizontal en la página de ajustes. |

---

## EXECUTIVE SUMMARY

1. **Top 10 Gaps por Severidad:**
   - [BLOCKER] Dependencia de URLs absolutas en imágenes de importación (rompe assets en migración).
   - [BLOCKER] Sincronización incompleta del CPT `wp_navigation` durante la importación JSON pura.
   - [HIGH] Controles muertos (`profileName`, selectores de efectos de bloque `effect`).
   - [HIGH] Puntos ciegos de estilos granulares por bloque (espaciados y sub-elementos).
   - [HIGH] Divergencias potenciales entre la inyección por `postMessage` en el preview y el render real en producción (`wp_head`).
   - [MEDIUM] Elementos estructurales de grid/columnas hardcodeados en los PHP patterns que el usuario no puede alterar desde el admin.
   - [MEDIUM] Falta de soporte en el import/export para empaquetar adjuntos físicos de la galería de medios.
   - [MEDIUM] Feedback visual mejorable en acciones asíncronas de guardado múltiple (global + page-specific).
   - [LOW] Redimensionamiento del viewport del preview susceptible a scroll horizontal en admin antiguo.
   - [LOW] Validación silenciosa de códigos HEX incompletos en inputs de color.

2. **% de Controles Muertos:** ~4% (2 de ~50 controles principales: nombre de perfil y selector de efectos visuales sin binding CSS).
3. **% de Frontend sin Control:** ~25% (Diseños de columnas internos, variantes de espaciado fino y detalles tipográficos avanzados de párrafos/headings secundarios).
4. **Estado del Round-Trip JSON:** **Parcial** (Funciona perfectamente para la estructura de opciones globales, tipografías, colores y metadatos de bloques; sin embargo, es **roto/incompleto** respecto a la portabilidad de adjuntos de medios locales y menús de navegación avanzados si no se ejecuta el pipeline completo de importación de verticales del Hub).

---
*Fin del reporte de auditoría de market-readiness guardado en `.project/audits/market-readiness-audit.md`.*