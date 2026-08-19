# EVIDENCE — VBB Pro Elite v0.3.2

## Cambios realizados

- Se movió `VBB Pro Elite` a un menú raíz del administrador de WordPress.
- Se agregó estructura de submenús:
  - Dashboard
  - Diseño
  - Verticales JSON
  - Bloques
  - Perfiles
  - Export / Import
- Se desactivó el registro anterior bajo `Apariencia` para `VBB Pro Elite`.
- `Verticales JSON` ahora se muestra como submenú dentro de `VBB Pro Elite`.
- Se agregaron paletas separadas `light` y `dark`.
- Se agregó `colorMode`: `light`, `dark` o `auto`.
- Se mantiene compatibilidad con perfiles antiguos basados en `colors`.
- El export/import Pro Elite conserva paletas Light/Dark.
- El frontend imprime CSS dinámico según modo seleccionado.

## Archivos modificados

- `functions.php`
- `style.css`
- `inc/pro-admin.php`
- `inc/pro-settings.php`
- `inc/pro-css-vars.php`
- `inc/admin-verticals.php`
- `config/presets/*.json`
- `assets/css/admin-pro.css`

## Validación esperada en WordPress

1. Ir al admin de WordPress.
2. Confirmar menú raíz `VBB Pro Elite`.
3. Entrar en `VBB Pro Elite > Diseño`.
4. Guardar colores Light y Dark.
5. Probar modo `light`, `dark` y `auto`.
6. Exportar JSON y confirmar que incluye `palettes.light` y `palettes.dark`.
7. Confirmar que `Verticales JSON` aparece dentro de `VBB Pro Elite`.

## Estado

Listo para prueba en WordPress real.
