<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Recommendation\EngagementEventRepositoryInterface;
use App\Contracts\Recommendation\RecommendationServiceInterface;
use App\Contracts\Recommendation\VideoEngagementRollupRepositoryInterface;
use App\Contracts\Repositories\BookmarkRepositoryInterface;
use App\Contracts\Repositories\BlockRepositoryInterface;
use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\CommentRepositoryInterface;
use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Contracts\Repositories\LiveSessionRepositoryInterface;
use App\Contracts\Repositories\LiveStreamRepositoryInterface;
use App\Contracts\Repositories\MediaUploadRepositoryInterface;
use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Repositories\UserDeviceRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VideoLikeRepositoryInterface;
use App\Contracts\Repositories\VideoProductRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Repositories\VideoShareRepositoryInterface;
use App\Contracts\Services\CartServiceInterface;
use App\Contracts\Services\CheckoutServiceInterface;
use App\Contracts\Services\CommentServiceInterface;
use App\Contracts\Services\CouponServiceInterface;
use App\Contracts\Services\HealthServiceInterface;
use App\Contracts\Services\InventoryServiceInterface;
use App\Contracts\Services\LiveAnalyticsServiceInterface;
use App\Contracts\Services\LiveAssistantServiceInterface;
use App\Contracts\Services\LiveSessionServiceInterface;
use App\Contracts\Services\MediaAssetServiceInterface;
use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\MessagingServiceInterface;
use App\Contracts\Services\MetricsServiceInterface;
use App\Contracts\Services\OrderNumberGeneratorInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Contracts\Services\OrderStateMachineInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PricingServiceInterface;
use App\Contracts\Services\PushNotificationInterface;
use App\Contracts\Services\ShippingCalculatorInterface;
use App\Contracts\Services\SmsProviderInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\StoreServiceInterface;
use App\Contracts\Services\StreamingProviderInterface;
use App\Contracts\Services\TaxServiceInterface;
use App\Contracts\Services\VideoCommerceServiceInterface;
use App\Contracts\Services\VideoInteractionServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\Services\VideoUploadServiceInterface;
use App\Contracts\Services\ViewerMetricsServiceInterface;
use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\Events\CommentCreated;
use App\Events\LiveSessionStarted;
use App\Events\MessageSent;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\RefundCompleted;
use App\Events\RefundRequested;
use App\Events\UserAuthenticated;
use App\Events\UserRegistered;
use App\Events\VideoLiked;
use App\Events\VideoUploadConfirmed;
use App\Listeners\CreateUserProfile;
use App\Listeners\DispatchVideoProcessingPipeline;
use App\Listeners\MergeGuestCartOnLogin;
use App\Listeners\NotifyOnComment;
use App\Listeners\NotifyOnLiveStarted;
use App\Listeners\NotifyOnMessageSent;
use App\Listeners\NotifyOnVideoLiked;
use App\Listeners\RecordOrderAnalytics;
use App\Repositories\Eloquent\BookmarkRepository;
use App\Repositories\Eloquent\BlockRepository;
use App\Repositories\Eloquent\BrandRepository;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\CommentRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\EngagementEventRepository;
use App\Repositories\Eloquent\FollowRepository;
use App\Repositories\Eloquent\LiveSessionRepository;
use App\Repositories\Eloquent\LiveStreamRepository;
use App\Repositories\Eloquent\MediaUploadRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\RefreshTokenRepository;
use App\Repositories\Eloquent\StoreRepository;
use App\Repositories\Eloquent\UserDeviceRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VideoEngagementRollupRepository;
use App\Repositories\Eloquent\VideoLikeRepository;
use App\Repositories\Eloquent\VideoProductRepository;
use App\Repositories\Eloquent\VideoRepository;
use App\Repositories\Eloquent\VideoShareRepository;
use App\Services\Auth\StubSmsProvider;
use App\Services\Cart\CartService;
use App\Services\Cart\GuestCartStore;
use App\Services\Checkout\CheckoutService;
use App\Services\Coupon\NoDiscountCouponService;
use App\Services\Health\HealthService;
use App\Services\Inventory\ProductInventoryService;
use App\Services\LiveSession\LiveAnalyticsService;
use App\Services\LiveSession\LiveAssistantService;
use App\Services\LiveSession\LiveSessionService;
use App\Services\LiveSession\ViewerMetricsService;
use App\Services\Media\MediaAssetService;
use App\Services\Media\MediaService;
use App\Services\Messaging\MessagingService;
use App\Services\Metrics\MetricsService;
use App\Services\Notification\StubFcmPushNotification;
use App\Services\Order\DateSequenceOrderNumberGenerator;
use App\Services\Order\OrderService;
use App\Services\Order\OrderStateMachine;
use App\Services\Order\ShortCodeOrderNumberGenerator;
use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\Listeners\ReleaseInventoryOnOrderCancelled;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\LocalPaymentGateway;
use App\Services\Payment\PaymentWebhookProcessor;
use App\Services\Pricing\ProductPricingService;
use App\Services\Recommendation\RecommendationService;
use App\Services\Shipping\FixedShippingCalculator;
use App\Services\Storage\StorageService;
use App\Services\Store\StoreService;
use App\Services\Streaming\AgoraProvider;
use App\Services\Streaming\FakeStreamingProvider;
use App\Services\Tax\ZeroTaxService;
use App\Services\Video\CommentService;
use App\Services\Video\Ffmpeg\CliFfmpegTranscoder;
use App\Services\Video\Ffmpeg\FakeFfmpegTranscoder;
use App\Services\Video\VideoCommerceService;
use App\Services\Video\VideoInteractionService;
use App\Services\Video\VideoStateMachine;
use App\Services\Video\VideoUploadService;
use App\Storage\Drivers\LocalStorageDriver;
use App\Storage\Drivers\S3StorageDriver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        RefreshTokenRepositoryInterface::class => RefreshTokenRepository::class,
        VideoRepositoryInterface::class => VideoRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        BrandRepositoryInterface::class => BrandRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        CartRepositoryInterface::class => CartRepository::class,
        StoreRepositoryInterface::class => StoreRepository::class,
        LiveStreamRepositoryInterface::class => LiveStreamRepository::class,
        LiveSessionRepositoryInterface::class => LiveSessionRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        UserDeviceRepositoryInterface::class => UserDeviceRepository::class,
        FollowRepositoryInterface::class => FollowRepository::class,
        BlockRepositoryInterface::class => BlockRepository::class,
        ConversationRepositoryInterface::class => ConversationRepository::class,
        MessageRepositoryInterface::class => MessageRepository::class,
        VideoLikeRepositoryInterface::class => VideoLikeRepository::class,
        CommentRepositoryInterface::class => CommentRepository::class,
        BookmarkRepositoryInterface::class => BookmarkRepository::class,
        VideoShareRepositoryInterface::class => VideoShareRepository::class,
        VideoProductRepositoryInterface::class => VideoProductRepository::class,
        MediaUploadRepositoryInterface::class => MediaUploadRepository::class,
        VideoEngagementRollupRepositoryInterface::class => VideoEngagementRollupRepository::class,
        EngagementEventRepositoryInterface::class => EngagementEventRepository::class,
        HealthServiceInterface::class => HealthService::class,
        StorageServiceInterface::class => StorageService::class,
        StoreServiceInterface::class => StoreService::class,
        LiveSessionServiceInterface::class => LiveSessionService::class,
        ViewerMetricsServiceInterface::class => ViewerMetricsService::class,
        LiveAnalyticsServiceInterface::class => LiveAnalyticsService::class,
        LiveAssistantServiceInterface::class => LiveAssistantService::class,
        MediaServiceInterface::class => MediaService::class,
        MessagingServiceInterface::class => MessagingService::class,
        VideoUploadServiceInterface::class => VideoUploadService::class,
        VideoInteractionServiceInterface::class => VideoInteractionService::class,
        VideoCommerceServiceInterface::class => VideoCommerceService::class,
        CheckoutServiceInterface::class => CheckoutService::class,
        CartServiceInterface::class => CartService::class,
        InventoryServiceInterface::class => ProductInventoryService::class,
        CouponServiceInterface::class => NoDiscountCouponService::class,
        ShippingCalculatorInterface::class => FixedShippingCalculator::class,
        PricingServiceInterface::class => ProductPricingService::class,
        OrderServiceInterface::class => OrderService::class,
        OrderStateMachineInterface::class => OrderStateMachine::class,
        TaxServiceInterface::class => ZeroTaxService::class,
        CommentServiceInterface::class => CommentService::class,
        MetricsServiceInterface::class => MetricsService::class,
        MediaAssetServiceInterface::class => MediaAssetService::class,
        VideoStateMachineInterface::class => VideoStateMachine::class,
        RecommendationServiceInterface::class => RecommendationService::class,
        SmsProviderInterface::class => StubSmsProvider::class,
        PushNotificationInterface::class => StubFcmPushNotification::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }

        $this->app->singleton(LocalStorageDriver::class);
        $this->app->singleton(S3StorageDriver::class);

        $this->app->singleton(GuestCartStore::class);

        $this->app->singleton(OrderNumberGeneratorInterface::class, function ($app): OrderNumberGeneratorInterface {
            $strategy = (string) config('commerce.order_number.strategy', 'date_sequence');

            return $strategy === 'short_code'
                ? $app->make(ShortCodeOrderNumberGenerator::class)
                : $app->make(DateSequenceOrderNumberGenerator::class);
        });

        $this->app->singleton(FfmpegTranscoderInterface::class, function ($app): FfmpegTranscoderInterface {
            if ($app->environment('testing')) {
                return new FakeFfmpegTranscoder;
            }

            return new CliFfmpegTranscoder(
                (string) config('video.ffmpeg_path', 'ffmpeg'),
                (string) config('video.ffprobe_path', 'ffprobe'),
            );
        });

        $this->app->singleton(StreamingProviderInterface::class, function ($app): StreamingProviderInterface {
            $provider = (string) config('streaming.provider', 'fake');

            if ($provider === 'agora') {
                return new AgoraProvider(
                    (string) config('streaming.agora.app_id', ''),
                    (string) config('streaming.agora.app_certificate', ''),
                );
            }

            return $app->make(FakeStreamingProvider::class);
        });

        $this->app->singleton(PaymentGatewayInterface::class, function ($app): PaymentGatewayInterface {
            $driver = (string) config('payment.driver', 'fake');

            if ($app->environment('production') && $driver === 'fake') {
                throw new \RuntimeException(
                    'PAYMENT_GATEWAY=fake is not allowed in production. Use local|click|payme|uzum.',
                );
            }

            return match ($driver) {
                'local', 'click', 'payme', 'uzum' => $app->make(LocalPaymentGateway::class),
                default => $app->make(FakePaymentGateway::class),
            };
        });

        $this->app->singleton(
            PaymentWebhookProcessorInterface::class,
            PaymentWebhookProcessor::class,
        );
    }

    public function boot(): void
    {
        Event::listen(UserRegistered::class, CreateUserProfile::class);
        Event::listen(UserAuthenticated::class, MergeGuestCartOnLogin::class);
        Event::listen(VideoUploadConfirmed::class, DispatchVideoProcessingPipeline::class);
        Event::listen(VideoLiked::class, NotifyOnVideoLiked::class);
        Event::listen(CommentCreated::class, NotifyOnComment::class);
        Event::listen(LiveSessionStarted::class, NotifyOnLiveStarted::class);
        Event::listen(MessageSent::class, NotifyOnMessageSent::class);
        Event::listen(OrderCreated::class, [RecordOrderAnalytics::class, 'handleOrderCreated']);
        Event::listen(OrderPaid::class, [RecordOrderAnalytics::class, 'handleOrderPaid']);
        Event::listen(OrderCancelled::class, [RecordOrderAnalytics::class, 'handleOrderCancelled']);
        Event::listen(OrderCancelled::class, ReleaseInventoryOnOrderCancelled::class);
        Event::listen(RefundRequested::class, [RecordOrderAnalytics::class, 'handleRefundRequested']);
        Event::listen(RefundCompleted::class, [RecordOrderAnalytics::class, 'handleRefundCompleted']);
    }
}
