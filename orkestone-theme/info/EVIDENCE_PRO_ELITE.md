# EVIDENCE — VBB Pro Elite v0.3.0

## Objetivo
Agregar un panel profesional de configuración visual para el theme `vertical-block-base`.

## Implementado
- Menú admin: `Apariencia > VBB Pro Elite`.
- Guardado de configuración en `wp_options`.
- Colores configurables.
- Tipografías configurables.
- Layout configurable: content width, wide width, radius, shadow, spacing.
- Activación/desactivación declarativa de bloques/secciones.
- Botones configurables.
- Presets Pro en `config/presets/`.
- Perfiles guardados.
- Export JSON de configuración.
- Import JSON de configuración.
- CSS variables dinámicas en frontend.

## Opciones usadas
- `vbb_pro_settings`
- `vbb_pro_saved_profiles`
- `vbb_pro_active_profile`

## Archivos agregados
- `inc/pro-settings.php`
- `inc/pro-presets.php`
- `inc/pro-css-vars.php`
- `inc/pro-admin.php`
- `assets/css/admin-pro.css`
- `assets/js/admin-pro.js`
- `config/presets/legal-elite.json`
- `config/presets/corporate-dark.json`
- `config/presets/boutique-gold.json`
- `config/presets/minimal-light.json`

## Limitación conocida
La activación/desactivación de bloques queda expuesta como clases del `body` y configuración guardada. Para ocultar/renderizar condicionalmente se recomienda conectar esta configuración con patrones y templates dinámicos en v0.4.0.

## Próxima tarea
Integrar los toggles de bloques con el generador de páginas y los patrones para que el importador omita secciones desactivadas.
