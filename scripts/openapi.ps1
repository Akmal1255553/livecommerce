$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Output = Join-Path $Root "docs\openapi.json"
$Temp = Join-Path $Backend "storage\app\openapi.json"

if (-not (Test-Path (Join-Path $Backend "vendor"))) {
    throw "Backend vendor/ missing. Run scripts/setup.ps1 first."
}

$docsDir = Split-Path $Output -Parent
if (-not (Test-Path $docsDir)) {
    New-Item -ItemType Directory -Path $docsDir | Out-Null
}

$tempDir = Split-Path $Temp -Parent
if (-not (Test-Path $tempDir)) {
    New-Item -ItemType Directory -Path $tempDir | Out-Null
}

Push-Location $Backend
try {
    php artisan scramble:export --path=storage/app/openapi.json
} finally {
    Pop-Location
}

Copy-Item -Path $Temp -Destination $Output -Force

Write-Host "OpenAPI spec exported to docs/openapi.json"
Write-Host "Live docs: http://localhost:8080/docs/api"
