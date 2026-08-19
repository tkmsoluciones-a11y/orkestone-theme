# Vertical Block Base

Theme base Gutenberg / Block Theme configurable por vertical vía JSON.

## Requisitos

- WordPress 6.6 o superior recomendado.
- PHP 7.4 o superior.
- Gutenberg/Site Editor disponible desde WordPress core.

## Instalación

Copia la carpeta `vertical-block-base` dentro de:

```text
wp-content/themes/
```

Luego activa el theme desde el panel de WordPress.

## Cambiar vertical activa

Edita:

```text
config/active-vertical.json
```

Ejemplo:

```json
{
  "active": "abogados",
  "fallback": "default"
}
```

La vertical activa debe existir en:

```text
config/verticals/{vertical}.json
```

## Generar páginas iniciales

Si usas WP-CLI, puedes ejecutar:

```bash
wp vbb generate-pages
```

Esto crea las páginas declaradas en el JSON activo, evitando duplicados por slug.

## Crear nueva vertical

1. Copia `config/verticals/default.json`.
2. Renombra el archivo, por ejemplo `clinica.json`.
3. Cambia `verticalKey`, `brand`, `pages`, `sections` y `contentModels`.
4. Cambia `config/active-vertical.json`.

## Archivos clave

- `theme.json`: estilos y settings nativos de WordPress.
- `config/verticals/*.json`: contenido, páginas, secciones y modelos por vertical.
- `inc/vertical-loader.php`: carga de vertical activa.
- `inc/page-blueprint.php`: generación controlada de páginas.
- `patterns/*.php`: secciones Gutenberg reutilizables.

## Nota MVP

Este theme no crea CPTs ni campos avanzados en el MVP. El modelo `contentModels` funciona como configuración declarativa para patterns, páginas iniciales y futura integración con plugin companion.

## Admin: importar verticales JSON

Desde la versión `0.2.0`, el theme incluye un panel visual en:

`Apariencia > Verticales JSON`

Desde ese panel se puede:

1. Subir un archivo `.json` de vertical.
2. Activarlo como vertical actual.
3. Generar páginas declaradas en `pages[]`.
4. Crear/actualizar navegación Gutenberg desde `navigation.primary`.
5. Asignar la página de inicio desde `importOptions.homepageKey`.
6. Importar gráficos/medios desde `graphics.images`, `graphics.themeAssets`, `graficos.imagenes` y `graficos.assetsDelThemeOriginal`.

Las verticales importadas desde el panel se guardan en:

```text
wp-content/uploads/vertical-block-base/verticals/
```

Esto evita depender de permisos de escritura sobre la carpeta del theme. Las verticales incluidas en el theme siguen estando en:

```text
vertical-block-base/config/verticals/
```

Las verticales importadas tienen prioridad sobre las incluidas si usan el mismo `verticalKey`.

## WP-CLI adicional

```bash
wp vbb generate-pages
wp vbb generate-navigation
wp vbb apply-front-page
wp vbb import-media --limit=25
wp vbb import-all
```

`import-all` ejecuta páginas, navegación y asignación de home. La importación de medios se mantiene separada para evitar timeouts en servidores lentos.
