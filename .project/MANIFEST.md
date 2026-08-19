# MANIFEST.md — orkestone-theme (ecosistema visual Aurix)

> Repo del theme + plugin WordPress de las verticales Aurix.
> Fuente de verdad de NEGOCIO: aurix-core-dev (.project/business/).
> Este repo es cliente visual, NO tiene deploy automático.

## Identidad
- Repo: github.com/tkmsoluciones-a11y/orkestone-theme
- Local: F:\Proyectos\theme Orkestone
- Composición: monorepo con theme + plugin
- Rol: superficie visual de las verticales Aurix (builder, bloques, agency hub)
- Deploy: MANUAL a sitio WP staging (ver M-T3)

## Componentes
- [THEME] orkestone-theme/ — theme WP. NUNCA renombrar (slug en DB).
- [HUB] orkestone-agency-hub/ — plugin WP. NUNCA renombrar (slug plugin).
- [BASE-LEGACY] logs/legacy/bck-vertical-block-base/ — base de bloques.

## Specs (SDD)
- Sistema único: openspec/ raíz (activas + archive).
- orkestone-theme/openspec/ = referencia histórica; NO crear changes ahí.
- Activas: builder-visual-polish, command-center, orkestone-engine, small-enhancements.

## Memoria
- .project/COMPONENTS.md, CONVENTIONS.md, IDENTITY.md, BUSINESS-RESUMEN.md
- .project/docs-tech/ (docs migradas)
- .project/components/{theme,hub,legacy}/STATE.md
- .project/reports/ y .project/learnings/
- .project/deploy/ — historial de deploys a staging
- info/EVIDENCE_*.md en componentes = historial de verificación

## Legacy
- logs/legacy/spec-vieja/, logs/legacy/bck-vertical-block-base/

## Herramientas (NO tocar)
.agents/, .claude/, .opencode/, .qwen/, .atl/, .superpowers/
