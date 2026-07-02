# Sprint 4.2 — Video Commerce · Index

**Status:** Approved (2026-06-28)  
**Canonical blueprint:** [blueprints/sprint-4.2-video-commerce.md](../blueprints/sprint-4.2-video-commerce.md)  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](./SPRINT_4_COMMERCE_PLAN.md)  
**Depends on:** Sprint 4.1 — Product Catalog  
**Blocks:** Sprint 4.3 — Shopping Cart

> **Gate rule:** No code until blueprint §15 is **Approved**.

---

## Summary

Attach multiple products to videos via `video_products` pivot with ordering, optional in-video timestamps, and a featured product. Expose overlay API for mobile feed.

```
Video → VideoProduct (pivot) → Product
```

| Layer | Sprint 4.2 |
|-------|------------|
| Persistence | `video_products` migration |
| Service | `VideoCommerceService` |
| API | `GET/PUT /videos/{id}/products` |
| Feed | `VideoResource.products` hydrated |
| Mobile | `ProductCard` + `VideoProductTag` DTOs |
| Event | `ProductAttachedToVideo` |

---

## Deliverables

| Item | Blueprint section |
|------|-------------------|
| Pivot schema | [§2](../blueprints/sprint-4.2-video-commerce.md#2-domain-model) |
| Service contracts | [§3](../blueprints/sprint-4.2-video-commerce.md#3-contracts--services) |
| API endpoints | [§5](../blueprints/sprint-4.2-video-commerce.md#5-api) |
| ProductCard DTO | [§6](../blueprints/sprint-4.2-video-commerce.md#6-dtos--api-resources) |
| Tests (+12) | [§9](../blueprints/sprint-4.2-video-commerce.md#9-test-plan-pest) |
| Implementation order | [§10](../blueprints/sprint-4.2-video-commerce.md#10-implementation-order) |

---

## API (quick reference)

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/videos/{id}/products` | Optional | Overlay products (ordered) |
| PUT | `/videos/{id}/products` | Required | Sync attachments (owner) |

Feed / `GET /videos/{id}` include `products: [ VideoProductResource ]`.

---

## Out of scope

Cart, checkout, orders, seller dashboard — see [SPRINT_4_COMMERCE_PLAN.md](./SPRINT_4_COMMERCE_PLAN.md).

---

## Approval

See [blueprint §15](../blueprints/sprint-4.2-video-commerce.md#15-approval).

**Pending** → unlock `feature/sprint-4.2-video-commerce`.
