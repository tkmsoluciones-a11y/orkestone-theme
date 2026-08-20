# STATE.md — [THEME] orkestone-theme

## Estado actual (2026-08-19)
- **Versión:** v2-MIGRATED (post-reestructuración)
- **Deploy:** Automatizado local con verificación visual + rollback (scripts/deploy-vps.ps1)
- **CI:** Smoke test en GitHub Actions (captura screenshots de las 7 páginas del theme)
- **URL staging:** https://tkmsoluciones.com
- **SSH:** root@157.173.108.103:50222

## Auditoría visual (local)
- **Estado:** ✅ PASS (último deploy)
- **Páginas monitoreadas (7):** home, home-mobile, diagnostico, admin-vbb-pro-elite, admin-vbb-verticals, admin-vbb-pro-elite-import-export, admin-vbb-command-center
- **Páginas excluidas:** admin-aurix-panel-v31 (aurix-core-dev), blockenberg, n8n-ollama-chatbot (plugins terceros), index-php-* (posts WordPress)

## Métricas acumuladas
- Ver: .project/deploy/metrics.json
- Reportes: .project/deploy/DEPLOY-*.md

## Smoke test CI
- **Workflow:** .github/workflows/smoke-test.yml
- **Trigger:** push a main + workflow_dispatch
- **Qué hace:** Captura screenshots de las 7 páginas del theme (sin deploy)
- **Secrets requeridos:** SITE_URL, WP_URL, WP_USER, WP_PASS
- **Artifact:** smoke-screenshots-{run_number} (retention 7 días)

## Mandatos activos
- M-T1: Slugs WP intactos (no renombrar carpetas)
- M-T2: Changes solo en openspec/ raíz
- M-T3: Deploy automatizado con verificación visual y rollback (LOCAL)
- M-T4: CI solo observa, no ejecuta deploy (sin acceso SSH)
- M-T5: Auditoría visual solo de páginas controladas por el theme
