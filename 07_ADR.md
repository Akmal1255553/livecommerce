# Architecture Decision Records (ADR)

Version: 1.0  
Project: LiveCommerce Platform  
Status: Architecture Phase  
Document Owner: Founder & CTO  
Last Updated: 2026-06-28

---

## Purpose

This document records every important architectural decision made during the LiveCommerce platform project. It preserves the reasoning behind technology choices, patterns, and approaches.

Every major technical decision must be documented here **before implementation**.

---

## ADR Index

| ADR | Title | Status | Date |
|-----|-------|--------|------|
| [ADR-001](#adr-001-flutter-for-mobile) | Flutter for Mobile | Accepted | 2026-06-27 |
| [ADR-002](#adr-002-laravel-modular-monolith) | Laravel Modular Monolith | Accepted | 2026-06-27 |
| [ADR-003](#adr-003-postgresql-as-primary-database) | PostgreSQL as Primary Database | Accepted | 2026-06-27 |
| [ADR-004](#adr-004-redis-for-cache-and-queues) | Redis for Cache and Queues | Accepted | 2026-06-27 |
| [ADR-005](#adr-005-jwt-authentication) | JWT Authentication | Accepted | 2026-06-27 |
| [ADR-006](#adr-006-repository-pattern) | Repository Pattern | Accepted | 2026-06-27 |
| [ADR-007](#adr-007-service-layer) | Service Layer | Accepted | 2026-06-27 |
| [ADR-008](#adr-008-docker-for-local-development) | Docker for Local Development | Accepted | 2026-06-27 |
| [ADR-009](#adr-009-riverpod-for-state-management) | Riverpod for State Management | Accepted | 2026-06-27 |
| [ADR-010](#adr-010-s3-compatible-object-storage) | S3-Compatible Object Storage | Accepted | 2026-06-27 |
| [ADR-011](#adr-011-agora-as-default-streaming-provider) | Agora as Default Streaming Provider | Accepted | 2026-06-27 |
| [ADR-012](#adr-012-ai-adapter-integration-strategy) | AI Adapter Integration Strategy | Accepted | 2026-06-27 |
| [ADR-013](#adr-013-pest-testing-framework) | Pest Testing Framework | Accepted | 2026-06-27 |
| [ADR-014](#adr-014-openapi-scramble-documentation) | OpenAPI / Scramble Documentation | Accepted | 2026-06-27 |
| [ADR-015](#adr-015-mailpit-for-local-email-testing) | Mailpit for Local Email Testing | Accepted | 2026-06-27 |
| [ADR-016](#adr-016-commerce-core-cart-orders-inventory-payment) | Commerce Core: Cart, Orders, Inventory, Payment | **Accepted** | 2026-06-28 |

---

## ADR Template

```
ADR-NNN: Title
Status: Proposed | Accepted | Deprecated | Superseded
Date: YYYY-MM-DD
Context: ...
Problem: ...
Considered Alternatives: ...
Decision: ...
Consequences: ...
Future Review: ...
```

---

## ADR-001: Flutter for Mobile

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The platform is mobile-first. Users consume short-form video, live streams, and shop primarily on smartphones (Android and iOS). A single codebase for both platforms reduces development cost and ensures UI consistency.

### Problem

Choose a mobile framework that supports high-performance video playback, smooth 60 FPS scrolling, live streaming SDK integration, and rapid UI development for a TikTok-style experience.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Flutter** | Single codebase, 60 FPS rendering, strong video packages, growing ecosystem | Smaller talent pool in Central Asia vs React Native |
| **React Native** | Large ecosystem, Expo tooling | Performance concerns for video-heavy feeds, bridge overhead |
| **Native (Swift + Kotlin)** | Best performance | Two codebases, 2× development cost, slower iteration |

### Decision

Use **Flutter 3.x** with Clean Architecture and Riverpod.

### Consequences

- Positive: Consistent UI across Android/iOS, excellent scroll performance, hot reload speeds development.
- Negative: Team must learn Dart/Flutter; some native SDK integrations require platform channels.
- Neutral: App size slightly larger than native (~15–20 MB overhead).

### Future Review

Revisit if Flutter video performance becomes a bottleneck at scale, or if a third platform (web desktop) is required.

---

## ADR-002: Laravel Modular Monolith

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The backend must serve a REST API for mobile clients, handle async jobs (video processing, notifications), and support eventual scale to millions of users.

### Problem

Choose backend architecture: monolith vs microservices vs modular monolith.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Laravel Monolith** | Fast development, mature ecosystem, built-in queues/events | Can become tangled without discipline |
| **Microservices** | Independent scaling | Premature complexity for MVP, operational overhead |
| **Node.js (NestJS)** | JS ecosystem | Less mature for heavy queue/worker workloads in our context |
| **Go microservices** | Performance | Higher development cost, no team expertise |

### Decision

Use **Laravel 12 modular monolith** with Service Layer, Repository Pattern, Events, and Queues. Extract to microservices only when a specific module proves to be a bottleneck.

### Consequences

- Positive: Rapid MVP delivery, Laravel Horizon for queues, excellent PostgreSQL support, large package ecosystem.
- Negative: Must enforce module boundaries via code review and architecture rules.
- Neutral: Migration path to microservices exists via event-driven boundaries.

### Future Review

When DAU exceeds 500K, evaluate extracting video processing and recommendation into separate services.

---

## ADR-003: PostgreSQL as Primary Database

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The platform needs a relational database for users, products, orders, social graph, and audit logs with ACID guarantees for financial transactions.

### Problem

Select primary database that supports complex queries, JSONB for flexible fields, full-text search, and future sharding.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **PostgreSQL** | ACID, JSONB, full-text search, mature, read replicas | Vertical scaling limits (mitigated by sharding later) |
| **MySQL** | Wide adoption | Weaker JSON support, less advanced indexing |
| **MongoDB** | Flexible schema | Poor fit for orders/payments relational data |
| **CockroachDB** | Distributed | Overkill for MVP, higher cost |

### Decision

Use **PostgreSQL 16+** as the sole primary relational database.

### Consequences

- Positive: Strong consistency for orders, excellent JSONB for addresses/settings, `gen_random_uuid()` for UUID PKs, full-text search for MVP.
- Negative: Full-text search may need Elasticsearch at scale (Phase 2).
- Neutral: Read replicas added when read load increases.

### Future Review

At 500K+ DAU, evaluate Elasticsearch for search and consider Citus/sharding for user-scoped tables.

---

## ADR-004: Redis for Cache and Queues

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The platform needs in-memory caching for feeds, rate limiting, session tokens, debounced counters (views/likes), and a job queue backend.

### Problem

Choose caching and queue infrastructure.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Redis** | Cache + queues + pub/sub in one, Laravel native support | Single point of failure without cluster |
| **Memcached** | Simple caching | No queue support |
| **RabbitMQ** | Advanced queuing | Separate system for cache, more ops overhead |
| **Database queues** | No extra infra | Too slow for video processing workloads |

### Decision

Use **Redis 7+** for cache, rate limiting, debounced counters, and Laravel queue backend. Laravel Horizon for queue monitoring.

### Consequences

- Positive: Single system for cache + queues, sub-millisecond reads, Laravel first-class support.
- Negative: Must monitor memory usage; cluster needed at scale.
- Neutral: Redis Cluster planned for Phase 2 (500K+ DAU).

### Future Review

When queue depth consistently exceeds 10K, add dedicated worker pools per queue type.

---

## ADR-005: JWT Authentication

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Mobile app communicates with stateless REST API. Multiple API servers behind load balancer. No server-side sessions.

### Problem

Choose authentication mechanism for mobile ↔ API communication.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **JWT (access + refresh)** | Stateless, scalable, mobile-friendly | Token revocation requires extra infrastructure |
| **Session cookies** | Easy revocation | Poor fit for mobile native apps |
| **OAuth2 only** | Standard | Overkill for first-party mobile app |
| **Sanctum tokens** | Laravel native | Less standard for mobile than JWT |

### Decision

Use **JWT with short-lived access tokens (15 min) and refresh tokens (30 days) with rotation**. Refresh tokens stored hashed in PostgreSQL for revocation.

### Consequences

- Positive: Stateless API scaling, standard mobile pattern, refresh rotation limits exposure.
- Negative: Refresh token table needed for revocation; logout requires DB write.
- Neutral: Social login (Google/Apple) added in v1.1 as additional auth method.

### Future Review

If token theft becomes a concern, add device binding and fingerprint validation.

---

## ADR-006: Repository Pattern

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Business logic must be decoupled from Eloquent ORM to enable testing, future database changes, and clean architecture.

### Problem

Where should database queries live?

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Repository Pattern** | Testable, swappable, clean boundaries | Extra abstraction layer |
| **Eloquent directly in services** | Less code | Harder to test, ORM leaks into business logic |
| **Query Objects** | Good for complex reads | Doesn't cover writes well |

### Decision

Use **Repository Pattern** with interfaces in `app/Contracts/Repositories/` and Eloquent implementations in `app/Repositories/Eloquent/`. Bound via Service Provider.

### Consequences

- Positive: Services testable with mock repositories, consistent query location, future read-replica routing possible.
- Negative: More files per module (~2 extra per entity).
- Neutral: Repositories return Models/Collections, not arrays.

### Future Review

None unless moving to CQRS pattern at scale.

---

## ADR-007: Service Layer

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Controllers must remain thin. Business logic must be reusable across controllers, jobs, and event listeners.

### Problem

Where does business logic live?

### Decision

All business logic in **Service classes** (`app/Services/{Module}/`). Controllers delegate to services. Jobs and listeners call services. Services dispatch events for side effects.

### Consequences

- Positive: Single location for business rules, testable, reusable across entry points.
- Negative: Discipline required to prevent logic leaking into controllers or repositories.
- Neutral: One service per domain module.

### Future Review

None.

---

## ADR-008: Docker for Local Development

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Team needs consistent local development environment across Windows, macOS, and Linux.

### Problem

How to standardize local dev setup for PHP, PostgreSQL, Redis, and MinIO?

### Decision

Use **Docker Compose** for local development. Production uses managed services / container orchestration separately.

### Consequences

- Positive: Identical environment for all developers, easy onboarding, includes MinIO for S3 testing.
- Negative: Docker resource usage on developer machines, Windows Docker Desktop quirks.
- Neutral: Production deployment not tied to Docker Compose.

### Future Review

Evaluate dev containers or Laravel Sail if team grows.

---

## ADR-009: Riverpod for State Management

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Flutter app uses Clean Architecture. State management must support dependency injection, testability, and granular rebuilds for performance.

### Problem

Choose Flutter state management solution.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Riverpod** | Compile-safe, testable, no BuildContext needed, granular rebuilds | Learning curve |
| **Bloc** | Well-documented, event-driven | More boilerplate |
| **Provider** | Simple | Less powerful, superseded by Riverpod |
| **GetX** | Fast to prototype | Tight coupling, discouraged for large apps |

### Decision

Use **Riverpod** with Notifier pattern for feature state.

### Consequences

- Positive: Excellent testability, compile-time safety, works well with Clean Architecture.
- Negative: Team must learn Riverpod patterns.
- Neutral: Code generation optional (riverpod_generator) for larger providers.

### Future Review

None.

---

## ADR-010: S3-Compatible Object Storage

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Platform stores videos, product images, avatars, and store logos. Files range from 50 KB (avatars) to 500 MB (videos). CDN required for delivery.

### Problem

Choose object storage provider.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Cloudflare R2** | No egress fees, S3-compatible, CDN integration | Newer service |
| **AWS S3** | Industry standard | Egress costs at video scale |
| **MinIO (self-hosted)** | Full control | Operational overhead |
| **Local disk storage** | Simple | Not scalable, no CDN |

### Decision

Use **S3-compatible storage** (Cloudflare R2 for production, MinIO for local dev). Laravel Filesystem with `s3` driver. CDN (Cloudflare) in front for media delivery.

### Consequences

- Positive: Zero egress fees with R2, pre-signed URL uploads (direct mobile → storage), CDN for global delivery.
- Negative: Must configure CORS and bucket policies correctly.
- Neutral: Storage provider swappable via S3-compatible API.

### Future Review

Monitor storage costs at 100K+ videos. Evaluate tiered storage for old content.

---

## ADR-011: Agora as Default Streaming Provider

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Live commerce requires real-time video streaming with low latency (< 3 seconds), Flutter SDK support, and reasonable pricing for Central Asia.

### Problem

Choose live streaming provider for MVP.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Agora** | Mature SDK, Flutter support, global infrastructure, recording | Pricing at very high scale |
| **100ms** | Developer-friendly, competitive pricing | Smaller ecosystem |
| **ZEGOCLOUD** | Good pricing in Asia | Less documentation |
| **Self-hosted (WebRTC)** | Full control | Massive operational complexity |

### Decision

Use **Agora** as default provider behind `StreamingProviderInterface`. Provider swappable via `.env` config.

### Consequences

- Positive: Proven latency, Flutter SDK, cloud recording available for Phase 1.1, abstracted for future swap.
- Negative: Vendor dependency, per-minute pricing.
- Neutral: 100ms and ZEGOCLOUD adapters implemented as alternatives.

### Future Review

Compare costs at 10K+ concurrent streams. Evaluate self-hosted only if costs exceed 15% of revenue.

---

## ADR-012: AI Adapter Integration Strategy

**Status:** Accepted  
**Date:** 2026-06-27

### Context

Platform is designed AI-first long-term. AI features include moderation, recommendations, product descriptions, subtitles, and seller assistant. MVP does not require AI.

### Problem

How to integrate AI without coupling business logic to specific providers?

### Decision

Use **AI Adapter pattern** (`AiProviderInterface`) with async job processing. All AI calls are queued. AI failures fail gracefully. AI outputs stored in database. MVP uses rule-based alternatives; ML added in Phase 2 (Sprint 9+).

### Consequences

- Positive: Provider swappable (OpenAI, local ML, custom models), core features work without AI, async prevents API blocking.
- Negative: AI features delayed to post-MVP sprints.
- Neutral: Interface defined in Architecture Phase; implementations in Sprint 9.

### Future Review

Evaluate cost of OpenAI vs self-hosted models when AI traffic exceeds 10K requests/day.

---

## ADR-013: Pest Testing Framework

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The backend requires a testing framework for Feature and Unit tests. Laravel 12 ships with PHPUnit; Pest provides a modern, expressive syntax with first-class Laravel integration.

### Problem

Choose the primary PHP test framework for the LiveCommerce backend.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Pest** | Expressive syntax, Laravel plugin, lower boilerplate | Team learning curve if unfamiliar |
| **PHPUnit only** | Laravel default, widely known | More verbose test code |

### Decision

Use **Pest 3.x** with `pest-plugin-laravel` as the primary test framework. PHPUnit remains as the underlying engine. Run tests via `php artisan test` or `composer test`.

### Consequences

- Positive: Faster test authoring, readable Feature tests, consistent with Laravel 12 community practice.
- Negative: Developers must learn Pest syntax (`test()`, `expect()`).
- Neutral: CI runs `composer qa` which includes Pest tests.

### Future Review

None unless Pest compatibility issues arise with a future Laravel major version.

---

## ADR-014: OpenAPI / Scramble Documentation

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The API Specification is the authoritative contract, but developers and QA need interactive, always-up-to-date API documentation during development.

### Problem

Choose tooling to generate OpenAPI documentation from the Laravel codebase.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Scramble (dedoc/scramble)** | Auto-generates from routes/controllers, Laravel-native | Newer project |
| **L5-Swagger** | Mature, annotation-based | Manual annotations drift from code |
| **Manual OpenAPI YAML** | Full control | High maintenance, guaranteed drift |

### Decision

Use **Scramble** for OpenAPI 3 generation. Expose interactive docs at `/docs/api` in local and staging. Export spec via `php artisan scramble:export`.

### Consequences

- Positive: Docs stay in sync with code; JWT security scheme configured; export scripts in Sprint 0.2.
- Negative: Complex validation may need Scramble attributes for full accuracy.
- Neutral: API Specification remains source of truth for business contract review.

### Future Review

Evaluate multi-version API docs if `/api/v2/` is introduced.

---

## ADR-015: Mailpit for Local Email Testing

**Status:** Accepted  
**Date:** 2026-06-27

### Context

The platform sends transactional emails (welcome, order confirmation, password reset). Developers need to inspect emails locally without sending real messages.

### Problem

Choose local email testing infrastructure for Docker development.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **Mailpit** | Web UI, SMTP catch-all, lightweight | Dev-only |
| **Mailhog** | Similar to Mailpit | Less actively maintained |
| **Log driver only** | Zero setup | No HTML preview |

### Decision

Use **Mailpit** in Docker Compose. Backend `.env` uses `MAIL_MAILER=smtp` pointing to Mailpit (`mailpit:1025`). Web UI at `http://localhost:8025`.

### Consequences

- Positive: Full email preview in browser; OTP and order emails testable locally.
- Negative: Additional Docker service (minimal resource usage).
- Neutral: Production uses real SMTP/SES.

### Future Review

None.

---

## ADR-016: Commerce Core — Cart, Orders, Inventory, Payment

**Status:** Accepted (2026-06-28)  
**Canonical architecture:** [docs/23_COMMERCE_CORE_ARCHITECTURE.md](./docs/23_COMMERCE_CORE_ARCHITECTURE.md)  
**Sprints:** 4.3 (Cart) · 4.4 (Orders) · 4.5 (Checkout/Payment) · 4.6 (Seller)

### Context

Sprint 4 commerce spans four tightly coupled concerns: **Cart**, **Orders**, **Inventory**, and **Payment**. Implementing them ad hoc in separate PRs risks inconsistent stock handling, mutable order prices, and payment logic leaking into cart controllers.

Product requirements already established:

1. **Price snapshot** — `order_items` must store `product_name`, `sku`, `unit_price`, `discount`, `currency`; never read live `products.price` after order creation.
2. **Inventory double-check** — validate at cart add (advisory) and again at checkout (authoritative); never trust cart-time check alone.
3. **Payment provider isolation** — all providers implement `PaymentGatewayInterface`; business logic never depends on Click/Payme/Stripe directly.

Architecture review v2 (2026-06-28) adds **mandatory** constraints before implementation:

| Priority | Requirement |
|----------|-------------|
| **P0** | **Cart versioning** — `carts.version`; checkout rejects stale `cart_version` (409) |
| **P0** | **Inventory reservation TTL** — 15 minutes; auto-release job if unpaid |
| **P0** | **Checkout idempotency** — `Idempotency-Key` header; no duplicate orders |
| **P1** | **Money value object** — no `float` for amounts in services |
| **P1** | **Cart events** — `CartMerged`, `CartExpired`, `CartCheckedOut` |
| **P2** | **CouponServiceInterface** — stub returns no discount |
| **P2** | **ShippingCalculatorInterface** — stub (fixed/zero rate) |

### Problem

Define a single architectural contract for how Cart, Order, Inventory, and Payment modules interact across Sprints 4.3–4.5 before any implementation begins.

### Considered Alternatives

| Option | Pros | Cons |
|--------|------|------|
| **A. Monolithic CommerceService** | One entry point | God object; untestable; violates module boundaries |
| **B. Cart creates orders directly** | Fewer classes | No checkout orchestration; payment coupled to cart |
| **C. Pipeline: Cart → Checkout → Order → Payment (chosen)** | Clear boundaries, testable interfaces, ADR-gated sprints | More files; requires upfront design |
| **D. Reserve stock in cart (Redis hold)** | Strong availability UX | Complex TTL, oversell edge cases; rejected for cart — use checkout reservation instead |

### Decision

Adopt **pipeline architecture (C)** documented in [23_COMMERCE_CORE_ARCHITECTURE.md](./docs/23_COMMERCE_CORE_ARCHITECTURE.md) **v2**:

```
CartService (mutable, versioned)
    → CheckoutService (idempotent orchestrator, Sprint 4.5)
        → cart_version check (P0)
        → Idempotency-Key (P0)
        → InventoryServiceInterface (reservation + 15min TTL + auto-release)
        → PricingServiceInterface + Money VO (P1)
        → CouponServiceInterface (stub P2)
        → ShippingCalculatorInterface (stub P2)
        → OrderService + OrderStateMachine (immutable, Sprint 4.4)
        → PaymentGatewayInterface (external I/O, Sprint 4.5)
```

**Binding rules:**

| Rule | Detail |
|------|--------|
| Cart storage | PostgreSQL for users; Redis for guests (`cart:guest:{token}`, 30d TTL) |
| Cart versioning | `carts.version` INTEGER; increment every mutation; guest cart JSON includes `version` |
| Checkout stale cart | `POST /checkout` requires `cart_version`; mismatch → `409 CartStaleException` |
| Cart items | `product_id`, `variant_id`, `quantity` only — **no persisted prices** |
| Money | `App\ValueObjects\Money` (`amount` int + `currency`); **no float** in services |
| Inventory checkpoint 1 | `CartService` → `assertAvailable` (advisory, no reservation) |
| Inventory checkpoint 2 | `CheckoutService` → `reserveForOrder` → decrement stock + `inventory_reservations` |
| Reservation TTL | **15 minutes**; `ReleaseExpiredInventoryReservationsJob` auto-releases unpaid stock |
| Payment confirm | `PaymentSucceeded` → `confirmReservation`; failure/timeout → `releaseReservation` |
| Idempotency | `Idempotency-Key` on `POST /checkout`; same key → same order response (24h TTL) |
| Price display | `PricingServiceInterface::priceCart` at read time (`Money`) |
| Price immutability | `buildOrderLineSnapshots` at checkout → `order_items` |
| Coupons | `CouponServiceInterface`; MVP `NoDiscountCouponService` |
| Shipping | `ShippingCalculatorInterface`; MVP fixed/zero rate |
| Order states | `Draft → … → Completed` (+ `Cancelled`, `RefundRequested`, `Refunded`) |
| Payment | Only `CheckoutService` and webhook handler call `PaymentGatewayInterface` |
| Cart events | `CartMerged`, `CartExpired` (4.3); `CartCheckedOut` (4.5) |

**order_items snapshot columns (Sprint 4.4 migration):**

`product_name`, `sku`, `unit_price`, `discount`, `currency`, `quantity`, `variant_name` (nullable), `product_id`, `variant_id`.

**Gate policy:** Any change touching **two or more** of {Cart, Orders, Inventory, Payment} requires updating this ADR (or a superseding ADR) **before** implementation.

### Consequences

- Positive: Consistent stock, pricing, and concurrency semantics across sprints.
- Positive: Idempotency prevents duplicate charges on network retries.
- Positive: Cart versioning prevents checkout with stale lines/prices.
- Positive: Reservation TTL protects limited stock without permanent locks.
- Negative: Extra tables (`inventory_reservations`, `idempotency_keys`) and scheduled job.
- Neutral: Coupon/shipping stubs add interfaces now, implementations later.

### Future Review

- Real payment gateways (Click, Payme, Stripe) — Sprint 7+; must implement `PaymentGatewayInterface`.
- Multi-store carts (single seller per order in MVP).
- Coupon campaigns — replace `NoDiscountCouponService` via DI.

---

## Adding New ADRs

1. Assign next ADR number (ADR-016, etc.).
2. Use the template above.
3. Set status to **Proposed** until reviewed.
4. Update this index table.
5. Reference related ADR in PR/commit when implementing.

---

## Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial ADR document with 12 accepted decisions |
| 1.1 | 2026-06-27 | Architecture Review | P0 patches: ADR-013 (Pest), ADR-014 (Scramble), ADR-015 (Mailpit) |
| 1.2 | 2026-06-28 | Architecture Review | ADR-016: Commerce Core (Cart, Orders, Inventory, Payment) |
| 1.3 | 2026-06-28 | Architecture Review | ADR-016 v2: cart versioning, reservation TTL, idempotency, Money VO, coupon/shipping stubs |

---

**Related Documents:** [Master Plan](./12_MASTER_PLAN.md) · [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md) · [Engineering Rules](./docs/06_ENGINEERING_RULES.md)
