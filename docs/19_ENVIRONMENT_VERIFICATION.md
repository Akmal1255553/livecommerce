# Environment Verification Report

**Generated:** 2026-06-27 09:59:50 UTC  
**Overall status:** **FAIL**  
**Results:** 0 passed, 1 failed, 15 skipped

> **Policy:** Do not use XAMPP or host PHP. Backend runs in Docker (PHP 8.4). Sprint 1 is **not complete** until overall status is **PASS** with zero failures.

## Prerequisites

| Tool | Required | Notes |
|------|----------|-------|
| Docker Desktop | Yes | Engine must be running |
| Flutter SDK | Yes | On PATH for mobile checks |
| XAMPP / host PHP | **No** | Causes PHP 8.1 incompatibility and broken vendor/ |

## Checklist

| Check | Status | Detail |
|-------|--------|--------|
| Docker daemon | FAIL | Docker Desktop is not running. |
| Docker containers | SKIP | Docker not available. |
| Backend .env | SKIP | Docker not available. |
| Composer install | SKIP | Docker not available. |
| Laravel boot | SKIP | Docker not available. |
| Migrations | SKIP | Docker not available. |
| PostgreSQL | SKIP | Docker not available. |
| Redis | SKIP | Docker not available. |
| Queue worker | SKIP | Docker not available. |
| MinIO | SKIP | Docker not available. |
| Mailpit | SKIP | Docker not available. |
| Health endpoint | SKIP | Docker not available. |
| Pest tests | SKIP | Docker not available. |
| Flutter pub get | SKIP | Flutter SDK not on PATH. |
| Flutter analyze | SKIP | Flutter SDK not on PATH. |
| Flutter test | SKIP | Flutter SDK not on PATH. |

## How to re-run

```powershell
.\scripts\verify-env.ps1
```

## Remediation

1. Start Docker Desktop; wait until docker info succeeds.
2. Copy backend/.env.example to backend/.env if DB was sqlite.
3. docker compose -f docker/docker-compose.yml up -d --build
4. docker compose -f docker/docker-compose.yml exec app composer install
5. docker compose -f docker/docker-compose.yml exec app php artisan key:generate
6. docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
7. Install Flutter SDK and add to PATH.
8. Re-run scripts/verify-env.ps1.

## Sprint gate

| Sprint | Status |
|--------|--------|
| Sprint 1 (Authentication) | Blocked until verification PASS |
| Sprint 2 (Social Foundation) | Blocked until Sprint 1 gate clears |
