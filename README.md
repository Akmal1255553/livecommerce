# LiveCommerce

AI-powered live commerce platform — mobile-first video shopping for Central Asia.

**Repository:** https://github.com/Akmal1255553/livecommerce

Users discover and buy products through short-form video and live streams (TikTok-style engagement + in-app checkout). The monorepo contains the Flutter mobile app, Laravel API, Docker infrastructure, and full architecture documentation.

```
╔══════════════════════════════════════════════════════════════╗
║  Architecture Freeze v1.0                                    ║
║  Status: APPROVED  ·  Consistency: 100%  ·  P0: Resolved   ║
║  Ready for Sprint 0                                          ║
╚══════════════════════════════════════════════════════════════╝
```

**Engineering Foundation:** Sprint 0–1 complete · **Sprint 2:** Follow, Notifications, Feed · **Sprint 3.1:** Upload Foundation · **Next:** Sprint 3.2 Video Processing (blueprint approval)

---

## Architecture

LiveCommerce is a **modular monolith** with a Clean Architecture mobile client.

```
┌─────────────────┐     HTTPS      ┌──────────────────┐
│  Flutter App    │ ──────────────▶│  Laravel 12 API  │
│  (Riverpod)     │                │  Service Layer   │
└─────────────────┘                └────────┬─────────┘
                                              │
                    ┌─────────────────────────┼─────────────────────────┐
                    ▼                         ▼                         ▼
              PostgreSQL 16              Redis 7                   S3 / MinIO
              (primary DB)            (cache, queues)            (media storage)
```

**Backend layers:** Controllers → Services → Repositories → Models  
**Mobile layers:** Presentation → Domain ← Data  
**Cross-cutting:** JWT auth, domain events, queued jobs, structured logging, OpenAPI docs (Scramble)

Key conventions:

- API prefix: `/api/v1/`
- No business logic in controllers
- All schema and API changes tracked in `docs/`
- Architectural changes require an ADR (`07_ADR.md`)

Full design: [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md) · [Modules](./08_MODULES.md) · [Architecture Freeze Report](./docs/18_ARCHITECTURE_FREEZE_REPORT.md)

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| **Mobile** | Flutter 3, Dart 3, Riverpod, GoRouter, Dio |
| **Backend** | Laravel 12, PHP 8.4 |
| **Database** | PostgreSQL 16 |
| **Cache / Queue** | Redis 7, Laravel Horizon (planned) |
| **Storage** | MinIO (local) · S3-compatible / Cloudflare R2 (prod) |
| **Email (local)** | Mailpit |
| **Live streaming** | Agora (via provider interface) |
| **Push** | Firebase Cloud Messaging |
| **Testing** | Pest, PHPStan, Laravel Pint · `flutter test` |
| **API docs** | Scramble (OpenAPI at `/docs/api`) |
| **CI/CD** | GitHub Actions |
| **Containers** | Docker Compose (Nginx, PHP-FPM, Postgres, Redis, MinIO, Mailpit) |

Decisions are recorded in [ADR](./07_ADR.md) (15 accepted decisions).

---

## Repository Structure

```
livecommerce/
├── backend/              # Laravel 12 REST API
│   ├── app/
│   │   ├── Contracts/    # Repository & service interfaces
│   │   ├── DTOs/         # Data transfer objects
│   │   ├── Http/         # Controllers, middleware, responses
│   │   ├── Repositories/ # Eloquent repository implementations
│   │   └── Services/     # Business logic (per module)
│   ├── routes/api.php
│   └── tests/            # Pest feature & unit tests
│
├── mobile/               # Flutter app (Clean Architecture)
│   └── lib/
│       ├── app/          # App shell, router, theme
│       ├── core/         # Network, errors, constants, l10n
│       ├── features/     # Feature modules (auth, feed, …)
│       └── shared/       # Reusable widgets
│
├── docker/               # Docker Compose, PHP, Nginx configs
├── docs/                 # Engineering documentation (authoritative)
├── scripts/              # Setup, QA, lint, test, OpenAPI export
├── web-admin/            # Admin panel (Sprint 14)
│
├── docs01_PRD.md         # Product requirements
├── 07_ADR.md … 13_ROADMAP.md   # Architecture & planning docs
└── .github/workflows/    # CI pipelines
```

Details: [Project Structure](./docs/05_PROJECT_STRUCTURE.md) · [Repository Tree](./docs/15_REPOSITORY_TREE.md)

---

## How to Run

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (**required**, must be running)
- [Git](https://git-scm.com/)
- [Flutter SDK](https://flutter.dev/docs/get-started/install) (stable) — for mobile

**Do not use XAMPP or host PHP** for backend work. The stack targets PHP 8.4 inside Docker; host PHP 8.1 breaks Composer and dev dependencies.

### Environment verification

Before treating Sprint 1 as complete, run the full environment check:

```powershell
.\scripts\verify-env.ps1
```

Report: [docs/19_ENVIRONMENT_VERIFICATION.md](./docs/19_ENVIRONMENT_VERIFICATION.md)

### Docker

One-command setup (installs dependencies, runs migrations, configures git hooks):

**Windows:**
```powershell
git clone <repository-url> livecommerce
cd livecommerce
.\scripts\setup.ps1
```

**Linux / macOS:**
```bash
git clone <repository-url> livecommerce
cd livecommerce
chmod +x scripts/setup.sh
./scripts/setup.sh
```

Manual start:
```bash
docker compose -f docker/docker-compose.yml up -d --build
```

Optional queue workers:
```bash
docker compose -f docker/docker-compose.yml --profile workers up -d worker
```

| Service | URL | Credentials |
|---------|-----|-------------|
| API | http://localhost:8080 | — |
| Health | http://localhost:8080/api/v1/health | — |
| API Docs | http://localhost:8080/docs/api | — |
| Mailpit | http://localhost:8025 | — |
| MinIO Console | http://localhost:9001 | minio / minio123456 |
| PostgreSQL | localhost:5432 | livecommerce / secret |
| Redis | localhost:6379 | — |

Environment templates:
```bash
cp backend/.env.example backend/.env
cp docker/docker-compose.override.yml.example docker/docker-compose.override.yml   # optional
```

### Backend

All commands below assume Docker is running. Run from repo root.

```bash
# Dependencies
docker compose -f docker/docker-compose.yml exec app composer install

# Migrations
docker compose -f docker/docker-compose.yml exec app php artisan migrate

# Artisan
docker compose -f docker/docker-compose.yml exec app php artisan <command>
```

Composer scripts (inside container or local `backend/` with `vendor/` installed):

| Command | Description |
|---------|-------------|
| `composer lint` | Pint code style check |
| `composer format` | Auto-fix code style |
| `composer analyse` | PHPStan static analysis |
| `composer test` | Pest test suite |
| `composer qa` | lint + analyse + test |

### API (Sprint 2)

Authenticated endpoints (`Authorization: Bearer {token}`) unless noted:

| Method | Path | Sprint | Description |
|--------|------|--------|-------------|
| POST | `/api/v1/users/{id}/follow` | 2.1 | Follow user |
| DELETE | `/api/v1/users/{id}/follow` | 2.1 | Unfollow user |
| GET | `/api/v1/users/{id}` | 2.1 | Public profile (optional auth) |
| GET | `/api/v1/users/{id}/followers` | 2.1 | Followers list |
| GET | `/api/v1/users/{id}/following` | 2.1 | Following list |
| GET | `/api/v1/notifications` | 2.2 | Notifications (cursor) |
| GET | `/api/v1/notifications/unread-count` | 2.2 | Unread count |
| PUT | `/api/v1/notifications/{id}/read` | 2.2 | Mark as read |
| PUT | `/api/v1/notifications/read-all` | 2.2 | Mark all read |
| PUT | `/api/v1/me/notification-settings` | 2.2 | Notification preferences |
| POST | `/api/v1/devices` | 2.2 | Register FCM token |
| DELETE | `/api/v1/devices/{token}` | 2.2 | Unregister device |
| GET | `/api/v1/feed/for-you` | 2.3 | For You video feed (cursor, optional auth) |
| GET | `/api/v1/feed/following` | 2.3 | Following video feed (cursor, auth required) |
| GET | `/api/v1/videos/{id}` | 3.1 | Video detail (owner sees processing) |
| POST | `/api/v1/videos` | 3.1 | Initiate video upload (presigned URL) |
| POST | `/api/v1/videos/{id}/confirm-upload` | 3.1 | Confirm upload + start pipeline |
| POST | `/api/v1/media/presigned-url` | 3.1 | Generic presigned upload URL |
| POST | `/api/v1/metrics/events` | 3.1 | Batch engagement events |

Full contract: [API Specification](./docs/04_API_SPECIFICATION.md) · Live docs: http://localhost:8080/docs/api

Monorepo scripts from repo root:

```powershell
.\scripts\qa.ps1              # full QA gate
.\scripts\format.ps1          # auto-fix PHP style
.\scripts\openapi.ps1         # export OpenAPI → docs/openapi.json
.\scripts\install-hooks.ps1   # git pre-commit hooks
```

### Flutter

If `mobile/android/` is missing, scaffold platform folders first:
```bash
cd mobile
flutter create . --org uz.livecommerce --project-name livecommerce_mobile
```

```bash
cd mobile
flutter pub get
flutter analyze
flutter run
```

API base URL — default targets Android emulator (`10.0.2.2` = host localhost):

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1
```

Constants live in `mobile/lib/core/constants/api_constants.dart`.

---

## Testing

### Backend (Pest)

```bash
# Via Docker
docker compose -f docker/docker-compose.yml exec app composer test

# Full quality gate
docker compose -f docker/docker-compose.yml exec app composer qa

# From repo root
.\scripts\qa.ps1        # Windows
./scripts/qa.sh         # Linux / macOS
```

Tests live in `backend/tests/Feature/` and `backend/tests/Unit/`.

### Mobile

```bash
cd mobile
flutter analyze
flutter test
```

### Git hooks

Pre-commit runs lint + static analysis on staged backend/mobile files:

```powershell
.\scripts\install-hooks.ps1
```

Optional: [Lefthook](https://github.com/evilmartians/lefthook) via `lefthook install` (`lefthook.yml` in repo root).

---

## CI

GitHub Actions run on pull requests to `develop` and `main`:

| Workflow | Triggers | Checks |
|----------|----------|--------|
| [`backend-ci.yml`](.github/workflows/backend-ci.yml) | `backend/**` | Pint, PHPStan, Pest (Postgres + Redis services) |
| [`mobile-ci.yml`](.github/workflows/mobile-ci.yml) | `mobile/**` | `flutter analyze`, `flutter test`, APK compile |

Branch protection (recommended): `main` and `develop` require passing CI before merge.

---

## Roadmap

17 two-week sprints (Sprint 0–16), ~8–9 months to v1.0.

| Phase | Sprints | Focus |
|-------|---------|-------|
| Engineering Foundation | 0 | Docs, Docker, CI, skeleton |
| Core Platform | 1, 2.1–2.3, 3.1–3.4 | Auth, social, feed, upload, interactions, recommendations |
| Commerce | 4–7 | Products, cart, orders, payments |
| Seller & Live | 5–6 | Store, live streaming |
| Growth & Admin | 12–16 | Analytics, admin, hardening |

**Current progress:**

| Sprint | Status |
|--------|--------|
| 0.1 — Infrastructure | Complete |
| 0.2 — Developer Experience | Complete |
| 0.3 — Project Skeleton | Complete |
| 1 — Authentication & Users | Complete |
| 2.1 — Follow System | Complete |
| 2.2 — Notifications Foundation | Complete |
| 2.3 — Feed Foundation | Complete |
| 3.1 — Video Upload Foundation | Complete |
| 3.2 — Video Processing | [Approved](./blueprints/video-processing.md) — ready for implementation |

Full plan: [Roadmap](./13_ROADMAP.md) · [Master Plan](./12_MASTER_PLAN.md)

---

## Contributing

1. Branch from `develop`: `feature/<name>` or `fix/<name>`
2. Follow [Engineering Rules](./docs/06_ENGINEERING_RULES.md) and [ADR](./07_ADR.md)
3. Run `.\scripts\qa.ps1` before opening a PR
4. Use [Conventional Commits](https://www.conventionalcommits.org/): `feat(auth): add OTP verification`
5. Open PR to `develop` — require CI green and code review
6. No direct commits to `main` or `develop`

**Before implementing a feature:**

- Check [API Specification](./docs/04_API_SPECIFICATION.md) and [Database Design](./docs/03_DATABASE_DESIGN.md)
- Add migration + tests with the feature
- Update docs if the contract changes

---

## Documentation Map

### Business & vision

| Document | Description |
|----------|-------------|
| [PRD](./docs01_PRD.md) | Product requirements, MVP scope, personas |
| [Project Context](./PROJECT_CONTEXT.md) | Overview and tech stack summary |
| [Master Plan](./12_MASTER_PLAN.md) | Unified project vision |
| [Roadmap](./13_ROADMAP.md) | Sprint plan and timeline |

### Architecture

| Document | Description |
|----------|-------------|
| [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md) | High-level system design |
| [Database Design](./docs/03_DATABASE_DESIGN.md) | Schema, ER diagram, indexes |
| [API Specification](./docs/04_API_SPECIFICATION.md) | REST endpoints and contracts |
| [Project Structure](./docs/05_PROJECT_STRUCTURE.md) | Folder layout and conventions |
| [Engineering Rules](./docs/06_ENGINEERING_RULES.md) | Coding standards, git, CI |
| [ADR](./07_ADR.md) | Architecture decision records |
| [Modules](./08_MODULES.md) | Business module definitions |
| [Event Flow](./09_EVENT_FLOW.md) | Domain events catalog |
| [State Machines](./10_STATE_MACHINES.md) | Entity lifecycle rules |
| [Design System](./11_DESIGN_SYSTEM.md) | UI tokens and patterns |

### Audit & freeze

| Document | Description |
|----------|-------------|
| [Validation Report](./docs/14_VALIDATION_REPORT.md) | Cross-document consistency audit |
| [Repository Tree](./docs/15_REPOSITORY_TREE.md) | Target repo layout |
| [Technology Audit](./docs/16_TECHNOLOGY_AUDIT.md) | Stack compatibility |
| [Dependency Matrix](./docs/17_DEPENDENCY_MATRIX.md) | Module dependencies |
| [Architecture Freeze Report](./docs/18_ARCHITECTURE_FREEZE_REPORT.md) | Freeze approval v1.0 |

### Sprint notes

| Document | Description |
|----------|-------------|
| [Sprint 0.1 — Infrastructure](./docs/SPRINT_0.1_INFRASTRUCTURE.md) | Docker, scaffold, CI |
| [Sprint 0.2 — Developer Experience](./docs/SPRINT_0.2_DEVELOPER_EXPERIENCE.md) | Lint, hooks, Swagger, VS Code |
| [Sprint 0.3 — Project Skeleton](./docs/SPRINT_0.3_PROJECT_SKELETON.md) | Base classes, repos, services |
| [Sprint 1 — Authentication](./docs/SPRINT_1_AUTHENTICATION.md) | JWT auth, OTP, profile, mobile auth UX |
| [Sprint 2.3 — Feed Foundation](./docs/SPRINT_2.3_FEED_FOUNDATION.md) | For You / Following feeds, cursor pagination |
| [Sprint 3.1 — Upload Blueprint](./docs/SPRINT_3.1_BLUEPRINT.md) | StorageService, presigned upload, pipeline skeleton |
| [Video Processing Blueprint](./blueprints/video-processing.md) | Sprint 3.2 — FFmpeg, HLS, publish (approved) |
| [Sprint 3.3 — Interactions Blueprint](./docs/SPRINT_3.3_BLUEPRINT.md) | Likes, comments, views, metrics |
| [Sprint 3.4 — Recommendations Blueprint](./docs/SPRINT_3.4_BLUEPRINT.md) | Trending, popular, new, For You rules |
| [Environment Verification](./docs/19_ENVIRONMENT_VERIFICATION.md) | Docker/Flutter gate before Sprint 1 sign-off |

---

## License

Proprietary — All rights reserved.
