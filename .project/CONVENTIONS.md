# CONVENTIONS.md — orkestone-theme

1. Leer MANIFEST.md y COMPONENTS.md antes de cualquier acción.
2. Memoria en .project/; bitácoras en logs/YYYY-MM-DD-tema.md; legacy en logs/legacy/.
3. orkestone-theme/ y orkestone-agency-hub/ NUNCA se renombran ni mueven (M-T1).
4. Changes nuevos solo en openspec/ raíz (M-T2).
5. Deploy a staging es MANUAL, documentado en .project/deploy/ (M-T3).
6. Sin SPEC aprobada no hay implementación.
7. Contract-first con aurix-core-dev: este repo consume la API, no duplica lógica de negocio.
8. Un dato, un lugar: no duplicar docs; referenciar.
9. Archivar, no borrar (openspec archive, EVIDENCE_*.md).
10. MANIFEST/CONVENTIONS/COMPONENTS/IDENTITY no se borran.
11. Aprendizajes a .project/learnings/ tras cada change o deploy.
12. Negocio: fuente completa en aurix-core-dev; aquí solo BUSINESS-RESUMEN.md.
