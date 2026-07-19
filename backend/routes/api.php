<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookmarkController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DiscoverController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LiveAnalyticsController;
use App\Http\Controllers\Api\V1\LiveAssistantController;
use App\Http\Controllers\Api\V1\LiveSessionController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ClickWebhookController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PaymeWebhookController;
use App\Http\Controllers\Api\V1\SandboxPaymentController;
use App\Http\Controllers\Api\V1\UzumWebhookController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use App\Http\Controllers\Api\V1\SellerStoreController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VideoCommentController;
use App\Http\Controllers\Api\V1\VideoController;
use App\Http\Controllers\Api\V1\VideoInteractionController;
use App\Http\Controllers\Api\V1\VideoProductController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminStoreController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminVideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::post('webhooks/payment', PaymentWebhookController::class)
        ->middleware('throttle:60,1');
    Route::post('webhooks/click', ClickWebhookController::class)
        ->middleware('throttle:60,1');
    Route::post('webhooks/payme', PaymeWebhookController::class)
        ->middleware('throttle:60,1');
    Route::post('webhooks/uzum', UzumWebhookController::class)
        ->middleware('throttle:60,1');

    Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth.api')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth.api')->group(function (): void {
        Route::get('me', [UserController::class, 'show']);
        Route::put('me', [UserController::class, 'update']);
        Route::put('me/notification-settings', [UserController::class, 'updateNotificationSettings']);
        Route::put('me/password', [UserController::class, 'changePassword']);
        Route::delete('me', [UserController::class, 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);

        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{token}', [DeviceController::class, 'destroy']);

        Route::post('users/{id}/follow', [UserController::class, 'follow']);
        Route::delete('users/{id}/follow', [UserController::class, 'unfollow']);
        Route::post('users/{id}/block', [UserController::class, 'block']);
        Route::delete('users/{id}/block', [UserController::class, 'unblock']);

        Route::get('conversations/unread-count', [ConversationController::class, 'unreadCount']);
        Route::get('conversations', [ConversationController::class, 'index']);
        Route::post('conversations', [ConversationController::class, 'store']);
        Route::get('conversations/{id}/messages', [ConversationController::class, 'messages']);
        Route::post('conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
        Route::put('conversations/{id}/read', [ConversationController::class, 'markRead']);

        Route::get('feed/following', [FeedController::class, 'following']);

        Route::post('videos', [VideoController::class, 'store']);
        Route::post('videos/{id}/confirm-upload', [VideoController::class, 'confirmUpload']);
        Route::put('videos/{id}', [VideoController::class, 'update']);
        Route::delete('videos/{id}', [VideoController::class, 'destroy']);
        Route::put('videos/{id}/products', [VideoProductController::class, 'sync']);

        Route::post('videos/{id}/like', [VideoInteractionController::class, 'like']);
        Route::delete('videos/{id}/like', [VideoInteractionController::class, 'unlike']);
        Route::post('videos/{id}/bookmark', [VideoInteractionController::class, 'bookmark']);
        Route::delete('videos/{id}/bookmark', [VideoInteractionController::class, 'unbookmark']);
        Route::post('videos/{id}/share', [VideoInteractionController::class, 'share']);
        Route::get('bookmarks', [BookmarkController::class, 'index']);

        Route::post('videos/{id}/comments', [VideoCommentController::class, 'store']);
        Route::delete('videos/{id}/comments/{commentId}', [VideoCommentController::class, 'destroy']);

        Route::post('media/presigned-url', [MediaController::class, 'presignedUrl']);

        Route::post('seller/apply', [SellerStoreController::class, 'apply']);

        Route::post('reports', [ReportController::class, 'store']);

        Route::prefix('admin')->middleware('role:admin,moderator')->group(function (): void {
            Route::get('videos/pending', [AdminVideoController::class, 'pending']);
            Route::put('videos/{id}/approve', [AdminVideoController::class, 'approve']);
            Route::put('videos/{id}/reject', [AdminVideoController::class, 'reject']);
            Route::put('videos/{id}/hide', [AdminVideoController::class, 'hide']);
            Route::get('reports', [AdminReportController::class, 'index']);
            Route::put('reports/{id}/resolve', [AdminReportController::class, 'resolve']);
            Route::put('reports/{id}/dismiss', [AdminReportController::class, 'dismiss']);
        });

        Route::prefix('admin')->middleware('role:admin')->group(function (): void {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::put('users/{id}/suspend', [AdminUserController::class, 'suspend']);
            Route::put('users/{id}/ban', [AdminUserController::class, 'ban']);
            Route::put('users/{id}/activate', [AdminUserController::class, 'activate']);
            Route::get('stores/pending', [AdminStoreController::class, 'pending']);
            Route::put('stores/{id}/approve', [AdminStoreController::class, 'approve']);
            Route::put('stores/{id}/reject', [AdminStoreController::class, 'reject']);
            Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
            Route::post('categories', [AdminCategoryController::class, 'store']);
            Route::put('categories/{id}', [AdminCategoryController::class, 'update']);
            Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy']);
        });

        Route::middleware('seller')->group(function (): void {
            Route::get('seller/dashboard', [SellerStoreController::class, 'dashboard']);
            Route::get('seller/analytics/summary', [SellerStoreController::class, 'analyticsSummary']);
            Route::get('seller/live/analytics', [LiveAnalyticsController::class, 'overview']);
            Route::get('seller/live/{id}/analytics', [LiveAnalyticsController::class, 'show']);

            Route::post('products', [ProductController::class, 'store']);
            Route::put('products/{id}', [ProductController::class, 'update']);
            Route::delete('products/{id}', [ProductController::class, 'destroy']);

            Route::get('seller/orders', [SellerOrderController::class, 'index']);
            Route::get('seller/orders/{id}', [SellerOrderController::class, 'show']);
            Route::put('seller/orders/{id}/status', [SellerOrderController::class, 'updateStatus']);
            Route::put('seller/refunds/{id}', [SellerOrderController::class, 'resolveRefund']);

            Route::post('live/start', [LiveSessionController::class, 'start']);
            Route::post('live/{id}/end', [LiveSessionController::class, 'end']);
            Route::post('live/{id}/pin-product', [LiveSessionController::class, 'pinProduct']);
            Route::delete('live/{id}/pin-product/{productId}', [LiveSessionController::class, 'unpinProduct']);
            Route::get('live/{id}/assistant/suggestions', [LiveAssistantController::class, 'suggestions']);
        });

        Route::post('live/{id}/chat', [LiveSessionController::class, 'chatStore'])
            ->middleware('throttle:live-chat');
        Route::post('live/{id}/join', [LiveSessionController::class, 'join']);
        Route::post('live/{id}/leave', [LiveSessionController::class, 'leave']);
        Route::post('live/{id}/add-to-cart', [LiveSessionController::class, 'addToCart']);

        Route::post('checkout', [CheckoutController::class, 'store'])
            ->middleware('throttle:checkout');
        Route::post('payments/sandbox/{id}/complete', [SandboxPaymentController::class, 'complete']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{id}/refund', [OrderController::class, 'requestRefund']);
        Route::get('refunds/{id}', [OrderController::class, 'showRefund']);
    });

    Route::middleware('auth.api.optional')->group(function (): void {
        Route::get('discover', [DiscoverController::class, 'index']);
        Route::get('feed/trending', [FeedController::class, 'trending']);
        Route::get('feed/popular', [FeedController::class, 'popular']);
        Route::get('feed/new', [FeedController::class, 'newFeed']);
        Route::get('feed/for-you', [FeedController::class, 'forYou']);
        Route::get('live', [LiveSessionController::class, 'index']);
        Route::get('live/replays', [LiveSessionController::class, 'replays']);
        Route::get('live/{id}', [LiveSessionController::class, 'show']);
        Route::get('live/{id}/chat', [LiveSessionController::class, 'chatIndex'])
            ->middleware('throttle:live-poll');
        Route::get('videos/{id}', [VideoController::class, 'show']);
        Route::get('videos/{id}/products', [VideoProductController::class, 'index']);
        Route::post('videos/{id}/view', [VideoInteractionController::class, 'view']);
        Route::get('videos/{id}/comments', [VideoCommentController::class, 'index']);
        Route::post('metrics/events', [MetricsController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'showPublic']);
        Route::get('users/{id}/followers', [UserController::class, 'followers']);
        Route::get('users/{id}/following', [UserController::class, 'following']);

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/search', [ProductController::class, 'search'])
            ->middleware('throttle:search');
        Route::get('products/{id}', [ProductController::class, 'show']);
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{id}/products', [CategoryController::class, 'products']);
        Route::get('brands', [BrandController::class, 'index']);

        Route::get('stores/{slug}', [StoreController::class, 'show']);
        Route::get('stores/{slug}/products', [StoreController::class, 'products']);

        Route::post('cart/guest', [CartController::class, 'createGuest']);
        Route::get('cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'storeItem']);
        Route::put('cart/items/{id}', [CartController::class, 'updateItem']);
        Route::delete('cart/items/{id}', [CartController::class, 'destroyItem']);
        Route::delete('cart', [CartController::class, 'clear']);
    });
});
