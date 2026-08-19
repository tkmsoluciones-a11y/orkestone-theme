# DECISIONS.md — orkestone-theme

| ID | Fecha | Decisión | Contexto | Estado |
|---|---|---|---|---|
| DEC-010 | 2026-08-19 | Deploy automatizado al VPS con verificación visual | Cambia mandato M-T3 de manual a automatizado. Script deploy-vps.ps1 sincroniza theme/plugin vía rsync, ejecuta auditoría visual post-deploy, y hace rollback automático si detecta regresiones. | ACTIVE |
