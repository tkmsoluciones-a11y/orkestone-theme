# Hero Style D — Specificación Completa

**Change ID**: `hero-style-d`
**Component**: `[PWP]` (Panel SaaS - Frontend) + `[API]` (Backend para vertical JSON)
**Fecha**: 2026-08-20
**Estado**: `PROPOSED`

---

## 1. Contexto y Objetivo

Crear **Hero Style D** — una plantilla de hero completa y configurable inspirada en:
- **Squarespace Goldstein Mehta LLC** (referencia legal): hero full-bleed con badge strip, secciones alternadas, grid de practice areas, blog carousel, formulario + mapa
- **Kaleidoscope** (video production portfolio): estética dark premium, acento dorado, tipografía editorial, motion sutil

**Objetivo**: Todos los elementos editables en **Command Center** y exportables como **vertical JSON** para deploy multi-tenant.

---

## 2. Alcance (In-Scope)

### 2.1 Bloques Nuevos / Variantes

| Bloque | Tipo | Descripción |
|--------|------|-------------|
| `hero-style-d` | `hero` | Hero full-bleed con background image/video, overlay, headline destacado, dual CTA |
| `trust-badges-strip` | `strip` | Banda full-width de badges/logos (imagen única o grid) |
| `alternating-section` | `section` | Sección 50/50 imagen + texto (izq/der alternable) |
| `practice-grid` | `grid` | Grid 4×3 de cards poster circulares con overlay button |
| `blog-carousel` | `carousel` | Carousel de posts (summary block) 3 slides, thumbnail+meta+excerpt |
| `contact-form-map` | `composite` | Formulario (name, email, phone, message) + mapa Google Maps + BBB badge |
| `header-config` | `header` | Config header transparente: logo, nav folders, CTA, mobile hamburger |
| `color-palette-dark` | `theme` | Paleta dark: black, white, accent-gold, darkAccent |

### 2.2 Command Center Fields

Cada bloque expone campos en el panel:
- **Hero**: background (image/video), overlay opacity, headline, subhead, highlight ranges, CTA1/2 (text, href, variant), section height
- **Trust Badges**: image(s), alt text, link URLs, strip height
- **Alternating**: headline, body, image, image position (left/right), caption, quote block opcional
- **Practice Grid**: array de items {image, title, href, button text, aspect ratio}
- **Blog Carousel**: collection ID, posts to show, layout (carousel/grid), meta fields
- **Contact Form**: form fields config, success message, map lat/lng/zoom, BBB embed code
- **Header**: logo, nav structure (folders/links), CTA button, mobile layout, overlay theme
- **Colors**: 8-color palette (primary, secondary, accent, darkAccent, black, white, muted, success/warning/error)

### 2.3 Vertical JSON Schema

Extend `tkm-soluciones-5.json` (o nuevo vertical) con:
```json
{
  "heroStyle": "d",
  "hero": { ... },
  "trustBadges": { ... },
  "alternatingSections": [ ... ],
  "practiceGrid": { ... },
  "blogCarousel": { ... },
  "contactFormMap": { ... },
  "header": { ... },
  "colorPalette": { ... }
}
```

---

## 3. Fuera de Alcance (Out-of-Scope)

- Backend CMS para gestionar posts/blog (usa collection existente)
- Autenticación / usuarios / multi-tenant auth (ya existe en API)
- Server-side rendering del hero (es client-side con JSON hidratado)
- Animaciones complejas GSAP (solo CSS transitions + IntersectionObserver básico)

---

## 4. Requisitos Funcionales

### RF-01: Hero Style D
- **Background**: Imagen (responsive srcset) O video (MP4/WebM, autoplay mute loop)
- **Overlay**: Opacidad 0–1 (default 0), color configurable (default `hsla(var(--black-hsl), 0.4)`)
- **Headline**: H1 centrado, soporte `highlight` ranges con color accent (como Squarespace `sqsrte-text-highlight`)
- **Subhead**: Opcional, debajo del headline
- **CTAs**: 2 botones (primary + tertiary/outline), texto + href (tel:, mailto:, /contact, #anchor)
- **Altura**: `section-height--large` + `customSectionHeight` (default 85vh)
- **Alineación**: Center/center (horizontal/vertical)

### RF-02: Trust Badges Strip
- Imagen única full-width (aspect ratio ~5:1) O grid de logos
- Altura fija pequeña (`section-height--small`)
- Sin overlay, sin padding vertical excesivo
- Link opcional por badge (si grid) o imagen completa clickeable

### RF-03: Alternating Sections
- Layout: `content-width--wide`, `horizontal-alignment--center`
- Imagen: `design-layout-inline` o `poster` (circular clip-path)
- Texto: H2 + párrafos + quote block opcional
- Alternar `float-right` / `float-left` automático por índice
- Separadores horizontales (`<hr>`) entre secciones

### RF-04: Practice Areas Grid
- 12 items (configurable 6–12)
- Card: `design-layout-poster`, `image-position-left`, `image-linked`
- Clip-path circular (`clipPath` SVG 50% radius)
- Overlay oscuro + botón primario centrado (texto + href)
- Hover: scale 1.02, overlay opacity transition
- Responsive: 4 cols (≥1024), 3 cols (≥768), 2 cols (≥480), 1 col (<480)

### RF-05: Blog Carousel
- Summary block v2, `design=carousel`, `slidesPerRow=3`
- Thumbnail aspect 1.5 (3:2), thumbnail size medium
- Meta: fecha primaria, categorías secundaria
- Excerpt clampped 3 líneas, "Read more →" link
- Auto-play opcional (default off), pause on hover

### RF-06: Contact Form + Map
- Form: Name (req), Email (req), Phone (opt), Message (req, textarea)
- Submit: "Get Help Now" (tertiary button), reCAPTCHA v3
- Success message configurable
- Map: lat/lng/zoom, marker, labels, style (roadmap/satellite)
- BBB badge: embed code configurable (iframe/img)

### RF-07: Header Config
- `headerStyle: "dynamic"` (transparent → solid on scroll)
- `tweak-transparent-header: true`
- Layout desktop: `navRight` (logo left, nav center, CTA right)
- Layout mobile: `logoRightNavLeft` (hamburger left, logo right)
- Nav: folders con sub-items (aria-expanded, keyboard nav)
- CTA: button variant `primary-inverse` (border white, text white)
- Menu overlay: `dark-bold`, animation `fade`

### RF-08: Color Palette Dark
```css
:root {
  --black: #000000;
  --white: #FFFFFF;
  --accent: #D4A843;      /* Gold */
  --darkAccent: #1A1A1A;  /* Near black */
  --primary: var(--accent);
  --secondary: var(--white);
  --muted: #6B6B6B;
  --success: #2E7D32;
  --warning: #F57F17;
  --error: #C62828;
}
```
- Variables CSS en `:root` + `[data-section-theme="dark"]` overrides
- Command Center: color pickers para cada token

---

## 5. Requisitos No Funcionales

| RNF | Detalle |
|-----|---------|
| **Performance** | Hero image: `fetchpriority="high"`, `loading="eager"`, WebP/AVIF via CDN. Lazy-load resto. |
| **Accesibilidad** | WCAG 2.2 AA: contrast ratios, focus visible, ARIA labels, keyboard nav, `prefers-reduced-motion` |
| **Responsive** | Breakpoints: 480, 768, 1024, 1400px. Mobile-first CSS. |
| **SEO** | Schema.org `WebSite`, `Organization`, `LocalBusiness`, `Person` (attorneys) en JSON-LD |
| **i18n** | Todos los strings en vertical JSON, claves `es_AR`, `en_US` |
| **Export** | Vertical JSON válido contra schema JSON (validación en `sdd-verify`) |

---

## 6. Casos de Uso / Escenarios

### CU-01: Configurar Hero para "Abogados Penalistas"
1. Usuario abre Command Center → Hero Style D
2. Sube background video (MP4) + poster image
3. Escribe headline: "Defensa Penal Experta en Buenos Aires"
4. Marca "Experta" como highlight (accent gold)
5. CTA1: "Llamar Ya" → `tel:+5411XXXXXXXX`
6. CTA2: "Consulta Gratis" → `/contacto`
7. Guarda → preview en iframe → publica

### CU-02: Agregar Practice Area "Cibercrimen"
1. Command Center → Practice Grid → "Agregar item"
2. Sube imagen circular, título "Cibercrimen", URL `/cibercrimen`
3. Botón: "Ver detalles"
4. Reordena drag-and-drop → guarda

### CU-03: Exportar Vertical para Cliente "Estudio López"
1. Command Center → "Exportar Vertical"
2. Descarga `estudio-lopez.json`
3. Deploy en nueva instancia → `import-vertical` → listo

---

## 7. Esquema Vertical JSON (Referencia)

Ver archivo adjunto: `vertical-schema.json`

---

## 8. Criterios de Aceptación (Definition of Done)

- [ ] Spec aprobada por stakeholder
- [ ] Design document completa (arquitectura, data flow, CSS architecture)
- [ ] Tasks desglosadas ≤ 2h c/u
- [ ] Implementación en `block-registry.php`, `block-baker.php`, `pro-frontend.css`, `admin-pro.js`
- [ ] Vertical JSON schema validado con `ajv`
- [ ] `sdd-verify`: Playwright tests (hero render, CTA clicks, form submit, carousel nav, mobile menu)
- [ ] Accesibilidad: axe-core 0 violations
- [ ] Performance: Lighthouse ≥ 90 (Performance, Accessibility, Best Practices, SEO)
- [ ] Documentación actualizada en `.project/docs/hero-style-d.md`
- [ ] Archivado en `openspec/changes/archive/YYYY-MM-DD-hero-style-d/`

---

## 9. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Video background pesado | Alta | Performance | Poster image obligatorio, video ≤ 5MB, `preload="metadata"` |
| Clip-path circular no Safari | Media | Visual | Fallback `border-radius: 50%` en `@supports not (clip-path: ...)` |
| JSON vertical muy grande | Baja | Deploy | Compresión gzip, lazy-load sections no críticas |
| Command Center fields complejos | Media | UX | Field groups colapsables, tooltips, preview en vivo |

---

## 10. Referencias

- Squarespace HTML: `goldsteinmehta.com` (adjunto en prompt)
- Figma Kaleidoscope: [link interno]
- Orkestone block registry: `orkestone-theme/inc/block-registry.php`
- Command Center: `orkestone-theme/assets/js/admin-pro.js`
- Vertical schema: `orkestone-theme/config/verticals/tkm-soluciones-5.json`
- ADRs relevantes: `GOVERNANCE.md` → `ADR-001` (Component segregation), `ADR-007` (Vertical JSON)