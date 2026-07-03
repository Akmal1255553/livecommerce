$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$RoutesOut = Join-Path $Root "docs\.audit\routes-api-v1.txt"

Write-Host "=== Sprint Release Audit (automated helpers) ===" -ForegroundColor Cyan
Write-Host ""

# 1. QA (lint, PHPStan, Pest)
Write-Host "[1/2] Running QA..." -ForegroundColor Yellow
& (Join-Path $Root "scripts\qa.ps1")

# 2. Export API routes for API Audit diff against 04_API_SPECIFICATION.md
Write-Host ""
Write-Host "[2/2] Exporting api/v1 routes..." -ForegroundColor Yellow
$AuditDir = Join-Path $Root "docs\.audit"
if (-not (Test-Path $AuditDir)) {
    New-Item -ItemType Directory -Path $AuditDir -Force | Out-Null
}

$ComposeFile = Join-Path $Root "docker\docker-compose.yml"
if (Get-Command docker -ErrorAction SilentlyContinue) {
    docker compose -f $ComposeFile exec -T app php artisan route:list --path=api/v1 --columns=method,uri,name,action 2>&1 |
        Out-File -FilePath $RoutesOut -Encoding utf8
    Write-Host "Routes written to: docs/.audit/routes-api-v1.txt"
} elseif (Test-Path (Join-Path $Backend "vendor")) {
    Push-Location $Backend
    try {
        php artisan route:list --path=api/v1 --columns=method,uri,name,action 2>&1 |
            Out-File -FilePath $RoutesOut -Encoding utf8
        Write-Host "Routes written to: docs/.audit/routes-api-v1.txt"
    } finally {
        Pop-Location
    }
} else {
    Write-Warning "Skip route export: Docker not available and backend/vendor missing."
}

Write-Host ""
Write-Host "Automated checks complete." -ForegroundColor Green
Write-Host "Complete manual audits per docs/21_SPRINT_RELEASE_AUDIT.md:" -ForegroundColor Cyan
Write-Host "  - Architecture Audit (ADR + Blueprint)"
Write-Host "  - API Audit (compare routes export vs 04_API_SPECIFICATION.md)"
Write-Host "  - Mobile Audit (if mobile changed)"
Write-Host "  - E2E Smoke Test (Phase A script after 4.5M)"
