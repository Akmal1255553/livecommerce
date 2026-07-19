# Sprint 14 — Administration · Blueprint

**Version:** 1  
**Sprint:** 14 (core)  
**Status:** Implemented (`feature/sprint-14-admin`)  
**Depends on:** Sprint 1–8  
**Module:** [08_MODULES.md §16](../08_MODULES.md)

## Goal

API-first moderation for private beta: pending seller approval, user suspend/ban, audit trail, content reports, category admin CRUD. Web admin panel deferred.

## In scope

| Area | Notes |
|------|--------|
| Middleware | `role:admin` / `role:admin,moderator` via existing `EnsureRole` |
| Seller gate | `POST /seller/apply` → store `pending`; admin approve/reject |
| Users | List + suspend / ban / activate + revoke refresh tokens |
| Videos | Pending queue + approve / reject / hide |
| Reports | `POST /reports` (user) + admin list/resolve |
| Categories | Admin POST/PUT/DELETE |
| Audit | `audit_logs` + `AuditService::record` |
| Mobile | Report user/video/product buttons |
| Tests | Admin actions, apply→pending, reports |

## Out of scope

- Full web admin SPA
- Admin analytics dashboards
- Refund admin (already partially elsewhere — wire if trivial)

## Release

1. Branch → `feature/sprint-14-admin`
2. Migrate + seed admin user (manual / seeder)
3. Approve pending sellers before they can use `/seller/*`
