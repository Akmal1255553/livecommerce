# Sprint 4.3 — Shopping Cart · Index

**Status:** Shipped (2026-06-28)  
**Canonical blueprint:** [blueprints/sprint-4.3-shopping-cart.md](../blueprints/sprint-4.3-shopping-cart.md) (v2)  
**ADR (required first):** [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) v2  
**Architecture:** [23_COMMERCE_CORE_ARCHITECTURE.md](./23_COMMERCE_CORE_ARCHITECTURE.md) v2

> **Gate:** ADR-016 v2 **Accepted** → blueprint **Approved** → implementation.

---

## v2 mandatory additions (architecture review)

| Priority | Item | Sprint |
|----------|------|--------|
| P0 | `carts.version` + checkout stale check | 4.3 field / 4.5 check |
| P0 | Inventory reservation TTL 15min + auto-release | 4.5 |
| P0 | `Idempotency-Key` on `POST /checkout` | 4.5 |
| P1 | `Money` value object (no float) | 4.3 |
| P1 | `CartMerged`, `CartExpired`, `CartCheckedOut` | 4.3 / 4.5 |
| P2 | `CouponServiceInterface` stub | 4.3 |
| P2 | `ShippingCalculatorInterface` stub | 4.3 |

---

## Summary

```
CartService → CartRepository
           ├─ InventoryService (advisory)
           ├─ PricingService (Money)
           ├─ CouponService (stub)
           └─ ShippingCalculator (stub)
```

---

## API (quick reference)

| Method | Path |
|--------|------|
| POST | `/cart/guest` |
| GET | `/cart` (includes `version`) |
| POST | `/cart/items` |
| PUT | `/cart/items/{id}` |
| DELETE | `/cart/items/{id}` |
| DELETE | `/cart` |

`POST /checkout` — Sprint 4.5 (`cart_version` + `Idempotency-Key`).

---

## Approval checklist

- [ ] ADR-016 v2 **Accepted**
- [ ] Blueprint v2 **Approved**
- [ ] Sprint 4.2 released
