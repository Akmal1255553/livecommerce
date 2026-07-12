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

Mobile:

```powershell
cd "d:\live Stream\mobile"
& "..\flutter\bin\flutter.bat" run -d chrome `
  --dart-define=API_BASE_URL=https://YOUR-SERVICE.onrender.com/api/v1
```

## Notes

- Free web services **sleep** after ~15 min idle → first request may take 30–60s.
- Free Postgres may be deleted after long inactivity — for durable DB switch `DB_URL` to **Supabase** (see [22_CLOUD…](./22_CLOUD_INFRA_SUPABASE_RAILWAY.md)).
- Dockerfile lives in `backend/`; Render `rootDir: backend`.

## Manual Web Service (without Blueprint)

Dashboard → New → Web Service → repo → Docker → Root Directory `backend` → Health Check `/up` → add env vars from `render.yaml`.
