# Sprint 4.4 — Order System · Blueprint

**Version:** 2  
**Sprint:** 4.4  
**Status:** Approved (2026-06-28)  
**Phase:** 4 — Commerce  
**Depends on:** Sprint 4.3 — Shopping Cart (`v0.4.3-shopping-cart`)  
**ADR (required):** [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate) — **Accepted**  
**Architecture:** [23_COMMERCE_CORE_ARCHITECTURE.md](../docs/23_COMMERCE_CORE_ARCHITECTURE.md) §8  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md)

> **Gate rules:**  
> 1. [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate) **Accepted**  
> 2. This blueprint **Approved**  
> 3. Sprint 4.3 CI green + released (`v0.4.3-shopping-cart`)  
> No code until all gates pass.

---

## 1. Goal

Ship the **Order domain** — immutable line snapshots, formal state machine, buyer/seller APIs, refund workflow — **without** checkout orchestration or payment gateway integration (Sprint 4.5).

**Architectural pillars:**

1. **ADR-017 aggregate** — `Order` root with `OrderItem[]`, `PaymentSnapshot`, `ShipmentSnapshot`, `OrderTimeline`.
2. **Immutable lines (P0)** — `order_items` INSERT only; no UPDATE/DELETE after creation.
3. **State machine (P0)** — all 19 transitions via `OrderStateMachine`; no direct `status` writes.
4. **Optimistic locking (P0)** — `orders.version`; mismatch → `409 OrderConcurrentModificationException`.
5. **Audit trail** — `order_status_transitions` append-only log with idempotency keys.
6. **Money VO** — all monetary fields use `Money`; no float in services.

**In scope:** migrations, models, enums, `OrderService`, `OrderStateMachine`, buyer/seller order APIs, refund requests, events, Pest tests, docs.  
**Out of scope:** `CheckoutService`, `POST /checkout`, `idempotency_keys`, `inventory_reservations`, `PaymentGateway`, payment webhooks, `CartCheckedOut` — **Sprint 4.5**.

---

## 2. P0 — Mandatory requirements (non-negotiable)

### 2.1 Order Number Generator

**Never** expose auto-increment `order_items.id` or raw UUID as the public order identifier.  
Buyers, sellers, and support use **`order_number`** only.

| Requirement | Rule |
|-------------|------|
| Unique | UNIQUE index on `orders.order_number`; generator retries on collision |
| Human-readable | Prefix + structured or short token |
| Opacity | Must **not** reveal total order volume to competitors |
| Immutable | Set once at `createFromCheckout()`; **no UPDATE** ever |

**Supported strategies** (config: `commerce.order_number.strategy`):

| Strategy | Example | Opacity | Notes |
|----------|---------|---------|-------|
| `date_sequence` | `LC-20260702-000001` | Medium — daily seq resets; use zero-padded 6 digits | Default for MVP |
| `short_code` | `LC-7YQ29AF4` | High — Crockford Base32, 8 chars | Alternative; no date leakage |

```php
// app/Services/Order/OrderNumberGenerator.php
interface OrderNumberGeneratorInterface
{
    public function generate(): string;
}
```

**`date_sequence` algorithm:**

1. Prefix from `config('commerce.order_number_prefix', 'LC')`.
2. Date part `YYYYMMDD` (app timezone).
3. Sequence: `SELECT COUNT(*) + 1 FROM orders WHERE order_number LIKE 'LC-20260702-%'` inside transaction **or** dedicated `order_number_sequences` row per day (preferred — avoids COUNT leak in hot path).
4. Format: `{prefix}-{date}-{seq:06d}`.

**`short_code` algorithm:** `{prefix}-{8 char Crockford Base32}`; retry on UNIQUE violation (max 5).

**Forbidden:** `order_number = (string) $order->id`, sequential BIGINT, or `order_items.id`.

**Test:** `OrderNumberGeneratorTest` — uniqueness, format, immutability after save.

### 2.2 Immutable Order

> **After order creation, line items are a frozen commercial contract.**  
> This is the cornerstone of e-commerce integrity (ADR-017 §2).

#### Forbidden after `OrderService::createFromCheckout()` commits

| Entity / field | Rule |
|----------------|------|
| **Entire `OrderItem` row** | No UPDATE, no DELETE |
| `order_items.quantity` | Immutable |
| `order_items.unit_price` | Immutable |
| `order_items.discount` | Immutable |
| `order_items.product_title` | Immutable |
| `order_items.sku` | Immutable |
| `order_items.variant_name` | Immutable |
| `order_items.line_total` | Immutable |
| `orders.subtotal`, `discount`, `tax`, `total` | Immutable (order-level totals frozen) |
| `orders.order_number` | Immutable |

#### Allowed mutations (only via `OrderService::transition()` or dedicated snapshot updaters)

| Target | When |
|--------|------|
| `orders.status` | State machine transitions |
| **PaymentSnapshot** | Payment / refund transitions only |
| **ShipmentSnapshot** | Fulfillment transitions only |
| `order_status_transitions` | APPEND only (timeline) |
| `refund_requests` | INSERT + status updates on refund entity |

**Enforcement (all required in 4.4):**

1. `OrderItem` model — disable `save()` on existing rows / no fillable price fields.
2. `OrderRepository` — **no** `updateOrderItem()`, `deleteOrderItem()`.
3. `OrderItemImmutableException` on any violation.
4. `OrderImmutabilityTest` + PHPStan: no `order_items` UPDATE in app code.

### 2.3 Optimistic lock (`orders.version`)

Protect against duplicate webhook processing and concurrent seller actions.

| Rule | Detail |
|------|--------|
| Column | `orders.version INTEGER NOT NULL DEFAULT 1` |
| Increment | Every successful `transition()` (+1) |
| Client header | `If-Match: {version}` on seller status PUT (recommended) |
| Conflict | `WHERE id = ? AND version = ?` → 0 rows → `409 OrderConcurrentModificationException` |
| Alternative rejected | `updated_at` alone — insufficient for idempotent replay detection |

**Pattern:**

```php
$updated = Order::where('id', $order->id)
    ->where('version', $expectedVersion)
    ->update([..., 'version' => $expectedVersion + 1]);

if ($updated === 0) {
    throw new OrderConcurrentModificationException();
}
```

Payment webhooks (4.5) **must** pass current `version`; duplicate `paid` webhook with same version → no-op success.

### 2.4 Timeline event store (`order_status_transitions`)

**Do not** store order history in JSON on `orders` (no `status_history` JSONB).  
Use a **dedicated append-only table** — required for support, analytics, audit, and future AI agents.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| order_id | UUID | FK → orders |
| from_status | VARCHAR(30) | |
| to_status | VARCHAR(30) | |
| actor_type | VARCHAR(20) | `buyer`, `seller`, `admin`, `system`, `payment_gateway` |
| actor_id | UUID | NULLABLE |
| reason | TEXT | NULLABLE — cancel/refund reason |
| idempotency_key | VARCHAR(64) | NULLABLE |
| metadata | JSONB | NULLABLE — gateway refs only; not a substitute for rows |
| created_at | TIMESTAMP | |

**Rules:** INSERT only. API `timeline[]` is a **read projection** from this table.  
**Unique:** `(order_id, idempotency_key)` WHERE `idempotency_key IS NOT NULL`.

---

## 3. P1 — Strongly recommended

### 3.1 Order domain events

Register all events in 4.4 even if not all have listeners yet. Implements `ShouldDispatchAfterCommit`.

| Event | When | Maps from ADR transition |
|-------|------|--------------------------|
| `OrderCreated` | Order persisted | T01 |
| `OrderConfirmed` | `pending` → `awaiting_payment` | T02 (alias: payment initiated) |
| `OrderCancelled` | → `cancelled` | T03, T05 |
| `OrderPaid` | → `paid` | T04 |
| `OrderPackingStarted` | → `packing` | T06 |
| `OrderReadyToShip` | → `ready_to_ship` | T08 |
| `OrderShipped` | → `shipped` | T09 |
| `OrderDelivered` | → `delivered` | T10 |
| `OrderCompleted` | → `completed` | T11 |
| `RefundRequested` | → `refund_requested` | T07, T12, T13 |
| `RefundApproved` | → `refund_approved` | T14 |
| `RefundRejected` | → `refund_rejected` | T15 |
| `RefundCompleted` | → `refunded` | T17 |

Legacy aliases acceptable in listeners: `OrderFulfillmentStarted` = `OrderPackingStarted`, `OrderRefunded` = `RefundCompleted`.

### 3.2 PaymentSnapshot (value object)

Even without a real payment gateway in 4.4 — model the snapshot now; populate in 4.5.

| Field | Column / type | 4.4 default |
|-------|---------------|-------------|
| `provider` | `payment_provider` VARCHAR(50) | `fake` or NULL |
| `method` | `payment_method` VARCHAR(50) | from checkout DTO |
| `transaction_id` | `payment_transaction_id` VARCHAR(255) NULLABLE | NULL |
| `currency` | `currency` CHAR(3) | `UZS` |
| `amount` | `total` BIGINT (Money) | frozen at creation |
| `status` | `payment_status` | `pending` → `paid` / `failed` / `refunded` |
| `reference` | `payment_reference` VARCHAR(255) NULLABLE | NULL (legacy compat) |
| `paid_at` | TIMESTAMP NULLABLE | set on T04 |

Updated **only** by payment/refund transitions — never from cart or product catalog.

### 3.3 ShipmentSnapshot (value object)

| Field | Column | 4.4 |
|-------|--------|-----|
| `address` | `shipping_address` JSONB | Required at creation |
| `carrier` | `carrier` VARCHAR(100) NULLABLE | Set on ship |
| `tracking_number` | `tracking_number` VARCHAR(255) NULLABLE | Required when `shipped` |
| `estimated_delivery` | `estimated_delivery_at` TIMESTAMP NULLABLE | Optional on `ready_to_ship` |
| `actual_delivery` | `delivered_at` TIMESTAMP NULLABLE | Set on T10 |
| `shipped_at` | `shipped_at` TIMESTAMP NULLABLE | Set on T09 |

Updated **only** by fulfillment transitions.

---

## 4. P2 — Desired

### 4.1 Order policy (authorization)

`App\Policies\OrderPolicy` — enforced in `OrderService` and controllers.

| Actor | Scope | Abilities |
|-------|-------|-----------|
| **Buyer** | Own orders (`user_id`) | `view`, `cancel` (pending/awaiting_payment), `requestRefund` |
| **Seller** | Store orders (`store_id` = seller's store) | `view`, `updateStatus` (fulfillment), `resolveRefund` |
| **Admin** | All orders | `view`, `transition` (override — future), `resolveRefund` |

```
Buyer  → orders WHERE user_id = auth.id
Seller → orders WHERE store_id = auth.seller.store_id
Admin  → orders (no scope filter)
```

Unauthorized access returns **404** (not 403) to avoid order ID enumeration.

### 4.2 Order metrics (analytics)

Record commerce funnel events via `MetricsService::record()` from order event listeners.  
Extends [ANALYTICS_LAYER.md](../docs/ANALYTICS_LAYER.md) — server-side only (no client batch for orders in 4.4).

| Metric event | Trigger | Payload (indicative) |
|--------------|---------|----------------------|
| `order_created` | `OrderCreated` | `order_id`, `store_id`, `total`, `currency`, `item_count` |
| `order_paid` | `OrderPaid` | `order_id`, `payment_provider`, `amount` |
| `order_cancelled` | `OrderCancelled` | `order_id`, `reason`, `status_before` |
| `refund_requested` | `RefundRequested` | `order_id`, `refund_id`, `reason` |
| `refund_completed` | `RefundCompleted` | `order_id`, `refund_id`, `amount` |

**Implementation:** `Listeners\RecordOrderAnalytics` subscribed to domain events; writes to `engagement_events` (or dedicated `commerce_events` table in future).  
Recommendation Engine (3.4+) can consume `order_paid` / `order_created` as purchase signals.

---

## 5. Prerequisites

| Gate | Document | Status |
|------|----------|--------|
| ADR | [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate) | **Accepted** |
| Architecture | [23_COMMERCE_CORE_ARCHITECTURE.md](../docs/23_COMMERCE_CORE_ARCHITECTURE.md) §8 | Accepted |
| Prior sprint | 4.3 Shopping Cart | ✅ Shipped |
| Blueprint | This document | Approved |

---

## 6. Domain model

### 6.1 Aggregate structure

Per [ADR-017 §1](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate):

```
Order (Aggregate Root — Eloquent `orders` + relations)
├── items: OrderItem[]           ← immutable children
├── payment: PaymentSnapshot     ← columns on orders + payment fields
├── shipment: ShipmentSnapshot   ← shipping_address JSON + shipment columns
├── totals: OrderTotals          ← subtotal, discount, shipping, tax, total (Money)
└── timeline: OrderStatusTransition[]  ← order_status_transitions
```

**Refund child entity:** `refund_requests` — linked via `orders.active_refund_id` (nullable).

### 6.2 PostgreSQL — `orders`

Migration aligns [03_DATABASE_DESIGN.md §3.20](../docs/03_DATABASE_DESIGN.md) with ADR-017.

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| order_number | VARCHAR(24) | UNIQUE; see §2.1 — **immutable** |
| user_id | UUID | FK → users (buyer) |
| store_id | UUID | FK → stores |
| status | VARCHAR(30) | `OrderStatus` enum |
| version | INTEGER | NOT NULL DEFAULT 1; ++ on every transition |
| status_before_refund | VARCHAR(30) | NULLABLE; set on refund request |
| active_refund_id | UUID | NULLABLE FK → refund_requests |
| subtotal | BIGINT | Money amount (UZS whole units) |
| shipping_cost | BIGINT | Money amount |
| discount | BIGINT | Money amount |
| tax | BIGINT | NOT NULL DEFAULT 0 (stub) |
| total | BIGINT | Money amount |
| currency | CHAR(3) | NOT NULL DEFAULT `UZS` |
| shipping_address | JSONB | Snapshot at checkout |
| payment_method | VARCHAR(50) | PaymentSnapshot.method |
| payment_provider | VARCHAR(50) | PaymentSnapshot.provider; NULLABLE in 4.4 |
| payment_status | VARCHAR(20) | PaymentSnapshot.status |
| payment_transaction_id | VARCHAR(255) | PaymentSnapshot.transaction_id; NULLABLE |
| payment_reference | VARCHAR(255) | Legacy/alias; NULLABLE |
| carrier | VARCHAR(100) | ShipmentSnapshot; NULLABLE |
| tracking_number | VARCHAR(255) | ShipmentSnapshot; NULLABLE |
| estimated_delivery_at | TIMESTAMP | ShipmentSnapshot; NULLABLE |
| notes | TEXT | NULLABLE |
| paid_at | TIMESTAMP | NULLABLE |
| shipped_at | TIMESTAMP | NULLABLE |
| delivered_at | TIMESTAMP | ShipmentSnapshot.actual_delivery; NULLABLE |
| completed_at | TIMESTAMP | NULLABLE |
| cancelled_at | TIMESTAMP | NULLABLE |
| created_at / updated_at | TIMESTAMP | |

**Replace legacy default** `pending_payment` → `pending`.

### 6.3 PostgreSQL — `order_items`

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| order_id | UUID | FK → orders |
| product_id | UUID | FK → products |
| variant_id | BIGINT | NULLABLE |
| product_title | VARCHAR(255) | Snapshot |
| variant_name | VARCHAR(100) | NULLABLE snapshot |
| sku | VARCHAR(100) | NULLABLE snapshot |
| quantity | INTEGER | NOT NULL — **immutable** |
| unit_price | BIGINT | Money snapshot |
| discount | BIGINT | NOT NULL DEFAULT 0 |
| line_total | BIGINT | Money |
| currency | CHAR(3) | NOT NULL DEFAULT `UZS` |
| created_at | TIMESTAMP | |

**Repository rule:** `INSERT` only. No `update()` on `OrderItem` model (guard or `OrderItemImmutableException`).

### 6.4 PostgreSQL — `order_status_transitions`

See §2.4. **Not JSON on `orders`.**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGSERIAL | PK |
| order_id | UUID | FK → orders |
| from_status | VARCHAR(30) | |
| to_status | VARCHAR(30) | |
| actor_type | VARCHAR(20) | `OrderActor` enum |
| actor_id | UUID | NULLABLE |
| reason | TEXT | NULLABLE |
| idempotency_key | VARCHAR(64) | NULLABLE |
| metadata | JSONB | NULLABLE |
| created_at | TIMESTAMP | |

**Unique index:** `(order_id, idempotency_key)` WHERE `idempotency_key IS NOT NULL`.

### 6.5 PostgreSQL — `refund_requests`

Align [03_DATABASE_DESIGN.md §3.22](../docs/03_DATABASE_DESIGN.md):

| Column | Type | Notes |
|--------|------|-------|
| id | UUID | PK |
| order_id | UUID | FK → orders |
| user_id | UUID | FK → users |
| reason | TEXT | NOT NULL |
| status | VARCHAR(20) | `requested`, `approved`, `rejected`, `processing`, `completed`, `failed` |
| created_at / updated_at | TIMESTAMP | |

---

## 7. Enums

| Enum | Values |
|------|--------|
| `OrderStatus` | `draft`, `pending`, `awaiting_payment`, `paid`, `packing`, `ready_to_ship`, `shipped`, `delivered`, `completed`, `cancelled`, `refund_requested`, `refund_approved`, `refund_rejected`, `refunded` |
| `OrderActor` | `buyer`, `seller`, `admin`, `system`, `payment_gateway` |
| `PaymentStatus` | `pending`, `paid`, `failed`, `refunded` |
| `RefundRequestStatus` | `requested`, `approved`, `rejected`, `processing`, `completed`, `failed` |

---

## 8. Contracts & services

### 8.1 Interfaces

| Interface | Methods |
|-----------|---------|
| `OrderServiceInterface` | `createFromCheckout()`, `getForBuyer()`, `getForSeller()`, `listForBuyer()`, `listForSeller()`, `transition()`, `requestRefund()`, `resolveRefund()` |
| `OrderStateMachineInterface` | `assertCanTransition()`, `allowedTargets()` |
| `TaxServiceInterface` | `calculate(Cart): Money` — **stub** `ZeroTaxService` |

Extend `PricingServiceInterface` (4.3):

| Method | Sprint |
|--------|--------|
| `buildOrderLineSnapshots(Cart $cart): array` | **4.4** |
| `calculateOrderTotals(snapshots, coupon, shipping): OrderTotals` | **4.4** |

### 8.2 `OrderService`

| Method | Description |
|--------|-------------|
| `createFromCheckout(CreateOrderData $data): Order` | T01: persist order + items + timeline; status `pending` or `draft`→`pending` in one TX |
| `transition(Order $order, OrderStatus $to, TransitionContext $ctx): Order` | Validates version, state machine, idempotency; appends timeline; dispatches events |
| `getForBuyer(userId, orderId): Order` | Authorization: buyer owns order |
| `getForSeller(storeId, orderId): Order` | Authorization: order.store_id matches seller store |
| `listForBuyer(userId, filters, pagination)` | Offset pagination |
| `listForSeller(storeId, filters, pagination)` | Filter by `status` |
| `requestRefund(userId, orderId, reason, idempotencyKey?)` | T07/T12/T13 |
| `resolveRefund(sellerId, refundId, approve\|reject, idempotencyKey?)` | T14/T15 |

**Creation path (4.4 tests + 4.5 checkout):**

```php
// Called by tests and future CheckoutService — NOT exposed as HTTP in 4.4
public function createFromCheckout(CreateOrderData $data): Order;
```

`CreateOrderData` DTO: `userId`, `storeId`, `items` (snapshots), `totals`, `payment`, `shipment`, `notes?`.

### 8.3 `OrderStateMachine`

Implements transition table from [ADR-017 §5](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate).

```php
final class OrderStateMachine implements OrderStateMachineInterface
{
    public function assertCanTransition(
        OrderStatus $from,
        OrderStatus $to,
        OrderActor $actor,
    ): void; // throws InvalidOrderTransitionException

    public function allowedTargets(OrderStatus $from, OrderActor $actor): array;
}
```

Unit-tested independently (`OrderStateMachineTest`).

### 8.4 `OrderRepository`

| Method | Rules |
|--------|-------|
| `create(Order $order, array $items, ?Transition $initial)` | INSERT order + items + first transition |
| `save(Order $order)` | UPDATE order fields; **never** touches order_items |
| `findByIdForBuyer(id, userId)` | |
| `findByIdForStore(id, storeId)` | |
| `paginateForBuyer(userId, filters)` | |
| `paginateForStore(storeId, filters)` | |
| `appendTransition(transition)` | INSERT only |
| `findByIdempotencyKey(orderId, key)` | For idempotent replay |

**Forbidden:** `updateOrderItem()`, `deleteOrderItem()`, `updateStatus()` shortcuts.

### 8.5 Config — `config/commerce.php` (extend)

```php
'order_number_prefix' => 'LC',
'order_number' => [
    'strategy' => 'date_sequence', // or 'short_code'
],
'refund_window_days' => 14,
'auto_complete_delivered_days' => 7,
'order_status_idempotency_ttl_hours' => 24,
```

---

## 9. State machine (reference)

Canonical transition table: **[ADR-017 §5](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate)**.

**4.4 implements transitions:**

| Group | Transitions | HTTP / trigger |
|-------|-------------|----------------|
| Creation | T01 | `OrderService::createFromCheckout()` (internal) |
| Payment prep | T02 | Internal helper `markAwaitingPayment()` for tests; full wire in 4.5 |
| Cancel | T03, T05 (partial) | `POST /orders/{id}/cancel` |
| Paid | T04 | Test helper `markPaid()`; webhook in 4.5 |
| Fulfillment | T06–T11 | `PUT /seller/orders/{id}/status` |
| Refund | T07, T12, T13–T17 | `POST /orders/{id}/refund`, `PUT /seller/refunds/{id}` |
| Restore | T16 | Automatic on T15 |

**Test helpers** (not production HTTP): `OrderTestHelper::createPaidOrder()`, `transitionTo()` for state machine coverage.

---

## 10. API

Prefix `/api/v1`. Controllers → `OrderServiceInterface` only.

### 10.1 Buyer endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/orders` | Yes | List own orders (offset) |
| GET | `/orders/{id}` | Yes | Order detail + timeline |
| POST | `/orders/{id}/cancel` | Yes | Cancel if `pending` / `awaiting_payment` |
| POST | `/orders/{id}/refund` | Yes | Request refund |
| GET | `/refunds/{id}` | Yes | Refund request detail |

**Headers (mutations):** `Idempotency-Key: {uuid}` recommended.

### 10.2 Seller endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/seller/orders` | Seller | List store orders |
| GET | `/seller/orders/{id}` | Seller | Order detail |
| PUT | `/seller/orders/{id}/status` | Seller | Fulfillment transitions |
| PUT | `/seller/refunds/{id}` | Seller | Approve / reject refund |

**PUT /seller/orders/{id}/status Request:**

```json
{
  "status": "packing",
  "tracking_number": "UZ123456789",
  "carrier": "UzPost"
}
```

**Validation:** `tracking_number` required when `status` = `shipped` (configurable).

### 10.3 `OrderResource` response

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "order_number": "LC-20260702-000001",
    "status": "paid",
    "version": 3,
    "store": { /* StoreCompactResource */ },
    "items": [
      {
        "id": 1,
        "product_title": "Summer Dress",
        "variant_name": "M",
        "sku": "DRS-001",
        "quantity": 1,
        "unit_price": { "amount": 250000, "currency": "UZS" },
        "line_total": { "amount": 250000, "currency": "UZS" },
        "product": { /* ProductCardResource */ }
      }
    ],
    "totals": {
      "subtotal": { "amount": 250000, "currency": "UZS" },
      "shipping": { "amount": 25000, "currency": "UZS" },
      "discount": { "amount": 0, "currency": "UZS" },
      "tax": { "amount": 0, "currency": "UZS" },
      "total": { "amount": 275000, "currency": "UZS" }
    },
    "payment": {
      "provider": "fake",
      "method": "fake",
      "transaction_id": null,
      "currency": "UZS",
      "amount": { "amount": 275000, "currency": "UZS" },
      "status": "paid",
      "reference": null,
      "paid_at": "2026-06-28T10:00:00Z"
    },
    "shipment": {
      "address": { /* shipping_address */ },
      "carrier": null,
      "tracking_number": null,
      "estimated_delivery": null,
      "shipped_at": null,
      "actual_delivery": null
    },
    "timeline": [
      {
        "from_status": "pending",
        "to_status": "paid",
        "actor_type": "system",
        "created_at": "2026-06-28T10:00:00Z"
      }
    ],
    "created_at": "2026-06-28T09:55:00Z"
  }
}
```

### 10.4 Errors

| Code | Exception | When |
|------|-----------|------|
| 404 | `OrderNotFoundException` | Order not found or not owned |
| 409 | `OrderConcurrentModificationException` | `version` mismatch |
| 409 | `InvalidOrderTransitionException` | Illegal status transition |
| 422 | `RefundNotAllowedException` | Outside refund window / wrong status |
| 422 | `OrderNotCancellableException` | Cancel not allowed in current status |

---

## 11. Events

See §3.1 for canonical list. All implement `ShouldDispatchAfterCommit`.

| Event | Transition | Sprint |
|-------|------------|--------|
| `OrderCreated` | T01 | 4.4 |
| `OrderConfirmed` | T02 | 4.4 |
| `OrderCancelled` | T03, T05 | 4.4 |
| `OrderPaid` | T04 | 4.4 |
| `OrderPackingStarted` | T06 | 4.4 |
| `OrderReadyToShip` | T08 | 4.4 |
| `OrderShipped` | T09 | 4.4 |
| `OrderDelivered` | T10 | 4.4 |
| `OrderCompleted` | T11 | 4.4 |
| `RefundRequested` | T07, T12, T13 | 4.4 |
| `RefundApproved` | T14 | 4.4 |
| `RefundRejected` | T15 | 4.4 |
| `RefundCompleted` | T17 | 4.4 |
| `PaymentSucceeded` / `PaymentFailed` | T04 / T05 | **4.5** |

**P2 listener:** `RecordOrderAnalytics` → `order_created`, `order_paid`, `order_cancelled`, `refund_requested`, `refund_completed`.

---

## 12. Jobs (4.4)

| Job | Schedule | Transition |
|-----|----------|------------|
| `CompleteDeliveredOrdersJob` | Daily | T11: `delivered` → `completed` after grace period |
| `ReleaseExpiredInventoryReservationsJob` | Every minute | **4.5** (not 4.4) |

---

## 13. File plan

| Path | Action |
|------|--------|
| `database/migrations/2026_07_03_000001_create_orders_tables.php` | Create `orders`, `order_items`, `order_status_transitions`, `refund_requests` |
| `app/Enums/OrderStatus.php` | Create |
| `app/Enums/OrderActor.php` | Create |
| `app/Enums/PaymentStatus.php` | Create |
| `app/Enums/RefundRequestStatus.php` | Create |
| `app/Models/Order.php` | Create (replace skeleton if any) |
| `app/Models/OrderItem.php` | Create |
| `app/Models/OrderStatusTransition.php` | Create |
| `app/Models/RefundRequest.php` | Create |
| `app/ValueObjects/OrderTotals.php` | Create |
| `app/DTOs/Order/CreateOrderData.php` | Create |
| `app/DTOs/Order/TransitionContext.php` | Create |
| `app/DTOs/Order/OrderLineSnapshot.php` | Create |
| `app/Contracts/Services/OrderServiceInterface.php` | Create |
| `app/Contracts/Services/OrderStateMachineInterface.php` | Create |
| `app/Contracts/Services/TaxServiceInterface.php` | Create |
| `app/Contracts/Repositories/OrderRepositoryInterface.php` | Create |
| `app/Services/Order/OrderService.php` | Create |
| `app/Services/Order/OrderStateMachine.php` | Create |
| `app/Contracts/Services/OrderNumberGeneratorInterface.php` | Create |
| `app/Services/Order/DateSequenceOrderNumberGenerator.php` | Create |
| `app/Services/Order/ShortCodeOrderNumberGenerator.php` | Create |
| `app/Policies/OrderPolicy.php` | Create (P2) |
| `app/Listeners/RecordOrderAnalytics.php` | Create (P2) |
| `app/DTOs/Order/PaymentSnapshot.php` | Create |
| `app/DTOs/Order/ShipmentSnapshot.php` | Create |
| `app/Services/Tax/ZeroTaxService.php` | Create stub |
| `app/Repositories/Eloquent/OrderRepository.php` | Create |
| `app/Exceptions/Domain/InvalidOrderTransitionException.php` | Create |
| `app/Exceptions/Domain/OrderConcurrentModificationException.php` | Create |
| `app/Exceptions/Domain/OrderItemImmutableException.php` | Create |
| `app/Exceptions/Domain/OrderNotCancellableException.php` | Create |
| `app/Exceptions/Domain/RefundNotAllowedException.php` | Create |
| `app/Http/Controllers/Api/V1/OrderController.php` | Create |
| `app/Http/Controllers/Api/V1/SellerOrderController.php` | Create |
| `app/Http/Controllers/Api/V1/RefundController.php` | Create |
| `app/Http/Resources/OrderResource.php` | Create |
| `app/Http/Resources/OrderItemResource.php` | Create |
| `app/Http/Resources/RefundResource.php` | Create |
| `app/Http/Requests/Order/CancelOrderRequest.php` | Create |
| `app/Http/Requests/Order/RequestRefundRequest.php` | Create |
| `app/Http/Requests/Order/UpdateSellerOrderStatusRequest.php` | Create |
| `app/Http/Requests/Order/ResolveRefundRequest.php` | Create |
| `app/Events/Order*.php`, `Refund*.php` | Create per §11 |
| `app/Jobs/CompleteDeliveredOrdersJob.php` | Create |
| `app/Providers/RepositoryServiceProvider.php` | Bind interfaces |
| `routes/api.php` | Add order + seller order routes |
| `config/commerce.php` | Extend order settings |
| `database/factories/OrderFactory.php` | Create |
| `tests/Unit/Order/OrderNumberGeneratorTest.php` | Create |
| `tests/Unit/Order/OrderStateMachineTest.php` | Create |
| `tests/Feature/Order/OrderTest.php` | Create |
| `tests/Feature/Order/OrderImmutabilityTest.php` | Create |
| `tests/Feature/Order/SellerOrderTest.php` | Create |
| `tests/Feature/Order/RefundTest.php` | Create |
| `tests/Pest.php` | Add `createOrderForBuyer()` helper |

**Not in 4.4:** `CheckoutService`, `PaymentGateway`, `idempotency_keys`, `inventory_reservations`.

---

## 14. Test plan (Pest)

Target: **+27 tests** minimum (155 → 182+).

### 14.0 `OrderNumberGeneratorTest` (unit, 3)

| # | Test |
|---|------|
| 1 | `date_sequence` format matches `LC-YYYYMMDD-000001` |
| 2 | Generated numbers are unique |
| 3 | `order_number` not derived from auto-increment id |

### 14.1 `OrderStateMachineTest` (unit, 8)

| # | Test |
|---|------|
| 1 | Happy path transitions allowed for seller |
| 2 | Buyer cannot transition to packing |
| 3 | Cancel allowed from pending only for buyer |
| 4 | Paid cannot transition to cancelled |
| 5 | Refund branch from paid allowed for buyer |
| 6 | RefundRejected restores allowed targets |
| 7 | Invalid transition throws |
| 8 | `allowedTargets` returns correct set |

### 14.2 `OrderTest` (feature, 8)

| # | Test |
|---|------|
| 1 | `createFromCheckout` persists immutable line snapshots |
| 2 | Buyer can list own orders |
| 3 | Buyer cannot view another user order |
| 4 | Buyer can cancel pending order |
| 5 | Buyer cannot cancel paid order |
| 6 | Order detail includes timeline and version |
| 7 | Duplicate cancel is idempotent (same Idempotency-Key) |
| 8 | Concurrent status update returns 409 on version mismatch |

### 14.3 `OrderImmutabilityTest` (feature, 5)

| # | Test |
|---|------|
| 1 | OrderItem rejects quantity update |
| 2 | OrderItem rejects unit_price update |
| 3 | OrderItem rejects product_title / sku update |
| 4 | Repository has no updateOrderItem method |
| 5 | Order totals match sum of immutable line snapshots |

### 14.4 `SellerOrderTest` (feature, 6)

| # | Test |
|---|------|
| 1 | Seller lists store orders filtered by status |
| 2 | Seller transitions paid → packing → ready_to_ship → shipped |
| 3 | Shipped requires tracking_number |
| 4 | Non-owner seller cannot view order |
| 5 | Seller transitions shipped → delivered |
| 6 | Fulfillment events dispatched |

### 14.5 `RefundTest` (feature, 5)

| # | Test |
|---|------|
| 1 | Buyer requests refund on paid order |
| 2 | Seller approves refund → refund_approved |
| 3 | Seller rejects refund → refund_rejected → status restored |
| 4 | Refund outside window → 422 |
| 5 | Duplicate refund request idempotent |

---

## 15. Implementation order

Branch: `feature/sprint-4.4-order-system`.

1. Migration + enums + models
2. Exceptions + `OrderStateMachine` + unit tests
3. `OrderRepository` + `OrderNumberGenerator`
4. Extend `ProductPricingService` for snapshots
5. `OrderService` + events
6. Controllers + resources + routes
7. `CompleteDeliveredOrdersJob`
8. Feature tests + immutability tests
9. Docs + QA + release `v0.4.4-order-system`

---

## 16. Documentation updates (post-implementation)

| Document | Update |
|----------|--------|
| `07_ADR.md` | ADR-017 → Accepted ✅ |
| `03_DATABASE_DESIGN.md` | orders schema v2, `order_status_transitions` |
| `04_API_SPECIFICATION.md` | §6.8 OrderResource Money JSON; §7.9 statuses |
| `CHANGELOG.md` | v0.4.4 entry |
| `13_ROADMAP.md` | 4.4 shipped |
| `docs/SPRINT_4_COMMERCE_PLAN.md` | 4.4 ✅, 4.5 current |

---

## 17. Acceptance criteria

- [ ] §2 P0 requirements implemented (order number, immutability, version lock, timeline table)
- [ ] ADR-017 transitions implemented and unit-tested
- [ ] `order_items` immutable — no UPDATE in application code (§2.2)
- [ ] `order_number` opaque and immutable — not auto-increment (§2.1)
- [ ] All buyer/seller endpoints per §10; `OrderPolicy` per §4.1
- [ ] `orders.version` optimistic locking works (409); `If-Match` supported
- [ ] `order_status_transitions` append-only — **no JSON history** on orders
- [ ] Domain events per §3.1 registered (listeners optional except P2 analytics)
- [ ] `PaymentSnapshot` + `ShipmentSnapshot` DTOs with nullable fields
- [ ] Idempotency-Key honoured on cancel, refund, seller status
- [ ] 182+ Pest tests; PHPStan 0 errors; Pint pass
- [ ] No `CheckoutService` or payment gateway code
- [ ] Release tag `v0.4.4-order-system`

---

## References

- [ADR-017](../07_ADR.md#adr-017-order-state-machine-and-order-aggregate)
- [ADR-016](../07_ADR.md#adr-016-commerce-core-cart-orders-inventory-payment)
- [SPRINT_4.4_BLUEPRINT.md](../docs/SPRINT_4.4_BLUEPRINT.md)
- [blueprints/sprint-4.3-shopping-cart.md](./sprint-4.3-shopping-cart.md)
