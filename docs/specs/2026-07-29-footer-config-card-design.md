# Footer Config Card — Diseño

## Resumen

Agregar una card "Footer" en el Command Center que permita configurar el contenido, estructura y colores del pie de página del sitio, manteniendo un layout de 2 columnas + barra inferior.

## Arquitectura

### Settings: `footerConfig`

Se agrega una nueva clave `footerConfig` en el objeto de settings, al mismo nivel que `headerConfig`, `menuConfig` y `topBar`.

```jsonc
{
  "footerConfig": {
    // Columna 1 — Brand
    "column1": {
      "logoUrl": "",                    // URL del logo (media library)
      "description": "",                // Texto descriptivo
      "socialFacebook": "",
      "socialInstagram": "",
      "socialLinkedin": "",
      "socialTwitter": ""
    },
    // Columna 2 — Links
    "column2": {
      "title": "",                      // Título de sección (ej. "Acceso rápido")
      "items": [
        { "text": "Servicios", "url": "/servicios" },
        { "text": "Contacto", "url": "/contacto" },
        { "text": "Blog", "url": "/blog" },
        { "text": "Legal", "url": "/legal" }
      ]
    },
    // Barra inferior
    "bottomBar": {
      "copyright": "© {year} TKM Soluciones 5.0. Todos los derechos reservados.",
      "button": {
        "text": "",
        "url": ""
      }
    },
    // Colores
    "bgColor": "#1a1a2e",
    "textColor": "#ffffff",
    "linkColor": "#b8b8d0",
    "linkHoverColor": "#ffffff",
    "bottomBarBgColor": "#0d0d1a"
  }
}
```

### Comportamiento

- `{year}` en el copyright se reemplaza dinámicamente por el año actual (PHP `gmdate('Y')`)
- La card se renderiza en el Command Center como una card más, después de Layout
- Los cambios siguen el mismo flujo que el resto de settings: debouncedSave → XHR al servidor → regeneración si es necesario
- El logo se sube/elige desde la media library de WordPress (mismo patrón que `headerConfig.logoUrl`)

## Defaults

Se agregan en `vbb_pro_default_settings()` junto al resto de defaults:

```php
'footerConfig' => array(
    'column1' => array(
        'logoUrl'        => '',
        'description'    => '',
        'socialFacebook'  => '',
        'socialInstagram' => '',
        'socialLinkedin'  => '',
        'socialTwitter'   => '',
    ),
    'column2' => array(
        'title' => 'Acceso rápido',
        'items' => array(
            array( 'text' => 'Inicio',     'url' => '/' ),
            array( 'text' => 'Servicios',  'url' => '/servicios' ),
            array( 'text' => 'Contacto',   'url' => '/contacto' ),
            array( 'text' => 'Legal',      'url' => '/legal' ),
        ),
    ),
    'bottomBar' => array(
        'copyright' => '© {year} Todos los derechos reservados.',
        'button'    => array(
            'text' => '',
            'url'  => '',
        ),
    ),
    'bgColor'         => '#1a1a2e',
    'textColor'       => '#ffffff',
    'linkColor'       => '#b8b8d0',
    'linkHoverColor'  => '#ffffff',
    'bottomBarBgColor'=> '#0d0d1a',
),
```

## Sanitization

En `vbb_pro_sanitize_settings()` se agrega:

```php
$out['footerConfig'] = array(
    'column1' => array(
        'logoUrl'        => esc_url_raw( $settings['footerConfig']['column1']['logoUrl'] ?? '' ),
        'description'    => sanitize_text_field( $settings['footerConfig']['column1']['description'] ?? '' ),
        'socialFacebook'  => esc_url_raw( $settings['footerConfig']['column1']['socialFacebook'] ?? '' ),
        'socialInstagram' => esc_url_raw( $settings['footerConfig']['column1']['socialInstagram'] ?? '' ),
        'socialLinkedin'  => esc_url_raw( $settings['footerConfig']['column1']['socialLinkedin'] ?? '' ),
        'socialTwitter'   => esc_url_raw( $settings['footerConfig']['column1']['socialTwitter'] ?? '' ),
    ),
    'column2' => array(
        'title' => sanitize_text_field( $settings['footerConfig']['column2']['title'] ?? $defaults['footerConfig']['column2']['title'] ),
        'items' => array(),
    ),
    'bottomBar' => array(
        'copyright' => sanitize_text_field( $settings['footerConfig']['bottomBar']['copyright'] ?? $defaults['footerConfig']['bottomBar']['copyright'] ),
        'button'    => array(
            'text' => sanitize_text_field( $settings['footerConfig']['bottomBar']['button']['text'] ?? '' ),
            'url'  => esc_url_raw( $settings['footerConfig']['bottomBar']['button']['url'] ?? '' ),
        ),
    ),
    'bgColor'         => sanitize_hex_color( $settings['footerConfig']['bgColor'] ?? '' ) ?: $defaults['footerConfig']['bgColor'],
    'textColor'       => sanitize_hex_color( $settings['footerConfig']['textColor'] ?? '' ) ?: $defaults['footerConfig']['textColor'],
    'linkColor'       => sanitize_hex_color( $settings['footerConfig']['linkColor'] ?? '' ) ?: $defaults['footerConfig']['linkColor'],
    'linkHoverColor'  => sanitize_hex_color( $settings['footerConfig']['linkHoverColor'] ?? '' ) ?: $defaults['footerConfig']['linkHoverColor'],
    'bottomBarBgColor'=> sanitize_hex_color( $settings['footerConfig']['bottomBarBgColor'] ?? '' ) ?: $defaults['footerConfig']['bottomBarBgColor'],
);
```

Para `column2.items`, se sanitiza cada item iterativamente:
```php
$raw_items = $settings['footerConfig']['column2']['items'] ?? array();
if ( is_array( $raw_items ) ) {
    foreach ( $raw_items as $item ) {
        if ( is_array( $item ) ) {
            $out['footerConfig']['column2']['items'][] = array(
                'text' => sanitize_text_field( $item['text'] ?? '' ),
                'url'  => esc_url_raw( $item['url'] ?? '' ),
            );
        }
    }
}
```

## Command Center — Card UI

Nueva card "Footer" en `renderCards()`, usando `renderFooterSettings(s)`.

### renderFooterSettings

Renderiza:

1. **Columna 1 — Brand**
   - Logo: media library picker (mismo patrón que headerConfig)
   - Descripción: textarea
   - Redes: 4 inputs de URL (Facebook, Instagram, LinkedIn, X/Twitter)

2. **Columna 2 — Links**
   - Título: input text
   - 4 items: cada uno con text + URL (input text + input text)

3. **Barra inferior**
   - Copyright: input text (con tooltip: usa `{year}` para año dinámico)
   - Botón opcional: text + URL

4. **Colores**
   - Footer Background: color picker
   - Footer Text Color: color picker
   - Link Color: color picker
   - Link Hover Color: color picker
   - Bottom Bar Background: color picker

### Rebind

Los inputs de la card siguen el mismo patrón que el resto: se vinculan con `data-path` y se manejan via `CC._handleChange`.

Los items de columna 2 NO tendrán botón de agregar/eliminar por ahora — se renderizan 4 items estáticos editables.

## Frontend — CSS Vars

Se agregan las siguientes CSS variables en `vbb_pro_print_css_vars()`:

```css
--vbb-footer-bg: {footerConfig.bgColor};
--vbb-footer-text: {footerConfig.textColor};
--vbb-footer-link: {footerConfig.linkColor};
--vbb-footer-link-hover: {footerConfig.linkHoverColor};
--vbb-footer-bottom-bg: {footerConfig.bottomBarBgColor};
```

## Frontend — Footer Template

Se actualiza `patterns/footer-commercial.php` para:

- Usar `vbb_pro_get_settings()` para obtener `footerConfig`
- Renderizar las 2 columnas dinámicamente
- Usar las CSS vars para colores
- Reemplazar `{year}` en copyright con el año actual
- Renderizar redes sociales como iconos si tienen URL
- Renderizar botón opcional en bottom bar si tiene texto

## Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `inc/pro-settings.php` | Agregar defaults y sanitization de `footerConfig` |
| `inc/pro-css-vars.php` | Agregar CSS vars del footer |
| `inc/pro-admin.php` | (opcional) campo en formulario legacy si existe |
| `patterns/footer-commercial.php` | Reemplazar contenido estático por dinámico con `footerConfig` |
| `assets/js/admin-pro.js` | Agregar `renderFooterSettings()`, card en `renderCards()`, bind de eventos |

## Scope

- No se incluye drag & drop de items
- No se incluye agregar/eliminar items desde la UI (4 items fijos editables)
- No se incluye preview en vivo del footer (sigue el flujo normal: save → regenerate → reload)
- Los iconos de redes se renderizan como texto plano (SVG simple o emoji) — sin FontAwesome ni librerías externas
