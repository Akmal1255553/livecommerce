<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Contracts\Repositories\LiveStreamRepositoryInterface;
use App\Contracts\Repositories\MediaUploadRepositoryInterface;
use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\RefreshTokenRepositoryInterface;
use App\Contracts\Repositories\StoreRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Repositories\UserDeviceRepositoryInterface;
use App\Contracts\Services\HealthServiceInterface;
use App\Contracts\Services\PushNotificationInterface;
use App\Contracts\Services\SmsProviderInterface;
use App\Contracts\Services\MediaServiceInterface;
use App\Contracts\Services\MediaAssetServiceInterface;
use App\Contracts\Services\MetricsServiceInterface;
use App\Contracts\Services\StorageServiceInterface;
use App\Contracts\Services\VideoStateMachineInterface;
use App\Contracts\Services\VideoUploadServiceInterface;
use App\Contracts\VideoProcessing\FfmpegTranscoderInterface;
use App\Events\UserRegistered;
use App\Events\VideoUploadConfirmed;
use App\Listeners\CreateUserProfile;
use App\Listeners\DispatchVideoProcessingPipeline;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Eloquent\FollowRepository;
use App\Repositories\Eloquent\LiveStreamRepository;
use App\Repositories\Eloquent\MediaUploadRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\RefreshTokenRepository;
use App\Repositories\Eloquent\StoreRepository;
use App\Repositories\Eloquent\UserDeviceRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VideoRepository;
use App\Services\Auth\StubSmsProvider;
use App\Services\Health\HealthService;
use App\Services\Media\MediaAssetService;
use App\Services\Media\MediaService;
use App\Services\Metrics\MetricsService;
use App\Services\Storage\StorageService;
use App\Services\Video\Ffmpeg\CliFfmpegTranscoder;
use App\Services\Video\Ffmpeg\FakeFfmpegTranscoder;
use App\Services\Video\VideoStateMachine;
use App\Services\Video\VideoUploadService;
use App\Storage\Drivers\LocalStorageDriver;
use App\Storage\Drivers\S3StorageDriver;
use App\Services\Notification\StubFcmPushNotification;
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
        OrderRepositoryInterface::class => OrderRepository::class,
        CartRepositoryInterface::class => CartRepository::class,
        StoreRepositoryInterface::class => StoreRepository::class,
        LiveStreamRepositoryInterface::class => LiveStreamRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        UserDeviceRepositoryInterface::class => UserDeviceRepository::class,
        FollowRepositoryInterface::class => FollowRepository::class,
        MediaUploadRepositoryInterface::class => MediaUploadRepository::class,
        HealthServiceInterface::class => HealthService::class,
        StorageServiceInterface::class => StorageService::class,
        MediaServiceInterface::class => MediaService::class,
        VideoUploadServiceInterface::class => VideoUploadService::class,
        MetricsServiceInterface::class => MetricsService::class,
        MediaAssetServiceInterface::class => MediaAssetService::class,
        VideoStateMachineInterface::class => VideoStateMachine::class,
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

        $this->app->singleton(FfmpegTranscoderInterface::class, function ($app): FfmpegTranscoderInterface {
            if ($app->environment('testing')) {
                return new FakeFfmpegTranscoder;
            }

            return new CliFfmpegTranscoder(
                (string) config('video.ffmpeg_path', 'ffmpeg'),
                (string) config('video.ffprobe_path', 'ffprobe'),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(UserRegistered::class, CreateUserProfile::class);
        Event::listen(VideoUploadConfirmed::class, DispatchVideoProcessingPipeline::class);
    }
}
