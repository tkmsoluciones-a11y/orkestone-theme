# EVIDENCE — ESTUDIO X import JSON pack

## Fuente analizada

- Archivo: `estudiox.WordPress.2026-05-19.xml`
- Tipo: WordPress WXR XML
- Sitio: `ESTUDIO X`
- URL base: `https://tkmsoluciones.com/legal50`
- Descripción: `Legal 5.0`
- Idioma: `es`
- Generator: `https://wordpress.org/?v=6.9.4`

## Extracción realizada

| Elemento | Cantidad |
|---|---:|
| Páginas totales | 8 |
| Páginas publicadas | 7 |
| Menús clásicos `nav_menu_item` | 6 |
| Navegaciones Gutenberg `wp_navigation` | 2 |
| Adjuntos/media referenciados | 36 |
| Posts totales | 13 |
| Servicios/practice areas mapeados | 12 |
| Assets del theme original detectados en block markup | 14 |

## Archivos generados

- `vertical-block-base/config/verticals/estudiox.json`
- `vertical-block-base/config/active-vertical.json`
- `imports/estudiox-import-blueprint.json`
- `import-manifest.json`

## Decisiones de mapeo

1. La página `Home` del XML no tenía contenido directo; la estructura visual principal estaba en `wp_template/front-page`.
2. Se creó `estudiox.json` como vertical compatible con `vertical-block-base`.
3. Se mapearon `nav_menu_item` como `navigation.primary`.
4. Se conservaron los bloques `wp_navigation` en `navigation.wpNavigationBlocks`.
5. Los posts de `Practice Areas` se mapearon como `contentModels.service.items`.
6. Las imágenes se referencian por URL porque el XML no incluye binarios.
7. Se agregó alias `graficos` además de `graphics` para lectura humana en español.

## Uso recomendado

1. Copiar `vertical-block-base/config/verticals/estudiox.json` dentro del theme.
2. Copiar `vertical-block-base/config/active-vertical.json` dentro del theme para activar la vertical.
3. Ejecutar en WordPress:

```bash
wp vbb generate-pages
```

4. Si necesitas media local, importar también el XML original con el importador de WordPress o descargar los assets referenciados.

## TAREA ACTUAL

Crear JSON importable con páginas, menús y gráficos desde el XML de WordPress para el theme `vertical-block-base`.

## PRÓXIMA TAREA

Actualizar el generador del theme para que además de páginas pueda importar navegación, asignar front page y descargar/media-library de imágenes referenciadas.
