# Sprint 4 — Commerce · Master Plan

**Version:** 2  
**Status:** Active planning  
**Phase:** 4 — Commerce · **Phase A — Commerce MVP**  
**Last updated:** 2026-07-03

---

## Purpose

Sprint 4 (Marketplace) is split into **independent sub-sprints**. Each sub-sprint follows the project workflow:

```
Blueprint → Architecture Review → Approval → Implementation → Tests → Documentation → Release
```

**Hard gates:**

1. No implementation before blueprint **Approved**.
2. No sub-sprint **N+1** until sub-sprint **N** CI is green and released.
3. Never bypass architecture (Services, Repositories, Events, Interfaces).
4. **ADR required:** any change touching **two or more** of {Cart, Orders, Inventory, Payment} must update [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) (or superseding ADR) **before** code.

---

4. **Phase A gate:** Sprint 5 (Seller Platform) and Sprint 6 (Live Commerce) are **blocked** until Phase A (4.5 + 4.5M) is CI green and released. Without 4.5M mobile screens there is **no MVP**.

---

## Phase A — Commerce MVP

Phase A closes the **end-to-end purchase journey** (backend + mobile). Nothing in Seller Platform or Live Commerce starts until Phase A ships.

| Sub-sprint | Layer | Name | Closes |
|------------|-------|------|--------|
| **4.5** | Backend | Checkout | Cart → order, inventory reservation, fake payment |
| **4.5M** | Mobile | Mobile Commerce | Feed overlay → product → cart → checkout → orders |

```
4.4 Order System ✅
    ↓
4.5 Checkout (backend)          ← Phase A starts
    ↓
4.5M Mobile Commerce (Flutter)  ← MVP gate — no MVP without this
    ↓
─── Phase A complete ───
    ↓
4.6 Seller Dashboard (backend REST)  ·  Sprint 5 Seller Platform
    ↓
Sprint 6 Live Commerce
```

---

## Sub-sprint map

| Sub-sprint | Name | Depends on | Status | Blueprint |
|------------|------|------------|--------|-----------|
| **4.1** | Product Catalog | Sprint 3.3 | ✅ Shipped | — (shipped) |
| **4.2** | Video Commerce | 4.1 | ✅ Shipped | [sprint-4.2-video-commerce.md](../blueprints/sprint-4.2-video-commerce.md) |
| **4.3** | Shopping Cart | 4.2 | ✅ Shipped | [sprint-4.3-shopping-cart.md](../blueprints/sprint-4.3-shopping-cart.md) |
| **4.4** | Order System | 4.3 | ✅ Shipped | [sprint-4.4-order-system.md](../blueprints/sprint-4.4-order-system.md) |
| **4.5** | Checkout | 4.4 | ✅ Shipped | — |
| **4.5M** | Mobile Commerce | 4.5 | ✅ Shipped | [sprint-4.5m-mobile-commerce.md](../blueprints/sprint-4.5m-mobile-commerce.md) |
| **4.6** | Seller Dashboard | 4.1, 4.4 | 🔒 Blocked (after Phase A E2E) | TBD |

```
4.1 Product Catalog ✅
    ↓
4.2 Video Commerce ✅
    ↓
4.3 Shopping Cart ✅
    ↓
4.4 Order System ✅
    ↓
4.5 Checkout ✅
    ↓
4.5M Mobile Commerce ✅           ← shipped; Phase A E2E Audit next
    ↓
4.6 Seller Dashboard (after Phase A E2E sign-off)
```

---

## 4.1 Product Catalog (shipped)

**Delivered:**

- Categories, brands, stores, products, images, variants
- Public catalog API + seller CRUD on `/api/v1/products`
- Inventory status (`out_of_stock` when `stock_quantity <= 0`)
- Discount via `compare_at_price`

---

## 4.2 Video Commerce (shipped)

**Delivered:** `video_products` pivot, `ProductCardResource`, `VideoCommerceService`, `product_version` snapshot, 142 tests.

→ [blueprints/sprint-4.2-video-commerce.md](../blueprints/sprint-4.2-video-commerce.md)

---

## 4.3 Shopping Cart (next)

**Prerequisite:** [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) **Accepted**

**Goal:** Cart CRUD, guest cart (Redis), merge on login, advisory inventory, live pricing.

| Feature | Detail |
|---------|--------|
| Storage | PostgreSQL (user) + Redis (guest) |
| Inventory | `InventoryServiceInterface::assertAvailable` (checkpoint 1) |
| Pricing | `PricingServiceInterface::priceCart` (display only) |
| Events | `CartUpdated`, `CartItemAdded`, `CartItemRemoved` |

**Not in scope:** orders, checkout, payment.

→ Full spec: [blueprints/sprint-4.3-shopping-cart.md](../blueprints/sprint-4.3-shopping-cart.md)  
→ Architecture: [23_COMMERCE_CORE_ARCHITECTURE.md](./23_COMMERCE_CORE_ARCHITECTURE.md)

---

## 4.4 Order System (shipped)

**Released:** `v0.4.4-order-system`

**Prerequisite:** [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate) **Accepted** · [blueprint](../blueprints/sprint-4.4-order-system.md) **Approved**

**State machine (ADR-017):**

```
Draft → Pending → AwaitingPayment → Paid → Packing → ReadyToShip → Shipped → Delivered → Completed

Pending / AwaitingPayment → Cancelled

Paid | Delivered | Completed → RefundRequested → RefundApproved → Refunded
                                              ↘ RefundRejected → (restore)
```

**Aggregate (immutable lines):**

```
Order
├── OrderItems      ← snapshot only; NO updates after creation
├── Payment
├── Shipment
└── Timeline        ← order_status_transitions
```

**Architecture:**

```
OrderStateMachine → OrderService → OrderRepository → Order events
```

- Every transition validated in state machine (initiator, checks, idempotency — see ADR-017 §5)
- No direct `orders.status` writes outside `OrderService`
- `order_items` use **price snapshot** columns (`Money`); INSERT only
- `orders.version` optimistic lock on transitions

**Events:** `OrderCreated`, `OrderPaid`, `OrderShipped`, `OrderDelivered`, `OrderCompleted`, `OrderCancelled`, `RefundRequested`, `OrderRefunded`

**Interfaces:** `TaxServiceInterface` (stub), `OrderStateMachineInterface`

---

## 4.5 Checkout (planned)

**Architecture:**

```
CheckoutService → PaymentGatewayInterface → Providers
```

| MVP | Future |
|-----|--------|
| `FakePaymentGateway` | Click, Payme, Stripe |

**Events:** `PaymentSucceeded`, `PaymentFailed`

**Rule:** Business logic never depends on a concrete payment provider.

**Inventory:** Re-validate stock at checkout (**checkpoint 2 of 2**) inside transaction before `OrderCreated`.

**Release:** `v0.4.5-checkout`

---

## 4.5M Mobile Commerce ✅

**Prerequisite:** Sprint 4.5 released (`v0.4.5-checkout`) — `POST /checkout` stable.

**Goal:** Mobile screens that complete the **Commerce MVP**. Backend APIs without this sprint do not constitute a shippable product.

| Screen | Detail |
|--------|--------|
| Product Overlay UI | Tap product tag on video feed |
| Product Page | Detail, variants, add to cart |
| Cart Screen | Items, quantities, totals |
| Checkout Screen | Address, payment method (fake), summary |
| Order Success | Post-checkout confirmation |
| Order History | List buyer orders |
| My Orders | Order detail, status |

**Architecture:** `mobile/lib/features/commerce/` — Clean Architecture, Riverpod, existing `ProductCard` / `VideoProductTag` DTOs.

**Not in scope:** seller flows, live pinning UI, real payment gateways, video upload.

→ Full spec: [sprint-4.5m-mobile-commerce.md](../blueprints/sprint-4.5m-mobile-commerce.md)  
→ Release audit: [SPRINT_4.5M_RELEASE_AUDIT.md](./SPRINT_4.5M_RELEASE_AUDIT.md)

**Release:** `v0.4.5m-mobile-commerce`

---

## 4.6 Seller Dashboard (planned)

**Blocked until Phase A (4.5 + 4.5M) is released.**

**Backend REST only** — no frontend complexity.

| Module | Endpoints (indicative) |
|--------|------------------------|
| Products | extend existing seller product APIs |
| Orders | list, detail, status (read-only until 4.4 transitions) |
| Revenue | aggregates by period |
| Inventory | low-stock alerts |
| Analytics | orders, views, conversion stubs |

---

## Cross-cutting commerce architecture

> **Canonical source:** [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) + [23_COMMERCE_CORE_ARCHITECTURE.md](./23_COMMERCE_CORE_ARCHITECTURE.md)

### A. Price snapshot (OrderItem)

**Rule:** Order line items must **never** read live prices from `products` after creation.

If a product costs 100 000 UZS today and 150 000 UZS tomorrow, existing orders stay unchanged.

`order_items` (Sprint 4.4) stores immutable snapshots:

| Column | Source at checkout |
|--------|-------------------|
| `product_name` | `products.title` |
| `sku` | `products.sku` or variant SKU |
| `unit_price` | price at checkout time |
| `discount` | applied per-line discount amount |
| `currency` | `UZS` |

`CheckoutService` + `PricingServiceInterface` produce snapshots; `OrderRepository` persists them. **No FK price lookups in order display.**

### B. Product versioning (video_products)

Videos can outlive many product edits. Pivot `video_products.product_version` captures `products.version` at attach time.

| Sprint | Scope |
|--------|-------|
| 4.2 | `products.version` column + pivot `product_version` on sync |
| Future | Optional “as tagged” overlay vs live product card |

### C. Inventory double-check + reservation TTL

| Checkpoint | Sprint | Behaviour |
|------------|--------|-----------|
| Cart add/update | 4.3 | `assertAvailable` (advisory) |
| Checkout | 4.5 | `reserveForOrder` + **TTL 15 min** |
| Unpaid expiry | 4.5 | `ReleaseExpiredInventoryReservationsJob` → auto-release stock |
| Payment success | 4.5 | `confirmReservation` |

### D. Cart versioning (P0)

`carts.version` incremented on every mutation. `POST /checkout` sends `cart_version`; mismatch → `409 CartStaleException`.

### E. Checkout idempotency (P0)

`Idempotency-Key` header on `POST /checkout`; duplicate key returns same order (24h TTL).

### F. Money value object (P1)

`App\ValueObjects\Money` — `amount` (int) + `currency`. No float in commerce services.

### G. Coupon & shipping stubs (P2)

`CouponServiceInterface` → `NoDiscountCouponService`  
`ShippingCalculatorInterface` → `FixedShippingCalculator`

### H. Cart events (P1)

`CartMerged`, `CartExpired` (4.3); `CartCheckedOut` (4.5)

---

## Cross-cutting interfaces

| Interface | Introduced in | Purpose |
|-----------|---------------|---------|
| `PaymentGatewayInterface` | exists (empty) | 4.5 Checkout |
| `InventoryServiceInterface` | 4.3 | Stock checks; reservation TTL in 4.5 |
| `PricingServiceInterface` | 4.3 | Totals, discounts (`Money`) |
| `CouponServiceInterface` | 4.3 stub | Coupons (no-op MVP) |
| `ShippingCalculatorInterface` | 4.3 stub | Shipping estimate (fixed rate MVP) |
| `TaxServiceInterface` | 4.4 | Tax lines |

Implementations are swappable via DI; services depend on interfaces only.

---

## Event registry (Sprint 4)

| Event | Sub-sprint |
|-------|------------|
| `ProductAttachedToVideo` | 4.2 |
| `CartUpdated` | 4.3 |
| `CartItemAdded` | 4.3 |
| `CartItemRemoved` | 4.3 |
| `CartMerged` | 4.3 |
| `CartExpired` | 4.3 |
| `CartCheckedOut` | 4.5 |
| `OrderCreated` | 4.4 |
| `PaymentSucceeded` | 4.5 |
| `PaymentFailed` | 4.5 |
| `OrderCompleted` | 4.4 |

---

## Quality gates (every sub-sprint)

| Gate | Requirement |
|------|-------------|
| PHPStan | Level max, 0 errors |
| Pest | All green (no regression) |
| Pint | Pass |
| Architecture | No logic in controllers; no rules in repositories |
| Docs | Blueprint + API + DB + CHANGELOG updated |
| Release | Git tag `v0.4.x-*` per sub-sprint |

### Release Audit (major sprints)

After each **major** sprint (`4.5`, `4.5M`, `5.0`, `6.0`, …) run [21_SPRINT_RELEASE_AUDIT.md](./21_SPRINT_RELEASE_AUDIT.md):

| Audit | Scope |
|-------|--------|
| Architecture | ADR + Blueprint compliance |
| API | `04_API_SPECIFICATION.md` ↔ `routes/api.php` |
| Mobile | Real APIs, no prod stubs (when mobile ships) |
| E2E Smoke | User journey smoke test |

**Phase A completion** requires **4.5 + 4.5M + Full E2E Audit** before Sprint 5 / Sprint 6.

```powershell
.\scripts\sprint-audit.ps1   # QA + route export; then complete manual checklists
```

---

## Documentation index

| When | Document |
|------|----------|
| Before each sub-sprint | `blueprints/sprint-4.x-*.md` |
| After approval | Update `03_DATABASE_DESIGN.md`, `04_API_SPECIFICATION.md` |
| After release | `CHANGELOG.md`, `13_ROADMAP.md`, `17_DEPENDENCY_MATRIX.md` |

---

## Current action

1. ~~Release `v0.4.5m-mobile-commerce`~~ — code shipped
2. **Full E2E Audit** — [SPRINT_4.5M_RELEASE_AUDIT.md](./SPRINT_4.5M_RELEASE_AUDIT.md) §4 smoke checklist
3. **Phase A complete** → unblock Sprint 5 / Sprint 6