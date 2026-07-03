# Sprint 4.5M — Mobile Commerce · Blueprint

**Version:** 1  
**Sprint:** 4.5M  
**Status:** Draft — pending approval  
**Phase:** A — Commerce MVP (mobile)  
**Depends on:** Sprint 4.5 — Checkout (`v0.4.5-checkout`)  
**Master plan:** [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md) · Phase A

> **Gate:** Phase A (4.5 + 4.5M) must be released before Sprint 5 (Seller Platform) or Sprint 6 (Live Commerce).  
> Without 4.5M there is **no MVP** — backend commerce APIs alone do not complete the user journey.

---

## 1. Goal

Ship the **mobile purchase path** end-to-end: discover product in feed → view product → cart → checkout → order confirmation → order history.

**In scope (P0 screens):**

| Screen | Purpose |
|--------|---------|
| **Product Overlay UI** | Tap product tag on video feed; compact card + CTA |
| **Product Page** | Full detail: images, variants, price, stock, add to cart |
| **Cart Screen** | Line items, quantity, totals, proceed to checkout |
| **Checkout Screen** | Shipping address, payment method (fake/MVP), order summary |
| **Order Success** | Confirmation after checkout (`order_number`, next steps) |
| **Order History** | Paginated list of buyer orders |
| **My Orders** | Order detail: items, status, timeline, cancel (if allowed) |

**Out of scope (later sprints):**

- Seller onboarding / seller app flows → Sprint 5
- Live stream product pinning UI → Sprint 6
- Real payment gateway UI (Click/Payme) → Sprint 7
- Wishlist / favorites, reviews, category browse → post-MVP backlog
- Video upload from mobile → separate track (can ship in parallel after Phase A if needed)

---

## 2. Architecture (mobile)

Follow existing Clean Architecture + Riverpod + GoRouter patterns (`mobile/lib/features/`).

```
features/commerce/
├── domain/       entities, repository contracts
├── data/         DTOs, API clients (cart, checkout, orders, products)
└── presentation/ screens, widgets, providers
```

**API dependencies (Sprint 4.5):**

- `GET /products/{id}` — product page
- `GET/POST/PUT/DELETE /cart` — cart CRUD
- `POST /checkout` — create order from cart (Idempotency-Key, cart version)
- `GET /orders`, `GET /orders/{id}` — history + detail
- `POST /orders/{id}/cancel` — cancel pending orders

Feed overlay reuses existing `VideoProductTag` / `ProductCard` DTOs from `features/feed/`.

---

## 3. UX flow

```
Feed (video + product overlay)
    → tap tag → Product Page
    → Add to Cart → Cart Screen
    → Checkout → Order Success
    → My Orders / Order History
```

Guest cart: `X-Guest-Cart-Token` header; merge on login (existing 4.3 behaviour).

---

## 4. Acceptance criteria

- [ ] User can tap product overlay on feed and open Product Page
- [ ] User can add/update/remove cart items from Product Page and Cart Screen
- [ ] User can complete checkout with fake payment and land on Order Success
- [ ] User can view Order History and open My Orders (detail + timeline)
- [ ] Happy path works for authenticated buyer (guest cart optional P1)
- [ ] `flutter analyze` clean; widget/integration tests for cart + checkout flow

---

## 5. Release

- Branch: `feature/sprint-4.5m-mobile-commerce`
- Tag: `v0.4.5m-mobile-commerce`
- Requires: Sprint 4.5 backend released and `POST /checkout` stable
- **After tag:** [Release Audit](../docs/21_SPRINT_RELEASE_AUDIT.md) — Mobile Audit + Phase A E2E smoke (§4)

---

## 6. Related docs

| Document | Role |
|----------|------|
| [SPRINT_4_COMMERCE_PLAN.md](../docs/SPRINT_4_COMMERCE_PLAN.md) | Phase A master plan |
| [13_ROADMAP.md](../13_ROADMAP.md) | Roadmap milestones |
| [04_API_SPECIFICATION.md](../docs/04_API_SPECIFICATION.md) | Checkout + orders API |
| [21_SPRINT_RELEASE_AUDIT.md](../docs/21_SPRINT_RELEASE_AUDIT.md) | Release Audit (post-4.5M E2E) |
| [11_DESIGN_SYSTEM.md](../11_DESIGN_SYSTEM.md) | UI tokens |
