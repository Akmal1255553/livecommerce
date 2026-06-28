# LiveCommerce Platform — Product Roadmap

Version: 1.1  
Status: Architecture Phase  
Document Owner: Founder & CTO  
Last Updated: 2026-06-27

---

# Purpose

This roadmap defines the development strategy for the LiveCommerce platform.

The project follows an iterative sprint-based development process. Each sprint delivers a complete, testable milestone.

**Business features must never be developed before the required engineering foundation is complete.**

All sprints must comply with:

- [PRD](./docs01_PRD.md)
- [Project Context](./PROJECT_CONTEXT.md)
- [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md)
- [Database Design](./docs/03_DATABASE_DESIGN.md)
- [API Specification](./docs/04_API_SPECIFICATION.md)
- [Project Structure](./docs/05_PROJECT_STRUCTURE.md)
- [Engineering Rules](./docs/06_ENGINEERING_RULES.md)

---

# Roadmap Overview

| Parameter | Value |
|-----------|-------|
| Sprint duration | 2 weeks |
| Total sprints (v1.0) | 17 (Sprint 0 – Sprint 16) |
| Estimated timeline to v1.0 | ~8–9 months |
| Initial launch market | Uzbekistan |
| Initial currency | UZS (Uzbek Som) |
| Initial languages | Uzbek, Russian |

---

# Development Phases

```
Phase 0 — Engineering Foundation        Sprint 0
         ↓
Phase 1 — Core Platform                 Sprint 1
         ↓
Phase 2 — Social & Engagement           Sprint 2
         ↓
Phase 3 — Video Platform                Sprint 3
         ↓
Phase 4 — Commerce                      Sprint 4, 5, 7
         ↓
Phase 5 — Live Commerce               Sprint 6
         ↓
Phase 6 — Communication               Sprint 8
         ↓
Phase 7 — AI                          Sprint 9, 10, 11
         ↓
Phase 8 — Analytics & Admin           Sprint 12, 14
         ↓
Phase 9 — Growth                      Sprint 13
         ↓
Phase 10 — Scaling & Launch           Sprint 15, 16
```

---

# Release Milestones

| Milestone | Sprints | Target | Description |
|-----------|---------|--------|-------------|
| **Foundation Ready** | Sprint 0 | Month 0 | Architecture approved, repo scaffolded, CI running |
| **Internal Alpha** | Sprint 1–3 | Month 2 | Auth, social, video feed working internally |
| **Commerce Alpha** | Sprint 4–5 | Month 4 | End-to-end purchase flow with test payments |
| **MVP Feature Complete** | Sprint 6–7 | Month 5 | Live streaming + payment infrastructure |
| **Private Beta** | Sprint 8 + 14 (core) | Month 6 | Messaging, admin moderation, invite-only users |
| **Public Beta** | Sprint 12–13 | Month 7 | Analytics, growth tools, wider user base |
| **v1.0 Launch** | Sprint 15–16 | Month 8–9 | Production hardened, publicly launched in Uzbekistan |

---

# Sprint Dependency Map

```
Sprint 0 ──→ Sprint 1 ──→ Sprint 2 ──→ Sprint 3
                              │            │
                              │            ├──→ Sprint 4 ──→ Sprint 5
                              │            │         │
                              │            │         └──→ Sprint 7
                              │            │
                              │            └──→ Sprint 6
                              │
                              └──→ Sprint 8 (requires Sprint 4 + 5)
                                        │
Sprint 3 + 4 ──→ Sprint 9 ──→ Sprint 10 ──→ Sprint 11
                                        │
Sprint 4 + 5 + 6 ──→ Sprint 12
                                        │
Sprint 1 + 4 ──→ Sprint 13
                                        │
Sprint 1–7 ──→ Sprint 14
                                        │
All sprints ──→ Sprint 15 ──→ Sprint 16
```

**Rule:** No sprint may begin until all dependency sprints are accepted.

---

# Sprint 0 — Engineering Foundation

**Phase:** 0 — Engineering Foundation  
**Duration:** 2 weeks  
**Status:** In Progress (Documentation Complete)

## Objectives

- Complete all architecture and engineering documentation
- Scaffold monorepo structure
- Prepare local development environment
- Prepare CI/CD pipelines (config only, no deploy)

## Modules

- Documentation
- Architecture
- Database Design
- API Specification
- Engineering Rules
- Repository Structure
- Docker Environment
- CI/CD Preparation

## Deliverables

| Deliverable | Status |
|-------------|--------|
| PRD | ✔ Complete |
| Project Context | ✔ Complete |
| System Architecture | ✔ Complete |
| Database Design | ✔ Complete |
| API Specification | ✔ Complete |
| Project Structure | ✔ Complete |
| Engineering Rules | ✔ Complete |
| Product Roadmap | ✔ Complete |
| Repository Skeleton (backend/, mobile/, docker/) | ☐ Pending |
| Docker Compose (local dev) | ☐ Pending |
| GitHub Actions CI workflows | ☐ Pending |
| README with setup instructions | ☐ Pending |

## Backend Tasks

- [ ] Initialize Laravel 12 project in `backend/`
- [ ] Configure PostgreSQL, Redis, S3 (MinIO) connections
- [ ] Set up JWT auth package
- [ ] Configure Laravel Pint, PHPStan
- [ ] Create base Service Provider bindings (repository interfaces)
- [ ] Add health check endpoint stub

## Mobile Tasks

- [ ] Initialize Flutter project in `mobile/`
- [ ] Configure folder structure (Clean Architecture)
- [ ] Set up Riverpod, GoRouter, Dio
- [ ] Configure theme (light/dark), l10n (uz, ru)
- [ ] Configure analysis_options.yaml

## Infrastructure Tasks

- [ ] Docker Compose: PHP, Nginx, PostgreSQL, Redis, MinIO
- [ ] `.env.example` files for backend
- [ ] `.github/workflows/backend-ci.yml`
- [ ] `.github/workflows/mobile-ci.yml`
- [ ] Branch protection on `main` and `develop`

## Success Criteria

- All architecture documents reviewed and approved
- `docker compose up` starts local environment
- CI pipelines run on empty project (lint + test pass)
- Development can begin safely on Sprint 1

---

# Sprint 1 — Authentication & Users

**Phase:** 1 — Core Platform  
**Duration:** 2 weeks  
**Depends on:** Sprint 0  
**Priority:** P0

## Modules

Authentication, Authorization, JWT, Refresh Tokens, OTP Verification, Password Reset, User Profile, Settings, Localization, Dark Mode

## Backend Deliverables

- [ ] Migrations: users, user_profiles, user_devices, refresh_tokens
- [ ] AuthService (register, login, logout, refresh, OTP, password reset)
- [ ] UserRepository, ProfileService
- [ ] AuthController + Form Requests
- [ ] JWT middleware, role middleware
- [ ] UserResource, UserPolicy
- [ ] SMS adapter interface + stub provider
- [ ] Feature tests: register, login, refresh, logout, OTP

## Mobile Deliverables

- [ ] Auth feature module (data, domain, presentation)
- [ ] Login, Register, OTP Verification screens
- [ ] Secure token storage + auto-refresh interceptor
- [ ] Profile screen (view + edit)
- [ ] Settings screen (locale, dark mode toggle)
- [ ] Light/dark theme implementation
- [ ] Uzbek + Russian localization (auth + profile strings)
- [ ] Widget tests: auth form, profile form

## API Endpoints

`POST /auth/register`, `/auth/login`, `/auth/verify-otp`, `/auth/resend-otp`, `/auth/refresh`, `/auth/logout`, `/auth/forgot-password`, `/auth/reset-password`, `GET /me`, `PUT /me`, `PUT /me/password`, `DELETE /me`

## Acceptance Criteria

- [ ] Users can register with email or phone
- [ ] Phone registration requires OTP verification
- [ ] Users can login with email, phone, or username
- [ ] JWT access + refresh token flow works with rotation
- [ ] Users can logout (refresh token revoked)
- [ ] Profile can be viewed and edited
- [ ] Password reset flow works via email
- [ ] Roles (user, seller, moderator, admin) assigned and enforced
- [ ] Dark mode and locale switching work
- [ ] All auth endpoints have Feature tests passing

---

# Sprint 2 — Social Foundation

**Phase:** 2 — Social & Engagement  
**Duration:** 3 sub-sprints (2.1–2.3)  
**Depends on:** Sprint 1  
**Priority:** P0

## Overview

Sprint 2 is split into focused sub-sprints:

| Sub-sprint | Focus | Status |
|------------|-------|--------|
| **2.1** | Follow System | **Complete** |
| **2.2** | Notifications Foundation | **Complete** |
| **2.3** | Feed Foundation | **Complete** |

Remaining Sprint 2 scope (blocks, user search, mobile social UI) moves to Sprint 2.4+ or aligns with Sprint 3 mobile work.

---

# Sprint 2.1 — Follow System ✅

**Status:** Complete  
**Doc:** [docs/SPRINT_2.1_FOLLOW_SYSTEM.md](./docs/SPRINT_2.1_FOLLOW_SYSTEM.md)

## Backend Deliverables

- [x] Migration: `follows`
- [x] `FollowService`, follow/unfollow with transactional counter updates
- [x] Event: `UserFollowed`
- [x] Public profile + followers/following lists
- [x] Feature tests: follow, unfollow, counters, pagination

## API Endpoints

`POST/DELETE /users/{id}/follow`, `GET /users/{id}/followers`, `GET /users/{id}/following`, `GET /users/{id}`

---

# Sprint 2.2 — Notifications Foundation ✅

**Status:** Complete  
**Doc:** [docs/SPRINT_2.2_NOTIFICATIONS.md](./docs/SPRINT_2.2_NOTIFICATIONS.md)

## Backend Deliverables

- [x] Migration: `notifications`
- [x] `NotificationService`, `NotificationData` DTO, `NotificationType` enum
- [x] `NotifyOnFollow` listener → `NEW_FOLLOWER` in-app notification
- [x] `SendPushNotificationJob` + `StubFcmPushNotification`
- [x] Device registration (`user_devices` model + repository)
- [x] Feature tests: notifications, devices, standardized payload

## API Endpoints

`GET /notifications`, `GET /notifications/unread-count`, `PUT /notifications/{id}/read`, `PUT /notifications/read-all`, `PUT /me/notification-settings`, `POST /devices`, `DELETE /devices/{token}`

---

# Sprint 2.3 — Feed Foundation ✅

**Status:** Complete
**Doc:** [docs/SPRINT_2.3_FEED_FOUNDATION.md](./docs/SPRINT_2.3_FEED_FOUNDATION.md)
**Depends on:** Sprint 2.1, Sprint 2.2
**Priority:** P0

## Backend Deliverables

- [x] Migrations: `videos` (minimal publishable schema)
- [x] `VideoService` — list published videos, cursor pagination
- [x] Feed endpoints: `GET /feed/for-you`, `GET /feed/following` (cursor pagination)
- [x] `VideoResource` per API spec
- [x] Following feed filters by `follows` graph (Sprint 2.1)
- [x] Feature tests: feed pagination, following feed shows followed creators only

## Mobile Deliverables

- [x] Feed module skeleton (`features/feed/`)
- [x] Vertical feed screen with cursor infinite scroll (API-backed)
- [x] Tab shell: For You / Following

## API Endpoints

`GET /feed/for-you`, `GET /feed/following`

## Acceptance Criteria

- [x] For-you feed returns paginated published videos
- [x] Following feed returns videos from followed users only
- [x] Cursor pagination matches API spec (`next_cursor`, `has_more`, `limit`)
- [x] Mobile feed screen loads first page from API
- [x] Feature tests passing

---

# Sprint 2 (legacy checklist — social remainder)

**Note:** Original monolithic Sprint 2 items not yet delivered:

## Modules (deferred)

Blocks, User Search, Likes, Comments, Bookmarks (partial overlap with Sprint 3)

## Mobile Deliverables (deferred)

- [ ] Follow/unfollow on user profiles
- [ ] Followers and following list screens
- [ ] Notification center screen
- [ ] Unread notification badge
- [ ] FCM integration (device token registration)
- [ ] User search screen

## Acceptance Criteria (remainder)

- [ ] Push notifications delivered for new follower (real FCM)
- [ ] In-app notification center works (mobile)
- [ ] Users can search for other users
- [ ] Users can block other users

---

# Sprint 3 — Video Platform

**Phase:** 3 — Video Platform  
**Duration:** 2 weeks  
**Depends on:** Sprint 2  
**Priority:** P0

## Modules

Video Upload, Video Processing, Video Feed, Infinite Scroll, Trending, Recommendations (Basic), Video Views, Comments, Likes, Bookmarks

## Backend Deliverables

- [ ] Migrations: videos, video_likes, comments, bookmarks, video_products, media_uploads
- [ ] VideoService, MediaService, RecommendationService (rule-based)
- [ ] Pre-signed URL generation for uploads
- [ ] ProcessVideoJob (transcoding stub → HLS in Sprint 15)
- [ ] Feed endpoints: for-you, trending, following (cursor pagination)
- [ ] Like, comment, bookmark endpoints
- [ ] View count tracking (Redis debounce → flush job)
- [ ] Events: VideoUploaded, VideoLiked, CommentCreated
- [ ] Feature tests: upload flow, feed, like, comment, bookmark

## Mobile Deliverables

- [ ] TikTok-style vertical video feed (full-screen)
- [ ] Infinite scroll with cursor pagination
- [ ] Auto-play / pause on scroll
- [ ] Double-tap like with heart animation
- [ ] Video upload flow (select/record → metadata → upload → confirm)
- [ ] Comments bottom sheet (view + add + reply)
- [ ] Bookmark/save video
- [ ] Share video link
- [ ] Video detail screen
- [ ] Trending feed tab

## API Endpoints

`GET /feed/for-you`, `/feed/trending`, `/feed/following`, `POST /videos`, `POST /videos/{id}/confirm-upload`, `GET /videos/{id}`, `DELETE /videos/{id}`, `POST/DELETE /videos/{id}/like`, `POST /videos/{id}/view`, `GET/POST /videos/{id}/comments`, `POST/DELETE /videos/{id}/bookmark`, `GET /bookmarks`, `POST /media/presigned-url`

## Acceptance Criteria

- [ ] Users can upload videos up to 60 seconds
- [ ] Upload uses pre-signed URL (direct to S3)
- [ ] Video feed loads with infinite scroll (< 500ms first page)
- [ ] Auto-play works smoothly at 60 FPS
- [ ] Users can like, comment, and bookmark videos
- [ ] Trending feed shows popular videos
- [ ] For You feed shows personalized content (rule-based)
- [ ] View counts tracked accurately
- [ ] All video endpoints have Feature tests passing

---

# Sprint 4 — Marketplace

**Phase:** 4 — Commerce  
**Duration:** 2 weeks  
**Depends on:** Sprint 3  
**Priority:** P0

## Modules

Products, Categories, Variants, Inventory, Wishlist (Favorites), Cart, Checkout, Orders, Product Search, Product-Video Tagging, Reviews

## Backend Deliverables

- [ ] Migrations: categories, products, product_images, product_variants, product_favorites, carts, cart_items, orders, order_items, reviews, coupons, coupon_usages
- [ ] ProductService, CartService, OrderService, CategoryService
- [ ] Product CRUD, variant management, inventory tracking
- [ ] Cart management (add, update, remove, clear)
- [ ] Checkout flow (validate cart, stock, address, create order)
- [ ] Basic payment initiation (redirect to gateway — full integration in Sprint 7)
- [ ] Product search with filters
- [ ] Product favorites (wishlist)
- [ ] Review system (verified purchase only)
- [ ] Tag products on videos (video_products)
- [ ] Events: OrderPlaced, ProductOutOfStock
- [ ] Feature tests: product list, cart, checkout, order creation

## Mobile Deliverables

- [ ] Product detail screen (images, variants, reviews, add to cart)
- [ ] Category browsing screen
- [ ] Product search screen with filters
- [ ] Shopping cart screen
- [ ] Checkout screen (address, payment method, order summary)
- [ ] Order confirmation screen
- [ ] Order history + order detail screens
- [ ] Product favorites/wishlist
- [ ] Product tag overlay on video feed
- [ ] Product bottom sheet from video tap

## API Endpoints

`GET /products`, `/products/{id}`, `/products/search`, `GET /categories`, `/categories/{id}/products`, `GET/POST/DELETE /cart`, `/cart/items`, `POST /orders`, `GET /orders`, `/orders/{id}`, `POST /products/{id}/favorite`, `GET /favorites`, `GET/POST /products/{id}/reviews`

## Acceptance Criteria

- [ ] Users can browse products by category
- [ ] Users can search and filter products
- [ ] Users can view product details with variants
- [ ] Users can add products to cart and modify quantities
- [ ] Users can complete checkout (order created, payment initiated)
- [ ] Users can view order history and track status
- [ ] Users can tag products in videos
- [ ] Users can tap product tags in videos to view/buy
- [ ] Verified buyers can leave reviews
- [ ] All commerce endpoints have Feature tests passing

---

# Sprint 5 — Seller Platform

**Phase:** 4 — Commerce  
**Duration:** 2 weeks  
**Depends on:** Sprint 4  
**Priority:** P0

## Modules

Seller Application, Seller Dashboard, Product Management, Order Management, Store Profile, Basic Analytics

## Backend Deliverables

- [ ] Migrations: stores (if not in Sprint 4)
- [ ] StoreService, seller middleware (EnsureSeller)
- [ ] Seller application flow (auto-approve for MVP)
- [ ] Seller product CRUD endpoints
- [ ] Seller order management (list, detail, status update)
- [ ] Seller analytics summary (revenue, orders, products)
- [ ] Store public page (by slug)
- [ ] Inventory decrement on order paid
- [ ] Audit logging for seller actions
- [ ] Feature tests: seller apply, product CRUD, order management

## Mobile Deliverables

- [ ] Seller application screen
- [ ] Seller dashboard (summary cards: revenue, orders, products)
- [ ] Seller product list screen
- [ ] Add/edit product screen (images, variants, pricing)
- [ ] Seller order list + order detail screens
- [ ] Order status update (confirm, ship)
- [ ] Store profile page (public view)
- [ ] Role-based navigation (buyer vs seller mode)

## API Endpoints

`POST /seller/apply`, `GET /seller/dashboard`, `GET/POST/PUT/DELETE /seller/products`, `GET /seller/orders`, `GET /seller/orders/{id}`, `PUT /seller/orders/{id}/status`, `GET /seller/analytics/summary`, `GET /stores/{slug}`, `/stores/{slug}/products`

## Acceptance Criteria

- [ ] Users can apply to become a seller
- [ ] Approved sellers can create and manage products
- [ ] Sellers can upload product images
- [ ] Sellers can manage inventory and variants
- [ ] Sellers can view and manage orders
- [ ] Sellers can update order status (confirmed → shipped → delivered)
- [ ] Seller dashboard shows basic analytics
- [ ] Public store page displays seller products
- [ ] All seller endpoints have Feature tests passing

---

# Sprint 6 — Live Commerce

**Phase:** 5 — Live Commerce  
**Duration:** 2 weeks  
**Depends on:** Sprint 4, Sprint 5  
**Priority:** P0

## Modules

Live Streaming, Streaming Provider Integration, Pinned Products, Live Chat, Viewer Count, Stream Discovery

## Backend Deliverables

- [ ] Migrations: live_streams, live_stream_products, live_chat_messages
- [ ] LiveStreamService + StreamingProviderInterface
- [ ] Agora provider implementation (default)
- [ ] Start/end stream, generate publisher/subscriber tokens
- [ ] Pin/unpin products during stream
- [ ] Live chat (REST + polling for MVP)
- [ ] Active streams listing
- [ ] Events: LiveStreamStarted, LiveStreamEnded
- [ ] Push notification to followers when seller goes live
- [ ] Feature tests: start stream, join stream, chat, pin product

## Mobile Deliverables

- [ ] "Go Live" screen for sellers (title, select products)
- [ ] Live broadcaster view (camera + overlay controls)
- [ ] Live viewer screen (video + chat + pinned products)
- [ ] Pin product overlay during live stream
- [ ] Live chat input + message list
- [ ] Active live streams discovery feed
- [ ] Tap pinned product → add to cart without leaving stream
- [ ] Agora SDK integration (Flutter)

## API Endpoints

`GET /live`, `POST /live/start`, `GET /live/{id}`, `POST /live/{id}/end`, `POST/DELETE /live/{id}/pin-product`, `GET /live/{id}/chat`, `POST /live/{id}/chat`

## Acceptance Criteria

- [ ] Sellers can start a live stream from the app
- [ ] Viewers can join and watch live streams
- [ ] Live chat works (3–5 second polling)
- [ ] Sellers can pin up to 3 products during stream
- [ ] Viewers can add pinned products to cart during stream
- [ ] Followers receive push notification when seller goes live
- [ ] Viewer count displayed
- [ ] Stream ends cleanly with status update
- [ ] All live streaming endpoints have Feature tests passing

---

# Sprint 7 — Payments

**Phase:** 4 — Commerce  
**Duration:** 2 weeks  
**Depends on:** Sprint 4  
**Priority:** P0

## Modules

Payment Gateway Integration, Transaction Processing, Webhooks, Refunds, Order Payment Status, Seller Payouts (Basic)

## Backend Deliverables

- [ ] PaymentGatewayInterface + local gateway adapter (Click/Payme/Uzum)
- [ ] Payment initiation on checkout (real gateway redirect)
- [ ] Webhook endpoint with signature verification
- [ ] ProcessPaymentWebhookJob
- [ ] Order payment status lifecycle (pending → paid → failed)
- [ ] Idempotent webhook processing
- [ ] Basic refund request flow
- [ ] Payment reference storage on orders
- [ ] Seller payout tracking table (basic, manual payout for MVP)
- [ ] Feature tests: payment initiation, webhook processing, refund

## Mobile Deliverables

- [ ] Payment method selection in checkout
- [ ] Redirect to payment gateway (WebView or deep link)
- [ ] Payment result handling (success/failure/cancel)
- [ ] Order payment status display
- [ ] Refund request screen (from order detail)

## API Endpoints

`POST /orders` (updated with real payment), `POST /webhooks/payment`, `POST /orders/{id}/cancel`, `POST /orders/{id}/refund`, `GET /refunds/{id}`

## Acceptance Criteria

- [ ] Users can pay for orders via local payment gateway
- [ ] Payment webhooks update order status correctly
- [ ] Duplicate webhooks handled idempotently
- [ ] Failed payments show clear error to user
- [ ] Refund request can be submitted
- [ ] No card data stored on platform
- [ ] Payment flow tested end-to-end on staging
- [ ] All payment endpoints have Feature tests passing

---

# Sprint 8 — Messaging

**Phase:** 6 — Communication  
**Duration:** 2 weeks  
**Depends on:** Sprint 4, Sprint 5  
**Priority:** P1

## Modules

Private Chat, Seller-Buyer Chat, Order Chat, Image Sharing, Real-Time Messaging

## Backend Deliverables

- [ ] Migrations: conversations, messages, conversation_participants
- [ ] MessagingService
- [ ] Create conversation (buyer ↔ seller)
- [ ] Send/receive messages (text + image)
- [ ] Order-linked conversations
- [ ] Message notifications via FCM
- [ ] Block check (blocked users cannot message)
- [ ] Feature tests: create conversation, send message, list messages

## Mobile Deliverables

- [ ] Conversation list screen
- [ ] Chat screen (message bubbles, input, image attach)
- [ ] Start conversation from product/store page
- [ ] Order-linked chat (from order detail)
- [ ] Unread message badge
- [ ] Push notification for new messages

## API Endpoints

`GET /conversations`, `POST /conversations`, `GET /conversations/{id}/messages`, `POST /conversations/{id}/messages`, `PUT /conversations/{id}/read`

## Acceptance Criteria

- [ ] Buyers can message sellers from product/store pages
- [ ] Sellers can reply to buyer messages
- [ ] Order-related conversations linked to order
- [ ] Image sharing works in chat
- [ ] Push notifications for new messages
- [ ] Blocked users cannot send messages
- [ ] All messaging endpoints have Feature tests passing

---

# Sprint 9 — AI Foundation

**Phase:** 7 — AI  
**Duration:** 2 weeks  
**Depends on:** Sprint 3, Sprint 4  
**Priority:** P1

## Modules

AI Adapter Layer, Recommendation Engine (ML), Content Moderation, AI-Enhanced Search, AI Chat Assistant (Basic)

## Backend Deliverables

- [ ] AiProviderInterface + OpenAI adapter (or local ML)
- [ ] AiService orchestration layer
- [ ] ML-based feed ranking (replace rule-based from Sprint 3)
- [ ] Content moderation job (VideoUploaded → ModerateContentJob)
- [ ] Moderation queue for human review
- [ ] AI-enhanced search (semantic search)
- [ ] Basic AI chat assistant endpoint
- [ ] AI output storage (moderation results, rankings cached)
- [ ] Feature tests: moderation flow, recommendation endpoint

## Mobile Deliverables

- [ ] Improved For You feed (ML-ranked)
- [ ] AI search results with relevance ranking
- [ ] Basic AI assistant chat screen
- [ ] Report content button (feeds into moderation queue)

## Acceptance Criteria

- [ ] AI adapter swappable via configuration
- [ ] ML feed ranking improves engagement metrics
- [ ] Uploaded videos auto-moderated (flag/reject/approve)
- [ ] Moderators can review flagged content
- [ ] AI search returns more relevant results
- [ ] AI failures do not break core features
- [ ] All AI endpoints have Feature tests passing

---

# Sprint 10 — AI Seller

**Phase:** 7 — AI  
**Duration:** 2 weeks  
**Depends on:** Sprint 9, Sprint 5  
**Priority:** P1

## Modules

AI Product Description, AI Product Title, AI Tags, AI SEO, AI Price Recommendation

## Backend Deliverables

- [ ] GenerateDescriptionJob, GenerateTitleJob
- [ ] AI endpoints: generate description, title, tags from product data/images
- [ ] AI price recommendation based on category/market data
- [ ] Seller AI assistant endpoints (suggest improvements)
- [ ] Store AI-generated content alongside manual content
- [ ] Feature tests: AI generation endpoints

## Mobile Deliverables

- [ ] "Generate with AI" button on product create/edit
- [ ] AI-suggested titles (pick from suggestions)
- [ ] AI-generated description (editable before save)
- [ ] AI-suggested tags and category
- [ ] AI price recommendation display

## Acceptance Criteria

- [ ] Sellers can generate product descriptions with one tap
- [ ] AI suggests 3–5 title options
- [ ] AI recommends tags and category
- [ ] AI price recommendation shown (seller can override)
- [ ] Generated content is editable before publishing
- [ ] All AI seller endpoints have Feature tests passing

---

# Sprint 11 — AI Video

**Phase:** 7 — AI  
**Duration:** 2 weeks  
**Depends on:** Sprint 9, Sprint 3  
**Priority:** P1

## Modules

Auto Captions, Translation, Highlight Detection, Thumbnail Generation, Video Quality Analysis

## Backend Deliverables

- [ ] GenerateSubtitlesJob (audio → SRT/VTT)
- [ ] GenerateThumbnailJob (best frame selection)
- [ ] TranslateSubtitlesJob
- [ ] HighlightDetectionJob
- [ ] Video quality scoring
- [ ] Store subtitles, thumbnails, highlights in media_uploads / video metadata
- [ ] Feature tests: subtitle generation, thumbnail generation

## Mobile Deliverables

- [ ] Auto-generated subtitles on videos (toggle on/off)
- [ ] Subtitle language selection (uz, ru)
- [ ] AI-selected thumbnail preview on upload
- [ ] Highlight clips suggested after upload

## Acceptance Criteria

- [ ] Subtitles auto-generated for uploaded videos
- [ ] Subtitles translatable to supported languages
- [ ] Best thumbnail auto-selected (seller can override)
- [ ] Highlight moments detected and suggested
- [ ] Video quality score shown to uploader
- [ ] All AI video jobs run asynchronously
- [ ] All AI video endpoints have Feature tests passing

---

# Sprint 12 — Analytics

**Phase:** 8 — Analytics & Admin  
**Duration:** 2 weeks  
**Depends on:** Sprint 4, Sprint 5, Sprint 6  
**Priority:** P1

## Modules

Platform Analytics, Creator Analytics, Seller Analytics (Advanced), Revenue Reports, Traffic Reports, Conversion Reports

## Backend Deliverables

- [ ] Migrations: analytics_events (or use audit_logs + aggregation)
- [ ] AnalyticsService with aggregation queries
- [ ] Seller analytics: revenue over time, top products, conversion rate
- [ ] Creator analytics: views, likes, follower growth, engagement rate
- [ ] Platform dashboard endpoints (admin-only)
- [ ] Daily/weekly/monthly aggregation jobs
- [ ] Feature tests: analytics endpoints

## Mobile Deliverables

- [ ] Seller analytics dashboard (charts: revenue, orders, views)
- [ ] Creator analytics on profile (views, engagement)
- [ ] Date range selector (7d, 30d, 90d)
- [ ] Top products list
- [ ] Conversion funnel visualization

## Acceptance Criteria

- [ ] Sellers see revenue and order trends
- [ ] Creators see video performance metrics
- [ ] Analytics data aggregated daily (not real-time for MVP)
- [ ] Admin can view platform-wide metrics
- [ ] Reports exportable (CSV — backend endpoint)
- [ ] All analytics endpoints have Feature tests passing

---

# Sprint 13 — Growth

**Phase:** 9 — Growth  
**Duration:** 2 weeks  
**Depends on:** Sprint 1, Sprint 4  
**Priority:** P1

## Modules

Referral Program, Coupons (Advanced), Campaigns, Affiliate Program (Basic), Promotional Ads, Push Campaigns

## Backend Deliverables

- [ ] Migrations: referrals, campaigns, affiliate_links
- [ ] ReferralService (invite code, reward tracking)
- [ ] Advanced coupon system (expiry, usage limits, store-specific)
- [ ] Campaign management (admin-created promotions)
- [ ] Basic affiliate link tracking
- [ ] Push campaign broadcast (admin → segment)
- [ ] Feature tests: referral, coupon, campaign

## Mobile Deliverables

- [ ] Referral invite screen (share link/code)
- [ ] Apply coupon at checkout
- [ ] Promotional banners in feed
- [ ] Campaign landing screens

## Acceptance Criteria

- [ ] Users can invite friends via referral link
- [ ] Referral rewards tracked
- [ ] Coupons apply correctly at checkout with validation
- [ ] Admin can create promotional campaigns
- [ ] Push campaigns sent to user segments
- [ ] All growth endpoints have Feature tests passing

---

# Sprint 14 — Administration

**Phase:** 8 — Analytics & Admin  
**Duration:** 2 weeks  
**Depends on:** Sprint 1–7  
**Priority:** P0 (core moderation needed before public beta)

## Modules

Admin Dashboard, Content Moderation, User Reports, Ban/Suspend System, Category Management, Seller Verification, Content Review Queue

## Backend Deliverables

- [ ] Admin middleware (EnsureAdmin, EnsureModerator)
- [ ] Admin endpoints: user management, seller approval, category CRUD
- [ ] Moderation queue (reported/flagged content)
- [ ] Ban/suspend user endpoints
- [ ] Report content endpoint (user-facing)
- [ ] Admin analytics overview
- [ ] Audit log viewer endpoint
- [ ] Feature tests: admin actions, moderation, reports

## Mobile Deliverables

- [ ] Report content/user button (on videos, profiles, products)
- [ ] (Admin panel is web-only for MVP — not mobile)

## Web Admin Panel (Separate — MVP Scope)

- [ ] Admin dashboard (users, orders, revenue overview)
- [ ] User management (view, suspend, ban)
- [ ] Seller verification queue
- [ ] Content moderation queue
- [ ] Category management
- [ ] Audit log viewer

## Acceptance Criteria

- [ ] Admins can view platform overview
- [ ] Admins can suspend/ban users
- [ ] Moderators can review and action flagged content
- [ ] Seller applications reviewed and approved/rejected
- [ ] Categories managed by admin
- [ ] All admin endpoints have Feature tests passing
- [ ] Admin panel deployed to staging

---

# Sprint 15 — Scaling

**Phase:** 10 — Scaling & Launch  
**Duration:** 2 weeks  
**Depends on:** All previous sprints  
**Priority:** P0

## Modules

Performance Optimization, Redis Caching, Queue Optimization, CDN Tuning, Database Optimization, Horizontal Scaling, Monitoring, Logging, Video Transcoding Pipeline

## Backend Deliverables

- [ ] Redis caching: feed pages, categories, trending, user profiles
- [ ] View/like counter flush jobs optimized
- [ ] Database query optimization (N+1 elimination, eager loading audit)
- [ ] Read replica routing for feed and search queries
- [ ] Laravel Horizon for queue monitoring
- [ ] Full video transcoding pipeline (HLS 720p/480p/360p)
- [ ] Sentry error tracking integrated
- [ ] Slow query logging and alerting
- [ ] Load test scripts (k6 or Artillery)
- [ ] Health check with dependency status

## Mobile Deliverables

- [ ] Feed performance optimization (prefetch, cache)
- [ ] Image caching optimization
- [ ] Video preloading (next video in feed)
- [ ] App startup time optimization (< 2 seconds)
- [ ] Memory leak audit and fixes

## Infrastructure Deliverables

- [ ] CDN configured for video and image delivery
- [ ] Auto-scaling rules for API servers
- [ ] Queue worker scaling configuration
- [ ] PostgreSQL connection pooling (PgBouncer)
- [ ] Staging load test: 1000 concurrent users
- [ ] Monitoring dashboards (Sentry, uptime, queue depth)

## Acceptance Criteria

- [ ] API p95 response time < 200ms for reads
- [ ] Feed loads in < 500ms (cached)
- [ ] Video transcoding pipeline processes uploads within 5 minutes
- [ ] Platform handles 1000 concurrent users on staging
- [ ] No N+1 queries in critical paths
- [ ] Error tracking active on staging
- [ ] Monitoring alerts configured

---

# Sprint 16 — Production Release

**Phase:** 10 — Scaling & Launch  
**Duration:** 2 weeks  
**Depends on:** Sprint 15  
**Priority:** P0

## Objectives

Security Audit, Performance Audit, Load Testing, Bug Fixes, Documentation Review, App Store Submission, Production Deployment

## Deliverables

### Security
- [ ] Security audit checklist completed (see Engineering Rules)
- [ ] Penetration test on auth, payment, and upload flows
- [ ] All secrets rotated for production
- [ ] Rate limiting verified
- [ ] CORS, CSP headers configured

### Performance
- [ ] Load test: 5000 concurrent users (target for launch)
- [ ] Mobile app tested on low-end Android devices
- [ ] Video streaming tested on 3G/4G networks
- [ ] App size optimized (< 50MB initial download)

### Quality
- [ ] All P0 bug fixes completed
- [ ] Full regression test pass
- [ ] API documentation finalized (OpenAPI spec generated)
- [ ] Mobile app screenshots and store listing prepared

### Deployment
- [ ] Production environment provisioned
- [ ] Database migrations run
- [ ] CDN configured with production bucket
- [ ] Payment gateway switched to production keys
- [ ] Streaming provider production credentials
- [ ] FCM production configuration
- [ ] App submitted to Google Play and App Store
- [ ] Rollback procedure documented and tested

### Launch
- [ ] Soft launch with invite-only users (1 week)
- [ ] Monitor error rates, performance, payment success rate
- [ ] Fix critical issues from soft launch
- [ ] Public launch in Uzbekistan

## Acceptance Criteria

- [ ] Version 1.0 deployed to production
- [ ] Apps approved and published on Google Play and App Store
- [ ] Payment flow works with real transactions
- [ ] Live streaming works with real users
- [ ] No P0/P1 bugs open
- [ ] Monitoring and alerting active
- [ ] Team trained on incident response

---

# MVP Definition

The **Minimum Viable Product** includes everything needed to launch in Uzbekistan and validate product-market fit.

## MVP Scope (Sprints 0–7 + Sprint 14 core)

| Included | Sprint |
|----------|--------|
| Auth, profiles, roles | Sprint 1 |
| Follow, likes, comments, bookmarks, notifications | Sprint 2 |
| Video upload, feed, trending, basic recommendations | Sprint 3 |
| Products, cart, checkout, orders, reviews | Sprint 4 |
| Seller dashboard, product/order management | Sprint 5 |
| Live streaming, pinned products, live chat | Sprint 6 |
| Payment gateway, webhooks, refunds | Sprint 7 |
| Admin moderation, user management | Sprint 14 (core) |
| Performance, caching, monitoring | Sprint 15 |
| Production release | Sprint 16 |

## Post-MVP (v1.1+)

| Feature | Sprint |
|---------|--------|
| Messaging | Sprint 8 |
| AI features | Sprint 9–11 |
| Advanced analytics | Sprint 12 |
| Growth tools | Sprint 13 |
| Social login, stream replay, seller verification | v1.1 fast-follow |

---

# Timeline Summary

| Month | Sprints | Focus |
|-------|---------|-------|
| Month 1 | 0, 1, 2 | Foundation + Auth + Social |
| Month 2 | 3, 4 | Video Platform + Marketplace |
| Month 3 | 5, 6 | Seller Platform + Live Commerce |
| Month 4 | 7, 8 | Payments + Messaging |
| Month 5 | 9, 10 | AI Foundation + AI Seller |
| Month 6 | 11, 12, 14 | AI Video + Analytics + Admin |
| Month 7 | 13, 15 | Growth + Scaling |
| Month 8 | 16 | Production Release |

**Note:** Sprints can overlap partially if team size allows (e.g., backend and mobile tracks in parallel). Timeline assumes a small team (2–4 developers).

---

# Future Versions

## Version 2.0 (Months 9–18)

- Cross-border commerce
- AI shopping assistant (conversational)
- AR product preview
- Video calls (buyer ↔ seller)
- Creator subscriptions
- Creator economy (tips, memberships)
- Social login (Google, Apple)
- Stream replay and recording
- Elasticsearch for search
- WebSocket real-time chat

## Version 3.0 (Year 2+)

- Global marketplace expansion (Kazakhstan, Kyrgyzstan, Turkey)
- Voice commerce
- AI shopping agent (autonomous purchasing)
- Marketplace public API
- Developer platform (third-party integrations)
- Virtual gifts during live streams
- Multi-region deployment
- Database sharding

---

# Definition of Done (Every Sprint)

Every sprint must satisfy ALL of the following before acceptance:

- [ ] All acceptance criteria met
- [ ] Code review completed and approved
- [ ] Unit tests written and passing (80%+ coverage on services/use cases)
- [ ] Feature tests written and passing (all new endpoints)
- [ ] Mobile widget/unit tests passing
- [ ] CI pipeline green (lint, analyze, test, build)
- [ ] API changes reflected in API Specification document
- [ ] Database changes reflected in Database Design document
- [ ] No P0 or P1 bugs introduced
- [ ] Performance verified (no regressions)
- [ ] Security reviewed (auth, input validation, authorization)
- [ ] Deployed to staging and smoke tested
- [ ] Demo completed with stakeholders

---

# Roadmap Rules

1. **No sprint may begin until the previous sprint is accepted** (unless explicitly parallelized with no dependency).
2. **Documentation before code.** API and database changes are documented before implementation.
3. **Tests before merge.** No PR merges without passing tests.
4. **MVP first.** Post-MVP features (Sprint 8+) must not delay MVP launch.
5. **Scope control.** New features mid-sprint require stakeholder approval and scope adjustment.
6. **Weekly demos.** End of each sprint week includes a demo of progress.
7. **Retrospective.** End of each sprint includes a retrospective and planning for next sprint.

---

# Risk Register (Roadmap Level)

| Risk | Impact | Mitigation | Affected Sprints |
|------|--------|------------|------------------|
| Payment gateway integration delays | High | Start integration early (Sprint 4 stub, Sprint 7 full) | 4, 7 |
| Streaming provider issues | High | Abstract provider; test Agora early in Sprint 6 | 6 |
| Video CDN costs | Medium | Upload limits, adaptive bitrate, CDN in Sprint 15 | 3, 15 |
| Small team bottleneck | High | Parallel backend/mobile tracks; strict scope per sprint | All |
| App store rejection | Medium | Follow guidelines from Sprint 1; submit early in Sprint 16 | 16 |
| Low seller adoption at launch | High | Pre-launch seller onboarding program | 5, 16 |

---

# Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial roadmap with sprint list |
| 1.1 | 2026-06-27 | Founder & CTO | Completed roadmap: dependencies, deliverables, acceptance criteria, MVP definition, timeline, DoD, risks |

---

**Related Documents:**
- [PRD](./docs01_PRD.md)
- [Project Context](./PROJECT_CONTEXT.md)
- [System Architecture](./docs/02_SYSTEM_ARCHITECTURE.md)
- [Database Design](./docs/03_DATABASE_DESIGN.md)
- [API Specification](./docs/04_API_SPECIFICATION.md)
- [Project Structure](./docs/05_PROJECT_STRUCTURE.md)
- [Engineering Rules](./docs/06_ENGINEERING_RULES.md)

**Status:** Architecture Phase — Pending Review
