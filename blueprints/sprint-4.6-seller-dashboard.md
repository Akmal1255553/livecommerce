# Sprint 4.6 — Seller Dashboard · Blueprint

**Version:** 1  
**Sprint:** 4.6  
**Status:** Shipped (`v0.4.6-seller-dashboard`)  
**Phase:** Commerce (backend REST)  
**Depends on:** Phase A (4.5 + 4.5M)  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md)

---

## 1. Goal

Ship seller onboarding and storefront/dashboard REST APIs so sellers can apply, see KPIs, and buyers can browse public stores — without Flutter seller UI (Sprint 5).

## 2. In scope

| Endpoint | Notes |
|----------|--------|
| `POST /api/v1/seller/apply` | Auth; auto-approve store + `role=seller` |
| `GET /api/v1/seller/dashboard` | Seller; products/orders/revenue/low-stock |
| `GET /api/v1/seller/analytics/summary` | Seller; period aggregates + daily series |
| `GET /api/v1/stores/{slug}` | Public store profile |
| `GET /api/v1/stores/{slug}/products` | Public active products |

Reuse existing seller product CUD (`/products`) and seller orders (no rename).

## 3. Out of scope

- Mobile seller screens → Sprint 5  
- Admin approve/reject stores  
- `/seller/products` path aliases (documented divergence from `04_API_SPECIFICATION.md`)

## 4. Architecture

`StoreServiceInterface` → `StoreService` (apply, getBySlug, dashboard, analytics, listPublicProducts).  
Controllers: `SellerStoreController`, `StoreController`.  
`StoreRepository`: `findBySlug` / `findActiveBySlug`.

## 5. Tests

`tests/Feature/Seller/SellerDashboardTest.php` — apply, conflict, dashboard/analytics auth, public storefront.

## 6. Release

- Branch: `feature/sprint-4.6-seller-dashboard`  
- Tag: `v0.4.6-seller-dashboard`  
- Deploy: push → Render auto-deploy on connected branch  
