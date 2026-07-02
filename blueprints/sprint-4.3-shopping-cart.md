# Sprint 4.3 — Shopping Cart · Blueprint

**Version:** 2  
**Sprint:** 4.3  
**Status:** Approved (2026-06-28)  
**Phase:** 4 — Commerce  
**Depends on:** Sprint 4.2 — Video Commerce (`v0.4.2-video-commerce`)  
**ADR (required):** [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) — **must be Accepted before implementation**  
**Architecture:** [23_COMMERCE_CORE_ARCHITECTURE.md](../docs/23_COMMERCE_CORE_ARCHITECTURE.md)  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md)

> **Gate rules:**  
> 1. [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) **Accepted**  
> 2. This blueprint **Approved**  
> 3. Sprint 4.2 CI green + released  
> No code until both gates pass.

---

## 1. Goal

Ship a **production-grade shopping cart** for authenticated users and guests, with advisory inventory checks and live pricing — without implementing orders, checkout, or payment.

**Architectural pillars:**

1. **ADR-first (v2)** — Implements P0 cart `version`, P1 `Money` VO, P2 coupon/shipping stubs per [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment).
2. **Cart is mutable, order is not** — `cart_items` store IDs + quantity only; prices via `PricingServiceInterface` (`Money`).
3. **Cart versioning (P0)** — `carts.version` incremented every mutation; returned in API; checkout validates in 4.5.
4. **Inventory checkpoint 1** — advisory `assertAvailable` only in 4.3 (reservation TTL in 4.5).
5. **Guest merge** — Redis → PostgreSQL; dispatch `CartMerged`.
6. **Event-driven** — `CartUpdated`, `CartItemAdded`, `CartItemRemoved`, `CartMerged`, `CartExpired`.

**In scope:** Cart CRUD, `version`, guest cart, merge, `Money` VO, pricing/inventory/coupon/shipping interfaces (+ stubs), events, tests.  
**Out of scope (ADR-defined, later sprints):** `CheckoutService`, idempotency table, `inventory_reservations`, `CartCheckedOut`, payment.

---

## 2. Prerequisites

| Gate | Document | Status |
|------|----------|--------|
| ADR | [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment) | **Accepted** |
| Architecture | [23_COMMERCE_CORE_ARCHITECTURE.md](../docs/23_COMMERCE_CORE_ARCHITECTURE.md) | Accepted |
| Prior sprint | 4.2 Video Commerce | ✅ Shipped |
| Blueprint | This document | Approved · Shipped |

---

## 3. Domain model

### 3.1 PostgreSQL (authenticated)

Aligns with [03_DATABASE_DESIGN.md §3.18–3.19](../docs/03_DATABASE_DESIGN.md).

**`carts`**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| user_id | UUID | FK → users, UNIQUE |
| version | INTEGER | NOT NULL, DEFAULT 1 — increment on every mutation |
| created_at / updated_at | TIMESTAMP | `updated_at` also exposed in API |

**`cart_items`**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| cart_id | BIGINT | FK → carts |
| product_id | UUID | FK → products |
| variant_id | BIGINT | NULLABLE, FK → product_variants |
| quantity | INTEGER | 1–99 |
| created_at / updated_at | TIMESTAMP | |

`UNIQUE (cart_id, product_id, variant_id)`

### 3.2 Redis (guest)

| Key | TTL | Payload |
|-----|-----|---------|
| `cart:guest:{token}` | 30 days | `{ "version": 1, "items": [...] }` |

Guest token: UUID v4 via `X-Guest-Cart-Token` header.

---

## 4. Contracts & services

### 4.1 New interfaces & value objects

| Artifact | Sprint 4.3 |
|----------|--------------|
| `App\ValueObjects\Money` | ✅ Full (P1) |
| `CartServiceInterface` | ✅ Full |
| `InventoryServiceInterface` | ✅ `assertAvailable` only |
| `PricingServiceInterface` | ✅ `priceCart` only (`Money`) |
| `CouponServiceInterface` | ✅ Stub `NoDiscountCouponService` (P2) |
| `ShippingCalculatorInterface` | ✅ Stub `FixedShippingCalculator` (P2) |

`reserveForOrder`, idempotency, checkout `cart_version` check — **ADR-defined**, implemented in **4.5**.

### 4.2 MVP implementations

| Class | Role |
|-------|------|
| `CartService` | CRUD, merge, `version++`, events |
| `ProductInventoryService` | `assertAvailable` from `stock_quantity` |
| `ProductPricingService` | Line totals as `Money` (no float math) |
| `NoDiscountCouponService` | `CouponApplication::none()` |
| `FixedShippingCalculator` | `Money::uzs(config('commerce.shipping_flat_rate_uzs', 0))` |

Bind in `RepositoryServiceProvider`:

```php
InventoryServiceInterface → ProductInventoryService
PricingServiceInterface → ProductPricingService
CouponServiceInterface → NoDiscountCouponService
ShippingCalculatorInterface → FixedShippingCalculator
CartServiceInterface → CartService
```

Every cart mutation: `version = version + 1` (PostgreSQL + Redis).

### 4.3 CartService methods

| Method | Description |
|--------|-------------|
| `getCart(User\|GuestContext $actor)` | Returns cart with priced lines |
| `addItem(actor, productId, variantId?, quantity)` | Merge or create line; inventory check |
| `updateItem(actor, itemId, quantity)` | Update qty; remove if 0 |
| `removeItem(actor, itemId)` | Delete line |
| `clear(actor)` | Empty cart |
| `mergeGuestIntoUser(guestToken, User $user)` | Called from auth listener |

All mutations: `DB::transaction` (user) or Redis atomic update (guest).

### 4.4 CartRepository extensions

| Method | Rules |
|--------|-------|
| `findOrCreateForUser(userId)` | Persistence only |
| `findItem(cartId, productId, variantId?)` | |
| `addOrUpdateItem(...)` | No stock/price logic |
| `deleteItem(itemId)` | |
| `clear(cartId)` | |

---

## 5. API

Prefix `/api/v1`. Controllers → `CartServiceInterface` only.

### 5.1 Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/cart/guest` | No | Issue `guest_cart_token` |
| GET | `/cart` | Optional | Get cart (user or guest header) |
| POST | `/cart/items` | Optional | Add item |
| PUT | `/cart/items/{id}` | Optional | Update quantity |
| DELETE | `/cart/items/{id}` | Optional | Remove item |
| DELETE | `/cart` | Optional | Clear cart |

**Auth resolution:**

- Bearer token → user cart (PostgreSQL)
- `X-Guest-Cart-Token` → guest cart (Redis)
- Both present → user cart wins

### 5.2 `GET /cart` response

```json
{
  "success": true,
  "data": {
    "id": "1",
    "type": "user",
    "version": 3,
    "items": [
      {
        "id": 1,
        "product": { /* ProductCardResource */ },
        "variant": { "id": 1, "name": "Size", "value": "M" },
        "quantity": 2,
        "unit_price": { "amount": 250000, "currency": "UZS" },
        "line_total": { "amount": 500000, "currency": "UZS" },
        "discount_amount": { "amount": 0, "currency": "UZS" }
      }
    ],
    "summary": {
      "subtotal": { "amount": 500000, "currency": "UZS" },
      "discount_total": { "amount": 0, "currency": "UZS" },
      "shipping_estimate": { "amount": 0, "currency": "UZS" },
      "currency": "UZS",
      "item_count": 2
    }
  }
}
```

> Client must send `version` back as `cart_version` on `POST /checkout` (Sprint 4.5).

### 5.3 Errors

| Code | When |
|------|------|
| 401 | Mutation without auth or guest token |
| 409 | Reserved for `CartStaleException` at checkout (4.5) |
| 422 | `InsufficientStockException`, invalid quantity, inactive product |
| 404 | Cart item not found |

---

## 6. Events

| Event | When | Sprint |
|-------|------|--------|
| `CartItemAdded` | Item added | 4.3 |
| `CartItemRemoved` | Item removed | 4.3 |
| `CartUpdated` | Any cart mutation | 4.3 |
| `CartMerged` | Guest cart merged into user cart | 4.3 |
| `CartExpired` | Guest cart TTL expired (job or lazy delete) | 4.3 |
| `CartCheckedOut` | Cart cleared after successful checkout | **4.5** |

All PostgreSQL mutations: `ShouldDispatchAfterCommit` where applicable.

---

## 7. Auth integration

**Listener:** `MergeGuestCartOnLogin` on `UserLoggedIn` / post-register (same hook).

1. Read `X-Guest-Cart-Token` from request or session metadata.
2. `CartService::mergeGuestIntoUser`.
3. Fail-open: merge errors logged, login not blocked.

---

## 8. File plan

| Path | Action |
|------|--------|
| `database/migrations/2026_07_02_000001_create_carts_tables.php` | Create |
| `app/Models/Cart.php`, `CartItem.php` | Create |
| `app/Contracts/Services/CartServiceInterface.php` | Create |
| `app/Contracts/Services/InventoryServiceInterface.php` | Create |
| `app/Contracts/Services/PricingServiceInterface.php` | Create |
| `app/ValueObjects/Money.php` | Create |
| `app/DTOs/Cart/CouponApplication.php` | Create |
| `app/Contracts/Services/CouponServiceInterface.php` | Create |
| `app/Contracts/Services/ShippingCalculatorInterface.php` | Create |
| `app/Services/Coupon/NoDiscountCouponService.php` | Create |
| `app/Services/Shipping/FixedShippingCalculator.php` | Create |
| `app/Events/CartMerged.php`, `CartExpired.php` | Create |
| `app/Services/Cart/CartService.php` | Create |
| `app/Services/Inventory/ProductInventoryService.php` | Create |
| `app/Services/Pricing/ProductPricingService.php` | Create |
| `app/Repositories/Eloquent/CartRepository.php` | Extend |
| `app/Http/Controllers/Api/V1/CartController.php` | Create |
| `app/Http/Resources/CartResource.php`, `CartItemResource.php` | Create |
| `app/Http/Requests/Cart/*` | Create |
| `app/DTOs/Cart/CartPricingResult.php`, `CartLinePricing.php` | Create |
| `app/Exceptions/Domain/CartStaleException.php` | Create (thrown in 4.5) |
| `config/commerce.php` | Extend (`shipping_flat_rate_uzs`, `inventory_reservation_ttl_minutes`) |
| `app/Listeners/MergeGuestCartOnLogin.php` | Create |
| `tests/Feature/Cart/CartTest.php` | Create |

**Not in 4.3:** `orders`, `order_items`, `CheckoutService`, `PaymentGateway` impl.

---

## 9. Test plan (Pest)

Target: **+18 tests** minimum.

| # | Test |
|---|------|
| 1 | Guest can create token and add item |
| 2 | Authenticated user add item |
| 3 | Add same product merges quantity |
| 4 | Update quantity increments `version` |
| 5 | Remove item increments `version` |
| 6 | Clear cart |
| 7 | Insufficient stock on add → 422 |
| 8 | Insufficient stock on quantity bump → 422 |
| 9 | Inactive product rejected |
| 10 | GET cart returns `Money` JSON + `version` |
| 11 | Variant line priced with adjustment (`Money`) |
| 12 | Guest cart merge on login → `CartMerged` event |
| 13 | Merge sums quantities and re-checks stock |
| 14 | Coupon stub returns zero discount |
| 15 | Shipping stub returns configured flat rate |
| 16 | CartItemAdded / CartUpdated events fired |
| 17 | Guest cart expiry dispatches `CartExpired` |
| 18 | Max quantity 99 enforced |

---

## 10. Implementation order

Branch: `feature/sprint-4.3-shopping-cart` (after ADR + blueprint approval).

1. **ADR-016** accepted (this gate)
2. Migration + models
3. Interfaces + DTOs
4. `ProductInventoryService`, `ProductPricingService`
5. `CartRepository` methods
6. `CartService` + events
7. Controller + resources + routes
8. `MergeGuestCartOnLogin` listener
9. Tests + docs + release `v0.4.3-shopping-cart`

---

## 11. Documentation updates (post-approval)

| Document | Update |
|----------|--------|
| `07_ADR.md` | ADR-016 → Accepted |
| `03_DATABASE_DESIGN.md` | carts / cart_items confirmed |
| `04_API_SPECIFICATION.md` | §7.8 + guest endpoints |
| `CHANGELOG.md` | v0.4.3 entry |
| `13_ROADMAP.md` | 4.3 status |

---

## 12. Out of scope (explicit)

| Item | Sprint |
|------|--------|
| Order creation | 4.4 |
| Price snapshot persistence (`order_items`) | 4.4 |
| Inventory `reserveForOrder` | 4.5 |
| Checkout / payment | 4.5 |
| Coupons | 4.5+ |

---

## 13. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | Akmal | — | **Pending** |
| Backend lead | — | — | **Pending** |

**Requires:** ADR-016 **Accepted** + this blueprint **Approved**.

---

## 14. References

- [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment)
- [23_COMMERCE_CORE_ARCHITECTURE.md](../docs/23_COMMERCE_CORE_ARCHITECTURE.md)
- [SPRINT_4.3_BLUEPRINT.md](../docs/SPRINT_4.3_BLUEPRINT.md)
