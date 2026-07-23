# Deploy LiveCommerce API on Render (free tier)

**Preferred free cloud path** while Railway trial is expired.  
Blueprint: [`render.yaml`](../render.yaml) at repo root.

## What gets created

| Resource | Plan | Role |
|----------|------|------|
| Web Service `livecommerce-api` | **free** | Laravel API (Docker) |
| PostgreSQL `livecommerce-db` | **free** | App database |

MVP uses `CACHE_STORE=file` + `QUEUE_CONNECTION=sync` (no Redis bill). Guest cart Redis features need Upstash later.

## One-click deploy

1. Push this branch / `main` to GitHub (`Akmal1255553/livecommerce`).
2. Open: [https://dashboard.render.com/select-repo?type=blueprint](https://dashboard.render.com/select-repo?type=blueprint)
3. Connect GitHub → select **livecommerce** → Blueprint uses `render.yaml`
4. For `APP_URL` paste the service URL after first deploy (e.g. `https://livecommerce-api.onrender.com`) — or set it once the hostname is known and **Manual Deploy** again
5. Wait for build (first Docker build ~5–10 min on free)

## After deploy

```text
GET https://YOUR-SERVICE.onrender.com/up
GET https://YOUR-SERVICE.onrender.com/api/v1/health
```

### Sentry (error tracking)

1. Create free projects at [sentry.io](https://sentry.io) — one **Laravel/PHP**, one **Flutter**.
2. In Render → Environment → set `SENTRY_LARAVEL_DSN` to the PHP project DSN (leave empty to disable).
3. Mobile release / chrome run:

```powershell
& "..\flutter\bin\flutter.bat" run -d chrome `
  --dart-define=API_BASE_URL=https://YOUR-SERVICE.onrender.com/api/v1 `
  --dart-define=SENTRY_DSN=https://YOUR_KEY@oXXXX.ingest.sentry.io/PROJECT
```

Without `SENTRY_DSN` / `SENTRY_LARAVEL_DSN`, Sentry stays off (safe for local + CI).

### Agora (real live video)

1. Create a project at [console.agora.io](https://console.agora.io) (App ID + Certificate / secured mode).
2. In Render → Environment set:

| Key | Value |
|-----|--------|
| `STREAMING_PROVIDER` | `agora` |
| `AGORA_APP_ID` | from Agora console |
| `AGORA_APP_CERTIFICATE` | from Agora console |

3. **Manual Deploy** (or wait for auto-redeploy) so the web process picks up env.
4. Test on **Android/iOS only** (Chrome shows a placeholder — Agora RTC is native).
5. As seller: start live → room should receive non-empty `app_id` + tokens from API.

If `STREAMING_PROVIDER` stays `fake` or App ID is empty, the app shows the live placeholder instead of camera.

Mobile:

```powershell
cd "d:\liveStream\mobile"
& "..\flutter\bin\flutter.bat" run -d chrome `
  --dart-define=API_BASE_URL=https://YOUR-SERVICE.onrender.com/api/v1
```

## Notes

- Free web services **sleep** after ~15 min idle → first request may take 30–60s.
- Free Postgres may be deleted after long inactivity — for durable DB switch `DB_URL` to **Supabase** (see [22_CLOUD…](./22_CLOUD_INFRA_SUPABASE_RAILWAY.md)).
- Dockerfile lives in `backend/`; Render `rootDir: backend`.

## Manual Web Service (without Blueprint)

Dashboard → New → Web Service → repo → Docker → Root Directory `backend` → Health Check `/up` → add env vars from `render.yaml`.
