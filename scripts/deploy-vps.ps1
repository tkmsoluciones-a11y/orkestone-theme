param(
    [string]$VPSHost = "157.173.108.103",
    [int]$VPSPort = 50222,
    [string]$VPSUser = "root",
    [string]$ThemeRemotePath = "/var/www/tkmsoluciones.com/wp-content/themes/orkestone-theme",
    [string]$HubRemotePath = "/var/www/tkmsoluciones.com/wp-content/plugins/orkestone-agency-hub",
    [switch]$AcceptVisualChanges
)

$ErrorActionPreference = "Stop"
$Timestamp = Get-Date -Format "yyyy-MM-dd-HHmmss"
$ProjectRoot = (Get-Item -Path $PSScriptRoot).Parent.FullName
$CommitHash = git -C $ProjectRoot rev-parse HEAD
$BranchName = git -C $ProjectRoot branch --show-current

function Write-DeployReport {
    param([string]$FileName, [string[]]$Lines)
    ($Lines -join "`n") | Set-Content (Join-Path $ProjectRoot ".project\deploy\$FileName") -Encoding UTF8
}

function Sync-ToVps {
    param([string]$LocalFolder, [string]$RemotePath, [string]$Label)

    Write-Host "[DEPLOY] Empaquetando $Label..." -ForegroundColor Cyan
    $TarName = "orkestone-$Label-$Timestamp.tar.gz"
    $LocalTar = Join-Path $env:TEMP $TarName
    tar -czf $LocalTar --exclude=node_modules --exclude=.git -C $ProjectRoot $LocalFolder
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Fallo el empaquetado de $Label" -ForegroundColor Red
        return $false
    }

    Write-Host "[DEPLOY] Subiendo $Label al VPS..." -ForegroundColor Cyan
    scp -P $VPSPort $LocalTar "${VPSUser}@${VPSHost}:/tmp/$TarName"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Fallo el scp de $Label" -ForegroundColor Red
        return $false
    }

    Write-Host "[DEPLOY] Extrayendo $Label en VPS..." -ForegroundColor Cyan
    ssh -p $VPSPort "${VPSUser}@${VPSHost}" "rm -rf $RemotePath && mkdir -p $RemotePath && tar -xzf /tmp/$TarName -C $RemotePath --strip-components=1 && rm -f /tmp/$TarName"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERROR] Fallo la extraccion de $Label" -ForegroundColor Red
        return $false
    }

    Remove-Item $LocalTar -ErrorAction SilentlyContinue
    return $true
}

Write-Host "[DEPLOY] Deploy to VPS - $Timestamp" -ForegroundColor Cyan

# 1. Baseline (si no existe)
if (!(Test-Path "$ProjectRoot\verification\baseline") -or @(Get-ChildItem "$ProjectRoot\verification\baseline\*.png").Count -eq 0) {
    Write-Host "[DEPLOY] Capturando baseline..." -ForegroundColor Yellow
    Push-Location "$ProjectRoot\verification"
    npm run baseline
    Pop-Location
}

# 2. Backup remoto del theme actual
Write-Host "[DEPLOY] Backup remoto..." -ForegroundColor Yellow
$BackupPath = "/tmp/orkestone-backup-$Timestamp.tar.gz"
ssh -p $VPSPort "${VPSUser}@${VPSHost}" "tar -czf $BackupPath -C $ThemeRemotePath ."
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Backup fallo. Abortando." -ForegroundColor Red
    exit 1
}

# 3. Sync theme (tar + scp, sin rsync)
$ThemeOk = Sync-ToVps -LocalFolder "orkestone-theme" -RemotePath $ThemeRemotePath -Label "theme"
if (-not $ThemeOk) {
    Write-Host "[ERROR] Sync de theme fallo. Rollback..." -ForegroundColor Red
    ssh -p $VPSPort "${VPSUser}@${VPSHost}" "tar -xzf $BackupPath -C $ThemeRemotePath"
    Write-DeployReport "DEPLOY-$Timestamp-FAILED.md" @(
        "# Deploy $Timestamp - FAILED", "", "## Error", "Fallo la sincronizacion del theme", "", "## Rollback", "Restaurado desde backup $BackupPath"
    )
    exit 1
}

# 4. Sync plugin (si existe)
if (Test-Path "$ProjectRoot\orkestone-agency-hub") {
    $HubOk = Sync-ToVps -LocalFolder "orkestone-agency-hub" -RemotePath $HubRemotePath -Label "hub"
    if (-not $HubOk) {
        Write-Host "[WARN] Sync de plugin fallo. Continuando solo con theme..." -ForegroundColor Yellow
    }
}

# 5. Permisos WordPress (www-data)
Write-Host "[DEPLOY] Ajustando permisos..." -ForegroundColor Yellow
ssh -p $VPSPort "${VPSUser}@${VPSHost}" "chown -R www-data:www-data $ThemeRemotePath $HubRemotePath 2>/dev/null || true"

# 6. Cache WordPress
Write-Host "[DEPLOY] Limpiando cache..." -ForegroundColor Yellow
ssh -p $VPSPort "${VPSUser}@${VPSHost}" "cd /var/www/tkmsoluciones.com && wp cache flush --allow-root 2>/dev/null || echo 'Cache manual'"

# 7. Auditoria visual post-deploy
Write-Host "[DEPLOY] Auditoria visual..." -ForegroundColor Cyan
Push-Location "$ProjectRoot\verification"
npm run audit
Pop-Location

# 8. Parsear resultado
$AuditJson = Get-ChildItem "$ProjectRoot\.project\reports\theme-audit-*.json" | Sort-Object LastWriteTime -Descending | Select-Object -First 1
$Report = Get-Content $AuditJson.FullName -Raw | ConvertFrom-Json

# 9. Decision PASS o ROLLBACK
if ($Report.verdict -eq "PASS") {
    Write-Host "[OK] Deploy exitoso - PASS" -ForegroundColor Green
    Write-DeployReport "DEPLOY-$Timestamp.md" @(
        "# Deploy $Timestamp", "", "## Contexto", "- Commit: $CommitHash", "- Branch: $BranchName", "- VPS: ${VPSUser}@${VPSHost}", "",
        "## Auditoria", "- Verdict: PASS", "- Console: $($Report.consoleErrors.Count)", "- Network: $($Report.networkErrors.Count)", "",
        "## Resultado", "Deploy exitoso sin regresiones visuales"
    )
} elseif ($AcceptVisualChanges) {
    Write-Host "[DEPLOY] Cambios visuales INTENCIONALES aceptados." -ForegroundColor Yellow
    Write-Host "[DEPLOY] Recapturando baseline con el nuevo estado..." -ForegroundColor Yellow
    Push-Location "$ProjectRoot\verification"
    npm run baseline
    Pop-Location
    $DiffPages = @($Report.pages | Where-Object { [double]$_.diffPercent -gt 0.5 }).Count
    Write-DeployReport "DEPLOY-$Timestamp-VISUAL-CHANGES.md" @(
        "# Deploy $Timestamp - Cambios visuales aceptados", "",
        "## Contexto", "- Commit: $CommitHash", "- Branch: $BranchName", "- VPS: ${VPSUser}@${VPSHost}", "",
        "## Auditoria vs baseline anterior", "- Verdict: $($Report.verdict)", "- Paginas con diff: $DiffPages", "",
        "## Accion", "Cambios visuales intencionales aprobados por el usuario. Baseline recapturado con el nuevo estado."
    )
    Write-Host "[OK] Deploy completado con baseline actualizado" -ForegroundColor Green
} else {
    Write-Host "[ERROR] Auditoria $($Report.verdict). Rollback..." -ForegroundColor Red
    ssh -p $VPSPort "${VPSUser}@${VPSHost}" "tar -xzf $BackupPath -C $ThemeRemotePath"
    $DiffPages = @($Report.pages | Where-Object { [double]$_.diffPercent -gt 0.5 }).Count
    Write-DeployReport "DEPLOY-$Timestamp-ROLLBACK.md" @(
        "# Deploy $Timestamp - ROLLBACK", "", "## Auditoria", "- Verdict: $($Report.verdict)", "- Paginas con diff > 0.5%: $DiffPages", "",
        "## Rollback", "Restaurado desde backup $BackupPath", "", "## Motivo", "Regresiones visuales detectadas. Revisar .project/reports/diff-screenshots/"
    )
}

Write-Host ""
Write-Host "========== RESUMEN ==========" -ForegroundColor Cyan
Write-Host "Reportes en: .project/deploy/"
Write-Host "============================="
# Enviar notificación a Discord/Slack
if ($env:DEPLOY_WEBHOOK) {
    $payload = @{
        text = "Deploy $DeployStatus - Commit $CommitHash`nBranch: $BranchName`nReport: .project/deploy/DEPLOY-$Timestamp.md"
    } | ConvertTo-Json
    
    Invoke-RestMethod -Uri $env:DEPLOY_WEBHOOK -Method Post -Body $payload -ContentType "application/json"
}