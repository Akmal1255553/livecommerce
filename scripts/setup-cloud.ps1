# LiveCommerce — cloud setup helper (Supabase + Railway, ADR-018)
# Usage: .\scripts\setup-cloud.ps1

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"

Write-Host "==> LiveCommerce cloud setup (no Docker)" -ForegroundColor Cyan
Write-Host "Docs: docs/22_CLOUD_INFRA_SUPABASE_RAILWAY.md"
Write-Host ""

if (-not (Get-Command railway -ErrorAction SilentlyContinue)) {
    Write-Host "Installing Railway CLI..." -ForegroundColor Yellow
    npm i -g @railway/cli
}

Write-Host "1) Login to Railway (browser will open)..." -ForegroundColor Yellow
railway login

Write-Host "2) Init / link project from backend/ ..." -ForegroundColor Yellow
Set-Location $Backend

$status = railway status --json 2>$null
if (-not $status) {
    railway init --name livecommerce
}

Write-Host "3) Ensure Redis exists in project..." -ForegroundColor Yellow
$services = railway service list --json 2>$null
if ($services -notmatch "redis|Redis") {
    railway add --database redis --json
} else {
    Write-Host "   Redis already present (or list failed — check dashboard)." -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "NEXT (manual — need your Supabase project):" -ForegroundColor Green
Write-Host "  A. Create Supabase project + copy DB password / host"
Write-Host "  B. railway variable set DB_HOST=... DB_PORT=6543 DB_DATABASE=postgres DB_USERNAME=postgres DB_PASSWORD=... DB_SSLMODE=require"
Write-Host "  C. Set APP_KEY, JWT_SECRET, APP_URL, Redis refs — see docs/22_CLOUD_INFRA_SUPABASE_RAILWAY.md"
Write-Host "  D. railway up -y -m `"Deploy API`""
Write-Host "  E. railway domain"
Write-Host "  F. flutter run --dart-define=API_BASE_URL=https://YOUR_DOMAIN/api/v1"
Write-Host ""
Write-Host "Done with CLI bootstrap." -ForegroundColor Cyan
