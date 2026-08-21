# Pending Items Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clear the three pending items: remove scratch file, fix the 6 pre-existing baker test failures, and implement the approved legal-dark-5 dark-mode vertical (spec: `docs/superpowers/specs/2026-08-21-legal-dark-5-design.md`).

**Architecture:** Two small PHP changes (heading placeholder tokens emitted by 4 bakers; `brand.colorMode`/`brand.palettes` support in settings defaults) plus one new vertical JSON config. All tests are standalone PHP harnesses (`inc/test-block-baker.php`, `inc/test-orkestone-engine.php`) run with `php <file>` — no PHPUnit.

**Tech Stack:** WordPress theme PHP 7.4+, standalone PHP test harnesses, JSON vertical configs.

## Global Constraints

- Conventional Commits, no AI attribution in commits.
- No comments added beyond what mirrors existing docblock style.
- Dark palette values (from spec, verbatim): background `#0B1220`, surface `#121C2E`, text `#EAEFF7`, mutedText `#9AA7BC`, primary `#E8D9A8`, secondary `#C9A227`, accent `#16233A`.
- Light palette values: background `#FFFFFF`, surface `#F5F2EA`, text `#141E30`, mutedText `#667085`, primary `#0F1B2D`, secondary `#C9A227`, accent `#F4F1EC`.
- Typography: headings `Georgia`, body `Inter`.
- Vertical key: `legal-dark-5`; demo content in Spanish.
- Baseline test status before this plan: `test-block-baker.php` 125/131 (6 known failures), `test-orkestone-engine.php` 99/99.

---

### Task 1: Remove scratch debug script

**Files:**
- Delete: `check_page.php` (repo root)

- [ ] **Step 1: Confirm it is untracked and delete it**

```bash
git status --short check_page.php
Remove-Item -LiteralPath check_page.php
```

Expected: `?? check_page.php` then file gone.

- [ ] **Step 2: Commit**

Nothing to commit (file was untracked). Skip commit.

---

### Task 2: Fix 6 baker test failures (heading placeholders, logo subtitle, column-count needle)

**Files:**
- Modify: `orkestone-theme/inc/block-baker.php` (functions `vbb_bake_process` ~L516, `vbb_bake_logo_cloud` ~L1047, `vbb_bake_pricing_tables` ~L1098, `vbb_bake_team_section` ~L1173)
- Modify: `orkestone-theme/inc/test-block-baker.php` (~L572, column-count assertion)

**Interfaces:**
- Consumes: token replacement map in `pro-settings.php` (`{{vbb_process_heading}}`, `{{vbb_pricing_heading}}`, `{{vbb_team_heading}}`, `{{vbb_logo_cloud_heading}}` already defined at L866-869).
- Produces: bakers emit heading tokens unconditionally, same pattern as `vbb_bake_services_grid` (L345: `$heading = '{{vbb_services_heading}}';`).

Rationale: `services_grid`, `benefits`, `testimonials`, `faq` emit `{{vbb_*_heading}}` tokens resolved at render time from Command Center settings (which seed themselves from vertical JSON headings). The 4 failing bakers inline `$data['heading']` instead — inconsistent with the pipeline.

- [ ] **Step 1: Run tests to confirm the 6 failures**

Run: `php orkestone-theme\inc\test-block-baker.php | Select-String "Results:"`
Expected: `Results: 125/131 passed, 6/131 failed`

- [ ] **Step 2: Fix `vbb_bake_process` heading emission**

In `vbb_bake_process`, replace:

```php
	$heading = isset( $data['heading'] ) ? esc_html( $data['heading'] ) : '';
```

with:

```php
	$heading = '{{vbb_process_heading}}';
```

and replace the conditional block:

```php
	if ( $heading ) {
		$html .= '<!-- wp:heading {"className":"vbb-section-title"} -->';
		$html .= '<h2 class="vbb-section-title">' . $heading . '</h2>';
		$html .= '<!-- /wp:heading -->';
	}
```

with unconditional emission:

```php
	$html .= '<!-- wp:heading {"className":"vbb-section-title"} -->';
	$html .= '<h2 class="vbb-section-title">' . $heading . '</h2>';
	$html .= '<!-- /wp:heading -->';
```

- [ ] **Step 3: Fix `vbb_bake_logo_cloud` heading + add subtitle**

Same transformation as Step 2 using `$heading = '{{vbb_logo_cloud_heading}}';`. Then, right after the heading emission lines, add subtitle support:

```php
	$subtitle = isset( $data['subtitle'] ) ? esc_html( $data['subtitle'] ) : '';
	if ( $subtitle ) {
		$html .= '<!-- wp:paragraph {"align":"center","className":"vbb-logo-cloud-subtitle"} -->';
		$html .= '<p class="has-text-align-center vbb-logo-cloud-subtitle">' . $subtitle . '</p>';
		$html .= '<!-- /wp:paragraph -->';
	}
```

- [ ] **Step 4: Fix `vbb_bake_pricing_tables` and `vbb_bake_team_section` headings**

Apply the same transformation in both: pricing uses `$heading = '{{vbb_pricing_heading}}';`, team uses `$heading = '{{vbb_team_heading}}';`, each emitting its heading block unconditionally.

- [ ] **Step 5: Fix the column-count test needle**

In `test-block-baker.php` (~L572), replace:

```php
		$count = substr_count( $output, '<!-- wp:column -->' );
```

with:

```php
		$count = substr_count( $output, '<!-- wp:column' );
```

(The baker emits `<!-- wp:column {"className":"vbb-process-step"} -->`; the old needle never matched.)

- [ ] **Step 6: Run tests to verify all pass**

Run: `php orkestone-theme\inc\test-block-baker.php | Select-String "Results:"`
Expected: `Results: 131/131 passed, 0/131 failed`

Also run the engine suite to catch cross-effects:

Run: `php orkestone-theme\inc\test-orkestone-engine.php | Select-String "Results:"`
Expected: `Results: 99/99 passed, 0/99 failed`

- [ ] **Step 7: Commit**

```bash
git add orkestone-theme/inc/block-baker.php orkestone-theme/inc/test-block-baker.php
git commit -m "fix(baker): emit heading placeholder tokens in process, logo cloud, pricing and team sections"
```

---

### Task 3: Support `brand.colorMode` and `brand.palettes` in settings defaults

**Files:**
- Modify: `orkestone-theme/inc/pro-settings.php` (`vbb_pro_default_settings()`, L146-391)
- Modify: `orkestone-theme/inc/test-orkestone-engine.php` (append new test section)

**Interfaces:**
- Consumes: `$config['brand']` via existing `vbb_get_vertical_config()`; sanitizer whitelist for `colorMode` already exists at L432; palette hex sanitization loop already exists at L519-520.
- Produces: defaults where `colorMode` reflects `brand.colorMode` (`light|dark|auto`) and both palettes deep-merge `brand.palettes.light` / `brand.palettes.dark` over built-in defaults. Verticals without these keys produce identical output to today.

- [ ] **Step 1: Write the failing tests**

Append before the final results block of `test-orkestone-engine.php` (locate `Results:` summary at end of file; insert above it):

```php
echo "\n=== Brand colorMode and palettes in defaults ===\n";
if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		return ( is_string( $color ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ) ? $color : '';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $default;
	}
}
require_once __DIR__ . '/pro-settings.php';

// Dark-by-default vertical resolves dark mode and custom dark palette.
$GLOBALS['vbb_test_vertical_config'] = array(
	'verticalKey' => 'legal-dark-5',
	'pages'       => array(),
	'sections'    => array(),
	'navigation'  => array( 'primary' => array() ),
	'importOptions' => array( 'homepageKey' => 'home', 'setFrontPage' => false ),
	'brand'       => array(
		'colorMode' => 'dark',
		'palettes'  => array(
			'dark' => array(
				'background' => '#0B1220',
				'surface'    => '#121C2E',
				'text'       => '#EAEFF7',
				'mutedText'  => '#9AA7BC',
				'primary'    => '#E8D9A8',
				'secondary'  => '#C9A227',
				'accent'     => '#16233A',
			),
		),
	),
);
$s = vbb_pro_default_settings();
assert_true( 'dark' === $s['colorMode'], 'brand.colorMode=dark propagates to defaults' );
assert_equals( '#0B1220', $s['palettes']['dark']['background'], 'custom dark background overrides default' );
assert_equals( '#EAEFF7', $s['palettes']['dark']['text'], 'custom dark text overrides default' );
assert_true( '' !== $s['palettes']['dark']['mutedText'], 'non-overridden dark keys still resolve' );

// Vertical without brand keys keeps today's behavior.
$GLOBALS['vbb_test_vertical_config'] = array(
	'verticalKey' => 'plain',
	'pages'       => array(),
	'sections'    => array(),
	'navigation'  => array( 'primary' => array() ),
	'importOptions' => array( 'homepageKey' => 'home', 'setFrontPage' => false ),
);
$s2 = vbb_pro_default_settings();
assert_true( 'light' === $s2['colorMode'], 'missing brand.colorMode defaults to light' );

// Restore original config for any subsequent assertions.
unset( $GLOBALS['vbb_test_vertical_config'] );
```

Note: the harness helpers are `assert_true()`, `assert_equals()` (already used above); insert the new section after the last test section, before the final results summary.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php orkestone-theme\inc\test-orkestone-engine.php | Select-String "Results:"`
Expected: FAIL count > 0 (the new checks fail because `colorMode` is hardcoded `'light'` and palettes ignore `brand`).

If requiring `pro-settings.php` triggers undefined-function errors, add the minimal conditional stubs following the file's existing pattern until the harness runs.

- [ ] **Step 3: Implement in `vbb_pro_default_settings()`**

After L153 (`$light_background = ...`), add:

```php
	$brand_color_mode = isset( $brand['colorMode'] ) ? (string) $brand['colorMode'] : 'light';
	$brand_palettes   = isset( $brand['palettes'] ) && is_array( $brand['palettes'] ) ? $brand['palettes'] : array();
	$dark_defaults    = array(
		'primary'    => '#F4E6C8',
		'secondary'  => $light_secondary,
		'accent'     => '#1E2A3A',
		'background' => '#0F1724',
		'surface'    => '#152033',
		'text'       => '#E7EDF5',
		'mutedText'  => '#A8B3C4',
	);
```

Replace the `'colorMode'   => 'light',` entry (~L269) with:

```php
		'colorMode'   => in_array( $brand_color_mode, array( 'light', 'dark', 'auto' ), true ) ? $brand_color_mode : 'light',
```

Replace the `'palettes'` array (~L346-366) with:

```php
		'palettes'    => array(

			'light' => array_merge(
				array(
					'primary'    => $light_primary,
					'secondary'  => $light_secondary,
					'accent'     => $light_accent,
					'background' => $light_background,
					'surface'    => '#F7F3ED',
					'text'       => '#172033',
					'mutedText'  => '#667085',
				),
				isset( $brand_palettes['light'] ) && is_array( $brand_palettes['light'] ) ? $brand_palettes['light'] : array()
			),
			'dark'  => array_merge(
				$dark_defaults,
				isset( $brand_palettes['dark'] ) && is_array( $brand_palettes['dark'] ) ? $brand_palettes['dark'] : array()
			),
		),
```

Sanitization downstream needs no change: `sanitize_key`-style whitelist handles `colorMode` (L432) and `vbb_pro_sanitize_hex` covers every palette key (L519-520).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php orkestone-theme\inc\test-orkestone-engine.php | Select-String "Results:"`
Expected: all pass including the new section.

Run: `php -l orkestone-theme\inc\pro-settings.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add orkestone-theme/inc/pro-settings.php orkestone-theme/inc/test-orkestone-engine.php
git commit -m "feat(settings): honor brand.colorMode and brand.palettes from vertical JSON defaults"
```

---

### Task 4: Create the legal-dark-5 vertical JSON

**Files:**
- Create: `orkestone-theme/config/verticals/legal-dark-5.json`

**Interfaces:**
- Consumes: Task 3's `brand.colorMode` / `brand.palettes`; existing bakers (`hero`, `logo_cloud`, `problem`, `benefits`, `process`, `stats`, `testimonials`, `faq`, `cta-final`, `hero-centered`, `contact-section`).
- Produces: importable vertical validated by `vbb_validate_vertical_config`.

- [ ] **Step 1: Write the JSON file**

```json
{
  "schemaVersion": "1.0.0",
  "verticalKey": "legal-dark-5",
  "name": "Legal Dark 5.0",
  "description": "Estudio jurídico premium en modo oscuro: elegancia, autoridad y resultados.",
  "brand": {
    "siteName": "Legal 5.0",
    "tagline": "Estrategia Legal de Alto Impacto",
    "colorMode": "dark",
    "palettes": {
      "light": {
        "primary": "#0F1B2D",
        "secondary": "#C9A227",
        "accent": "#F4F1EC",
        "background": "#FFFFFF",
        "surface": "#F5F2EA",
        "text": "#141E30",
        "mutedText": "#667085"
      },
      "dark": {
        "primary": "#E8D9A8",
        "secondary": "#C9A227",
        "accent": "#16233A",
        "background": "#0B1220",
        "surface": "#121C2E",
        "text": "#EAEFF7",
        "mutedText": "#9AA7BC"
      }
    },
    "fontHeading": "Georgia",
    "fontBody": "Inter"
  },
  "navigation": {
    "primary": [
      { "label": "Inicio", "url_slug": "inicio" },
      { "label": "Servicios", "url_slug": "servicios" },
      { "label": "Nosotros", "url_slug": "nosotros" },
      { "label": "Contacto", "url_slug": "contacto" }
    ]
  },
  "pages": [
    {
      "key": "home",
      "title": "Inicio",
      "slug": "inicio",
      "template": "front-page",
      "sections": ["hero", "logo_cloud", "problem", "benefits", "process", "stats", "testimonials", "faq", "cta-final"],
      "hero": {
        "eyebrow": "Legal 5.0 · Estudio Jurídico",
        "title": "Defensa Legal Estratégica Cuando Más Importa",
        "subtitle": "Combinamos trayectoria, precisión y tecnología para proteger sus intereses con resultados medibles.",
        "primaryCta": "Consulta Confidencial",
        "primaryUrl": "/contacto",
        "image_url": ""
      },
      "logo_cloud": {
        "heading": "Confían en Nosotros",
        "subtitle": "Empresas y particulares que eligieron nuestra defensa",
        "items": [
          { "name": "Grupo Andes", "logo": "" },
          { "name": "TechNova SA", "logo": "" },
          { "name": "Estudio Meridiano", "logo": "" },
          { "name": "Finanzas del Sur", "logo": "" }
        ]
      },
      "stats": {
        "items": [
          { "value": "20+", "label": "Años de Experiencia", "icon": "awards", "description": "Trayectoria en derecho corporativo y civil" },
          { "value": "92%", "label": "Casos Favorables", "icon": "yes", "description": "Resoluciones exitosas en litigios" },
          { "value": "800+", "label": "Clientes Asistidos", "icon": "groups", "description": "Empresas y particulares representados" },
          { "value": "24/7", "label": "Disponibilidad", "icon": "clock", "description": "Atención ante urgencias legales" }
        ]
      }
    },
    {
      "key": "services",
      "title": "Servicios Legales",
      "slug": "servicios",
      "template": "page",
      "sections": ["hero-centered", "benefits", "cta-final"],
      "hero-centered": {
        "title": "Especialidades Jurídicas",
        "subtitle": "Asesoría integral en las áreas críticas del derecho moderno."
      }
    },
    {
      "key": "about",
      "title": "Sobre el Estudio",
      "slug": "nosotros",
      "template": "page",
      "sections": ["hero-centered", "process", "stats"],
      "hero-centered": {
        "title": "Compromiso, Ética y Resultados",
        "subtitle": "Dos décadas transformando la práctica legal con estándares de élite."
      }
    },
    {
      "key": "contact",
      "title": "Contacto",
      "slug": "contacto",
      "template": "page",
      "sections": ["hero-centered", "contact-section"],
      "hero-centered": {
        "title": "Hablemos de su Caso",
        "subtitle": "Primera consulta confidencial sin cargo. Respondemos en menos de 24 horas."
      }
    }
  ],
  "sections": {
    "problem": {
      "eyebrow": "El Problema",
      "title": "Un Error Legal Puede Costar Décadas",
      "description": "Los casos mal manejados desde el inicio se vuelven irreversibles: plazos perdidos, pruebas inadmisibles y acuerdos desfavorables.",
      "solution": "Nuestro equipo interviene desde el primer día con una estrategia diseñada a medida, auditoría completa del caso y comunicación transparente en cada instancia."
    },
    "benefits": {
      "heading": "Por Qué Elegirnos",
      "items": [
        {
          "icon": "lock",
          "title": "Confidencialidad Absoluta",
          "description": "Cada caso se maneja bajo estricto secreto profesional desde la primera consulta."
        },
        {
          "icon": "awards",
          "title": "Trayectoria Comprobable",
          "description": "Más de 20 años litigando en derecho corporativo y civil con tasa de éxito superior al 90%."
        },
        {
          "icon": "calculator",
          "title": "Honorarios Transparentes",
          "description": "Esquemas claros desde el inicio: cuotas fijas o porcentaje por éxito, sin sorpresas."
        }
      ]
    },
    "process": {
      "heading": "Cómo Trabajamos",
      "steps": [
        { "number": "1", "title": "Consulta Inicial", "description": "Analizamos su caso en profundidad y evaluamos todas las variables." },
        { "number": "2", "title": "Estrategia", "description": "Diseñamos la hoja de ruta legal más eficiente para su objetivo." },
        { "number": "3", "title": "Ejecución", "description": "Representación activa y defensa rigurosa en todas las instancias." }
      ]
    },
    "testimonials": {
      "heading": "Lo Que Dicen Nuestros Clientes",
      "items": [
        { "quote": "Resolvieron en tres meses un conflicto corporativo que llevaba años estancado.", "author": "M. Fernández", "role": "CEO, TechNova SA" },
        { "quote": "Profesionalismo absoluto. Siempre supimos qué estaba pasando con nuestro caso.", "author": "L. Gutiérrez", "role": "Directora, Grupo Andes" }
      ]
    },
    "faq": {
      "heading": "Preguntas Frecuentes",
      "items": [
        { "q": "¿Cómo se calculan los honorarios?", "a": "Ofrecemos cuotas fijas por etapa o porcentaje por éxito. Todo queda acordado por escrito antes de comenzar." },
        { "q": "¿Atienden casos en otras provincias?", "a": "Sí, contamos con corresponsales y capacidad de litigio remoto en todo el país." }
      ]
    }
  },
  "contentModels": {
    "service": {
      "label": "Servicios",
      "singular": "Servicio",
      "items": [
        { "title": "Derecho Corporativo", "summary": "Constitución de sociedades, fusiones, adquisiciones y gobernanza empresarial.", "icon": "briefcase", "ctaText": "Saber más", "ctaUrl": "/servicios" },
        { "title": "Litigio Civil", "summary": "Resolución de conflictos contractuales, propiedad y responsabilidad civil.", "icon": "scale", "ctaText": "Saber más", "ctaUrl": "/servicios" },
        { "title": "Propiedad Intelectual", "summary": "Registro de marcas, patentes y protección de activos intangibles.", "icon": "shield", "ctaText": "Saber más", "ctaUrl": "/servicios" }
      ]
    }
  },
  "cta": {
    "final": {
      "text": "¿Necesita una defensa legal de primer nivel?",
      "buttonText": "Agendar Consulta",
      "buttonUrl": "/contacto"
    }
  },
  "contact": {
    "email": "consultas@legal50.com",
    "phone": "+54 11 5000-2020"
  },
  "seoDefaults": {
    "titlePattern": "%page% | Legal 5.0",
    "metaDescription": "Legal 5.0: estudio jurídico premium especializado en derecho corporativo y civil."
  }
}
```

Note: stats data placement follows `legales-5.json` (page-level under home). If the stats baker reads page-level `stats` differently at validation/bake time, mirror exactly how `legales-5.json` places it (it lives inside the `home` page object as `"stats": { "items": [...] }`). Verify against `legales-5.json` L39-66 and adjust: move stats into the `home` page object as `"stats": { "items": [...] }` rather than root level if that is what the importer expects.

- [ ] **Step 2: Validate against the vertical validator**

Run:

```bash
php -r "require 'orkestone-theme/inc/helpers.php'; require 'orkestone-theme/inc/vertical-validator.php'; var_dump(vbb_validate_vertical_config(json_decode(file_get_contents('orkestone-theme/config/verticals/legal-dark-5.json'), true)));"
```

Expected: `bool(true)`

Also verify JSON syntax strictly:

Run: `php -r "json_decode(file_get_contents('orkestone-theme/config/verticals/legal-dark-5.json')); echo json_last_error_msg() . PHP_EOL;"`
Expected: `No error`

- [ ] **Step 3: Commit**

```bash
git add orkestone-theme/config/verticals/legal-dark-5.json
git commit -m "feat(verticals): add legal-dark-5 dark-mode law firm demo vertical"
```

---

### Task 5: Final full-suite verification

- [ ] **Step 1: Lint all touched PHP files**

```bash
php -l orkestone-theme/inc/block-baker.php
php -l orkestone-theme/inc/pro-settings.php
php -l orkestone-theme/inc/test-orkestone-engine.php
php -l orkestone-theme/inc/test-block-baker.php
```

Expected: `No syntax errors detected` × 4.

- [ ] **Step 2: Run both suites**

```bash
php orkestone-theme/inc/test-block-baker.php | Select-String "Results:"
php orkestone-theme/inc/test-orkestone-engine.php | Select-String "Results:"
```

Expected: `131/131` and `99 + new checks` all passing.

- [ ] **Step 3: Commit any remaining fixes and report**

If anything fails, fix before declaring done. Do not deploy to VPS in this plan — deployment is a separate user-approved step.
