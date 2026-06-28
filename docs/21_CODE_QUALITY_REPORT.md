# Code Quality Report — PHPStan Level 6 Cleanup

**Date:** 2026-06-28  
**Branch:** `feature/sprint-3.3-interactions`  
**PHPStan level:** 6 (unchanged)  
**Baseline:** none (`baseline.neon` not introduced)

---

## Summary

| Metric | Count |
|--------|------:|
| Original PHPStan errors | 123 |
| Fixed | 123 |
| Remaining | **0** |

Post-cleanup verification (Docker `app` container):

| Check | Result |
|-------|--------|
| `./vendor/bin/phpstan analyse --memory-limit=512M` | ✅ 0 errors (243 files) |
| `./vendor/bin/pint --test` | ✅ 278 files pass |
| `php artisan test` | ✅ 102 tests pass |

---

## Categories of Fixes

### 1. Eloquent model typing (15 models)

Added `@property` PHPDoc for enum casts (`VideoStatus`, `UserRole`, `NotificationType`, etc.), datetime fields, and counters. Added generic relation return types (`BelongsTo<Related, $this>`, `HasOne<UserProfile, $this>`).

**Impact:** Eliminates false `string` vs enum comparisons across services, resources, and state machine code.

### 2. Repository layer generics (16 repositories + base)

- `BaseEloquentRepository`: `@template TModel` with `@return TModel|null` / `@return TModel` on find methods.
- All concrete repos: `@extends BaseEloquentRepository<Model>`.
- Query results: explicit `@var` assertions where Eloquent returns `Model` instead of concrete type.

### 3. Interface & DTO iterable types

Added `array<string, mixed>`, `array{id: string, created_at: string}|null`, `array{message: string, retry_after: int}`, and paginator generics to contracts, DTOs, and controller traits.

### 4. HTTP layer

- **Removed** unused `ApiResource` (invalid abstract override of `JsonResource::toArray()`).
- Fixed `ApiResponse` PHPDoc; added `CursorPaginationData<mixed>` generic.
- Replaced unnecessary nullsafe operators in resources/services with explicit null checks.
- `SetLocale`: `$request->headers->get()` instead of `header()` (template type resolution).

### 5. Service-layer correctness

- **MetricsService:** removed redundant `??` on typed batch event arrays.
- **VideoInteractionService:** Redis `SET NX EX` → `Cache::add()` / `Cache::forget()` (Laravel-idiomatic, PHPStan-safe).
- **ProfileService:** removed unused `UserRepositoryInterface` dependency.
- **PublishVideoStep:** HLS master playlist variants typed as `array<string, string>` via `array_combine`.
- **CliFfmpegTranscoder:** removed redundant `array_values()` on list.
- **NotifyOnFollow:** `instanceof User` guard after repository lookup.
- **S3StorageDriver:** `FilesystemAdapter` return type for `temporaryUploadUrl()`.

### 6. Code style (Pint)

Normalized LF line endings and `fully_qualified_strict_types` imports across backend (278 files). Ensures Linux CI passes `./vendor/bin/pint --test`.

---

## Architectural Improvements

1. **Typed repository contract** — `BaseEloquentRepository<TModel>` propagates concrete model types to callers, reducing `instanceof` casts at service boundaries.

2. **Enum-aware models** — Central `@property` annotations document cast behavior for PHPStan without runtime changes; services compare enums directly against `VideoStatus::Published`, etc.

3. **Cache over raw Redis for dedup/locks** — View deduplication and like locks use `Cache::add()` with TTL instead of phpredis-specific `SET … NX EX` signatures, aligning with Laravel conventions and static analysis.

4. **Removed dead abstraction** — `ApiResource` was unused and violated Laravel's `JsonResource` contract; resources extend `JsonResource` directly.

---

## CI Gate

Backend CI (`/.github/workflows/backend-ci.yml`) must pass all three steps before Sprint 3.3 merge:

1. Run Pint (code style)
2. Run PHPStan
3. Run tests

---

## Related Docs

- [ANALYTICS_LAYER.md](./ANALYTICS_LAYER.md) — Sprint 3.3 analytics funnel
- [SPRINT_3.3_BLUEPRINT.md](./SPRINT_3.3_BLUEPRINT.md) — Video interactions spec
