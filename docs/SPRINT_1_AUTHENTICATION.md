# Sprint 1 — Authentication & Users

**Status:** Implemented — **blocked on [Environment Verification](./19_ENVIRONMENT_VERIFICATION.md) PASS**
**Phase:** 1 — Core Platform  
**Depends on:** Sprint 0

## Summary

Sprint 1 delivers JWT authentication (access + refresh with rotation), phone OTP verification, password reset, user profile management, and the mobile auth UX (login, register, OTP, profile, settings with locale and dark mode).

## Backend

### Migrations

| Table | Purpose |
|-------|---------|
| `users` | UUID primary key, username, email, phone, role, status, locale |
| `user_profiles` | Display name, counters, notification settings |
| `user_devices` | FCM tokens (schema ready; wiring in later sprints) |
| `refresh_tokens` | Hashed refresh tokens with rotation |

### Services

- `JwtService` — HS256 access tokens (`firebase/php-jwt`)
- `OtpService` — Redis/cache OTP with 60s resend cooldown
- `AuthService` — register, login, OTP, refresh, logout, password reset
- `ProfileService` — profile CRUD, password change, account deletion
- `StubSmsProvider` — logs OTP in non-production

### API Endpoints

All under `/api/v1`:

| Method | Path | Auth |
|--------|------|------|
| POST | `/auth/register` | No |
| POST | `/auth/login` | No |
| POST | `/auth/verify-otp` | No |
| POST | `/auth/resend-otp` | No |
| POST | `/auth/refresh` | No |
| POST | `/auth/logout` | Yes |
| POST | `/auth/forgot-password` | No |
| POST | `/auth/reset-password` | No |
| GET | `/me` | Yes |
| PUT | `/me` | Yes |
| PUT | `/me/password` | Yes |
| DELETE | `/me` | Yes |

### Tests

`backend/tests/Feature/Auth/AuthTest.php` — register, login, OTP, refresh rotation, logout, profile, password reset.

Run (Docker):

```bash
docker compose -f docker/docker-compose.yml exec app composer install
docker compose -f docker/docker-compose.yml exec app php artisan migrate --force
docker compose -f docker/docker-compose.yml exec app ./vendor/bin/pest
```

## Mobile

### Feature module

`mobile/lib/features/auth/` — data layer (`AuthRepository`), Riverpod providers, screens:

- Login, Register, OTP verification
- Profile (view/edit)
- Settings (locale UZ/RU, dark mode, logout)

### Core integrations

- `SecureTokenStorage` — `flutter_secure_storage`
- `AuthInterceptor` — Bearer header + auto-refresh on 401
- Router guards via `go_router` redirect

### Localization

Auth and profile strings in `lib/core/l10n/app_uz.arb` and `app_ru.arb`.

### Tests

`mobile/test/features/auth/auth_form_test.dart` — widget tests for auth forms.

Run:

```bash
cd mobile
flutter pub get
flutter gen-l10n
flutter test
```

## Configuration

`backend/.env`:

```
JWT_SECRET=your-64-char-secret
JWT_TTL=15
JWT_REFRESH_TTL=43200
```

## Acceptance criteria

> Sprint 1 is **not signed off** until `scripts/verify-env.ps1` reports **Overall status: PASS** (see [19_ENVIRONMENT_VERIFICATION.md](./19_ENVIRONMENT_VERIFICATION.md)).

| Criterion | Status |
|-----------|--------|
| Register with email or phone | Done |
| Phone OTP verification | Done |
| Login with email/phone/username | Done |
| JWT + refresh rotation | Done |
| Logout revokes refresh token | Done |
| Profile view/edit | Done |
| Password reset via email | Done |
| Roles defined + middleware | Done |
| Dark mode + locale (mobile) | Done |
| Feature tests | Done |

## Next sprint

**Sprint 2 — Social Foundation** (follow, likes, comments, bookmarks, notifications).
