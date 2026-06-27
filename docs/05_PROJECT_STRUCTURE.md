# Project Structure

Version: 1.0  
Project: LiveCommerce Platform  
Status: Architecture Phase — Approved for Implementation Planning  
Document Owner: Founder & CTO  
Last Updated: 2026-06-27

---

## Document Purpose

This document defines the complete folder structure, module organization, and shared conventions for the LiveCommerce monorepo. All code must be placed according to this structure.

---

## Table of Contents

1. [Monorepo Layout](#1-monorepo-layout)
2. [Laravel Backend Structure](#2-laravel-backend-structure)
3. [Flutter Mobile Structure](#3-flutter-mobile-structure)
4. [Documentation Structure](#4-documentation-structure)
5. [Infrastructure Structure](#5-infrastructure-structure)
6. [Shared Conventions](#6-shared-conventions)
7. [Naming Conventions](#7-naming-conventions)
8. [File Organization Rules](#8-file-organization-rules)
9. [Document Revision History](#9-document-revision-history)

---

## 1. Monorepo Layout

```
livecommerce/
├── backend/                     # Laravel 12 REST API
├── mobile/                      # Flutter application (Android + iOS)
├── docs/                        # Engineering documentation
│   ├── 02_SYSTEM_ARCHITECTURE.md
│   ├── 03_DATABASE_DESIGN.md
│   ├── 04_API_SPECIFICATION.md
│   ├── 05_PROJECT_STRUCTURE.md
│   └── 06_ENGINEERING_RULES.md
├── docker/                      # Local development Docker configs
│   ├── php/
│   │   └── Dockerfile
│   ├── nginx/
│   │   └── default.conf
│   └── docker-compose.yml
├── .github/                     # CI/CD workflows
│   └── workflows/
│       ├── backend-ci.yml
│       ├── mobile-ci.yml
│       └── deploy-staging.yml
├── docs01_PRD.md            # Product Requirements Document
├── PROJECT_CONTEXT.md       # Project context and tech stack
├── .gitignore
├── .editorconfig
└── README.md
```

### Repository Rules

1. Single monorepo for backend and mobile.
2. Each top-level directory is an independent deployable unit.
3. Shared documentation lives in `docs/`.
4. No code in the repository root except configuration files.
5. Environment files (`.env`) are never committed.

---

## 2. Laravel Backend Structure

### 2.1 Top-Level

```
backend/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── artisan
├── composer.json
├── composer.lock
├── phpunit.xml
└── .env.example
```

### 2.2 Application Layer (`app/`)

```
app/
├── Console/
│   └── Commands/
│       ├── CleanupExpiredTokensCommand.php
│       ├── CleanupOldNotificationsCommand.php
│       └── FlushViewCountsCommand.php
│
├── Contracts/
│   ├── Repositories/
│   │   ├── UserRepositoryInterface.php
│   │   ├── VideoRepositoryInterface.php
│   │   ├── ProductRepositoryInterface.php
│   │   ├── OrderRepositoryInterface.php
│   │   ├── CartRepositoryInterface.php
│   │   ├── StoreRepositoryInterface.php
│   │   ├── LiveStreamRepositoryInterface.php
│   │   ├── NotificationRepositoryInterface.php
│   │   └── FollowRepositoryInterface.php
│   └── Services/
│       ├── StreamingProviderInterface.php
│       ├── PaymentGatewayInterface.php
│       ├── SmsProviderInterface.php
│       ├── PushNotificationInterface.php
│       └── AiProviderInterface.php
│
├── Events/
│   ├── UserRegistered.php
│   ├── UserFollowed.php
│   ├── VideoUploaded.php
│   ├── VideoProcessed.php
│   ├── VideoLiked.php
│   ├── CommentCreated.php
│   ├── OrderPlaced.php
│   ├── OrderPaid.php
│   ├── OrderShipped.php
│   ├── VideoPublished.php
│   ├── ProductCreated.php
│   ├── SellerVerified.php
│   ├── LiveStreamStarted.php
│   └── LiveStreamEnded.php
│
├── Exceptions/
│   ├── Handler.php
│   ├── BusinessException.php
│   ├── InsufficientStockException.php
│   ├── PaymentFailedException.php
│   └── InvalidStateTransitionException.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── AuthController.php
│   │           ├── UserController.php
│   │           ├── FeedController.php
│   │           ├── VideoController.php
│   │           ├── ProductController.php
│   │           ├── CategoryController.php
│   │           ├── CartController.php
│   │           ├── OrderController.php
│   │           ├── StoreController.php
│   │           ├── Seller/
│   │           │   ├── DashboardController.php
│   │           │   ├── ProductController.php
│   │           │   ├── OrderController.php
│   │           │   └── AnalyticsController.php
│   │           ├── LiveStreamController.php
│   │           ├── SearchController.php
│   │           ├── NotificationController.php
│   │           ├── MediaController.php
│   │           ├── DeviceController.php
│   │           ├── WebhookController.php
│   │           └── HealthController.php
│   │
│   ├── Middleware/
│   │   ├── AuthenticateApi.php
│   │   ├── EnsureSeller.php
│   │   ├── EnsureRole.php
│   │   └── SetLocale.php
│   │
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── RegisterRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   └── VerifyOtpRequest.php
│   │   ├── Video/
│   │   │   ├── CreateVideoRequest.php
│   │   │   └── UpdateVideoRequest.php
│   │   ├── Product/
│   │   │   ├── CreateProductRequest.php
│   │   │   └── UpdateProductRequest.php
│   │   ├── Order/
│   │   │   └── CreateOrderRequest.php
│   │   ├── Cart/
│   │   │   └── AddCartItemRequest.php
│   │   ├── LiveStream/
│   │   │   ├── StartStreamRequest.php
│   │   │   └── SendChatMessageRequest.php
│   │   └── Seller/
│   │       └── ApplySellerRequest.php
│   │
│   └── Resources/
│       ├── UserResource.php
│       ├── UserCompactResource.php
│       ├── VideoResource.php
│       ├── CommentResource.php
│       ├── ProductResource.php
│       ├── ProductCompactResource.php
│       ├── StoreResource.php
│       ├── CategoryResource.php
│       ├── CartResource.php
│       ├── CartItemResource.php
│       ├── OrderResource.php
│       ├── OrderItemResource.php
│       ├── RefundResource.php
│       ├── LiveStreamResource.php
│       ├── NotificationResource.php
│       └── ReviewResource.php
│
├── Jobs/
│   ├── ProcessVideoJob.php
│   ├── SendPushNotificationJob.php
│   ├── SendEmailJob.php
│   ├── DecrementInventoryJob.php
│   ├── FlushViewCountsJob.php
│   └── ProcessPaymentWebhookJob.php
│
├── Listeners/
│   ├── SendWelcomeNotification.php
│   ├── NotifyOnFollow.php
│   ├── NotifyOnLike.php
│   ├── NotifyOnComment.php
│   ├── NotifyOnOrderPlaced.php
│   ├── NotifyOnLiveStreamStarted.php
│   └── QueueVideoProcessing.php
│
├── Models/
│   ├── User.php
│   ├── UserProfile.php
│   ├── UserDevice.php
│   ├── RefreshToken.php
│   ├── Follow.php
│   ├── Block.php
│   ├── Video.php
│   ├── VideoLike.php
│   ├── VideoProduct.php
│   ├── Comment.php
│   ├── Bookmark.php
│   ├── Category.php
│   ├── Store.php
│   ├── Product.php
│   ├── ProductImage.php
│   ├── ProductVariant.php
│   ├── ProductFavorite.php
│   ├── Cart.php
│   ├── CartItem.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Coupon.php
│   ├── CouponUsage.php
│   ├── LiveStream.php
│   ├── LiveStreamProduct.php
│   ├── LiveChatMessage.php
│   ├── Notification.php
│   ├── Review.php
│   ├── MediaUpload.php
│   └── AuditLog.php
│
├── Policies/
│   ├── VideoPolicy.php
│   ├── ProductPolicy.php
│   ├── OrderPolicy.php
│   ├── StorePolicy.php
│   ├── CommentPolicy.php
│   └── LiveStreamPolicy.php
│
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── EventServiceProvider.php
│   └── RepositoryServiceProvider.php
│
├── Repositories/
│   └── Eloquent/
│       ├── UserRepository.php
│       ├── VideoRepository.php
│       ├── ProductRepository.php
│       ├── OrderRepository.php
│       ├── CartRepository.php
│       ├── StoreRepository.php
│       ├── LiveStreamRepository.php
│       ├── NotificationRepository.php
│       └── FollowRepository.php
│
└── Services/
    ├── Auth/
    │   └── AuthService.php
    ├── User/
    │   └── ProfileService.php
    ├── Video/
    │   └── VideoService.php
    ├── Product/
    │   └── ProductService.php
    ├── Cart/
    │   └── CartService.php
    ├── Order/
    │   └── OrderService.php
    ├── Store/
    │   └── StoreService.php
    ├── LiveStream/
    │   ├── LiveStreamService.php
    │   └── Providers/
    │       ├── AgoraProvider.php
    │       ├── HundredMsProvider.php
    │       └── ZegocloudProvider.php
    ├── Notification/
    │   └── NotificationService.php
    ├── Search/
    │   └── SearchService.php
    ├── Recommendation/
    │   └── RecommendationService.php
    ├── Media/
    │   └── MediaService.php
    ├── Payment/
    │   └── PaymentGatewayService.php
    ├── Audit/
    │   └── AuditService.php
    └── Ai/
        └── AiService.php
```

### 2.3 Routes (`routes/`)

```
routes/
├── api.php          # All API routes (versioned inside)
├── channels.php     # Broadcast channels (Phase 1.1)
└── console.php      # Artisan commands
```

**`routes/api.php` organization:**

```php
// Public
Route::prefix('v1')->group(function () {
    Route::get('health', ...);
    Route::prefix('auth')->group(function () { ... });

    // Public read endpoints
    Route::get('feed/trending', ...);
    Route::get('products', ...);
    Route::get('categories', ...);
    Route::get('search', ...);
    Route::get('live', ...);

    // Authenticated
    Route::middleware('auth:api')->group(function () {
        Route::get('me', ...);
        Route::apiResource('videos', ...);
        Route::prefix('cart')->group(function () { ... });
        Route::apiResource('orders', ...);
        Route::prefix('seller')->middleware('seller')->group(function () { ... });
        Route::prefix('live')->group(function () { ... });
        // ...
    });

    // Webhooks (signature verified)
    Route::post('webhooks/payment', ...);
});
```

### 2.4 Database (`database/`)

```
database/
├── migrations/
│   ├── 2026_01_01_000001_create_users_table.php
│   ├── 2026_01_01_000002_create_user_profiles_table.php
│   ├── 2026_01_01_000003_create_user_devices_table.php
│   ├── 2026_01_01_000004_create_refresh_tokens_table.php
│   ├── 2026_01_01_000005_create_follows_table.php
│   ├── 2026_01_01_000006_create_blocks_table.php
│   ├── 2026_01_01_000007_create_categories_table.php
│   ├── 2026_01_01_000008_create_stores_table.php
│   ├── 2026_01_01_000009_create_products_table.php
│   ├── 2026_01_01_000010_create_product_images_table.php
│   ├── 2026_01_01_000011_create_product_variants_table.php
│   ├── 2026_01_01_000012_create_product_favorites_table.php
│   ├── 2026_01_01_000013_create_videos_table.php
│   ├── 2026_01_01_000014_create_video_products_table.php
│   ├── 2026_01_01_000015_create_video_likes_table.php
│   ├── 2026_01_01_000016_create_comments_table.php
│   ├── 2026_01_01_000017_create_bookmarks_table.php
│   ├── 2026_01_01_000018_create_carts_table.php
│   ├── 2026_01_01_000019_create_cart_items_table.php
│   ├── 2026_01_01_000020_create_coupons_table.php
│   ├── 2026_01_01_000021_create_coupon_usages_table.php
│   ├── 2026_01_01_000022_create_orders_table.php
│   ├── 2026_01_01_000023_create_order_items_table.php
│   ├── 2026_01_01_000024_create_refund_requests_table.php
│   ├── 2026_01_01_000025_create_live_streams_table.php
│   ├── 2026_01_01_000026_create_live_stream_products_table.php
│   ├── 2026_01_01_000027_create_live_chat_messages_table.php
│   ├── 2026_01_01_000028_create_notifications_table.php
│   ├── 2026_01_01_000029_create_reviews_table.php
│   ├── 2026_01_01_000030_create_media_uploads_table.php
│   └── 2026_01_01_000031_create_audit_logs_table.php
│
├── seeders/
│   ├── DatabaseSeeder.php
│   ├── CategorySeeder.php
│   ├── AdminUserSeeder.php
│   └── DemoDataSeeder.php
│
└── factories/
    ├── UserFactory.php
    ├── VideoFactory.php
    ├── ProductFactory.php
    ├── OrderFactory.php
    └── StoreFactory.php
```

### 2.5 Tests (`tests/`)

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── RegisterTest.php
│   │   ├── LoginTest.php
│   │   └── TokenRefreshTest.php
│   ├── Video/
│   │   ├── VideoFeedTest.php
│   │   ├── VideoUploadTest.php
│   │   └── VideoLikeTest.php
│   ├── Product/
│   │   ├── ProductListTest.php
│   │   └── ProductSearchTest.php
│   ├── Cart/
│   │   └── CartManagementTest.php
│   ├── Order/
│   │   ├── CheckoutTest.php
│   │   └── OrderStatusTest.php
│   ├── Seller/
│   │   ├── SellerApplyTest.php
│   │   └── SellerProductTest.php
│   └── LiveStream/
│       ├── StartStreamTest.php
│       └── LiveChatTest.php
│
├── Unit/
│   ├── Services/
│   │   ├── AuthServiceTest.php
│   │   ├── VideoServiceTest.php
│   │   ├── OrderServiceTest.php
│   │   └── CartServiceTest.php
│   └── Repositories/
│       ├── VideoRepositoryTest.php
│       └── OrderRepositoryTest.php
│
├── TestCase.php
└── CreatesApplication.php
```

---

## 3. Flutter Mobile Structure

### 3.1 Top-Level

```
mobile/
├── lib/
├── assets/
├── test/
├── integration_test/
├── android/
├── ios/
├── pubspec.yaml
├── analysis_options.yaml
└── l10n.yaml
```

### 3.2 Application Layer (`lib/`)

```
lib/
├── main.dart
│
├── app/
│   ├── app.dart                          # MaterialApp / root widget
│   ├── router.dart                       # GoRouter configuration
│   └── app_bootstrap.dart                # Initialization (storage, DI, etc.)
│
├── core/
│   ├── constants/
│   │   ├── api_constants.dart            # Base URL, endpoints, timeouts
│   │   ├── app_constants.dart            # App-wide constants
│   │   └── storage_keys.dart             # Local storage key names
│   │
│   ├── errors/
│   │   ├── exceptions.dart               # Custom exceptions
│   │   ├── failures.dart                 # Failure classes for domain layer
│   │   └── error_handler.dart            # Global error mapping
│   │
│   ├── network/
│   │   ├── api_client.dart               # Dio instance configuration
│   │   ├── auth_interceptor.dart         # JWT token injection + refresh
│   │   ├── logging_interceptor.dart      # Request/response logging (dev)
│   │   └── network_info.dart             # Connectivity check
│   │
│   ├── storage/
│   │   ├── secure_storage.dart           # Token storage wrapper
│   │   └── local_storage.dart            # Hive/Drift initialization
│   │
│   ├── theme/
│   │   ├── app_theme.dart                # Light + dark ThemeData
│   │   ├── app_colors.dart               # Color palette
│   │   ├── app_typography.dart           # Text styles
│   │   └── app_spacing.dart              # Spacing constants
│   │
│   ├── l10n/
│   │   ├── app_uz.arb                    # Uzbek translations
│   │   └── app_ru.arb                    # Russian translations
│   │
│   └── utils/
│       ├── formatters.dart               # Price, date, number formatters
│       ├── validators.dart               # Input validation helpers
│       └── extensions.dart               # Dart extension methods
│
├── features/
│   ├── auth/
│   │   ├── data/
│   │   │   ├── datasources/
│   │   │   │   ├── auth_remote_datasource.dart
│   │   │   │   └── auth_local_datasource.dart
│   │   │   ├── models/
│   │   │   │   ├── user_model.dart
│   │   │   │   └── auth_response_model.dart
│   │   │   └── repositories/
│   │   │       └── auth_repository_impl.dart
│   │   ├── domain/
│   │   │   ├── entities/
│   │   │   │   └── user.dart
│   │   │   ├── repositories/
│   │   │   │   └── auth_repository.dart
│   │   │   └── usecases/
│   │   │       ├── login.dart
│   │   │       ├── register.dart
│   │   │       ├── logout.dart
│   │   │       └── refresh_token.dart
│   │   └── presentation/
│   │       ├── providers/
│   │       │   └── auth_provider.dart
│   │       ├── screens/
│   │       │   ├── login_screen.dart
│   │       │   ├── register_screen.dart
│   │       │   └── otp_verification_screen.dart
│   │       └── widgets/
│   │           └── auth_form.dart
│   │
│   ├── feed/
│   │   ├── data/ ...
│   │   ├── domain/ ...
│   │   └── presentation/
│   │       ├── providers/
│   │       │   └── feed_provider.dart
│   │       ├── screens/
│   │       │   └── feed_screen.dart
│   │       └── widgets/
│   │           ├── video_player_widget.dart
│   │           ├── video_overlay.dart
│   │           ├── like_animation.dart
│   │           └── product_tag_overlay.dart
│   │
│   ├── video/       # Upload, detail, comments
│   ├── product/     # Product detail, favorites
│   ├── cart/        # Shopping cart
│   ├── checkout/    # Checkout flow
│   ├── orders/      # Order history, detail
│   ├── profile/     # User profile, edit, followers
│   ├── search/      # Global search
│   ├── notifications/
│   ├── live/        # Live streaming (broadcaster + viewer)
│   └── seller/      # Seller dashboard, products, orders
│
└── shared/
    ├── widgets/
    │   ├── app_button.dart
    │   ├── app_text_field.dart
    │   ├── loading_indicator.dart
    │   ├── error_widget.dart
    │   ├── empty_state.dart
    │   ├── cached_image.dart
    │   ├── price_tag.dart
    │   ├── user_avatar.dart
    │   └── bottom_sheet_wrapper.dart
    │
    └── providers/
        ├── connectivity_provider.dart
        └── locale_provider.dart
```

### 3.3 Feature Module Template

Every feature follows this exact structure:

```
features/{feature_name}/
├── data/
│   ├── datasources/
│   │   ├── {feature}_remote_datasource.dart
│   │   └── {feature}_local_datasource.dart     # If offline support needed
│   ├── models/
│   │   └── {entity}_model.dart                 # JSON serializable
│   └── repositories/
│       └── {feature}_repository_impl.dart
├── domain/
│   ├── entities/
│   │   └── {entity}.dart                     # Pure Dart class
│   ├── repositories/
│   │   └── {feature}_repository.dart           # Abstract interface
│   └── usecases/
│       └── {action}.dart                       # One use case per action
└── presentation/
    ├── providers/
    │   └── {feature}_provider.dart             # Riverpod Notifier/Provider
    ├── screens/
    │   └── {feature}_screen.dart
    └── widgets/
        └── {specific_widget}.dart
```

### 3.4 Assets

```
assets/
├── images/
│   ├── logo.png
│   ├── placeholder_avatar.png
│   └── onboarding/
├── fonts/
│   └── (custom fonts if needed)
└── l10n/
    ├── app_uz.arb
    └── app_ru.arb
```

### 3.5 Tests

```
test/
├── features/
│   ├── auth/
│   │   ├── data/
│   │   │   └── auth_repository_impl_test.dart
│   │   ├── domain/
│   │   │   └── login_test.dart
│   │   └── presentation/
│   │       └── auth_provider_test.dart
│   ├── feed/
│   ├── cart/
│   └── ...
├── core/
│   ├── network/
│   └── utils/
└── shared/
    └── widgets/

integration_test/
├── auth_flow_test.dart
├── checkout_flow_test.dart
└── video_upload_flow_test.dart
```

---

## 4. Documentation Structure

```
docs/
├── 02_SYSTEM_ARCHITECTURE.md     # System design, services, event flows
├── 03_DATABASE_DESIGN.md         # Schema, ER diagram, indexes
├── 04_API_SPECIFICATION.md       # REST endpoints, auth, resources
├── 05_PROJECT_STRUCTURE.md       # This document
└── 06_ENGINEERING_RULES.md       # Coding standards, git, CI/CD
```

Root-level documents:

```
docs01_PRD.md                 # Product requirements (business)
PROJECT_CONTEXT.md            # Project overview and tech stack
```

### Documentation Rules

1. Architecture documents live in `docs/` with numbered prefixes.
2. Business documents live at repository root.
3. All documents are Markdown (.md).
4. Documents are versioned with revision history tables.
5. Implementation must not begin until architecture documents are approved.

---

## 5. Infrastructure Structure

```
docker/
├── docker-compose.yml
├── php/
│   ├── Dockerfile
│   └── php.ini
├── nginx/
│   └── default.conf
└── postgres/
    └── init.sql                     # Extensions (pgcrypto, etc.)

.github/
└── workflows/
    ├── backend-ci.yml               # Lint + test on PR
    ├── mobile-ci.yml                # Analyze + test on PR
    └── deploy-staging.yml           # Deploy on merge to develop
```

---

## 6. Shared Conventions

### 6.1 Cross-Platform Identifiers

| Concept | Backend (PHP) | Mobile (Dart) | API (JSON) |
|---------|---------------|---------------|------------|
| User ID | `$user->id` (UUID string) | `user.id` (String) | `"id": "uuid"` |
| Product ID | `$product->id` | `product.id` (String) | `"id": "uuid"` |
| Timestamps | Carbon / `created_at` | `DateTime` | ISO 8601 string |
| Money | `decimal(12,2)` | `double` | `250000.00` |
| Status enums | PHP backed enum or string const | Dart enum | `"status": "active"` |

### 6.2 API ↔ Mobile Mapping

| Backend | Mobile |
|---------|--------|
| Controller | Remote DataSource |
| Form Request | Validator (client-side) + server validation |
| API Resource | Model (data layer) → Entity (domain layer) |
| Service | UseCase (domain layer) |
| Repository | Repository interface (domain) + impl (data) |
| Policy | Checked server-side; UI hides unauthorized actions |
| Job | Not visible to mobile (async server-side) |
| Event | Not visible to mobile (server-side) |

### 6.3 Environment Configuration

| Variable | Backend (.env) | Mobile (dart-define / env) |
|----------|----------------|---------------------------|
| API URL | `APP_URL` | `API_BASE_URL` |
| S3 Bucket | `AWS_BUCKET` | Not exposed (pre-signed URLs) |
| Streaming | `STREAMING_PROVIDER` | SDK keys in native config |
| FCM | `FCM_SERVER_KEY` | `google-services.json` / `GoogleService-Info.plist` |
| Sentry DSN | `SENTRY_DSN` | `--dart-define=SENTRY_DSN=` |

### 6.4 Error Code Mapping

Both platforms use consistent error handling:

| HTTP Status | Backend Exception | Mobile Failure |
|-------------|-------------------|----------------|
| 401 | `AuthenticationException` | `AuthFailure` → redirect to login |
| 403 | `AuthorizationException` | `PermissionFailure` → show message |
| 404 | `ModelNotFoundException` | `NotFoundFailure` → show empty state |
| 422 | `ValidationException` | `ValidationFailure` → show field errors |
| 429 | `TooManyRequestsException` | `RateLimitFailure` → show retry timer |
| 500 | `ServerException` | `ServerFailure` → show retry button |

---

## 7. Naming Conventions

### 7.1 Backend (PHP / Laravel)

| Element | Convention | Example |
|---------|------------|---------|
| Classes | PascalCase | `VideoService` |
| Methods | camelCase | `getFeedVideos()` |
| Variables | camelCase | `$videoCount` |
| Constants | UPPER_SNAKE_CASE | `MAX_UPLOAD_SIZE` |
| Database tables | snake_case, plural | `video_likes` |
| Database columns | snake_case | `created_at` |
| Migration files | snake_case | `create_videos_table` |
| Routes | kebab-case, plural | `/api/v1/live-streams` |
| Config keys | snake_case | `streaming.provider` |
| Environment vars | UPPER_SNAKE_CASE | `STREAMING_PROVIDER` |
| Test methods | snake_case | `test_user_can_like_video` |

### 7.2 Mobile (Dart / Flutter)

| Element | Convention | Example |
|---------|------------|---------|
| Files | snake_case | `video_player_widget.dart` |
| Classes | PascalCase | `VideoPlayerWidget` |
| Variables / methods | camelCase | `videoCount`, `fetchFeed()` |
| Constants | camelCase or lowerCamelCase | `maxUploadSize` |
| Providers | camelCase + Provider suffix | `feedProvider` |
| Entities | PascalCase (singular) | `Video`, `Product` |
| Models | PascalCase + Model suffix | `VideoModel` |
| Screens | PascalCase + Screen suffix | `FeedScreen` |
| Widgets | PascalCase + Widget suffix | `LikeAnimationWidget` |
| Use cases | PascalCase (verb + noun) | `GetFeedVideos`, `AddToCart` |

### 7.3 Git

| Element | Convention | Example |
|---------|------------|---------|
| Branches | kebab-case with prefix | `feature/video-upload` |
| Commits | Conventional Commits | `feat(video): add upload confirmation endpoint` |
| Tags | Semantic versioning | `v1.0.0`, `v1.1.0` |
| PR titles | Same as commit convention | `feat(cart): implement add to cart API` |

---

## 8. File Organization Rules

### 8.1 One Class Per File

Every class, enum, and typedef gets its own file. File name matches the class name in snake_case (Dart) or PascalCase (PHP).

### 8.2 No Cross-Feature Imports (Mobile)

Features must not import from other features' internal layers. Shared code goes in `core/` or `shared/`.

```
// Allowed
import 'package:livecommerce/core/network/api_client.dart';
import 'package:livecommerce/shared/widgets/app_button.dart';

// Forbidden
import 'package:livecommerce/features/auth/data/models/user_model.dart';
// from within features/feed/
```

Cross-feature data needs go through domain entities or shared models in `core/`.

### 8.3 Controller Thinness (Backend)

Controllers must not exceed ~30 lines per method. If a controller method grows complex, extract logic to a service.

### 8.4 Repository Interface Location

- Backend: interfaces in `app/Contracts/Repositories/`, implementations in `app/Repositories/Eloquent/`.
- Mobile: interfaces in `features/{name}/domain/repositories/`, implementations in `features/{name}/data/repositories/`.

### 8.5 Test Mirroring

Test files mirror the source structure:

```
app/Services/Video/VideoService.php
  → tests/Unit/Services/VideoServiceTest.php

lib/features/feed/domain/usecases/get_feed.dart
  → test/features/feed/domain/get_feed_test.dart
```

---

## 9. Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-06-27 | Founder & CTO | Initial project structure document |

---

**Related Documents:**
- [System Architecture](./02_SYSTEM_ARCHITECTURE.md)
- [Database Design](./03_DATABASE_DESIGN.md)
- [API Specification](./04_API_SPECIFICATION.md)
- [Engineering Rules](./06_ENGINEERING_RULES.md)

**Status:** Architecture Phase — Pending Review
