# Repository Tree — Final Design

Version: 1.0  
Project: LiveCommerce Platform  
Audit Type: Architecture Freeze v1.0 — Task 2  
Date: 2026-06-27  
Status: Approved for Scaffold (No Code)

---

## Purpose

Define the final monorepo directory structure. Every module has a logical, unambiguous location. This document is the blueprint for Sprint 0 repository scaffolding.

**No code is generated.** Structure only.

---

## Monorepo Root

```
livecommerce/
├── .github/                          # CI/CD workflows
├── .vscode/                          # Optional: shared editor settings (non-secret)
├── backend/                          # Laravel 12 REST API
├── mobile/                           # Flutter application
├── web-admin/                        # Admin panel (Sprint 14 — Laravel Blade or Inertia)
├── docker/                           # Local development infrastructure
├── docs/                             # Engineering documentation (authoritative)
├── scripts/                          # Dev/ops helper scripts (no business logic)
├── docs01_PRD.md                     # Product requirements
├── PROJECT_CONTEXT.md                # Project overview (slim; links to docs/)
├── 07_ADR.md                         # Architecture decisions
├── 08_MODULES.md                     # Module definitions
├── 09_EVENT_FLOW.md                  # Domain events
├── 10_STATE_MACHINES.md              # Entity lifecycles
├── 11_DESIGN_SYSTEM.md               # UI design tokens
├── 12_MASTER_PLAN.md                 # Unified project vision
├── 13_ROADMAP.md                     # Sprint plan
├── .editorconfig
├── .gitignore
└── README.md                         # Monorepo entry point + quick start
```

---

## Documentation Layout (`docs/`)

```
docs/
├── 02_SYSTEM_ARCHITECTURE.md
├── 03_DATABASE_DESIGN.md
├── 04_API_SPECIFICATION.md
├── 05_PROJECT_STRUCTURE.md
├── 06_ENGINEERING_RULES.md
├── 14_VALIDATION_REPORT.md           # This freeze audit
├── 15_REPOSITORY_TREE.md             # This document
├── 16_TECHNOLOGY_AUDIT.md
├── 17_DEPENDENCY_MATRIX.md
├── 18_ARCHITECTURE_FREEZE_REPORT.md
└── adr/                              # Future ADR appendices (ADR-013+)
    └── (empty until new decisions)
```

---

## Docker Infrastructure (`docker/`)

```
docker/
├── docker-compose.yml                # Orchestrates all local services
├── docker-compose.override.yml.example
├── php/
│   ├── Dockerfile                    # PHP 8.4-FPM for Laravel
│   └── php.ini
├── nginx/
│   └── default.conf                  # Reverse proxy → php-fpm
└── postgres/
    └── init.sql                      # Extensions: pgcrypto, uuid-ossp
```

### Docker Compose Services

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| `app` | Custom PHP 8.4-FPM | — | Laravel application |
| `nginx` | nginx:alpine | 8080 | HTTP entry |
| `postgres` | postgres:16-alpine | 5432 | Primary database |
| `redis` | redis:7-alpine | 6379 | Cache + queues |
| `minio` | minio/minio | 9000, 9001 | S3-compatible storage |
| `mailpit` | axllent/mailpit | 8025, 1025 | Local email capture (SMTP + UI) |
| `horizon` | Same as `app` | — | Queue worker (profile: workers) |

---

## Backend (`backend/`)

```
backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── CleanupExpiredTokensCommand.php
│   │       ├── CleanupOldNotificationsCommand.php
│   │       ├── FlushViewCountsCommand.php
│   │       ├── CancelStaleOrdersCommand.php
│   │       └── AutoConfirmDeliveredOrdersCommand.php
│   │
│   ├── Contracts/
│   │   ├── Repositories/             # Repository interfaces (1 per aggregate)
│   │   └── Services/                 # External adapter interfaces
│   │       ├── StreamingProviderInterface.php
│   │       ├── PaymentGatewayInterface.php
│   │       ├── SmsProviderInterface.php
│   │       ├── PushNotificationInterface.php
│   │       └── AiProviderInterface.php
│   │
│   ├── Events/                       # Domain events (09_EVENT_FLOW.md)
│   │   ├── UserRegistered.php
│   │   ├── UserFollowed.php
│   │   ├── VideoUploaded.php
│   │   ├── VideoProcessed.php
│   │   ├── VideoPublished.php
│   │   ├── VideoLiked.php
│   │   ├── CommentCreated.php
│   │   ├── ProductCreated.php
│   │   ├── OrderPlaced.php
│   │   ├── OrderPaid.php
│   │   ├── OrderCancelled.php
│   │   ├── OrderShipped.php
│   │   ├── LiveStreamStarted.php
│   │   ├── LiveStreamEnded.php
│   │   ├── SellerVerified.php
│   │   ├── NotificationSent.php
│   │   └── MessageSent.php         # Sprint 8
│   │
│   ├── Exceptions/
│   │   ├── Handler.php
│   │   ├── BusinessException.php
│   │   ├── InvalidStateTransitionException.php
│   │   ├── InsufficientStockException.php
│   │   └── PaymentFailedException.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   │           ├── AuthController.php              # Module: Authentication
│   │   │           ├── UserController.php              # Module: Users, Followers
│   │   │           ├── FeedController.php              # Module: Feed
│   │   │           ├── VideoController.php             # Module: Videos
│   │   │           ├── ProductController.php           # Module: Products
│   │   │           ├── CategoryController.php          # Module: Categories
│   │   │           ├── CartController.php              # Module: Orders (cart)
│   │   │           ├── OrderController.php             # Module: Orders
│   │   │           ├── StoreController.php             # Module: Seller
│   │   │           ├── LiveStreamController.php        # Module: Live Streaming
│   │   │           ├── SearchController.php            # Module: Search
│   │   │           ├── NotificationController.php      # Module: Notifications
│   │   │           ├── MediaController.php             # Module: Media (shared)
│   │   │           ├── DeviceController.php            # Module: Notifications
│   │   │           ├── ConversationController.php      # Module: Messaging (Sprint 8)
│   │   │           ├── WebhookController.php           # Module: Payments
│   │   │           ├── HealthController.php
│   │   │           ├── Seller/
│   │   │           │   ├── DashboardController.php     # Module: Seller
│   │   │           │   ├── ProductController.php       # Module: Products
│   │   │           │   ├── OrderController.php         # Module: Orders
│   │   │           │   └── AnalyticsController.php     # Module: Analytics (Sprint 12)
│   │   │           └── Admin/                          # Module: Admin (Sprint 14)
│   │   │               ├── DashboardController.php
│   │   │               ├── UserController.php
│   │   │               ├── SellerController.php
│   │   │               ├── ModerationController.php
│   │   │               ├── CategoryController.php
│   │   │               └── AuditLogController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── AuthenticateApi.php
│   │   │   ├── EnsureSeller.php
│   │   │   ├── EnsureRole.php
│   │   │   └── SetLocale.php
│   │   │
│   │   ├── Requests/                 # 1 folder per module
│   │   │   ├── Auth/
│   │   │   ├── User/
│   │   │   ├── Video/
│   │   │   ├── Product/
│   │   │   ├── Order/
│   │   │   ├── Cart/
│   │   │   ├── LiveStream/
│   │   │   ├── Seller/
│   │   │   ├── Messaging/            # Sprint 8
│   │   │   └── Admin/                # Sprint 14
│   │   │
│   │   └── Resources/                # API response transformers
│   │       ├── UserResource.php
│   │       ├── UserCompactResource.php
│   │       ├── VideoResource.php
│   │       ├── ProductResource.php
│   │       ├── OrderResource.php
│   │       ├── LiveStreamResource.php
│   │       ├── NotificationResource.php
│   │       └── ...
│   │
│   ├── Jobs/                         # Async tasks
│   │   ├── ProcessVideoJob.php
│   │   ├── SendPushNotificationJob.php
│   │   ├── SendBulkPushNotificationJob.php
│   │   ├── SendEmailJob.php
│   │   ├── DecrementInventoryJob.php
│   │   ├── FlushViewCountsJob.php
│   │   ├── ProcessPaymentWebhookJob.php
│   │   ├── ModerateContentJob.php          # Sprint 9
│   │   └── GenerateRecommendationJob.php   # Sprint 9
│   │
│   ├── Listeners/                    # Event consumers
│   │   ├── CreateUserProfile.php
│   │   ├── NotifyOnFollow.php
│   │   ├── NotifyOnLike.php
│   │   ├── NotifyOnComment.php
│   │   ├── NotifyOnOrderPlaced.php
│   │   ├── NotifyOnLiveStreamStarted.php
│   │   └── QueueVideoProcessing.php
│   │
│   ├── Models/                       # 1 model per DB table
│   ├── Policies/                     # 1 policy per authorizable model
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   └── RepositoryServiceProvider.php
│   │
│   ├── Repositories/
│   │   └── Eloquent/                 # Repository implementations
│   │
│   └── Services/                     # Business logic (1 folder per module)
│       ├── Auth/
│       ├── User/
│       ├── Follow/
│       ├── Video/
│       ├── Feed/
│       ├── Product/
│       ├── Category/
│       ├── Cart/
│       ├── Order/
│       ├── Store/
│       ├── LiveStream/
│       │   └── Providers/            # Agora, 100ms, ZEGOCLOUD
│       ├── Notification/
│       ├── Search/
│       ├── Recommendation/
│       ├── Media/
│       ├── Payment/
│       │   └── Providers/            # Click, Payme, Uzum
│       ├── Messaging/                # Sprint 8
│       ├── Analytics/                # Sprint 12
│       ├── Admin/                    # Sprint 14
│       ├── Audit/
│       └── Ai/
│           └── Providers/            # OpenAI, local ML
│
├── bootstrap/
├── config/
│   ├── streaming.php                 # Provider selection
│   ├── payment.php
│   ├── ai.php
│   └── livecommerce.php              # Platform-wide settings
│
├── database/
│   ├── migrations/                   # Ordered per 03_DATABASE_DESIGN.md
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── api.php                       # All /api/v1 routes
│   └── console.php
│
├── tests/
│   ├── Feature/                      # Mirrors module structure
│   │   ├── Auth/
│   │   ├── Video/
│   │   ├── Product/
│   │   ├── Order/
│   │   ├── Seller/
│   │   ├── LiveStream/
│   │   ├── Messaging/                # Sprint 8
│   │   └── Admin/                    # Sprint 14
│   ├── Unit/
│   │   ├── Services/
│   │   └── Repositories/
│   ├── Pest.php                      # Pest configuration
│   └── TestCase.php
│
├── storage/
├── public/
├── .env.example
├── composer.json
├── phpstan.neon
├── phpunit.xml                       # Used by Pest
└── README.md
```

---

## Mobile (`mobile/`)

```
mobile/
├── lib/
│   ├── main.dart
│   ├── app/
│   │   ├── app.dart
│   │   ├── app_bootstrap.dart
│   │   └── router.dart               # GoRouter — all routes
│   │
│   ├── core/                         # Shared infrastructure (no business logic)
│   │   ├── constants/
│   │   ├── errors/
│   │   ├── network/
│   │   ├── storage/
│   │   ├── theme/                    # 11_DESIGN_SYSTEM.md tokens
│   │   ├── l10n/
│   │   └── utils/
│   │
│   ├── features/                     # 1 folder per module
│   │   ├── auth/                     # Sprint 1
│   │   ├── profile/                  # Sprint 1
│   │   ├── feed/                     # Sprint 3
│   │   ├── video/                    # Sprint 3
│   │   ├── product/                  # Sprint 4
│   │   ├── cart/                     # Sprint 4
│   │   ├── checkout/                 # Sprint 4
│   │   ├── orders/                   # Sprint 4
│   │   ├── search/                   # Sprint 2–4
│   │   ├── notifications/            # Sprint 2
│   │   ├── live/                     # Sprint 6
│   │   ├── seller/                   # Sprint 5
│   │   ├── messaging/                # Sprint 8
│   │   └── settings/                 # Sprint 1
│   │
│   └── shared/
│       └── widgets/                  # 11_DESIGN_SYSTEM.md components
│
├── assets/
│   ├── images/
│   ├── fonts/
│   └── l10n/
│       ├── app_uz.arb
│       └── app_ru.arb
│
├── test/                             # Mirrors features/
├── integration_test/                 # Critical user flows
├── android/
├── ios/
├── pubspec.yaml
├── analysis_options.yaml
├── l10n.yaml
└── README.md
```

---

## Web Admin (`web-admin/`) — Sprint 14

```
web-admin/
├── (Laravel Inertia + Vue/React OR Blade — decision at Sprint 14)
├── Shares backend/ database and Admin services
└── Deployed as separate route group or subdomain: admin.livecommerce.uz
```

**Freeze decision:** Admin panel reuses `backend/` Admin services. Separate frontend scaffold deferred to Sprint 14. Placeholder folder created in Sprint 0 with README only.

---

## CI/CD (`.github/`)

```
.github/
├── workflows/
│   ├── backend-ci.yml                # Pint, PHPStan, Pest
│   ├── mobile-ci.yml                 # analyze, test, build
│   └── deploy-staging.yml            # Deploy on merge to develop
├── PULL_REQUEST_TEMPLATE.md
└── CODEOWNERS                        # Optional
```

---

## Scripts (`scripts/`)

```
scripts/
├── setup.sh                          # First-time dev environment setup
├── migrate-fresh.sh                  # Reset local database
├── seed-demo.sh                      # Load demo data
└── export-openapi.sh                 # Generate OpenAPI spec (Sprint 16)
```

---

## Module → Location Mapping

| Module | Backend Service | Backend Controller(s) | Mobile Feature | Sprint |
|--------|----------------|----------------------|----------------|--------|
| Authentication | `Services/Auth/` | `AuthController` | `features/auth/` | 1 |
| Users / Profiles | `Services/User/` | `UserController` | `features/profile/` | 1 |
| Followers | `Services/Follow/` | `UserController` | `features/profile/` | 2 |
| Videos | `Services/Video/` | `VideoController` | `features/video/` | 3 |
| Feed | `Services/Feed/` | `FeedController` | `features/feed/` | 3 |
| Recommendations | `Services/Recommendation/` | (internal) | `features/feed/` | 3, 9 |
| Products | `Services/Product/` | `ProductController`, `Seller/ProductController` | `features/product/` | 4 |
| Categories | `Services/Category/` | `CategoryController` | `features/product/` | 4 |
| Orders | `Services/Cart/`, `Services/Order/` | `CartController`, `OrderController` | `features/cart/`, `checkout/`, `orders/` | 4 |
| Payments | `Services/Payment/` | `WebhookController`, `OrderController` | `features/checkout/` | 7 |
| Seller | `Services/Store/` | `Seller/*`, `StoreController` | `features/seller/` | 5 |
| Live Streaming | `Services/LiveStream/` | `LiveStreamController` | `features/live/` | 6 |
| Notifications | `Services/Notification/` | `NotificationController`, `DeviceController` | `features/notifications/` | 2 |
| Search | `Services/Search/` | `SearchController` | `features/search/` | 2–4 |
| Messaging | `Services/Messaging/` | `ConversationController` | `features/messaging/` | 8 |
| Analytics | `Services/Analytics/` | `Seller/AnalyticsController` | `features/seller/` | 12 |
| Admin | `Services/Admin/` | `Admin/*` | — (web-admin) | 14 |
| AI | `Services/Ai/` | `AiController` (Phase 2) | various | 9–11 |
| Media | `Services/Media/` | `MediaController` | shared | 3 |

---

## Files Explicitly Excluded from Repo

| Item | Reason |
|------|--------|
| `.env` | Secrets — never committed |
| `vendor/`, `node_modules/` | Dependencies |
| `storage/logs/*` | Runtime logs |
| `mobile/build/` | Build artifacts |
| `*.pem`, `*.key` | Certificates |
| `google-services.json` (real) | Use `.example` template |

---

## Scaffold Order (Sprint 0)

1. Create root `README.md`, `.gitignore`, `.editorconfig`
2. Create `docker/` + `docker-compose.yml` (all 7 services)
3. `composer create-project` → `backend/`
4. `flutter create` → `mobile/`
5. Create `backend/app/Contracts/`, `Services/`, empty module folders
6. Create `mobile/lib/core/`, `features/` placeholder READMEs
7. Create `web-admin/README.md` (Sprint 14 placeholder)
8. Create `.github/workflows/` (CI skeleton)
9. Create `scripts/setup.sh`
10. Verify `docker compose up` + `php artisan test` + `flutter analyze` pass

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Architecture Review | Final repository tree for Architecture Freeze v1.0 |

---

**Related:** [Validation Report](./14_VALIDATION_REPORT.md) · [Architecture Freeze Report](./18_ARCHITECTURE_FREEZE_REPORT.md)
