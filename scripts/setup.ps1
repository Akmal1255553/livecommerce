# LiveCommerce — Sprint 0.1 Setup Script (Windows)
# Requires Docker Desktop running

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot

Write-Host "==> LiveCommerce Sprint 0.1 Setup" -ForegroundColor Cyan
Write-Host "Root: $Root"

# Check Docker
docker info | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Error "Docker is not running. Start Docker Desktop and retry."
}

Set-Location $Root

Write-Host "==> Building and starting Docker services..." -ForegroundColor Cyan
docker compose -f docker/docker-compose.yml up -d --build postgres redis minio mailpit

Write-Host "==> Waiting for PostgreSQL..." -ForegroundColor Cyan
Start-Sleep -Seconds 8

Write-Host "==> Starting app + nginx..." -ForegroundColor Cyan
docker compose -f docker/docker-compose.yml up -d app nginx

Write-Host "==> Installing backend dependencies..." -ForegroundColor Cyan
docker compose -f docker/docker-compose.yml exec -T app composer install --no-interaction

if (-not (Test-Path "$Root\backend\.env")) {
    Write-Host "==> Creating backend .env..." -ForegroundColor Cyan
    docker compose -f docker/docker-compose.yml exec -T app cp .env.example .env
    docker compose -f docker/docker-compose.yml exec -T app php artisan key:generate
}

Write-Host "==> Running migrations..." -ForegroundColor Cyan
docker compose -f docker/docker-compose.yml exec -T app php artisan migrate --force

Write-Host "==> Creating MinIO bucket (if mc available)..." -ForegroundColor Cyan
Write-Host "    Create bucket 'livecommerce' manually at http://localhost:9001 if needed."

# Flutter platform scaffold
if (-not (Test-Path "$Root\mobile\android")) {
    Write-Host "==> Flutter android/ not found." -ForegroundColor Yellow
    $flutter = Get-Command flutter -ErrorAction SilentlyContinue
    if ($flutter) {
        Set-Location "$Root\mobile"
        flutter create . --org uz.livecommerce --project-name livecommerce_mobile
        flutter pub get
        Set-Location $Root
    } else {
        Write-Host "    Install Flutter SDK and run:" -ForegroundColor Yellow
        Write-Host "    cd mobile && flutter create . --org uz.livecommerce --project-name livecommerce_mobile" -ForegroundColor Yellow
    }
} else {
    Set-Location "$Root\mobile"
    flutter pub get
    Set-Location $Root
}

Write-Host ""
Write-Host "==> Installing git hooks (Sprint 0.2)..." -ForegroundColor Cyan
& (Join-Path $Root "scripts\install-hooks.ps1")

Write-Host ""
Write-Host "==> Setup complete!" -ForegroundColor Green
Write-Host "API:     http://localhost:8080/api/v1/health"
Write-Host "API Docs: http://localhost:8080/docs/api"
Write-Host "Mailpit: http://localhost:8025"
Write-Host "MinIO:   http://localhost:9001 (minio / minio123456)"
Write-Host ""
Write-Host "Run QA:  .\scripts\qa.ps1" -ForegroundColor Cyan
