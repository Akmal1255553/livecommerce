# Sprint 0.2 — Developer Experience

**Status:** Complete  
**Depends on:** Sprint 0.1 (Infrastructure)

## Goal

Establish linting, formatting, static analysis, git hooks, testing, API documentation, helper scripts, VS Code/Cursor configuration so every developer (and CI) runs the same quality gates.

---

## Deliverables

### Backend (Laravel)

| Tool | Purpose | Command |
|------|---------|---------|
| **Laravel Pint** | Code formatting (PSR-12 + Laravel preset) | `composer format` / `composer lint` |
| **PHPStan + Larastan** | Static analysis (level 6) | `composer analyse` |
| **Pest** | Testing framework | `composer test` |
| **Scramble** | OpenAPI / Swagger UI from routes | `/docs/api` |
| **Composer scripts** | `lint`, `format`, `analyse`, `test`, `qa` | `composer qa` |

**Config files:**
- `backend/pint.json` — strict types, single quotes, ordered imports
- `backend/phpstan.neon` — Larastan extension, paths `app`, `config`, `routes`
- `backend/config/scramble.php` — API title, JWT security scheme
- `backend/tests/Pest.php` — Pest bootstrap

### Mobile (Flutter)

| Tool | Purpose | Command |
|------|---------|---------|
| **flutter_lints** | Lint rules | `flutter analyze` |
| **flutter test** | Widget/unit tests | `flutter test` |

Config: `mobile/analysis_options.yaml` (const constructors, no print)

### Monorepo Scripts

| Script | Description |
|--------|-------------|
| `scripts/lint.ps1` / `.sh` | Backend Pint + mobile analyze |
| `scripts/format.ps1` / `.sh` | Backend Pint fix |
| `scripts/analyse.ps1` / `.sh` | Backend PHPStan + mobile analyze |
| `scripts/test.ps1` / `.sh` | Backend Pest + mobile tests |
| `scripts/qa.ps1` / `.sh` | Full quality gate (lint + analyse + test) |
| `scripts/openapi.ps1` / `.sh` | Export OpenAPI to `docs/openapi.json` |
| `scripts/install-hooks.ps1` / `.sh` | Install git pre-commit hooks |

### Git Hooks

Two options (pick one):

1. **Built-in (default):** `.githooks/pre-commit` — runs lint + analyse on staged `backend/` and `mobile/` files  
   Install: `.\scripts\install-hooks.ps1`

2. **Lefthook (optional):** `lefthook.yml` — parallel checks with globs  
   Install: `lefthook install` (requires [Lefthook](https://github.com/evilmartians/lefthook))

### VS Code

- `.vscode/settings.json` — format on save, Intelephense PHP 8.4, Dart line length
- `.vscode/tasks.json` — Docker up, backend QA, mobile test, OpenAPI export
- `.vscode/launch.json` — Flutter debug/profile
- `.vscode/extensions.json` — recommended extensions

### Cursor Rules

- `.cursor/rules/project-core.mdc` — monorepo conventions (always apply)
- `.cursor/rules/backend-laravel.mdc` — Laravel patterns
- `.cursor/rules/mobile-flutter.mdc` — Flutter structure
- `.cursor/rules/api-spec.mdc` — API + OpenAPI conventions

### CI/CD

`backend-ci.yml` updated with PHPStan step after Pint.

---

## Verification Checklist

```powershell
# After Sprint 0.1 setup (Docker running, composer install done)

# Backend
docker compose -f docker/docker-compose.yml exec app composer qa

# Or locally if vendor/ exists
cd backend && composer qa

# Mobile (requires Flutter SDK)
cd mobile && flutter analyze && flutter test

# Monorepo
.\scripts\qa.ps1

# Git hooks
.\scripts\install-hooks.ps1

# OpenAPI
.\scripts\openapi.ps1
# Browse: http://localhost:8080/docs/api
```

---

## New Dependencies (backend)

```json
"pestphp/pest": "^3.0",
"pestphp/pest-plugin-laravel": "^3.0",
"larastan/larastan": "^3.0",
"dedoc/scramble": "^0.12"
```

Run after pulling:

```bash
docker compose -f docker/docker-compose.yml exec app composer update
```

---

## Next Sprint

**Sprint 1 — Authentication & Users:** JWT auth, user migrations, AuthService, mobile login/register screens.
