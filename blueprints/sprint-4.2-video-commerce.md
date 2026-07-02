# Sprint 4.2 — Video Commerce · Blueprint

**Version:** 2  
**Sprint:** 4.2  
**Status:** Approved (2026-06-28)  
**Phase:** 4 — Commerce  
**Depends on:** Sprint 4.1 — Product Catalog (merged)  
**Overview:** [docs/SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md)  
**Database:** [docs/03_DATABASE_DESIGN.md](../docs/03_DATABASE_DESIGN.md) §3.8 (extended below)  
**API:** [docs/04_API_SPECIFICATION.md](../docs/04_API_SPECIFICATION.md) §7.5 (to be updated on approval)

> **Gate rule:** No implementation until this blueprint is **Approved** (§15).  
> Sprint 4.3 (Cart) is **blocked** until 4.2 CI is green and released.

---

## 1. Goal

Attach **multiple products** to a **video** via a many-to-many pivot (`video_products`), with ordering, optional in-video timestamps, and a single featured product — exposed through backend API and a mobile-ready overlay contract.

**Architectural pillars:**

1. **Pivot-first** — `Video` ↔ `VideoProduct` ↔ `Product`; no product data duplicated on `videos`.
2. **Service-owned rules** — ownership, purchasability, featured uniqueness, sort order live in `VideoCommerceService`.
3. **Repository = persistence only** — `VideoProductRepository` syncs rows; no business validation.
4. **Event-driven hook** — `ProductAttachedToVideo` for analytics / future recommendations (Sprint 9).
5. **Overlay-optimized payload** — `ProductCard` DTO + `VideoProductResource`; full `ProductResource` only on product detail.
6. **Feed hydration** — `VideoResource.products` populated from the same service (no duplicate query logic).

**In scope:** Migration, model, repository, service, events, API, resources, mobile DTO parsing, Pest tests, docs.  
**Out of scope:** Cart, checkout, favorites UI, product detail screen, live-stream pinning (Sprint 6), recommendation scoring on products (Sprint 9).

---

## 2. Domain model

```
Video (1) ──< VideoProduct >── (N) Product
```

### 2.1 `video_products` table (migration)

Extends [03_DATABASE_DESIGN.md §3.8](../docs/03_DATABASE_DESIGN.md). Sprint 4.1 created `products`; this sprint adds the pivot.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGSERIAL | PK | |
| video_id | UUID | FK → videos.id, NOT NULL, ON DELETE CASCADE | |
| product_id | UUID | FK → products.id, NOT NULL, ON DELETE RESTRICT | |
| sort_order | SMALLINT | NOT NULL, DEFAULT 0 | Display order (0 = first) |
| is_featured | BOOLEAN | NOT NULL, DEFAULT false | Primary product chip on overlay |
| starts_at | DECIMAL(8,3) | NULLABLE | Seconds into video when tag becomes visible |
| ends_at | DECIMAL(8,3) | NULLABLE | Seconds into video when tag hides |
| position_x | DECIMAL(5,2) | NULLABLE | Overlay X (0–100 %) |
| position_y | DECIMAL(5,2) | NULLABLE | Overlay Y (0–100 %) |
| product_version | INTEGER | NOT NULL, DEFAULT 1 | Product version at attach time (see §2.3) |
| created_at | TIMESTAMP | NOT NULL | |
| updated_at | TIMESTAMP | NOT NULL | |

**Constraints:**

- `UNIQUE (video_id, product_id)`
- Partial unique index: at most one `is_featured = true` per `video_id` (PostgreSQL partial unique index)
- Check: `starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at`
- Check: `position_x IS NULL OR (position_x >= 0 AND position_x <= 100)`
- Check: `position_y IS NULL OR (position_y >= 0 AND position_y <= 100)`

**Index:** `(video_id, sort_order)` for ordered reads.

### 2.2 Eloquent models

| Model | Notes |
|-------|-------|
| `VideoProduct` | Pivot model with `$table = 'video_products'`; belongsTo Video, Product |
| `Video` | `products(): BelongsToMany` via `video_products` with pivot attributes + `orderBy('sort_order')` |
| `Product` | `videos(): BelongsToMany` inverse; `version` column incremented on seller update |

### 2.3 Product versioning (pivot snapshot)

Videos may remain published for years while sellers edit product copy/images. The pivot stores **`product_version`** — the `products.version` value **at attach time**.

| Table | Column | Behaviour |
|-------|--------|-----------|
| `products` | `version` INTEGER DEFAULT 1 | Incremented on seller `PUT /products/{id}` |
| `video_products` | `product_version` | Set on sync; **not auto-updated** when product changes |

Sprint 4.2 does **not** resolve version mismatches in overlay UI — field is persisted for future audit / “as tagged” display. Live overlay continues to read current `Product` data via `ProductCardResource`.

---

## 3. Contracts & services

### 3.1 Interfaces (`app/Contracts/`)

| Interface | Location | Responsibility |
|-----------|----------|----------------|
| `VideoCommerceServiceInterface` | `Contracts/Services/` | List tags, sync tags, resolve overlay for feed |
| `VideoProductRepositoryInterface` | `Contracts/Repositories/` | CRUD/sync pivot rows, eager-load helpers |

**Not in 4.2** (deferred to later sub-sprints per master plan):

- `InventoryServiceInterface` → Sprint 4.3
- `PricingServiceInterface` → Sprint 4.3
- `PaymentGatewayInterface` → Sprint 4.5 (stub exists)
- `TaxServiceInterface`, `ShippingCalculatorInterface` → Sprint 4.4–4.5

### 3.2 `VideoCommerceService`

| Method | Description |
|--------|-------------|
| `listForVideo(string $videoId, ?User $viewer): Collection` | Ordered `VideoProduct` rows with `product` (+ images, store) loaded; filters non-active products for public viewers |
| `syncForVideo(User $owner, string $videoId, array $attachments): Collection` | Replace-all sync; validates ownership, product eligibility, featured rule |
| `hydrateForVideos(Collection $videos, ?User $viewer): Collection` | Batch-load products onto video models for feed (N+1 safe) |

**Business rules (service only):**

| Rule | Behaviour |
|------|-----------|
| Video ownership | Only `videos.user_id === auth.id` may sync |
| Video state | Sync allowed when `status ∈ {processing, published}`; blocked for `uploading`, `failed`, `rejected`, `hidden` |
| Product eligibility | Product `status = active`, not soft-deleted, `store.user_id === video.user_id` |
| Max products | Config `config('commerce.video_max_products', 20)` |
| Featured | At most one `is_featured: true` per request; if none set, first item becomes featured |
| Sort order | Explicit `sort_order` in payload; auto-assign 0..n if omitted |
| Timestamps | Optional; both null = always visible; validate against `video.duration` when set |
| Public read | Viewers see only **active** products; owners see all attached (including out_of_stock) on their own videos |

### 3.3 `VideoProductRepository`

| Method | Description |
|--------|-------------|
| `syncForVideo(string $videoId, array $rows): void` | Delete missing + upsert within DB transaction |
| `findByVideoIds(array $videoIds): Collection` | Batch fetch with products |
| `countByVideo(string $videoId): int` | For validation |

No validation in repository.

---

## 4. Events

### 4.1 `ProductAttachedToVideo`

```php
final class ProductAttachedToVideo
{
    public function __construct(
        public readonly string $videoId,
        public readonly string $userId,
        /** @var list<string> */
        public readonly array $productIds,
        public readonly ?string $featuredProductId,
    ) {}
}
```

- Dispatched **after** successful sync (queue listener stub in 4.2; analytics in Sprint 11).
- Implements `ShouldDispatchAfterCommit`.

**Not in 4.2:** `CartUpdated`, `OrderCreated`, `PaymentSucceeded`, etc. (later sub-sprints).

---

## 5. API

Prefix: `/api/v1`. Controllers delegate to `VideoCommerceService` only.

### 5.1 Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/videos/{id}/products` | Optional | Ordered overlay products for one video |
| PUT | `/videos/{id}/products` | Required | Replace all product attachments (video owner) |

**Existing endpoints (updated behaviour, no new routes):**

| Endpoint | Change |
|----------|--------|
| `GET /videos/{id}` | `VideoResource.products` populated via `VideoCommerceService` |
| `GET /feed/*` | Feed videos include `products` array (compact overlay) |
| `PUT /videos/{id}` | Accept optional `products` array (same shape as PUT `/videos/{id}/products`) |

### 5.2 `PUT /videos/{id}/products` request

```json
{
  "products": [
    {
      "product_id": "uuid-1",
      "sort_order": 0,
      "is_featured": true,
      "starts_at": 5.0,
      "ends_at": 30.5,
      "position_x": 72.5,
      "position_y": 85.0
    },
    {
      "product_id": "uuid-2",
      "sort_order": 1,
      "is_featured": false
    }
  ]
}
```

- Empty `products: []` clears all tags.
- `product_id` required per item; other fields optional.

### 5.3 Response shapes

#### `VideoProductResource` (overlay item)

```json
{
  "product": { /* ProductCard fields — see §6 */ },
  "sort_order": 0,
  "is_featured": true,
  "starts_at": 5.0,
  "ends_at": 30.5,
  "position_x": 72.5,
  "position_y": 85.0
}
```

#### `GET /videos/{id}/products` response

```json
{
  "success": true,
  "data": [
    { /* VideoProductResource */ }
  ]
}
```

#### `VideoResource.products` (feed / detail)

Array of `VideoProductResource` (not empty `[]` stub).

**Errors:**

| Code | When |
|------|------|
| 403 | Not video owner (PUT) |
| 404 | Video or product not found |
| 422 | Validation (max products, invalid timestamps, ineligible product, duplicate featured) |

---

## 6. DTOs & API resources

### 6.1 `ProductCardData` (backend DTO)

`app/DTOs/Product/ProductCardData.php` — readonly DTO for overlay-optimized payload.

| Field | Type | Source |
|-------|------|--------|
| id | string | product.id |
| title | string | product.title |
| price | float | product.price |
| compare_at_price | ?float | product.compare_at_price |
| discount_percent | ?int | `Product::discountPercent()` |
| currency | string | `'UZS'` |
| thumbnail | ?string | first image url |
| store_name | ?string | store.name |
| status | string | product.status enum value |
| is_purchasable | bool | `Product::isPurchasable()` |

Factory: `ProductCardData::fromProduct(Product $product): self`

### 6.2 Resources

| Resource | Uses |
|----------|------|
| `ProductCardResource` | Maps `ProductCardData` or `Product` → JSON (overlay) |
| `VideoProductResource` | Pivot metadata + nested `ProductCardResource` |
| `ProductResource` | **Unchanged** — full detail for `GET /products/{id}` |
| `ProductCompactResource` | **Deprecated for video overlay** — use `ProductCardResource` instead |

### 6.3 Mobile `ProductCard` (Flutter)

`mobile/lib/features/feed/domain/entities/product_card.dart`

```dart
class ProductCard {
  final String id;
  final String title;
  final double price;
  final double? compareAtPrice;
  final int? discountPercent;
  final String currency;
  final String? thumbnail;
  final String? storeName;
  final String status;
  final bool isPurchasable;
  // fromJson factory matching ProductCardResource JSON
}

class VideoProductTag {
  final ProductCard product;
  final int sortOrder;
  final bool isFeatured;
  final double? startsAt;
  final double? endsAt;
  final double? positionX;
  final double? positionY;
}
```

**Mobile deliverables (4.2):**

- Parse `products` from `FeedVideo` / `VideoResource` JSON
- Extend `FeedVideo` with `List<VideoProductTag> products`
- **No** cart/checkout UI; optional read-only overlay chip widget (featured product title + price) behind feature flag

---

## 7. Integration points

### 7.1 Feed pipeline

`RecommendationService` / `VideoService::enrichVideosForViewer()` calls `VideoCommerceService::hydrateForVideos()` after video load — same pattern as `is_liked` / `is_bookmarked`.

### 7.2 Video upload flow

`CreateVideoUploadRequest` may accept optional `products` array (deferred sync until `published` **or** sync on create if video exists — **decision: sync on PUT/metadata update and dedicated PUT products endpoint**; upload initiate ignores products to keep 3.1 flow stable).

### 7.3 Recommendations (future)

`ProductAttachedToVideo` → stub listener logs event. Sprint 9 may boost videos with tagged purchasable products.

---

## 8. File plan

### 8.1 Backend (new / modified)

| Path | Action |
|------|--------|
| `database/migrations/2026_07_01_000001_create_video_products_table.php` | Create |
| `app/Models/VideoProduct.php` | Create |
| `app/Models/Video.php` | Add `products()` relation |
| `app/Models/Product.php` | Add `videos()` relation |
| `app/Contracts/Repositories/VideoProductRepositoryInterface.php` | Create |
| `app/Contracts/Services/VideoCommerceServiceInterface.php` | Create |
| `app/Repositories/Eloquent/VideoProductRepository.php` | Create |
| `app/Services/Video/VideoCommerceService.php` | Create |
| `app/DTOs/Product/ProductCardData.php` | Create |
| `app/Http/Resources/ProductCardResource.php` | Create |
| `app/Http/Resources/VideoProductResource.php` | Create |
| `app/Http/Resources/VideoResource.php` | Wire `products` |
| `app/Http/Controllers/Api/V1/VideoProductController.php` | Create (thin) |
| `app/Http/Requests/Video/SyncVideoProductsRequest.php` | Create |
| `app/Events/ProductAttachedToVideo.php` | Create |
| `app/Providers/AppServiceProvider.php` | Bind interfaces |
| `routes/api.php` | Register routes |
| `config/commerce.php` | `video_max_products` |
| `database/factories/VideoProductFactory.php` | Create |
| `tests/Feature/Video/VideoCommerceTest.php` | Create |

### 8.2 Mobile (new / modified)

| Path | Action |
|------|--------|
| `lib/features/feed/domain/entities/product_card.dart` | Create |
| `lib/features/feed/domain/entities/feed_video.dart` | Add `products` |
| `lib/features/feed/data/feed_repository.dart` | Parse products (if needed) |

---

## 9. Test plan (Pest)

Target: **+12 tests** (minimum), all green with existing suite.

| # | Test | Assert |
|---|------|--------|
| 1 | GET overlay empty video | `200`, `data: []` |
| 2 | PUT attach 2 products | `200`, ordered, one featured |
| 3 | GET overlay returns ProductCard fields | price, thumbnail, is_purchasable |
| 4 | PUT reorder changes sort_order | order matches payload |
| 5 | PUT set starts_at / ends_at | persisted on pivot |
| 6 | PUT featured exclusivity | second featured → 422 |
| 7 | PUT max products exceeded | 422 |
| 8 | PUT product not owned by creator | 403 or 422 |
| 9 | PUT product inactive | 422 |
| 10 | PUT not video owner | 403 |
| 11 | GET video detail includes products | VideoResource.products non-empty |
| 12 | Feed video includes products | `/feed/for-you` first item has products |
| 13 | ProductAttachedToVideo dispatched | `Event::fake()` after sync |
| 14 | Public viewer hides non-active product | owner sees all, guest sees active only |

**Quality gates:** PHPStan max, Pint, Pest 100% pass.

---

## 10. Implementation order

Branch: `feature/sprint-4.2-video-commerce` (from `develop` after approval).

1. Migration + models + factory
2. Repository + contracts
3. `ProductCardData` + resources
4. `VideoCommerceService` + event
5. Controller + request + routes
6. Feed/video hydration wiring
7. Mobile DTOs + `FeedVideo` parse
8. Pest tests
9. Docs + CHANGELOG + release tag `v0.4.2-video-commerce`

---

## 11. Documentation updates (post-approval)

| Document | Update |
|----------|--------|
| `docs/03_DATABASE_DESIGN.md` | §3.8 extended columns |
| `docs/04_API_SPECIFICATION.md` | §7.5 video products endpoints + ProductCard schema |
| `docs/08_MODULES.md` | Videos module — product tagging ✅ |
| `docs/17_DEPENDENCY_MATRIX.md` | Videos → Products hardens to ● for tagging |
| `13_ROADMAP.md` | 4.2 checkbox |
| `CHANGELOG.md` | v0.4.2 entry |
| `blueprints/README.md` | Status → Approved |

---

## 12. Architecture review notes

### 12.1 Open decisions (resolve before approval)

| # | Question | Recommendation |
|---|----------|----------------|
| A | Sync on video create (`POST /videos`) or only via `PUT /videos/{id}/products`? | **PUT only** in 4.2 — keeps upload flow unchanged |
| B | Allow tagging on `processing` videos? | **Yes** — creator prepares before publish |
| C | `ProductCompactResource` vs new `ProductCardResource`? | **New `ProductCardResource`** — adds `is_purchasable`, aligns with mobile DTO |
| D | Delete pivot on product soft-delete? | **RESTRICT** on FK — seller must detach first |

### 12.2 Risks

| Risk | Mitigation |
|------|------------|
| Feed N+1 on products | `hydrateForVideos()` batch query by video_ids |
| Large product lists on feed | Cap at 20; overlay shows featured + collapse UI in mobile 4.x |
| Timestamp validation vs duration | Service validates; null duration skips check |

### 12.3 Review checklist

- [ ] Pivot schema matches PostgreSQL constraints
- [ ] No business logic in controllers / repositories
- [ ] Service depends on interfaces only
- [ ] Event name matches cross-sprint event registry
- [ ] Mobile DTO matches API JSON 1:1
- [ ] No cart/checkout leakage into 4.2
- [ ] Sprint 4.3 remains blocked until 4.2 release

---

## 13. Acceptance criteria

- [ ] Creator can attach 1–20 own active products to their video
- [ ] Products returned in explicit `sort_order` with one `is_featured`
- [ ] Optional `starts_at` / `ends_at` / overlay positions persisted
- [ ] `GET /videos/{id}/products` returns overlay-ready payload
- [ ] Feed and video detail include `products` array
- [ ] `ProductAttachedToVideo` fires on sync
- [ ] Mobile parses `ProductCard` from API
- [ ] PHPStan 0 errors, Pest green, Pint pass
- [ ] No regression in Sprint 4.1 product catalog tests

---

## 14. Out of scope reminder

| Item | Sprint |
|------|--------|
| Add to cart from overlay | 4.3 |
| Checkout | 4.5 |
| Order state machine | 4.4 |
| Seller dashboard analytics | 4.6 |
| Live stream product pinning | 6.x |

---

## 15. Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| CTO / Founder | Akmal | 2026-06-28 | **Approved** |
| Backend lead | — | 2026-06-28 | **Approved** |

**On approval:** update Status to `Approved`, unlock branch `feature/sprint-4.2-video-commerce`.

---

## 16. References

| Doc | Role |
|-----|------|
| [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md) | Sub-sprint sequence 4.1–4.6 |
| [03_DATABASE_DESIGN.md](../docs/03_DATABASE_DESIGN.md) | Canonical schema |
| [04_API_SPECIFICATION.md](../docs/04_API_SPECIFICATION.md) | API contracts |
| [06_ENGINEERING_RULES.md](../docs/06_ENGINEERING_RULES.md) | Quality gates |
| [17_DEPENDENCY_MATRIX.md](../docs/17_DEPENDENCY_MATRIX.md) | Module deps |
