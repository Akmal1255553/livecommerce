# Sprint 4.4 — Order System · Index

**Status:** Approved — blueprint v2  
**Canonical blueprint:** [blueprints/sprint-4.4-order-system.md](../blueprints/sprint-4.4-order-system.md) (v2)  
**ADR:** [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate) — **Accepted** (2026-06-28)  
**Architecture:** [23_COMMERCE_CORE_ARCHITECTURE.md](./23_COMMERCE_CORE_ARCHITECTURE.md) §8  
**Depends on:** Sprint 4.3 — Shopping Cart (`v0.4.3-shopping-cart`)

> **Gate:** ADR-017 **Accepted** ✅ → blueprint v2 **Approved** ✅ → implement on `feature/sprint-4.4-order-system`.

---

## P0 requirements (blueprint §2)

| # | Requirement |
|---|-------------|
| 1 | **Order number** — `LC-YYYYMMDD-000001` or `LC-7YQ29AF4`; not auto-increment; immutable |
| 2 | **Immutable order** — no changes to `OrderItem`, prices, SKU, product name after creation |
| 3 | **Optimistic lock** — `orders.version` (+ `If-Match`); 409 on conflict |
| 4 | **Timeline** — `order_status_transitions` table; **not** JSON on `orders` |

## P1 / P2

| Priority | Items |
|----------|-------|
| **P1** | Domain events (`OrderConfirmed`, `OrderPackingStarted`, `RefundCompleted`, …), `PaymentSnapshot`, `ShipmentSnapshot` |
| **P2** | `OrderPolicy` (buyer/seller/admin), analytics metrics (`order_created`, `order_paid`, …) |

---

## Implementation

Branch: `feature/sprint-4.4-order-system`  
Release: `v0.4.4-order-system`  
Tests: **+27** minimum (182+ total)
