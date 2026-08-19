# EVIDENCE — Pro Elite Section Toggles v0.3.1

## Objetivo
Conectar los toggles de bloques/secciones del panel `Apariencia > VBB Pro Elite` con el generador de páginas.

## Cambios realizados

### `inc/pro-settings.php`
Se agregaron funciones:

- `vbb_pro_get_block_section_map()`
- `vbb_pro_is_section_enabled($section_slug)`
- `vbb_pro_filter_sections($sections)`

### `inc/page-blueprint.php`
El generador ahora filtra las secciones declaradas en cada página antes de convertirlas en patrones Gutenberg.

## Mapa de toggles

| Section JSON | Toggle Pro Elite |
|---|---|
| `hero` | `hero` |
| `hero-centered` | `hero` |
| `services-grid` | `servicesGrid` |
| `benefits` | `benefits` |
| `process` | `process` |
| `testimonials` | `testimonials` |
| `faq` | `faq` |
| `contact-section` | `contact` |
| `cta-final` | `ctaFinal` |

## Comportamiento esperado

- Si `faq` está apagado, no se genera el patrón `vertical-block-base/faq`.
- Si `testimonials` está apagado, no se genera el patrón `vertical-block-base/testimonials`.
- Si `ctaFinal` está apagado, no se genera el patrón `vertical-block-base/cta-final`.
- Secciones desconocidas permanecen activas para compatibilidad futura.
- Si Pro Elite no está disponible, el generador funciona igual que antes.

## Validaciones locales

- PHP lint ejecutado sobre `functions.php` e `inc/*.php`.
- ZIP generado como `vertical-block-base_theme_v0.3.1_pro_elite_section_toggles.zip`.

## Pendiente de validación en WordPress real

1. Activar theme.
2. Ir a `Apariencia > VBB Pro Elite`.
3. Apagar `FAQ`, `Testimonios` y `CTA final`.
4. Guardar configuración.
5. Ir a `Apariencia > Verticales JSON`.
6. Generar páginas.
7. Confirmar que las páginas nuevas no contienen esos patrones.
