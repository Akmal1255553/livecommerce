# Local PHP + Supabase (no Docker, no Railway)

Use when Railway trial/plan is unavailable. Stack still matches ADR-018 for **Postgres** (Supabase); API runs on the Windows host.

## Prerequisites

1. **PHP 8.4** + extensions: `pdo_pgsql`, `pgsql`, `redis` (optional), `intl`, `zip`, `bcmath`, `gd`  
   Install via [windows.php.net](https://windows.php.net/download/) or Scoop: `scoop install php composer`
2. **Composer**
3. **Supabase** project (Postgres)
4. Redis: **Upstash** free Redis (recommended) **or** for MVP only:
   - `CACHE_STORE=file`
   - `QUEUE_CONNECTION=sync`
   - `SESSION_DRIVER=file`  
   (guest cart Redis TTL will need Upstash later)

## Steps

```powershell
cd "d:\live Stream\backend"
copy .env.example .env
# Edit .env — see below
composer install
php artisan key:generate
php artisan migrate --force
php artisan serve --host=127.0.0.1 --port=8080
```

### `.env` (Supabase)

```env
APP_URL=http://127.0.0.1:8080

DB_CONNECTION=pgsql
DB_HOST=db.YOUR_REF.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=YOUR_PASSWORD
DB_SSLMODE=require

# MVP without Redis:
CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Or Upstash:
# REDIS_HOST=....upstash.io
# REDIS_PORT=6379
# REDIS_PASSWORD=...
# CACHE_STORE=redis
# QUEUE_CONNECTION=redis
# SESSION_DRIVER=redis

MAIL_MAILER=log
FILESYSTEM_DISK=local
```

### Mobile

```powershell
cd "d:\live Stream\mobile"
& "..\flutter\bin\flutter.bat" run -d chrome --dart-define=API_BASE_URL=http://127.0.0.1:8080/api/v1
```

Android emulator: use `10.0.2.2:8080` instead of `127.0.0.1`.

## When Railway is paid again

Follow [22_CLOUD_INFRA_SUPABASE_RAILWAY.md](./22_CLOUD_INFRA_SUPABASE_RAILWAY.md) — keep the same Supabase DB, move API to Railway.
