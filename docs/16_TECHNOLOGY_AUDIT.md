# Technology Audit

Version: 1.0  
Project: LiveCommerce Platform  
Audit Type: Architecture Freeze v1.0 — Task 3  
Date: 2026-06-27  
Status: Complete

---

## Executive Summary

All selected technologies are **compatible** for the LiveCommerce platform. No blocking incompatibilities were found. Three items require documentation decisions (Pest, OpenAPI tooling, Mailpit) before Sprint 0 scaffold.

| Category | Verdict |
|----------|---------|
| Backend stack | ✔ Compatible |
| Mobile stack | ✔ Compatible |
| Data layer | ✔ Compatible |
| Infrastructure | ✔ Compatible |
| Testing | ✔ Compatible (decision needed: Pest vs PHPUnit) |
| Observability | ✔ Compatible |
| External services | ✔ Compatible |

**Overall technology readiness:** 94 / 100

---

## Technology Compatibility Matrix

### Backend Core

| Technology | Version | Compatible With | Status | Notes |
|------------|---------|-----------------|--------|-------|
| **Laravel** | 12.x | PHP 8.2–8.5 | ✔ Pass | Official support through Feb 2027 (security) |
| **PHP** | 8.4+ | Laravel 12 | ✔ Pass | Supported; 8.3 recommended for max stability, 8.4 fully works |
| **PostgreSQL** | 16+ | Laravel 12, Eloquent | ✔ Pass | Native driver `pdo_pgsql` |
| **Redis** | 7+ | Laravel Horizon, cache, queues | ✔ Pass | `phpredis` or `predis` extension |

**Laravel 12 + PHP 8.4:** Officially supported. Laravel 13 (Q1 2026) requires PHP 8.3+ — migration path available when needed. **No change required for freeze.**

---

### Mobile Core

| Technology | Version | Compatible With | Status | Notes |
|------------|---------|-----------------|--------|-------|
| **Flutter** | 3.x (stable) | Dart 3.x | ✔ Pass | Android + iOS from single codebase |
| **Dart** | 3.x | Flutter 3.x | ✔ Pass | Required for latest Flutter |
| **Riverpod** | 2.x | Flutter 3.x | ✔ Pass | No BuildContext dependency |
| **GoRouter** | 14.x | Flutter 3.x, Riverpod | ✔ Pass | Declarative routing, deep links |
| **Dio** | 5.x | Flutter 3.x | ✔ Pass | HTTP client with interceptors |

**Riverpod + GoRouter:** Standard combination. No conflicts. Riverpod providers can be read in GoRouter redirect callbacks via `ProviderContainer`.

---

### Mobile + Backend Integration

| Combination | Status | Notes |
|-------------|--------|-------|
| Flutter Dio → Laravel REST API | ✔ Pass | JSON over HTTPS |
| JWT (tymon/jwt-auth or php-open-source-saver/jwt-auth) | ✔ Pass | Laravel 12 compatible packages available |
| Flutter secure storage + JWT refresh | ✔ Pass | `flutter_secure_storage` for refresh tokens |
| Pre-signed S3 upload from Flutter | ✔ Pass | Direct PUT to MinIO/R2; no Laravel file proxy |
| Agora Flutter SDK + Laravel token API | ✔ Pass | RTC tokens generated server-side |
| FCM + Laravel queue notifications | ✔ Pass | `kreait/laravel-firebase` or HTTP FCM API |

---

### Infrastructure

| Technology | Version | Compatible With | Status | Notes |
|------------|---------|-----------------|--------|-------|
| **Docker** | 24+ | All services | ✔ Pass | Windows via Docker Desktop |
| **Docker Compose** | v2 | PHP, Nginx, Postgres, Redis, MinIO | ✔ Pass | Local dev standard |
| **Nginx** | alpine | PHP-FPM 8.4 | ✔ Pass | Reverse proxy |
| **MinIO** | latest | Laravel S3 driver | ✔ Pass | `AWS_USE_PATH_STYLE_ENDPOINT=true` |
| **Mailpit** | latest | Laravel SMTP mailer | ✔ Pass | **Not yet in docs — add to Docker** |
| **Cloudflare R2** | — | S3-compatible API | ✔ Pass | Production storage |
| **Cloudflare CDN** | — | R2, custom origins | ✔ Pass | Media delivery |

**Mailpit compatibility:**
```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
```
Web UI at `http://localhost:8025`. Fully compatible with Laravel 12. **Recommend adding to Sprint 0 Docker Compose.**

---

### Testing

| Technology | Version | Compatible With | Status | Notes |
|------------|---------|-----------------|--------|-------|
| **PHPUnit** | 11.x | Laravel 12 | ✔ Pass | Laravel default |
| **Pest** | 3.x | Laravel 12, PHPUnit 11 | ✔ Pass | Built on PHPUnit; recommended |
| **PHPStan** | Level 6+ | Laravel 12 | ✔ Pass | Use `larastan/larastan` |
| **Laravel Pint** | latest | PHP 8.4 | ✔ Pass | PSR-12 formatter |
| **flutter test** | — | Flutter 3.x | ✔ Pass | Unit + widget tests |
| **integration_test** | — | Flutter 3.x | ✔ Pass | E2E flows |
| **mocktail** | — | Flutter 3.x | ✔ Pass | Mocking for Dart |

**Pest vs PHPUnit:**

| Aspect | PHPUnit | Pest |
|--------|---------|------|
| Laravel 12 support | Native | ✔ Via pestphp/pest-plugin-laravel |
| Syntax | Class-based | Expressive, less boilerplate |
| CI compatibility | ✔ | ✔ (uses PHPUnit runner) |
| Team learning curve | Lower | Low–medium |
| Engineering Rules doc | Currently PHPUnit | **Needs ADR-013** |

**Recommendation:** Adopt **Pest 3.x** as primary test framework. PHPUnit remains underlying engine. Update Engineering Rules.

---

### API Documentation

| Technology | Compatible With | Status | Notes |
|------------|-----------------|-------|
| **OpenAPI 3.1** | Laravel REST JSON API | ✔ Pass | Spec format |
| **Scramble** (dedoc/scramble) | Laravel 12 | ✔ Pass | Auto-generates from code |
| **L5-Swagger** | Laravel 12 | ✔ Pass | Annotation-based |
| **Manual YAML** | Any | ✔ Pass | High maintenance |

**Recommendation:** Use **Scramble** for auto-generated OpenAPI from Laravel routes/Form Requests. Export in Sprint 16.

---

### Queue & Monitoring

| Technology | Compatible With | Status | Notes |
|------------|---------|-----------------|-------|
| **Laravel Horizon** | Redis 7, Laravel 12 | ✔ Pass | Queue monitoring |
| **Laravel Telescope** | Laravel 12 (local only) | ✔ Pass | Debug tool; disabled in prod |
| **Sentry** | Laravel 12, Flutter | ✔ Pass | Error tracking both platforms |

---

### Streaming & Real-Time

| Technology | Compatible With | Status | Notes |
|------------|---------|-----------------|-------|
| **Agora RTC SDK** | Flutter 3.x (Android/iOS) | ✔ Pass | Default provider |
| **100ms SDK** | Flutter 3.x | ✔ Pass | Alternative adapter |
| **ZEGOCLOUD SDK** | Flutter 3.x | ✔ Pass | Alternative adapter |
| **WebSocket (Phase 1.1)** | Laravel Reverb / Pusher | ✔ Pass | Live chat upgrade path |

**Note:** Live chat MVP uses REST polling — no WebSocket required for freeze.

---

### Payment Gateways (Uzbekistan)

| Gateway | Laravel Integration | Status | Notes |
|---------|-------------------|--------|-------|
| **Click** | REST API + webhooks | ✔ Compatible | Primary candidate |
| **Payme** | REST API + webhooks | ✔ Compatible | Alternative |
| **Uzum** | REST API + webhooks | ✔ Compatible | Alternative |

All integrate via `PaymentGatewayInterface` adapter. No PCI storage on platform.

---

### AI Providers (Phase 2)

| Provider | Laravel Integration | Status | Notes |
|----------|-------------------|--------|-------|
| **OpenAI API** | HTTP client | ✔ Compatible | Via AiProviderInterface |
| **Local ML models** | Python sidecar or API | ✔ Compatible | Future option |

AI is async (queued). No runtime dependency for MVP.

---

## Incompatible Combinations Checked

| Combination | Result |
|-------------|--------|
| Laravel 12 + PHP 8.1 | ✗ Not supported (PHP 8.2 minimum) |
| Laravel 12 + PHP 8.4 | ✔ Supported |
| Riverpod + Bloc (simultaneous) | ⚠ Avoid — pick one state manager |
| GoRouter + Navigator 1.0 mixed | ⚠ Avoid — GoRouter only |
| PostgreSQL + MySQL dual-write | ✗ Not in architecture |
| Redis queues + database queues mixed | ⚠ Use Redis only in production |
| MinIO + local disk storage mixed | ⚠ S3 driver only; MinIO for local |
| JWT + Sanctum session (simultaneous) | ⚠ JWT only for mobile API |
| Pest + separate PHPUnit test suites | ✔ Pest wraps PHPUnit — compatible |
| Flutter web as MVP client | ✗ Out of scope per PRD |
| Mailpit + real SMTP in local | ✗ Use Mailpit only locally |

**No blocking incompatibilities found for the selected stack.**

---

## PHP 8.4 Extension Requirements

Required for Laravel 12 (verify in Docker image):

```
ctype, curl, dom, fileinfo, filter, hash, mbstring,
openssl, pcre, pdo, pdo_pgsql, session, tokenizer,
xml, bcmath, intl, redis (phpredis), gd or imagick (thumbnails)
```

Optional: `ffprobe`/`ffmpeg` in worker container for video transcoding (Sprint 15).

---

## Flutter Package Compatibility (Planned)

| Package | Purpose | Compatible |
|---------|---------|------------|
| `flutter_riverpod` | State management | ✔ |
| `go_router` | Navigation | ✔ |
| `dio` | HTTP client | ✔ |
| `flutter_secure_storage` | Token storage | ✔ |
| `cached_network_image` | Image caching | ✔ |
| `video_player` + `chewie` or `media_kit` | HLS playback | ✔ |
| `agora_rtc_engine` | Live streaming | ✔ |
| `firebase_messaging` | Push notifications | ✔ |
| `hive_flutter` or `drift` | Offline cache | ✔ |
| `freezed` + `json_serializable` | Immutable models | ✔ |
| `intl` | Localization | ✔ |

**Video player note:** HLS (m3u8) playback requires `video_player` with platform HLS support (iOS native; Android via ExoPlayer). Test on mid-range Android devices in Sprint 3.

---

## Version Pinning Recommendations (Sprint 0)

| Component | Pin Strategy |
|-----------|-------------|
| PHP | `8.4` in Dockerfile |
| Laravel | `^12.0` in composer.json |
| PostgreSQL | `16-alpine` in Docker |
| Redis | `7-alpine` in Docker |
| Flutter | `stable` channel, record version in README |
| Node.js | `20 LTS` (only if admin panel uses Vite) |

---

## Technology Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Laravel 12 security support ends Feb 2027 | Low | Plan Laravel 13 upgrade in Year 2 |
| PHP 8.4 edge-case extensions | Low | Pin Docker image; test in CI |
| Agora SDK breaking changes | Medium | Abstract behind interface (done) |
| Flutter video performance on low-end Android | Medium | Test Sprint 3; fallback to lower quality HLS |
| Payment gateway API changes | Medium | Adapter pattern (done) |
| Pest adoption learning curve | Low | Document examples in Engineering Rules |

---

## Recommended ADR Additions

| ADR | Decision |
|-----|----------|
| ADR-013 | Pest 3.x as primary test framework |
| ADR-014 | Scramble for OpenAPI generation |
| ADR-015 | Mailpit for local email development |

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Architecture Review | Initial technology audit |

---

**Related:** [Validation Report](./14_VALIDATION_REPORT.md) · [Architecture Freeze Report](./18_ARCHITECTURE_FREEZE_REPORT.md)
