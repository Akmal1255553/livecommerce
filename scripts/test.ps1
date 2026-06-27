$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Mobile = Join-Path $Root "mobile"

function Invoke-BackendTest {
    if (-not (Test-Path (Join-Path $Backend "vendor"))) {
        throw "Backend vendor/ missing. Run scripts/setup.ps1 first."
    }
    Push-Location $Backend
    try {
        composer test
    } finally {
        Pop-Location
    }
}

function Invoke-MobileTest {
    if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
        Write-Host "Flutter not found — skipping mobile tests."
        return
    }
    Push-Location $Mobile
    try {
        flutter test
    } finally {
        Pop-Location
    }
}

Invoke-BackendTest
Invoke-MobileTest
Write-Host "Tests passed."
