# LiveCommerce — Environment Verification (Windows)
# Requires Docker Desktop. Do not use XAMPP/local PHP.

$ErrorActionPreference = "Continue"
$Root = Split-Path -Parent $PSScriptRoot
$ComposeFile = Join-Path $Root "docker\docker-compose.yml"
$ReportPath = Join-Path $Root "docs\19_ENVIRONMENT_VERIFICATION.md"
$Timestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-dd HH:mm:ss UTC")

$script:Results = [ordered]@{}

function Set-CheckResult {
    param([string]$Name, [string]$Status, [string]$Detail)
    $script:Results[$Name] = @{ Status = $Status; Detail = $Detail }
    $color = switch ($Status) {
        "PASS" { "Green" }
        "FAIL" { "Red" }
        "SKIP" { "Yellow" }
        default { "White" }
    }
    Write-Host "[$Status] $Name - $Detail" -ForegroundColor $color
}

Set-Location $Root

docker info 2>$null | Out-Null
$dockerUp = ($LASTEXITCODE -eq 0)
if ($dockerUp) {
    Set-CheckResult "Docker daemon" "PASS" "Docker engine reachable."
} else {
    Set-CheckResult "Docker daemon" "FAIL" "Docker Desktop is not running."
}

if ($dockerUp) {
    docker compose -f $ComposeFile up -d --build 2>&1 | Out-Null
    Start-Sleep -Seconds 12
    $running = @(docker compose -f $ComposeFile ps --status running -q 2>$null).Count
    if ($running -lt 5) {
        Set-CheckResult "Docker containers" "FAIL" "Only $running containers running (expected >= 5)."
    } else {
        Set-CheckResult "Docker containers" "PASS" "$running containers running."
    }

    if (-not (Test-Path "$Root\backend\.env") -or (Select-String -Path "$Root\backend\.env" -Pattern "^DB_CONNECTION=sqlite" -Quiet)) {
        Copy-Item "$Root\backend\.env.example" "$Root\backend\.env" -Force
        Set-CheckResult "Backend .env" "PASS" "Reset from .env.example (removed SQLite/XAMPP config)."
    } else {
        Set-CheckResult "Backend .env" "PASS" "Docker-oriented .env present."
    }

    docker compose -f $ComposeFile exec -T app composer install --no-interaction 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Set-CheckResult "Composer install" "FAIL" "composer install exited $LASTEXITCODE"
    } else {
        Set-CheckResult "Composer install" "PASS" "Dependencies installed in container."
    }

    docker compose -f $ComposeFile exec -T app php artisan key:generate --force 2>&1 | Out-Null
    $envFile = "$Root\backend\.env"
    $envText = Get-Content $envFile -Raw
    if ($envText -notmatch "JWT_SECRET=\S+") {
        $jwt = [guid]::NewGuid().ToString("N") + [guid]::NewGuid().ToString("N")
        $envText = $envText -replace "(?m)^JWT_SECRET=.*", "JWT_SECRET=$jwt"
        Set-Content -Path $envFile -Value $envText -NoNewline
    }

    docker compose -f $ComposeFile exec -T app php artisan about --no-ansi 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Set-CheckResult "Laravel boot" "FAIL" "php artisan about failed."
    } else {
        Set-CheckResult "Laravel boot" "PASS" "Artisan runs successfully."
    }

    docker compose -f $ComposeFile exec -T app php artisan migrate --force 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Set-CheckResult "Migrations" "FAIL" "php artisan migrate failed."
    } else {
        Set-CheckResult "Migrations" "PASS" "All migrations applied."
    }

    docker compose -f $ComposeFile exec -T postgres pg_isready -U livecommerce -d livecommerce 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Set-CheckResult "PostgreSQL" "PASS" "pg_isready OK."
    } else {
        Set-CheckResult "PostgreSQL" "FAIL" "pg_isready failed."
    }

    $redisOut = docker compose -f $ComposeFile exec -T redis redis-cli ping 2>&1
    if ($redisOut -match "PONG") {
        Set-CheckResult "Redis" "PASS" "PONG received."
    } else {
        Set-CheckResult "Redis" "FAIL" "$redisOut"
    }

    docker compose -f $ComposeFile --profile workers up -d worker 2>&1 | Out-Null
    Start-Sleep -Seconds 3
    $workerState = docker compose -f $ComposeFile ps worker --format "{{.State}}" 2>$null
    if ($workerState -match "running") {
        Set-CheckResult "Queue worker" "PASS" "lc-worker container running."
    } else {
        Set-CheckResult "Queue worker" "FAIL" "Worker state: $workerState"
    }

    try {
        $minio = Invoke-WebRequest -Uri "http://localhost:9000/minio/health/live" -UseBasicParsing -TimeoutSec 5
        if ($minio.StatusCode -eq 200) {
            Set-CheckResult "MinIO" "PASS" "Health endpoint returned 200."
        } else {
            Set-CheckResult "MinIO" "FAIL" "HTTP $($minio.StatusCode)"
        }
    } catch {
        Set-CheckResult "MinIO" "FAIL" $_.Exception.Message
    }

    try {
        $mail = Invoke-WebRequest -Uri "http://localhost:8025" -UseBasicParsing -TimeoutSec 5
        if ($mail.StatusCode -eq 200) {
            Set-CheckResult "Mailpit" "PASS" "Web UI reachable on port 8025."
        } else {
            Set-CheckResult "Mailpit" "FAIL" "HTTP $($mail.StatusCode)"
        }
    } catch {
        Set-CheckResult "Mailpit" "FAIL" $_.Exception.Message
    }

    try {
        $health = Invoke-RestMethod -Uri "http://localhost:8080/api/v1/health" -TimeoutSec 10
        if ($health.success -eq $true -and $health.data.status -eq "ok") {
            Set-CheckResult "Health endpoint" "PASS" "status=ok database=$($health.data.database)"
        } else {
            Set-CheckResult "Health endpoint" "FAIL" ($health | ConvertTo-Json -Compress)
        }
    } catch {
        Set-CheckResult "Health endpoint" "FAIL" $_.Exception.Message
    }

    docker compose -f $ComposeFile exec -T app ./vendor/bin/pest --no-ansi 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Set-CheckResult "Pest tests" "FAIL" "Exit code $LASTEXITCODE"
    } else {
        Set-CheckResult "Pest tests" "PASS" "All tests passed."
    }
} else {
    foreach ($name in @(
        "Docker containers", "Backend .env", "Composer install", "Laravel boot",
        "Migrations", "PostgreSQL", "Redis", "Queue worker", "MinIO",
        "Mailpit", "Health endpoint", "Pest tests"
    )) {
        Set-CheckResult $name "SKIP" "Docker not available."
    }
}

$flutter = Get-Command flutter -ErrorAction SilentlyContinue
if (-not $flutter) {
    Set-CheckResult "Flutter pub get" "SKIP" "Flutter SDK not on PATH."
    Set-CheckResult "Flutter analyze" "SKIP" "Flutter SDK not on PATH."
    Set-CheckResult "Flutter test" "SKIP" "Flutter SDK not on PATH."
} else {
    Push-Location "$Root\mobile"
    flutter pub get 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { Set-CheckResult "Flutter pub get" "FAIL" "flutter pub get failed." }
    else { Set-CheckResult "Flutter pub get" "PASS" "Dependencies resolved." }
    flutter gen-l10n 2>&1 | Out-Null
    flutter analyze 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { Set-CheckResult "Flutter analyze" "FAIL" "Analyzer reported issues." }
    else { Set-CheckResult "Flutter analyze" "PASS" "No issues found." }
    flutter test 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { Set-CheckResult "Flutter test" "FAIL" "Tests failed." }
    else { Set-CheckResult "Flutter test" "PASS" "All tests passed." }
    Pop-Location
}

$passCount = @($script:Results.Values | Where-Object { $_.Status -eq "PASS" }).Count
$failCount = @($script:Results.Values | Where-Object { $_.Status -eq "FAIL" }).Count
$skipCount = @($script:Results.Values | Where-Object { $_.Status -eq "SKIP" }).Count
if ($failCount -eq 0 -and $skipCount -eq 0) { $overall = "PASS" }
elseif ($failCount -eq 0) { $overall = "PASS (with skips)" }
else { $overall = "FAIL" }

$lines = @(
    "# Environment Verification Report",
    "",
    "**Generated:** $Timestamp  ",
    "**Overall status:** **$overall**  ",
    "**Results:** $passCount passed, $failCount failed, $skipCount skipped",
    "",
    "> **Policy:** Do not use XAMPP or host PHP. Backend runs in Docker (PHP 8.4). Sprint 1 is **not complete** until overall status is **PASS** with zero failures.",
    "",
    "## Prerequisites",
    "",
    "| Tool | Required | Notes |",
    "|------|----------|-------|",
    "| Docker Desktop | Yes | Engine must be running |",
    "| Flutter SDK | Yes | On PATH for mobile checks |",
    "| XAMPP / host PHP | **No** | Causes PHP 8.1 incompatibility and broken vendor/ |",
    "",
    "## Checklist",
    "",
    "| Check | Status | Detail |",
    "|-------|--------|--------|"
)
foreach ($name in $script:Results.Keys) {
    $r = $script:Results[$name]
    $detail = ($r.Detail -replace '\|', '/')
    $lines += "| $name | $($r.Status) | $detail |"
}
$lines += @(
    "",
    "## How to re-run",
    "",
    '```powershell',
    '.\scripts\verify-env.ps1',
    '```',
    "",
    "## Remediation",
    "",
    "1. Start Docker Desktop; wait until docker info succeeds.",
    "2. Copy backend/.env.example to backend/.env if DB was sqlite.",
    "3. docker compose -f docker/docker-compose.yml up -d --build",
    "4. docker compose -f docker/docker-compose.yml exec app composer install",
    "5. docker compose -f docker/docker-compose.yml exec app php artisan key:generate",
    "6. docker compose -f docker/docker-compose.yml exec app php artisan migrate --force",
    "7. Install Flutter SDK and add to PATH.",
    "8. Re-run scripts/verify-env.ps1.",
    "",
    "## Sprint gate",
    "",
    "| Sprint | Status |",
    "|--------|--------|",
    "| Sprint 1 (Authentication) | Blocked until verification PASS |",
    "| Sprint 2 (Social Foundation) | Blocked until Sprint 1 gate clears |"
)
Set-Content -Path $ReportPath -Value ($lines -join "`n") -Encoding UTF8
Write-Host ""
Write-Host "Report: docs/19_ENVIRONMENT_VERIFICATION.md"
Write-Host "Overall: $overall ($passCount pass, $failCount fail, $skipCount skip)"
if ($failCount -gt 0) { exit 1 }
exit 0
