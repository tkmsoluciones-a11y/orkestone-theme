# ANTIPATTERNS.md — orkestone-theme

## Anti-patrones detectados y evitados

### AP-001: Agente editando variables sin ver resultado visual
- **Contexto:** En iteraciones anteriores, el agente corregía variables PHP/CSS sin acceso visual al sitio.
- **Problema:** Feedback loop manual campo por campo (ineficiente, propenso a errores).
- **Solución:** Sistema de auditoría visual con baseline + pixel diff (verification/).
- **Regla:** Todo cambio visual debe pasar por auditoría antes de deploy.

### AP-002: Renombrar carpetas de theme/plugin WordPress
- **Contexto:** WordPress almacena slugs en DB.
- **Problema:** Renombrar orkestone-theme/ o orkestone-agency-hub/ rompe el sitio activo.
- **Solución:** Mandato M-T1 (nunca renombrar).
- **Regla:** Cualquier reorganización interna pasa por SPEC con validación de deploy.

### AP-003: Duplicar lógica de negocio en PHP
- **Contexto:** Theme/plugin podrían intentar replicar lógica que ya existe en aurix-core-dev API.
- **Problema:** Inconsistencia, mantenimiento duplicado.
- **Solución:** Mandato M-T4 (contract-first).
- **Regla:** PHP solo renderiza; lógica vive en la API.

### AP-004: Crear changes en orkestone-theme/openspec/
- **Contexto:** Existe un openspec anidado en el theme (referencia histórica).
- **Problema:** Confusión sobre dónde crear nuevos changes.
- **Solución:** Mandato M-T2 (changes nuevos solo en openspec/ raíz).
- **Regla:** orkestone-theme/openspec/ es solo referencia histórica.

### AP-005: Deploy sin documentación
- **Contexto:** Deploy manual al WP staging.
- **Problema:** Sin trazabilidad de qué se deployó y cuándo.
- **Solución:** Mandato M-T3 (todo deploy documentado en .project/deploy/).
- **Regla:** Cada release requiere DEPLOY-YYYY-MM-DD-tema.md con checklist completo.

## Anti-patrones a monitorear

### AP-006: Baseline desactualizado
- **Riesgo:** Si el baseline no se actualiza tras fixes aprobados, el pixel diff pierde validez.
- **Mitigación:** Ofrecer recapturar baseline después de cada fix aprobado.
- **Frecuencia:** Cada vez que un fix visual pasa auditoría.

### AP-007: Commits atómicos vs. commits grandes
- **Riesgo:** Commits grandes dificultan identificar qué cambio rompió algo.
- **Mitigación:** Un commit por fix, con mensaje descriptivo.
- **Frecuencia:** Siempre.

### AP-008: Reemplazar CSS vars en lugar de acumularlas
- **Contexto:** En el preview del Command Center, cada actualización de CSS vars reemplazaba completamente el contenido del style tag.
- **Problema:** Si el CC enviaba múltiples actualizaciones, las variables anteriores se perdían.
- **Solución:** Acumular las nuevas variables con las existentes, separadas por un comentario CSS.
- **Regla:** En handlers de bb:css-vars, acumular en lugar de reemplazar.

### AP-009: Ocultar bloques por defecto si el toggle no está configurado
- **Contexto:** La lógica de visibilidad de bloques usaba !empty(['enabled']).
- **Problema:** Si el toggle no estaba configurado, el bloque se ocultaba (comportamiento contraintuitivo).
- **Solución:** Solo ocultar si nabled === false explícitamente; por defecto mostrar.
- **Regla:** En lógica de visibilidad, el default debe ser "mostrar", no "ocultar".

### AP-010: Usar rsync de cwrsync en Windows para deploy a VPS Linux
- **Contexto:** Script de deploy intentaba usar cwrsync instalado por Chocolatey.
- **Problema:** cwrsync interpreta rutas con `:` (ej: `F:\Proyectos`) como host remoto → error "source and destination cannot both be remote".
- **Solución:** Usar `tar` + `scp` + `ssh` (herramientas nativas que ya funcionan).
- **Regla:** En Windows, preferir tar+scp sobre rsync para deploy a Linux.

### AP-011: Here-strings de PowerShell con emojis en Windows PowerShell 5.1
- **Contexto:** Script de deploy usaba here-strings (`@" "@`) con emojis (🚀, ✅, ❌).
- **Problema:** PowerShell 5.1 corrompe el encoding de emojis dentro de here-strings → "Unexpected token" al parsear.
- **Solución:** Usar texto plano (`[DEPLOY]`, `[OK]`, `[ERROR]`) o arrays `@()` en lugar de here-strings.
- **Regla:** En PS 5.1, evitar emojis dentro de here-strings. Usar arrays para construir texto multi-línea.

### AP-012: CI intentando deployar al VPS sin credenciales SSH
- **Contexto:** Workflow inicial intentaba ejecutar el deploy completo desde GitHub Actions.
- **Problema:** CI no tiene acceso SSH al VPS, ni credenciales WP, ni baseline guardado, ni lógica de rollback.
- **Solución:** CI solo hace smoke test (captura screenshots). El deploy real queda local con rollback automático.
- **Regla:** El deploy verificado con rollback vive donde tiene acceso SSH. CI es observador, no ejecutor.

### AP-013: Auditoría visual de páginas dinámicas o ajenas al theme
- **Contexto:** `pages.json` tenía 12 páginas incluyendo `admin-aurix-panel-v31` (del proyecto aurix-core-dev) y plugins terceros.
- **Problema:** 94% diff en páginas con nonces dinámicos o contenido de otros proyectos → falsos FAIL.
- **Solución:** Auditar solo páginas controladas por el theme (7 páginas: home, mobile, diagnostico, 4 admin VBB).
- **Regla:** El auditor visual solo verifica páginas cuyo estado visual es determinista y pertenece al proyecto.