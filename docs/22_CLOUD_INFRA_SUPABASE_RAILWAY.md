# Cloud runtime — Supabase + Railway (no Docker)

**ADR:** [ADR-018](../07_ADR.md#adr-018-supabase--railway-cloud-runtime)  
**Status:** Preferred MVP path

Replace local Docker Compose with:

| Role | Service |
|------|---------|
| PostgreSQL | **Supabase** project |
| Laravel API | **Railway** service `api` |
| Redis | **Railway** Redis plugin |
| Object storage | **Supabase Storage** (S3) or Railway Bucket |
| Mobile | Flutter → Railway HTTPS `/api/v1` |

---

## 1. Supabase (database)

1. Open **Supabase** app / [https://supabase.com/dashboard](https://supabase.com/dashboard)
2. Create project **livecommerce** (region closest to you)
3. **Project Settings → Database** — copy:
   - **URI** (direct, port `5432`) — for migrations
   - **Connection pooling** (Transaction, port `6543`) — for app runtime
4. Optional: **Storage** → create bucket `livecommerce` (public read for product images if needed)

You will paste these into Railway variables in step 3.

---

## 2. Railway CLI (one-time)

CLI is installed via npm (`@railway/cli`). From PowerShell:

```powershell
railway login
```

Browser opens — sign in / create account. Then from repo:

```powershell
cd "d:\live Stream\backend"
railway init --name livecommerce
railway add --database redis --json
```

> **If you see:** `Your trial has expired. Please select a plan`  
> → Open [https://railway.com/workspace](https://railway.com/workspace) → choose **Hobby** (or Team) → then re-run `railway init`.  
> Until then use [23_LOCAL_PHP_SUPABASE.md](./23_LOCAL_PHP_SUPABASE.md) (local PHP + Supabase DB, no Docker).

Link Redis variables to the API service (Railway UI: **Variables → Add reference** `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` from Redis service, or use `${{Redis.REDIS_URL}}` style references).

---

## 3. Railway API service — environment variables

Set on service **api** (Railway dashboard or `railway variable set`):

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-railway-domain>
APP_KEY=base64:...          # generate: php artisan key:generate --show  OR openssl

DB_CONNECTION=pgsql
DB_HOST=<supabase-db-host>  # e.g. db.xxxx.supabase.co
DB_PORT=6543                # pooler for runtime
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<supabase-db-password>
DB_SSLMODE=require

# Prefer DATABASE_URL if you set it instead of discrete vars:
# DATABASE_URL=postgresql://postgres:...@db.xxxx.supabase.co:6543/postgres?sslmode=require

REDIS_CLIENT=phpredis
REDIS_HOST=${{Redis.REDISHOST}}     # adjust to your Redis plugin var names
REDIS_PORT=${{Redis.REDISPORT}}
REDIS_PASSWORD=${{Redis.REDISPASSWORD}}

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<supabase-or-bucket-key>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=livecommerce
AWS_ENDPOINT=https://<project-ref>.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true

MAIL_MAILER=log

JWT_SECRET=<long-random>
JWT_TTL=15
JWT_REFRESH_TTL=43200
```

Generate secrets locally (no Docker):

```powershell
# APP_KEY
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
# JWT_SECRET
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

If PHP is not on PATH, generate in Railway shell after first deploy: `railway run php artisan key:generate --show`.

---

## 4. Deploy API

```powershell
cd "d:\live Stream\backend"
railway up -y -m "Deploy LiveCommerce API (ADR-018)"
railway domain   # attach public HTTPS domain if not auto-created
```

Migrations on deploy are run by the container start script (`php artisan migrate --force`).

Worker (optional second service): same image, start command:

```text
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

---

## 5. Mobile → cloud API

```powershell
cd "d:\live Stream\mobile"
& "d:\live Stream\flutter\bin\flutter.bat" run -d chrome `
  --dart-define=API_BASE_URL=https://YOUR-RAILWAY-DOMAIN/api/v1
```

Or set default in `api_constants.dart` after first domain is known (keep `--dart-define` for overrides).

---

## 6. Smoke checklist

1. `GET https://YOUR-DOMAIN/api/v1/health` (or `/up`) → 200  
2. Register / login from mobile  
3. Feed → product → cart → checkout (Phase A E2E)

---

## Mapping from Docker Compose

| Docker service | Cloud |
|----------------|-------|
| `postgres` | Supabase Postgres |
| `redis` | Railway Redis |
| `app` + `nginx` | Railway `api` |
| `worker` | Railway `worker` (optional) |
| `minio` | Supabase Storage / Railway Bucket |
| `mailpit` | `MAIL_MAILER=log` |

Compose files under `docker/` stay in the repo for optional offline use only.
