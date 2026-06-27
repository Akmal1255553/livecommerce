$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Hooks = Join-Path $Root ".githooks"

if (-not (Test-Path (Join-Path $Root ".git"))) {
    throw "Not a git repository."
}

git -C $Root config core.hooksPath ".githooks"
Write-Host "Git hooks installed from .githooks/"
