# Dependency Resolution Report

**Generated:** 2026-06-27  
**Track applied:** Pest 3 (minimal Laravel 12 compatibility fix)  
**Overall status:** **PASS** — all verification commands succeeded

---

## Summary

Development dependencies were incompatible due to loose Pest constraints (`^3.0`) and a PHPUnit version pin (`11.5.55`) that conflicted with both Pest 3.8 and Collision 8.9.4. Constraints were tightened, dependencies were resolved with `--with-all-dependencies`, and the full test suite passes inside Docker.

---

## Root Cause

| # | Issue | Detail |
|---|--------|--------|
| 1 | Loose Pest plugin constraint | `pestphp/pest-plugin-laravel ^3.0` allowed v3.0.0 (Laravel 11 only) |
| 2 | PHPUnit pin mismatch | Lock had `phpunit/phpunit 11.5.55`; Pest 3.8 conflicts with `>11.5.50`; Collision 8.9.4 conflicts with `<11.5.50` |
| 3 | Incomplete vendor install | Prior `composer update` failed mid-install (dirty `vendor/symfony/console` from source sync) |
| 4 | Missing packages in lock | Pest, Scramble, Larastan, and `firebase/php-jwt` were never fully installed |

---

## composer.json Changes

**Commit:** `367565a` — `chore: fix Laravel 12 development dependency compatibility`

| Package | Before | After |
|---------|--------|-------|
| `pestphp/pest` | `^3.0` | `^3.8.2` |
| `pestphp/pest-plugin-laravel` | `^3.0` | `^3.2.0` |

No other constraint changes were made.

---

## Command Executed

```bash
docker compose -f docker/docker-compose.yml exec app \
  composer update pestphp/pest pestphp/pest-plugin-laravel firebase/php-jwt dedoc/scramble larastan/larastan \
  --with-all-dependencies --no-interaction
```

**Install note:** The update wrote `composer.lock` successfully but failed during package extraction (`vendor/symfony/console has uncommitted changes` from a prior partial source install). Recovery:

```bash
docker compose -f docker/docker-compose.yml exec app sh -c \
  "rm -rf vendor && composer install --no-interaction --prefer-dist"
```

---

## Packages Changed

### New installs (21)

| Package | Version |
|---------|---------|
| `brianium/paratest` | v7.8.5 |
| `dedoc/scramble` | v0.12.36 |
| `doctrine/deprecations` | 1.1.6 |
| `fidry/cpu-core-counter` | 1.3.0 |
| `firebase/php-jwt` | v7.1.0 |
| `iamcal/sql-parser` | v0.7 |
| `jean85/pretty-package-versions` | 2.1.1 |
| `larastan/larastan` | v3.10.0 |
| `pestphp/pest` | v3.8.6 |
| `pestphp/pest-plugin` | v3.0.0 |
| `pestphp/pest-plugin-arch` | v3.1.1 |
| `pestphp/pest-plugin-laravel` | v3.2.0 |
| `pestphp/pest-plugin-mutate` | v3.0.5 |
| `phpdocumentor/reflection-common` | 2.2.0 |
| `phpdocumentor/reflection-docblock` | 6.0.3 |
| `phpdocumentor/type-resolver` | 2.0.0 |
| `phpstan/phpdoc-parser` | 2.3.2 |
| `phpstan/phpstan` | 2.2.2 |
| `spatie/laravel-package-tools` | 1.93.1 |
| `ta-tikoma/phpunit-architecture-test` | 0.8.7 |
| `webmozart/assert` | 2.4.1 |

### Updated (10)

| Package | From | To |
|---------|------|-----|
| `phpunit/phpunit` | 11.5.55 | **11.5.50** |
| `symfony/console` | v7.4.13 | v7.4.14 |
| `symfony/error-handler` | v7.4.8 | v7.4.14 |
| `symfony/event-dispatcher` | v8.1.0 | v8.1.1 |
| `symfony/finder` | v7.4.8 | v7.4.14 |
| `symfony/http-foundation` | v7.4.13 | v7.4.14 |
| `symfony/http-kernel` | v7.4.13 | v7.4.14 |
| `symfony/mailer` | v7.4.12 | v7.4.14 |
| `symfony/translation` | v8.1.0 | v8.1.1 |
| `symfony/var-dumper` | v7.4.8 | v7.4.14 |

### Unchanged (key runtime)

| Package | Version |
|---------|---------|
| `laravel/framework` | v12.62.0 |
| `nunomaduro/collision` | v8.9.4 |
| PHP (Docker) | 8.4.22 |
| Composer (Docker) | 2.10.1 |

---

## Lock File Changes

```
backend/composer.lock | 1589 insertions(+), 180 deletions(-)
```

Total packages in vendor after install: **132**

---

## Post-Install Code Fixes (required for tests)

These were **not** dependency issues but blocked verification:

1. **UTF-8 BOM** on 9 service files under `app/Services/` — caused `strict_types declaration must be the very first statement` fatal errors when Pest loaded auth classes. BOM bytes removed.
2. **`bootstrap/app.php`** — removed redundant `use Throwable;` (PHP 8.4 warning: non-compound name has no effect); type hint uses `\Throwable`.

---

## Remaining Warnings

| Warning | Severity | Action |
|---------|----------|--------|
| `public/storage` not linked | Info | Run `php artisan storage:link` when media uploads are needed |
| PHPUnit pinned at 11.5.50 | Info | Intentional — required intersection of Pest 3.8 + Collision 8.9 |
| `composer.lock` not committed | Pending | Commit lock + BOM fixes before CI/deploy |
| Flutter SDK not on PATH | Out of scope | Mobile verification still skipped (see `docs/19_ENVIRONMENT_VERIFICATION.md`) |

**Security audit:** `composer audit` — no vulnerability advisories found.

---

## Verification Results

All commands run inside Docker (`lc-app` container).

| Command | Result | Output |
|---------|--------|--------|
| `composer validate` | **PASS** | `./composer.json is valid` |
| `composer validate --strict` | **PASS** | `./composer.json is valid` |
| `php artisan about` | **PASS** | Laravel 12.62.0, PHP 8.4.22, PostgreSQL, Redis |
| `php artisan migrate --force` | **PASS** | 6 migrations applied (users, cache, jobs, profiles, devices, refresh_tokens) |
| `./vendor/bin/pest` | **PASS** | 13 passed (65 assertions), 10.14s |
| `php artisan test` | **PASS** | 13 passed (65 assertions), 9.90s |

### Test breakdown

| Suite | Tests | Status |
|-------|-------|--------|
| `Tests\Unit\Services\HealthServiceTest` | 1 | PASS |
| `Tests\Feature\Auth\AuthTest` | 7 | PASS |
| `Tests\Feature\ExceptionHandlingTest` | 3 | PASS |
| `Tests\Feature\HealthCheckTest` | 2 | PASS |

---

## Compatibility Matrix (resolved)

| Component | Constraint | Installed | Compatible |
|-----------|------------|-----------|------------|
| PHP | `^8.2` | 8.4.22 | Yes |
| Laravel | `^12.0` | 12.62.0 | Yes |
| Pest | `^3.8.2` | 3.8.6 | Yes |
| Pest Laravel plugin | `^3.2.0` | 3.2.0 | Yes |
| PHPUnit | (transitive) | 11.5.50 | Yes |
| Collision | `^8.6` | 8.9.4 | Yes |
| firebase/php-jwt | `^7.0` | 7.1.0 | Yes |
| dedoc/scramble | `^0.12` | 0.12.36 | Yes |
| larastan/larastan | `^3.0` | 3.10.0 | Yes |

---

## Sprint 2 Gate

| Gate | Status |
|------|--------|
| Dependency resolution | **PASS** |
| Migrations | **PASS** |
| Pest / artisan test | **PASS** |
| Full env verification script | **Not re-run** — recommend `.\scripts\verify-env.ps1` |
| Flutter mobile checks | **Skipped** — SDK not on PATH |

**Do not start Sprint 2** until `scripts/verify-env.ps1` reports overall **PASS** (including Flutter if mobile work is planned).
