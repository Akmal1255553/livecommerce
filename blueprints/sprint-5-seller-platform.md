# Sprint 5 — Seller Platform · Blueprint

**Version:** 1  
**Sprint:** 5  
**Status:** Shipped (mobile)  
**Phase:** Commerce (Flutter seller UI)  
**Depends on:** Sprint 4.6 Seller Dashboard API  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md)

---

## 1. Goal

Give authenticated users a mobile seller path: apply → dashboard KPIs → manage products/orders → public storefront.

## 2. In scope

| Screen / route | Notes |
|----------------|--------|
| `/seller/apply` | Store name + description → `POST /seller/apply`; refresh `/me` |
| `/seller` | Dashboard KPIs from `GET /seller/dashboard` |
| `/seller/products` | `GET /products?mine=1` + delete |
| `/seller/products/new` | Create via `POST /products` (category, price, stock, optional image URL) |
| `/seller/orders` | `GET /seller/orders` |
| `/seller/orders/:id` | Detail + status update (`Idempotency-Key`, `If-Match`) |
| `/stores/:slug` | Public store + products |
| Profile CTA | “Become a seller” vs “Seller center” via `AuthUser.isSeller` |

## 3. Out of scope (thin MVP)

- Native image upload pipeline (URL field only)
- Full variants editor
- `/seller/products` path aliases (still use `/products`)
- Admin approve/reject

## 4. Architecture

`features/seller/` — domain entities, `SellerRepository`, Riverpod notifiers, screens.  
Auth: `AuthUser.role` + `refreshUser()` after apply.

## 5. Release

- Branch: `feature/sprint-5-seller-platform`  
- Tag: `v0.5.0-seller-platform` (after QA)  
- API: existing Render deploy on Sprint 4.6 is sufficient (no new backend required)
