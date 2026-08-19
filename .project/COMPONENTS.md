# COMPONENTS.md — orkestone-theme

| ID | Tag | Ruta | Tipo | Restricción |
|---|---|---|---|---|
| THEME | [THEME] | orkestone-theme/ | WP theme | NUNCA renombrar (slug en DB WP) |
| HUB | [HUB] | orkestone-agency-hub/ | WP plugin | NUNCA renombrar (slug plugin) |
| BASE | — | logs/legacy/bck-vertical-block-base/ | backup | legacy |

## Mandato M-T1 (slugs)
WordPress guarda el slug del theme y del plugin en la base de datos.
Renombrar estas carpetas rompe el sitio. Cualquier reorganización interna
debe pasar por SPEC con validación de deploy.

## Mandato M-T2 (OpenSpec)
Los changes nuevos se crean SOLO en openspec/ raíz.
orkestone-theme/openspec/ queda como referencia histórica.

## Mandato M-T3 (Deploy manual a staging)
Este proyecto NO tiene deploy automático. Deploy es MANUAL vía:
1. git push al repo GitHub (respaldo + colaboración).
2. Upload manual a wp-content/ del WP staging:
   - orkestone-theme/ a wp-content/themes/orkestone-theme/
   - orkestone-agency-hub/ a wp-content/plugins/orkestone-agency-hub/
3. Activación en WP admin si aplica.

Reglas:
- Cada deploy a staging se registra en .project/deploy/YYYY-MM-DD-tema.md
  con commit hash, cambios incluidos, URL del sitio staging y verificación.
- NO hay auto-deploy: cualquier pipeline es manual y documentado.
- El VPS principal (aurix-core-dev) NO toca este repo ni estos componentes.
