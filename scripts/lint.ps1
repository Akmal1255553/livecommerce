$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Mobile = Join-Path $Root "mobile"

function Invoke-BackendLint {
    if (-not (Test-Path (Join-Path $Backend "vendor"))) {
        throw "Backend vendor/ missing. Run scripts/setup.ps1 first."
    }
    Push-Location $Backend
    try {
        composer lint
    } finally {
        Pop-Location
    }
}

function Invoke-MobileLint {
    if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
        Write-Host "Flutter not found — skipping mobile lint."
        return
    }
    Push-Location $Mobile
    try {
        flutter analyze --no-fatal-infos
    } finally {
        Pop-Location
    }
}

Invoke-BackendLint
Invoke-MobileLint
Write-Host "Lint passed."
