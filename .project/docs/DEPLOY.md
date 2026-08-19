# Deploy to VPS

## Prerequisites
- SSH key configured: `ssh-copy-id -p 50222 root@157.173.108.103`
- Node.js + Playwright installed in `verification/`
- Baseline captured: `npm run baseline`

## Deploy normal (cambio que no debería afectar apariencia)
.\scripts\deploy-vps.ps1

## Deploy con cambio visual intencional
.\scripts\deploy-vps.ps1 -AcceptVisualChanges

## Rollback manual (si algo sale mal)
ssh -p 50222 root@157.173.108.103 "ls /tmp/orkestone-backup-*.tar.gz"
ssh -p 50222 root@157.173.108.103 "tar -xzf /tmp/orkestone-backup-YYYY-MM-DD-HHMMSS.tar.gz -C /var/www/tkmsoluciones.com/wp-content/themes/orkestone-theme"