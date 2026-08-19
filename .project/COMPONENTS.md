# COMPONENTS.md — orkestone-theme

| ID | Tag | Ruta | Tipo | Restricción |
|---|---|---|---|---|
| THEME | `[THEME]` | `orkestone-theme/` | WP theme | ⚠️ NUNCA renombrar (slug en DB WP) |
| HUB | `[HUB]` | `orkestone-agency-hub/` | WP plugin | ⚠️ NUNCA renombrar (slug plugin) |
| BASE | — | `logs/legacy/bck-vertical-block-base/` | backup | legacy |

## Mandato M-T1 (slugs)
WordPress guarda el slug del theme y del plugin en la base de datos.
Renombrar estas carpetas rompe el sitio. Cualquier reorganización interna
debe pasar por SPEC con validación de deploy.

## Mandato M-T2 (OpenSpec)
Los changes nuevos se crean SOLO en `openspec/` raíz.
`orkestone-theme/openspec/` queda como referencia histórica.

## Mandato M-T3 (Deploy automatizado con verificación visual)
Deploy es AUTOMATIZADO vía `scripts/deploy-vps.ps1`:
1. Captura baseline pre-deploy (si no existe)
2. Backup remoto del theme actual en VPS
3. Rsync de theme y plugin al VPS (excluye node_modules, .git, .project, verification, logs, openspec)
4. Limpia cache de WordPress
5. Ejecuta auditoría visual (`npm run audit`)
6. Si PASS: deploy exitoso, registra en `.project/deploy/`
7. Si FAIL/WARNING: rollback automático desde backup, registra incidente

Reglas:
- Cada deploy se registra en `.project/deploy/DEPLOY-YYYY-MM-DD-HHmmss.md`
- Si la auditoría falla, el rollback es automático (no requiere intervención manual)
- El VPS es: `ssh -p 50222 root@157.173.108.103`
- Path remoto: `/var/www/tkmsoluciones.com/wp-content/themes/orkestone-theme`
