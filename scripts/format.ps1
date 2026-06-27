$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"

if (-not (Test-Path (Join-Path $Backend "vendor"))) {
    throw "Backend vendor/ missing. Run scripts/setup.ps1 first."
}

Push-Location $Backend
try {
    composer format
} finally {
    Pop-Location
}

Write-Host "Format complete."
