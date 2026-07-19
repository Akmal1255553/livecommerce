# Web Admin Panel

**Status:** Sprint 14 — thin SPA (Private Beta)

Static admin UI served from the Laravel API at `/admin/`.

## URL

- Production: `https://livecommerce-api.onrender.com/admin/`
- Local: `http://localhost:8000/admin/`

## Stack

- Vanilla HTML/CSS/JS in `backend/public/admin/`
- Calls existing `/api/v1/admin/*` endpoints with JWT from login
- Roles: `admin` (all tabs) · `moderator` (reports + pending videos)

## Features

| Tab | Actions |
|-----|---------|
| Reports | Resolve / dismiss open content reports |
| Pending videos | Approve / reject / hide |
| Stores | Approve / reject pending seller stores (admin) |
| Users | Suspend / ban / activate (admin) |

## Access

Sign in with an account that has `role=admin` or `role=moderator`.

## Docs

- [API Admin §7.17](../docs/04_API_SPECIFICATION.md)
- [Modules — Admin](../08_MODULES.md)
