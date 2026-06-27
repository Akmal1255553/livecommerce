# Sprint 0.3 — Project Skeleton

**Status:** Complete  
**Depends on:** Sprint 0.1 (Infrastructure), Sprint 0.2 (Developer Experience)

## Goal

Establish the full project skeleton — module layout, base classes, interfaces, DTOs, repositories, services, standardized API responses, exception handling, logging, and health checks — so feature sprints (1+) can focus on business logic only.

---

## Backend Deliverables

### Folder Structure

Aligned with `docs/05_PROJECT_STRUCTURE.md`:

```
app/
├── Contracts/Repositories/     # 9 repository interfaces + base
├── Contracts/Services/         # External adapters + HealthServiceInterface
├── DTOs/                       # DataTransferObject base + Health, Pagination
├── Enums/ErrorCode.php
├── Exceptions/                 # BusinessException + domain exceptions
├── Http/
│   ├── Controllers/Concerns/RespondsWithJson.php
│   ├── Middleware/             # Request ID, logging, auth stubs, locale
│   ├── Responses/ApiResponse.php
│   └── Resources/ApiResource.php
├── Logging/StructuredLogger.php
├── Models/                     # Stub models for all repository domains
├── Modules/Module.php          # Module enum registry
├── Providers/RepositoryServiceProvider.php
├── Repositories/Eloquent/      # Base + 9 implementations
└── Services/                   # BaseService + 15 module service stubs
    └── Health/HealthService.php
```

### Base Classes

| Class | Purpose |
|-------|---------|
| `DataTransferObject` | Readonly DTO base with `toArray()` |
| `BaseEloquentRepository` | Shared `findById` / `findByIdOrFail` |
| `BaseService` | Injects `StructuredLogger` |
| `ApiResponse` | Standard `{ success, data, meta?, message?, errors? }` envelope |
| `ApiResource` | Base JSON resource for API output |
| `RespondsWithJson` | Controller trait wrapping `ApiResponse` |

### Exception Handling

Configured in `bootstrap/app.php` for all `/api/*` routes:

| Exception | HTTP | Envelope |
|-----------|------|----------|
| `BusinessException` | Custom | `{ success: false, message, errors? }` |
| `ValidationException` | 422 | Field errors included |
| `AuthenticationException` | 401 | Standard message |
| `NotFoundHttpException` | 404 | Resource not found |
| `TooManyRequestsHttpException` | 429 | Rate limit message |
| Unhandled (production) | 500 | Generic server error |

### Logging

- `StructuredLogger` — structured context with `request_id`
- `AssignRequestId` middleware — sets `X-Request-Id` header
- `LogApiRequest` middleware — logs method, path, status, duration

### Health Checks

- `HealthService` — checks database, Redis, queue
- `HealthStatusData` DTO — matches API spec (`queue: "running"`)
- `HealthController` — thin controller using `ApiResponse`

**Endpoint:** `GET /api/v1/health`

### Repository Bindings

All interfaces bound in `RepositoryServiceProvider`:

`User`, `Video`, `Product`, `Order`, `Cart`, `Store`, `LiveStream`, `Notification`, `Follow`

---

## Mobile Deliverables

### Core Layer

| Path | Purpose |
|------|---------|
| `core/constants/` | `app_constants`, `storage_keys` |
| `core/errors/` | Exceptions, failures, error mapping |
| `core/network/` | `ApiClient`, `LoggingInterceptor`, `NetworkInfo` |
| `core/utils/app_logger.dart` | Debug logging wrapper |

### Shared Widgets

- `LoadingIndicator`, `ErrorDisplay`, `EmptyState`

### Feature Placeholders

- `features/auth/`, `features/feed/` — module stubs for Clean Architecture expansion

---

## Tests

| Test | Coverage |
|------|----------|
| `HealthCheckTest` | API spec compliance + request ID header |
| `ExceptionHandlingTest` | JSON error envelope + ApiResponse |
| `HealthServiceTest` | HealthService DTO structure |
| `error_handler_test.dart` | Mobile exception → failure mapping |

---

## Verification

```powershell
# Backend (Docker running, vendor installed)
docker compose -f docker/docker-compose.yml exec app composer qa

# Mobile
cd mobile && flutter analyze && flutter test
```

---

## Next Sprint

**Sprint 1 — Authentication & Users:** JWT, migrations, AuthService implementation, mobile auth screens.
