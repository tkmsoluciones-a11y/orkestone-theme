# Flujo de Deploy Manual a Staging

Este proyecto NO tiene deploy automático. Cada release a staging sigue este proceso:

## 1. Preparación local
```bash
git checkout main
git pull origin main
# Hacer cambios, implementar SPECs
git add -A
git commit -m "feat: descripción del cambio"
git push origin main