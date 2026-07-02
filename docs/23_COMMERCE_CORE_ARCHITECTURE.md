# Commerce Core Architecture

**Version:** 2  
**Status:** Accepted (ADR-016 v2, 2026-06-28)  
**ADR:** [07_ADR.md § ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment)  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](./SPRINT_4_COMMERCE_PLAN.md)  
**Last updated:** 2026-06-28

---

## Purpose

Canonical architecture for the **Cart → Checkout → Order → Payment** pipeline.  
Any sprint that touches two or more of these concerns **must** align with this document and [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment).

---

## 1. Module boundaries

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────┐     ┌─────────────────────┐
│ CartService │────▶│ CheckoutService  │────▶│ OrderService│────▶│ PaymentGatewayIface │
│  (mutable)  │     │  (orchestrator)  │     │ (immutable) │     │   (external I/O)    │
└──────┬──────┘     └────────┬─────────┘     └──────┬──────┘     └─────────────────────┘
       │                     │                      │
       ▼                     ▼                      ▼
 InventoryService      InventoryService         OrderStateMachine
 (advisory)             (reservation + TTL)      OrderRepository
 PricingService         PricingService
 CouponService (stub)   CouponService
 ShippingCalc (stub)   ShippingCalculator
 Money VO               Money VO
```

| Module | Mutability | Owns |
|--------|------------|------|
| **Cart** | Mutable until checkout | `carts` (+ `version`), `cart_items`, guest cart in Redis |
| **Checkout** | Stateless orchestration | `idempotency_keys`; coordinates services |
| **Order** | Immutable line snapshots | `orders`, `order_items`, status transitions |
| **Payment** | External + idempotent webhooks | `payment_reference` on order; no cart access |
| **Inventory** | Reservations with TTL | `inventory_reservations` |

**Hard rules:**

1. `PaymentGatewayInterface` is called **only** from `CheckoutService` (and webhook handler → `OrderService`).
2. `CartService` **never** creates orders or initiates payment.
3. `OrderService` **never** reads live `products.price` for display — only `order_items` snapshots.
4. `InventoryServiceInterface` is the **only** stock authority; repositories do not validate stock.
5. **No `float` for money** in services — use `Money` value object everywhere.
6. **Checkout is idempotent** — `Idempotency-Key` header required on `POST /checkout`.

---

## 2. Money value object (P1)

`App\ValueObjects\Money` — mandatory in all commerce services.

```php
final readonly class Money
{
    public function __construct(
        public int $amount,       // smallest unit; UZS = whole sum (no decimals)
        public string $currency,  // ISO 4217, default UZS
    ) {}

    public static function uzs(int $amount): self;

    public function add(self $other): self;    // throws if currency mismatch
    public function subtract(self $other): self;
    public function multiply(int $factor): self;
    public function isZero(): bool;
}
```

| Rule | Detail |
|------|--------|
| Storage | `DECIMAL` in DB; cast to `Money` at repository boundary |
| API JSON | `{ "amount": 250000, "currency": "UZS" }` |
| Forbidden | `float` / `(float)` arithmetic in `CartService`, `PricingService`, `CheckoutService`, `OrderService` |

---

## 3. Service interfaces

| Interface | Introduced | Responsibility |
|-----------|------------|----------------|
| `InventoryServiceInterface` | 4.3 / 4.5 | Advisory check; reservation + TTL release |
| `PricingServiceInterface` | 4.3 | Cart pricing; checkout snapshots (`Money`) |
| `CouponServiceInterface` | 4.3 stub | Discount resolution (MVP: always zero) |
| `ShippingCalculatorInterface` | 4.3 stub | Shipping cost (MVP: fixed or zero) |
| `TaxServiceInterface` | 4.4 | Tax lines (stub) |
| `PaymentGatewayInterface` | 4.5 | Initiate payment, webhooks |

### 3.1 InventoryServiceInterface

```php
interface InventoryServiceInterface
{
    /** Checkpoint 1 — advisory (CartService). */
    public function assertAvailable(string $productId, ?int $variantId, int $quantity): void;

    /**
     * Checkpoint 2 — creates reservation, decrements stock.
     * TTL = 15 minutes (config: commerce.inventory_reservation_ttl_minutes).
     *
     * @return string reservation_group_id
     */
    public function reserveForOrder(string $orderId, array $lines): string;

    /** Payment succeeded — mark reservations confirmed (stock stays decremented). */
    public function confirmReservation(string $reservationGroupId): void;

    /** Payment failed / cancel — restore stock immediately. */
    public function releaseReservation(string $reservationGroupId): void;
}
```

#### Inventory reservation lifecycle (P0)

```
reserveForOrder()
    ↓
TTL = 15 minutes (expires_at)
    ↓
┌─────────────────┬──────────────────────┐
│ PaymentSucceeded│ Expired (scheduled job)│
│ confirmReservation│ releaseReservation │
└─────────────────┴──────────────────────┘
```

| Stage | Action |
|-------|--------|
| **Reserve** | Decrement `stock_quantity`; insert `inventory_reservations` rows with `expires_at = now() + 15min`, `status = active` |
| **Confirm** | On `PaymentSucceeded`: `status = confirmed` |
| **Expire** | `ReleaseExpiredInventoryReservationsJob` (every minute): `status = active AND expires_at < now()` → restore stock, `status = released` |
| **Release** | On payment failure / order cancel before pay: immediate restore |

Table `inventory_reservations`:

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| reservation_group_id | UUID | Groups lines per checkout |
| order_id | UUID | FK → orders |
| product_id | UUID | |
| variant_id | BIGINT | NULLABLE |
| quantity | INTEGER | |
| status | VARCHAR(20) | `active`, `confirmed`, `released` |
| expires_at | TIMESTAMP | NOT NULL |
| created_at | TIMESTAMP | |

### 3.2 PricingServiceInterface

All amounts return `Money`.

```php
interface PricingServiceInterface
{
    public function priceCart(Cart $cart): CartPricingResult;

    /** @return list<OrderLineSnapshot> */
    public function buildOrderLineSnapshots(Cart $cart): array;

    public function calculateOrderTotals(
        array $snapshots,
        CouponApplication $coupon,
        Money $shipping,
    ): OrderTotals;
}
```

### 3.3 CouponServiceInterface (P2 — stub in 4.3)

```php
interface CouponServiceInterface
{
    public function resolve(?string $code, Cart $cart): CouponApplication;
}

/** MVP: NoDiscountCouponService — always CouponApplication::none() */
```

### 3.4 ShippingCalculatorInterface (P2 — stub in 4.3)

```php
interface ShippingCalculatorInterface
{
    public function calculate(Cart $cart, array $shippingAddress): Money;
}

/** MVP: FixedShippingCalculator — config commerce.shipping_flat_rate_uzs or 0 */
```

### 3.5 PaymentGatewayInterface

Unchanged — Sprint 4.5.

---

## 4. Cart versioning (P0)

Prevent checkout races when the cart changes between opening checkout and payment.

| Storage | Field | Behaviour |
|---------|-------|-----------|
| PostgreSQL `carts` | `version` INTEGER NOT NULL DEFAULT 1 | Increment on **every** mutation |
| Redis guest cart | `version` in JSON payload | Same semantics |

**Rules:**

1. `GET /cart` returns `version` in response.
2. `POST /checkout` requires `cart_version` (body) matching current cart.
3. If `cart_version !== cart.version` → `409 Conflict` (`CartStaleException`): *"Cart changed — refresh and try again."*
4. Successful checkout increments version when clearing cart (or deletes cart).

```php
// CheckoutService (4.5)
if ($request->cartVersion !== $cart->version) {
    throw new CartStaleException();
}
```

---

## 5. Checkout idempotency (P0)

All checkout requests **must** be safely retryable.

| Item | Detail |
|------|--------|
| Header | `Idempotency-Key: {uuid-v4}` required on `POST /checkout` |
| Storage | `idempotency_keys` table (or Redis with 24h TTL) |
| Behaviour | Same `user_id` + `key` → return **cached response** (same `order_id`) |
| Conflict | Same key + different request body hash → `422` |
| TTL | 24 hours |

Table `idempotency_keys`:

| Column | Type |
|--------|------|
| id | BIGSERIAL |
| user_id | UUID |
| idempotency_key | VARCHAR(64) |
| request_hash | VARCHAR(64) |
| order_id | UUID NULLABLE |
| response_json | JSONB |
| expires_at | TIMESTAMP |

```
POST /checkout + Idempotency-Key
    ↓
Key exists? ──yes──▶ return cached response (no second order)
    ↓ no
Execute checkout pipeline → store response → return
```

---

## 6. Cart architecture (Sprint 4.3)

### 6.1 Storage

| Actor | Storage | Key |
|-------|---------|-----|
| Authenticated user | PostgreSQL `carts` + `cart_items` | `user_id` (1:1) |
| Guest | Redis JSON | `cart:guest:{token}` TTL 30 days |

Guest payload:

```json
{
  "version": 3,
  "items": [{ "product_id": "...", "variant_id": null, "quantity": 2 }]
}
```

### 6.2 CartService flow

```
CartController → CartService → CartRepository
                    ├─ InventoryServiceInterface::assertAvailable
                    ├─ PricingServiceInterface::priceCart (Money)
                    ├─ CouponServiceInterface (stub)
                    ├─ ShippingCalculatorInterface (stub, summary only)
                    └─ version++ on every mutation
```

### 6.3 Guest → user merge

Dispatches **`CartMerged`** after successful merge.

---

## 7. Checkout orchestration (Sprint 4.5)

```
CheckoutService::checkout(user, cartVersion, idempotencyKey, ...)
  1. Resolve idempotency (return cached if hit)
  2. Load cart; assert cartVersion === cart.version  ← P0
  BEGIN TRANSACTION
    3. InventoryService::reserveForOrder(orderId, lines)  ← TTL 15min
    4. coupon = CouponService::resolve(code, cart)
    5. shipping = ShippingCalculator::calculate(cart, address)
    6. snapshots = PricingService::buildOrderLineSnapshots(cart)
    7. totals = PricingService::calculateOrderTotals(snapshots, coupon, shipping)
    8. order = OrderService::createFromCheckout(...)
    9. CartService::clear(cart) → dispatch CartCheckedOut
  COMMIT
  10. Store idempotency response
  11. PaymentGateway::initiate(order)
```

On `PaymentSucceeded` → `InventoryService::confirmReservation`  
On `PaymentFailed` / timeout → `InventoryService::releaseReservation`

---

## 8. Order state machine (Sprint 4.4)

Unchanged — see v1 doc. `order_items` use `Money` columns + snapshot fields.

---

## 9. Events

| Event | Emitter | Sprint | Purpose |
|-------|---------|--------|---------|
| `CartItemAdded` | `CartService` | 4.3 | Analytics |
| `CartItemRemoved` | `CartService` | 4.3 | Analytics |
| `CartUpdated` | `CartService` | 4.3 | Analytics |
| `CartMerged` | `CartService` | 4.3 | Guest→user merge |
| `CartExpired` | `CartService` / job | 4.3 | Guest TTL cleanup |
| `CartCheckedOut` | `CheckoutService` | 4.5 | Funnel, recovery |
| `OrderCreated` | `OrderService` | 4.4 | |
| `OrderCompleted` | `OrderService` | 4.4 | |
| `PaymentSucceeded` | Checkout / webhook | 4.5 | |
| `PaymentFailed` | Checkout / webhook | 4.5 | |

---

## 10. Sprint delivery map

| Sprint | Delivers |
|--------|----------|
| **4.3** | Cart + `version`, `Money` VO, inventory/pricing/coupon/shipping interfaces (+ stubs), cart events (incl. `CartMerged`, `CartExpired`) |
| **4.4** | Orders, state machine, `order_items` snapshots (`Money`) |
| **4.5** | `CheckoutService`, idempotency, cart version check, inventory TTL + release job, `CartCheckedOut`, `FakePaymentGateway` |
| **4.6** | Seller APIs |

---

## 11. Quality & dependency rules

- PHPStan max; `Money` enforced — no float money math in services.
- ADR-016 v2 **Accepted** before Sprint 4.3 implementation.

---

## References

- [03_DATABASE_DESIGN.md](./03_DATABASE_DESIGN.md)
- [04_API_SPECIFICATION.md](./04_API_SPECIFICATION.md)
- [blueprints/sprint-4.3-shopping-cart.md](../blueprints/sprint-4.3-shopping-cart.md)
