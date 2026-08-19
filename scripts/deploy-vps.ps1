# deploy-vps.ps1 — Deploy automatizado del theme/plugin al VPS con verificación visual
param(
    [string]$VPSHost = "157.173.108.103",
    [int]$VPSPort = 50222,
    [string]$VPSUser = "root",
    [string]$ThemeRemotePath = "/var/www/tkmsoluciones.com/wp-content/themes/orkestone-theme",
    [string]$HubRemotePath = "/var/www/tkmsoluciones.com/wp-content/plugins/orkestone-agency-hub"
)

$ErrorActionPreference = "Stop"
$Timestamp = Get-Date -Format "yyyy-MM-dd-HHmmss"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "🚀 Deploy to VPS — $Timestamp" -ForegroundColor Cyan

# 1. Capturar baseline pre-deploy (si no existe)
if (!(Test-Path "$ProjectRoot\verification\baseline") -or (Get-ChildItem "$ProjectRoot\verification\baseline\*.png").Count -eq 0) {
    Write-Host "📸 Capturando baseline pre-deploy..." -ForegroundColor Yellow
    Push-Location "$ProjectRoot\verification"
    npm run baseline
    Pop-Location
}

# 2. Backup remoto del theme actual
Write-Host "💾 Backup remoto del theme actual..." -ForegroundColor Yellow
$BackupPath = "/tmp/orkestone-backup-$Timestamp.tar.gz"
ssh -p $VPSPort "${VPSUser}@${VPSHost}" "tar -czf $BackupPath -C $ThemeRemotePath ."

# 3. Rsync del theme
Write-Host "📤 Sincronizando theme..." -ForegroundColor Cyan
rsync -avz --delete --exclude='node_modules' --exclude='.git' --exclude='.project' --exclude='verification' --exclude='logs' --exclude='openspec' --exclude='migration-audit' --exclude='*.md' -e "ssh -p $VPSPort" "$ProjectRoot\orkestone-theme\" "${VPSUser}@${VPSHost}:$ThemeRemotePath"

# 4. Rsync del plugin (si existe)
if (Test-Path "$ProjectRoot\orkestone-agency-hub") {
    Write-Host "📤 Sincronizando plugin..." -ForegroundColor Cyan
    rsync -avz --delete --exclude='node_modules' --exclude='.git' --exclude='.project' --exclude='verification' --exclude='logs' --exclude='openspec' --exclude='migration-audit' --exclude='*.md' -e "ssh -p $VPSPort" "$ProjectRoot\orkestone-agency-hub\" "${VPSUser}@${VPSHost}:$HubRemotePath"
}

# 5. Limpiar cache de WordPress
Write-Host "🧹 Limpiando cache de WordPress..." -ForegroundColor Yellow
ssh -p $VPSPort "${VPSUser}@${VPSHost}" "cd /var/www/tkmsoluciones.com && wp cache flush --allow-root 2>/dev/null || echo 'WP-CLI no disponible, cache manual requerido'"

# 6. Auditoría visual post-deploy
Write-Host "🔍 Auditoría visual post-deploy..." -ForegroundColor Cyan
Push-Location "$ProjectRoot\verification"
$AuditResult = npm run audit
Pop-Location

# 7. Parsear resultado
$AuditJson = Get-ChildItem "$ProjectRoot\.project\reports\theme-audit-*.json" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$Report = Get-Content $AuditJson.FullName | ConvertFrom-Json

# 8. Decisión: PASS o ROLLBACK
if ($Report.verdict -eq "PASS") {
    Write-Host "✅ Deploy exitoso — auditoría PASS" -ForegroundColor Green
    $DeployStatus = "SUCCESS"
    
    $DeployReport = @"
# Deploy $Timestamp

## Contexto
- Commit: $(git rev-parse HEAD)
- Branch: $(git branch --show-current)
- VPS: ${VPSUser}@${VPSHost}:$ThemeRemotePath

## Auditoría visual
- Verdict: PASS
- Console errors: $($Report.consoleErrors.Count)
- Network errors: $($Report.networkErrors.Count)

## Resultado
✅ Deploy exitoso sin regresiones visuales
"@
    $DeployReport | Set-Content "$ProjectRoot\.project\deploy\DEPLOY-$Timestamp.md" -Encoding UTF8
} else {
    Write-Host "❌ Deploy falló — auditoría $($Report.verdict). Rollback..." -ForegroundColor Red
    ssh -p $VPSPort "${VPSUser}@${VPSHost}" "tar -xzf $BackupPath -C $ThemeRemotePath"
    $DeployStatus = "ROLLBACK"
    
    $DeployReport = @"
# Deploy $Timestamp — ROLLBACK

## Contexto
- Commit: $(git rev-parse HEAD)
- Branch: $(git branch --show-current)
- VPS: ${VPSUser}@${VPSHost}:$ThemeRemotePath

## Auditoría visual
- Verdict: $($Report.verdict)
- Páginas con diff > 0.5%: $(($Report.pages | Where-Object { $_.diffPercent -gt 0.5 }).Count)
- Console errors: $($Report.consoleErrors.Count)
- Network errors: $($Report.networkErrors.Count)

## Rollback
✅ Restaurado desde backup $BackupPath

## Motivo
La auditoría visual detectó regresiones. Revisar .project/reports/diff-screenshots/ para identificar el componente problemático.
"@
    $DeployReport | Set-Content "$ProjectRoot\.project\deploy\DEPLOY-$Timestamp-ROLLBACK.md" -Encoding UTF8
}

Write-Host ""
Write-Host "========== RESUMEN ==========" -ForegroundColor Cyan
Write-Host "Status: $DeployStatus"
Write-Host "Report: .project/deploy/DEPLOY-$Timestamp.md"
Write-Host "============================="
