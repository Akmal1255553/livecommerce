# Sprint 0.1 — Infrastructure

**Status:** Complete (scaffold)  
**Date:** 2026-06-27

## Deliverables

| Item | Status | Location |
|------|--------|----------|
| Repository root config | Done | `.gitignore`, `.editorconfig`, `README.md` |
| Docker Compose | Done | `docker/docker-compose.yml` |
| PHP 8.4-FPM image | Done | `docker/php/Dockerfile` |
| Nginx | Done | `docker/nginx/default.conf` |
| PostgreSQL 16 + init | Done | `docker/postgres/init.sql` |
| Redis 7 | Done | docker-compose service |
| MinIO | Done | docker-compose service |
| Mailpit | Done | docker-compose service |
| Queue worker profile | Done | `worker` service (--profile workers) |
| Laravel 12 backend | Done | `backend/` |
| Health API endpoint | Done | `GET /api/v1/health` |
| Module folder scaffold | Done | `backend/app/Services/`, `Contracts/` |
| Flutter mobile scaffold | Done | `mobile/` |
| CI/CD — Backend | Done | `.github/workflows/backend-ci.yml` |
| CI/CD — Mobile | Done | `.github/workflows/mobile-ci.yml` |
| Setup scripts | Done | `scripts/setup.ps1`, `scripts/setup.sh` |
| Web admin placeholder | Done | `web-admin/README.md` |
| Environment template | Done | `backend/.env.example` |

## Post-Setup Commands

```powershell
# Start Docker Desktop, then:
.\scripts\setup.ps1
```

## Verification Checklist

- [ ] `docker compose -f docker/docker-compose.yml ps` — all services healthy
- [ ] `curl http://localhost:8080/api/v1/health` — returns `"status":"ok"`
- [ ] http://localhost:8025 — Mailpit UI loads
- [ ] http://localhost:9001 — MinIO console loads
- [ ] `docker compose exec app php artisan test` — tests pass
- [ ] `cd mobile && flutter pub get && flutter test` — after Flutter SDK setup

## Next Sprint

**Sprint 1 — Authentication & Users**
