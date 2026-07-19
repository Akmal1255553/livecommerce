# Changelog

All notable changes to the LiveCommerce monorepo are documented in this file.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

#### Feed HLS playback

- Mobile `video_player` in For You / Following: play active slide, pause neighbors, mute-by-default tap unmute, thumbnail fallback

#### Sprint 8 — Messaging

- Tables: `blocks`, `conversations`, `conversation_participants`, `messages`
- API: conversations CRUD-ish, messages, mark read, unread count, user block/unblock
- `MessagingService` + block enforcement + `NEW_MESSAGE` notifications (stub FCM)
- Mobile: conversation list, chat screen (5s poll), entry from product/order/profile

### Changed

- **ADR-018 / Render:** free-tier API path via Render Blueprint (`render.yaml`) + optional Supabase; Railway when paid. See [docs/24_RENDER_DEPLOY.md](./docs/24_RENDER_DEPLOY.md).
- Preferred infra is **Supabase (Postgres)** + **Render/Railway (API)**; Docker optional. See [docs/22_CLOUD_INFRA_SUPABASE_RAILWAY.md](./docs/22_CLOUD_INFRA_SUPABASE_RAILWAY.md).

#### UX polish (post-RC1)

- Wired shared `EmptyState` / `ErrorDisplay` / skeletons on feed, cart, orders, live, product, order detail
- Offline banner via `connectivity_plus` (`OfflineBanner` in app shell)
- Lightweight pulse skeletons (no shimmer package)
- Feed interactions: like / bookmark / comments sheet / view tracking
- GoRouter `fadeSlide` / `slideUp` page transitions
- Pull-to-refresh on cart, orders, and live discovery
- i18n coverage for feed, commerce, live, shared widgets (uz/ru ARBs); theme polish (buttons, inputs, snackbars)
- Fix live room 429: split `live-poll` vs `live-chat` throttles, raise auth API limit, slower client poll

## [0.7.0-rc1] — 2026-07-17

First release candidate for closed beta. Includes Sprint 5–7 (seller, live commerce, payments) + RC1 security hardening.

**Tag:** `v0.7-rc1` · **Branch:** `feature/rc1-hardening` · **Audit:** [docs/V0.7_RC1_RELEASE_AUDIT.md](./docs/V0.7_RC1_RELEASE_AUDIT.md)

### Added

#### RC1 hardening

- Fix missing `OrderController` import on buyer order routes
- API rate limits: guest/auth/checkout/live-chat/search
- OTP verify lockout after 5 failed attempts
- Atomic checkout idempotency claim (unique key before payment)
- Payment webhook binds `transaction_id` to order payment reference
- `PAYMENT_GATEWAY=fake` blocked in production; sandbox gated by `PAYMENT_SANDBOX_ENABLED`
- API spec v1.2: `/checkout`, Sprint 6–7 surfaces, rate limits aligned with code

#### Sprint 7 — Payments

- Local payment gateway (`PAYMENT_GATEWAY=local|click|payme|uzum`) with redirect URL; `fake` stays instant-paid for tests
- `POST /webhooks/payment` (HMAC `X-Signature`) + `ProcessPaymentWebhookJob` + idempotent `payment_webhook_events`
- Sandbox complete: `POST /payments/sandbox/{id}/complete` for mobile Pay/Cancel
- `seller_payouts` on successful payment (manual MVP)
- Mobile: payment method (Click/Payme/Uzum) → `/payment/:id` → success/fail; refund request from order detail
- Blueprint: [blueprints/sprint-7-payments.md](./blueprints/sprint-7-payments.md)

#### Sprint 6.5 — AI Live Assistant (rule-based)

- Host API: `GET /live/{id}/assistant/suggestions` (pin / FAQ reply / engage tips)
- Rule engine only — ADR-012; OpenAI deferred to Sprint 9+
- Mobile: Live room ✨ assistant sheet for host (Pin / draft reply)
- Blueprint: [blueprints/sprint-6.5-ai-live-assistant.md](./blueprints/sprint-6.5-ai-live-assistant.md)

#### Sprint 6.4 — Live Analytics

- Seller APIs: `GET /seller/live/analytics`, `GET /seller/live/{id}/analytics`
- Aggregates viewers, pins, add-to-cart, conversion, top products from live events
- Mobile: Seller dashboard → Live analytics overview + session detail
- Blueprint: [blueprints/sprint-6.4-live-analytics.md](./blueprints/sprint-6.4-live-analytics.md)

#### Sprint 6.3 — Live Replay

- On live end: Fake provider sets `replay_url`; API returns `duration_seconds` + `product_timeline`
- `GET /live/replays` — ended sessions with recordings
- Mobile: Live tab **Replays** + `/live/replay/:id` scrubber with pin markers
- Blueprint: [blueprints/sprint-6.3-live-replay.md](./blueprints/sprint-6.3-live-replay.md)

#### Sprint 6.2 — Commerce inside Live

- API: `POST /live/{id}/add-to-cart` — pinned products only; returns cart + `commerce` chat message
- Attribution: `product_added_to_cart` analytics with `offset_seconds`
- Mobile: quick-add `+` on pinned chips, cart badge in live room, no navigation required
- Blueprint: [blueprints/sprint-6.2-live-commerce.md](./blueprints/sprint-6.2-live-commerce.md)

#### Sprint 6.1 — Mobile Live

- Feature module `mobile/lib/features/live/` — discovery, go-live, room (chat poll, pin, join/leave)
- Routes: `/live`, `/live/go`, `/live/:id`
- Feed AppBar Live shortcut; Seller dashboard **Go Live**
- Chrome: LIVE video placeholder (Agora deferred)
- Blueprint: [blueprints/sprint-6.1-mobile-live.md](./blueprints/sprint-6.1-mobile-live.md)

#### Sprint 6.0 — Live Commerce (backend foundation)

- Domain: `LiveSession` (+ pin timeline, typed chat `user|system|commerce`, viewer metrics, analytics events)
- `StreamingProviderInterface` → `FakeStreamingProvider` (default) / `AgoraProvider`
- Services: `LiveSessionService`, `ViewerMetricsService`, `LiveAnalyticsService`
- APIs: `/live/*` session control, join/leave; `GET /discover` + mixed `/feed/for-you` (`ContentItem` video|live)
- `LiveCandidateSource` + ADR-019 (Live as Content)
- Feature tests: `tests/Feature/Live/LiveSessionTest.php`
- Blueprint: [blueprints/sprint-6.0-live-commerce.md](./blueprints/sprint-6.0-live-commerce.md)

#### Sprint 5 — Seller Platform (mobile)

- Feature module `mobile/lib/features/seller/` — apply, dashboard, products, orders, public store
- Routes: `/seller`, `/seller/apply`, `/seller/products`, `/seller/products/new`, `/seller/orders`, `/seller/orders/:id`, `/stores/:slug`
- Profile: “Become a seller” / “Seller center” based on `AuthUser.role`
- Wires Sprint 4.6 + existing seller product/order APIs
- Blueprint: [blueprints/sprint-5-seller-platform.md](./blueprints/sprint-5-seller-platform.md)

#### Sprint 4.6 — Seller Dashboard (backend)

- `StoreService` — apply (auto-approve), dashboard KPIs, analytics summary, public storefront
- `POST /api/v1/seller/apply`, `GET /seller/dashboard`, `GET /seller/analytics/summary`
- `GET /api/v1/stores/{slug}`, `GET /stores/{slug}/products`
- Feature tests: `SellerDashboardTest`
- Note: seller product CUD remains on `/products` (+ `?mine=1`); spec `/seller/products` deferred
- Blueprint: [blueprints/sprint-4.6-seller-dashboard.md](./blueprints/sprint-4.6-seller-dashboard.md)

#### Sprint 4.5M — Mobile Commerce

- Feature module `mobile/lib/features/commerce/` — domain, repository, Riverpod providers
- Screens: Product, Cart, Checkout, Order Success, Orders, Order Detail
- Feed product overlay chip → `/products/:id`
- Cart badge + orders entry on feed AppBar; My Orders on profile
- Live API wiring: `GET /products/{id}`, cart CRUD, `POST /checkout` (+ `Idempotency-Key`), `GET /orders`
- Routes: `/products/:id`, `/cart`, `/checkout`, `/order-success`, `/orders`, `/orders/:id`
- Tests: `commerce_entities_test.dart` (Cart / MoneyAmount parsing)
- Release Audit notes: [docs/SPRINT_4.5M_RELEASE_AUDIT.md](./docs/SPRINT_4.5M_RELEASE_AUDIT.md)

#### Sprint 4.5 — Checkout

- `CheckoutService` — cart → order orchestration per ADR-016
- `POST /api/v1/checkout` — `cart_version`, `Idempotency-Key`, shipping address
- `inventory_reservations` + `ProductInventoryService` reserve/confirm/release
- `checkout_idempotency_keys` — 24h TTL, duplicate key returns same order
- `FakePaymentGateway` — synchronous MVP payment to `paid`
- Events: `CartCheckedOut`, `PaymentSucceeded`, `PaymentFailed`
- `ReleaseExpiredInventoryReservationsJob` (every minute)
- Release Audit process: [docs/21_SPRINT_RELEASE_AUDIT.md](./docs/21_SPRINT_RELEASE_AUDIT.md), `scripts/sprint-audit.ps1`
- Tests: `CheckoutTest` (6) — **193 tests total**

#### Sprint 4.4 — Order System

- `orders`, `order_items`, `order_status_transitions`, `refund_requests`, `order_number_sequences` tables
- ADR-017 state machine — 19 transitions, optimistic `orders.version`, append-only timeline
- Immutable `order_items` line snapshots; `OrderService` + `OrderStateMachine`
- Order number generator (`LC-YYYYMMDD-000001` default)
- Buyer API: `GET/POST /orders`, cancel, refund request
- Seller API: `GET/PUT /seller/orders`, `PUT /seller/refunds/{id}`
- Events: `OrderCreated`, `OrderPaid`, fulfillment + refund lifecycle (13 events)
- `RecordOrderAnalytics` listener; `CompleteDeliveredOrdersJob` (daily)
- Tests: `OrderTest`, `OrderImmutabilityTest`, `SellerOrderTest`, `RefundTest`, `OrderStateMachineTest` — **187 tests total**

#### Sprint 4.3 — Shopping Cart

- `carts` + `cart_items` tables with `carts.version` (optimistic concurrency for checkout in 4.5)
- `CartService` — user + guest carts, merge on login, advisory inventory checks
- `Money` value object — no float arithmetic in commerce services
- `ProductPricingService`, `ProductInventoryService`, coupon/shipping stubs
- Guest cart in Redis (`cart:guest:{token}`) with `X-Guest-Cart-Token` header
- Events: `CartItemAdded`, `CartItemRemoved`, `CartUpdated`, `CartMerged`, `CartExpired`
- API: `POST /cart/guest`, `GET/POST/PUT/DELETE /cart`, `DELETE /cart/items/{id}`
- Tests: `CartTest` (13) — **155 tests total**

#### Sprint 4.2 — Video Commerce

- `video_products` pivot: ordering, featured, timestamps, overlay positions, `product_version` snapshot
- `products.version` incremented on seller update
- `VideoCommerceService` + `GET/PUT /api/v1/videos/{id}/products`
- `ProductCardData` / `ProductCardResource` overlay payload
- Feed + video detail hydrate `products` array
- Event: `ProductAttachedToVideo`
- Mobile: `ProductCard` + `VideoProductTag` DTOs
- Tests: `VideoCommerceTest` (11) — **142 tests total**

#### Sprint 4 — Commerce architecture (cross-cutting)

- **Price snapshot** — `order_items` stores immutable price fields at checkout (Sprint 4.4 — shipped)
- **Product versioning** — `product_version` on pivot + `products.version` (Sprint 4.2)
- **Inventory double-check** — cart (4.3) + checkout (4.5)

### Added

#### Sprint 3.4 — Recommendation Engine v1

- `RecommendationEngineInterface` → `RuleBasedRecommendationEngine` (+ `AiRecommendationEngine` stub)
- Ranking pipeline: Candidate → Filter → Score → Diversity → Exploration → Final Rank
- 9 candidate sources (Category, Seller stubs); weights in `config/recommendation.php`
- `video_engagement_rollups` + `AggregateEngagementRollupsJob`
- `GET /feed/trending`, `/feed/popular`, `/feed/new`; enhanced `/feed/for-you`
- Redis snapshots + ranked cursor; `meta.strategy`, `meta.snapshot`, `meta.engine`
- Tests: `RecommendationFeedTest` (14), unit pipeline tests, rollup job test — **123 tests total**

#### Sprint 3.4 — Recommendation Engine v1 (blueprint approved 2026-06-28)

- Canonical blueprint: [blueprints/recommendation-engine-v1.md](./blueprints/recommendation-engine-v1.md) — **Approved**
- Architecture: [docs/22_RECOMMENDATION_ARCHITECTURE.md](./docs/22_RECOMMENDATION_ARCHITECTURE.md) — pipeline, `RecommendationEngineInterface`, diversity, exploration
- Sprint index: [docs/SPRINT_3.4_BLUEPRINT.md](./docs/SPRINT_3.4_BLUEPRINT.md)

#### Sprint 3.3 — Video Interactions + Analytics Layer

- Migrations: `video_likes`, `comments`, `bookmarks`, `video_shares`
- `VideoInteractionService` — like/unlike (idempotent), view (Redis 24h debounce), share, bookmark
- `CommentService` — 1-level replies, pagination, soft-delete own
- `VideoManagementService` — owner PUT metadata, soft DELETE
- `FlushVideoViewsJob` — scheduled every 60s to flush Redis view counters
- Events: `VideoLiked`, `VideoUnliked`, `CommentCreated`, `VideoShared`, `VideoBookmarked`, `VideoViewRecorded`
- **Analytics Layer** — full recommendation funnel in `EngagementEventType` (`video_start`, progress milestones, `watch_time`, `save`, `view`, `follow_after_watch`); see [`docs/ANALYTICS_LAYER.md`](./docs/ANALYTICS_LAYER.md)
- Interaction APIs require `session_id` (UUID) for funnel correlation via `MetricsService::record()`
- Extended `EngagementEventType`: `like`, `comment`, `share`, `save`, `view`, `watch_time`, `skip`, progress milestones
- `VideoService::enrichVideosForViewer()` — batch `is_liked` / `is_bookmarked` on feeds and detail
- API routes per `docs/SPRINT_3.3_BLUEPRINT.md` §2.1
- Tests: `VideoInteractionTest.php`, `VideoCommentTest.php`, `AnalyticsFunnelTest.php`

#### Sprint 3.2 — Video Processing (v2 architecture)

- `VideoStateMachine` — `uploaded` → `queued` → `processing` → `published` | `failed`
- `VideoProcessingPipelineOrchestrator` — DAG stages with 8 idempotent steps
- `media_assets` table + `MediaAssetService` — decoupled derivative files from `Video`
- `ValidateVideoStep`, `TranscodeHls720Step`, `TranscodeHls480Step`
- `FfmpegTranscoderInterface` — `CliFfmpegTranscoder` (prod) / `FakeFfmpegTranscoder` (tests)
- `StorageService::publicUrl()`, `downloadToTemp()`, `putFile()`
- `RetryVideoProcessingStepJob` for per-step retry
- Events: `VideoProcessingStarted`, `VideoPublished`, `VideoProcessingFailed`
- FFmpeg in Docker `app` image
- Tests: `VideoProcessingTest.php` (8), `MediaAssetServiceTest.php` (2) — **73 tests total**

### Changed

- Sprint 3.2 blueprint **v2**: [blueprints/video-processing.md](./blueprints/video-processing.md) — state machine, DAG, MediaAsset

#### Sprint 3 restructure (2026-06-28)

The monolithic **Sprint 3 — Video Platform** (2 weeks, upload + feed + likes + trending in one sprint) was split into **Sprint 3.1–3.4** after Sprint 2.3 shipped the feed read-path separately.

**Why split:**

1. **Feed already delivered** — Sprint 2.3 covers `GET /feed/for-you` and `/feed/following`; Sprint 3 no longer mixes read and write paths.
2. **Storage abstraction required** — `StorageService` (Local → S3 → MinIO → CDN) must exist before upload; no direct `Storage::` in controllers.
3. **Processing pipeline is multi-stage** — Upload → VirusScan → Metadata → Thumbnail → Transcoding → Moderation → Publish needs discrete queued jobs, not one `ProcessVideoJob`.
4. **Metrics before scale** — Engagement events (`feed_open`, `video_impression`, `watch_time`, etc.) start in 3.1, not after launch.
5. **Interactions vs ranking are separate concerns** — Likes/comments/views (3.3) ship before rule-based trending/popular/new (3.4); Sprint 9 ML replaces 3.4 internals only.

**New sub-sprints:**

| Sub-sprint | Scope |
|------------|-------|
| 3.1 | Upload Foundation — `StorageService`, MinIO, presigned URL, pipeline skeleton, metrics |
| 3.2 | Video Processing — FFmpeg thumbnail + HLS, queue errors |
| 3.3 | Video Interactions — likes, comments, views, shares, bookmarks |
| 3.4 | Recommendation Engine v1 — trending, popular, new, rule-based For You |

**Dependency updates:**

- Sprint 4 → depends on **Sprint 3.3** (published videos + interactions for product–video tagging)
- Sprint 9 → depends on **Sprint 3.4** (rule-based recommendations baseline for ML replacement)

**Docs updated:** [13_ROADMAP.md](./13_ROADMAP.md), [12_MASTER_PLAN.md](./12_MASTER_PLAN.md), [docs/17_DEPENDENCY_MATRIX.md](./docs/17_DEPENDENCY_MATRIX.md), [docs/SPRINT_3_VIDEO_PLATFORM.md](./docs/SPRINT_3_VIDEO_PLATFORM.md), [docs01_PRD.md](./docs01_PRD.md) (implementation mapping)

#### Sprint 3 blueprints (2026-06-28)

Implementation **blocked** until each sub-sprint blueprint is marked **Approved**:

| Blueprint | Scope |
|-----------|-------|
| [SPRINT_3.1_BLUEPRINT.md](./docs/SPRINT_3.1_BLUEPRINT.md) | Upload, StorageService, MinIO, metrics foundation |
| [blueprints/video-processing.md](./blueprints/video-processing.md) | FFmpeg, HLS, publish — **v2** (state machine, DAG, MediaAsset) |
| [SPRINT_3.3_BLUEPRINT.md](./docs/SPRINT_3.3_BLUEPRINT.md) | Likes, comments, bookmarks, shares, views |
| [SPRINT_3.4_BLUEPRINT.md](./docs/SPRINT_3.4_BLUEPRINT.md) | Trending, popular, new, rule-based For You |

Each blueprint defines: goal, API, DB schema, events, queues, test cases, acceptance criteria.

### Changed

- Sprint 3.2 blueprint **v2**: [blueprints/video-processing.md](./blueprints/video-processing.md) — **Approved** 2026-06-28 — state machine, DAG orchestrator, `MediaAsset`, idempotent steps; MVP 720p/480p HLS

### Added

- `StorageService` + Local/S3 drivers — no `Storage::` in controllers
- Migrations: `media_uploads`, `video_processing_steps`, `engagement_events`; `videos` processing columns
- `VideoUploadService`, `MediaService`, `MetricsService`
- Pipeline skeleton: `ProcessVideoPipelineJob` + 6 processing steps (stubs; publish deferred to 3.2)
- Events: `VideoCreated`, `VideoUploadConfirmed` → `DispatchVideoProcessingPipeline`
- API: `POST /videos`, `POST /videos/{id}/confirm-upload`, `GET /videos/{id}`, `POST /media/presigned-url`, `POST /metrics/events`
- Tests: `VideoUploadTest.php` (15), `StorageServiceTest.php` (2) — **64 tests total**

#### Sprint 2.3 — Feed Foundation

- `videos` table migration (minimal publishable schema)
- `VideoStatus`, `VideoVisibility` enums
- `VideoService` — For You and Following feeds with cursor pagination
- `VideoRepository::cursorPaginateFeed()` — published/public filter, following user filter
- `VideoResource`, `UserCompactResource`, `FeedController`
- API endpoints:
  - `GET /api/v1/feed/for-you` (optional auth)
  - `GET /api/v1/feed/following` (auth required)
- `CursorPaginationData` video cursor helpers (`encodeVideoCursor`, `decodeVideoCursor`, `nextVideoCursor`)
- Feature tests: `tests/Feature/Feed/FeedTest.php` (7 tests)
- Mobile: `features/feed/` — repository, providers, vertical `FeedScreen` with For You / Following tabs

#### Sprint 2.2 — Notifications Foundation

- `notifications` table migration with cursor-friendly indexes
- `NotificationType` enum: `NEW_FOLLOWER`, `NEW_COMMENT`, `NEW_LIKE`, `LIVE_STARTED`, `ORDER_CREATED`, `ORDER_PAID`, `REFUND_APPROVED`
- `NotificationData` DTO — fixed payload schema (`user_id`, `avatar`, `username`, `entity_id`, `entity_type`, `deep_link`)
- `NotificationService` — in-app notifications, cursor pagination, unread count, mark read/all
- `UserDevice` model + repository — FCM token register/unregister
- `NotifyOnFollow` listener on `UserFollowed` → `NEW_FOLLOWER` notification
- `SendPushNotificationJob` + `StubFcmPushNotification` (log-only push adapter)
- API endpoints:
  - `GET /api/v1/notifications`
  - `GET /api/v1/notifications/unread-count`
  - `PUT /api/v1/notifications/{id}/read`
  - `PUT /api/v1/notifications/read-all`
  - `PUT /api/v1/me/notification-settings`
  - `POST /api/v1/devices`
  - `DELETE /api/v1/devices/{token}`
- `NotificationResource`, `NotificationController`, `DeviceController`
- `CursorPaginationData` + `ApiResponse::cursorPaginated()`
- Feature tests: `tests/Feature/Notification/NotificationTest.php` (11 tests)

#### Sprint 2.1 — Follow System

- `follows` table migration
- `FollowService` — follow/unfollow with transactional counter updates
- `UserFollowed` event
- API endpoints:
  - `GET /api/v1/users/{id}`
  - `POST /api/v1/users/{id}/follow`
  - `DELETE /api/v1/users/{id}/follow`
  - `GET /api/v1/users/{id}/followers`
  - `GET /api/v1/users/{id}/following`
- `auth.api.optional` middleware for public profile routes
- Feature tests: `tests/Feature/Follow/FollowTest.php` (16 tests)

### Changed

- `NotificationType` values standardized to `SCREAMING_SNAKE_CASE` (was `new_follower`)
- Notification `data` column restricted to `NotificationData` schema (no arbitrary JSON)
- Roadmap restructured: Sprint 2 split into 2.1 (Follow), 2.2 (Notifications), 2.3 (Feed Foundation)

### Fixed

- Pest dev dependency compatibility for Laravel 12 (`pest ^3.8.2`, `phpunit 11.5.50`)
- UTF-8 BOM removed from service files blocking `strict_types`
- Duplicate event listener registration causing double counter increments (Sprint 2.1)

## [0.1.0] — 2026-06-27

### Added

- Sprint 0 — infrastructure, project skeleton, developer experience
- Sprint 1 — JWT authentication, OTP, profile management, mobile auth UX
- Docker Compose stack (PHP 8.4, PostgreSQL, Redis, MinIO, Mailpit)
- GitHub Actions CI for backend and mobile

[Unreleased]: https://github.com/Akmal1255553/livecommerce/compare/main...HEAD
[0.1.0]: https://github.com/Akmal1255553/livecommerce/releases/tag/v0.1.0
