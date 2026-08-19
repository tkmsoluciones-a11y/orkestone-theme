# Deploy a Staging — YYYY-MM-DD — <tema>

## Contexto
- Commit hash: <ejecutar: git rev-parse HEAD>
- Branch: main
- Spec(s) implementada(s): <IDs de OpenSpec>
- Sitio staging: <URL del WP staging>

## Cambios incluidos

### [THEME] orkestone-theme/
- Lista de cambios visuales/funcionales en el theme
- Archivos modificados:

### [HUB] orkestone-agency-hub/
- Lista de cambios en el plugin
- Archivos modificados:

## Upload manual (checklist)
- [ ] Theme subido vía FTP/SFTP a `wp-content/themes/orkestone-theme/`
- [ ] Plugin subido vía FTP/SFTP a `wp-content/plugins/orkestone-agency-hub/`
- [ ] Cache de WordPress limpiado (plugin de cache o manual)
- [ ] Activación verificada en WP admin (si aplica)
- [ ] Permisos de archivos verificados (644 para archivos, 755 para carpetas)

## Verificación post-deploy
- [ ] Sitio carga sin errores PHP (revisar error_log)
- [ ] Builder de bloques funciona correctamente
- [ ] Agency Hub responde a requests
- [ ] Logs de error de WordPress revisados
- [ ] Tests manuales de flows críticos completados

## Resultado
- **Estado:** ✅ Éxito / ⚠️ Parcial / ❌ Fallido
- **Notas:** <cualquier observación o issue detectado>
- **Aprendizajes:** <referenciar a .project/learnings/ si aplica>

## Rollback (si fue necesario)
- [ ] Versión anterior restaurada
- [ ] Sitio funcionando con versión previa
- [ ] Incidente documentado en logs/YYYY-MM-DD-incidente.md