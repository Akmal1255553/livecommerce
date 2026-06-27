$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Mobile = Join-Path $Root "mobile"

function Invoke-BackendAnalyse {
    if (-not (Test-Path (Join-Path $Backend "vendor"))) {
        throw "Backend vendor/ missing. Run scripts/setup.ps1 first."
    }
    Push-Location $Backend
    try {
        composer analyse
    } finally {
        Pop-Location
    }
}

function Invoke-MobileAnalyse {
    if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
        Write-Host "Flutter not found — skipping mobile analysis."
        return
    }
    Push-Location $Mobile
    try {
        flutter analyze --no-fatal-infos
    } finally {
        Pop-Location
    }
}

Invoke-BackendAnalyse
Invoke-MobileAnalyse
Write-Host "Static analysis passed."
