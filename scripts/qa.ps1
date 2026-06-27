$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot

& (Join-Path $Root "scripts\lint.ps1")
& (Join-Path $Root "scripts\analyse.ps1")
& (Join-Path $Root "scripts\test.ps1")

Write-Host "QA complete."
