<?php

declare(strict_types=1);

namespace App\Services\LiveSession;

use App\Contracts\Services\LiveAnalyticsServiceInterface;
use App\Enums\LiveAnalyticsEventType;
use App\Logging\StructuredLogger;
use App\Models\LiveAnalyticsEvent;
use App\Models\User;
use App\Services\BaseService;

class LiveAnalyticsService extends BaseService implements LiveAnalyticsServiceInterface
{
    public function __construct(StructuredLogger $logger)
    {
        parent::__construct($logger);
    }

    public function record(
        string $liveSessionId,
        LiveAnalyticsEventType $type,
        ?User $user = null,
        ?array $payload = null,
    ): LiveAnalyticsEvent {
        $event = LiveAnalyticsEvent::query()->create([
            'live_session_id' => $liveSessionId,
            'user_id' => $user?->id,
            'event_type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        $this->logger->info('live.analytics', [
            'live_session_id' => $liveSessionId,
            'event_type' => $type->value,
            'user_id' => $user?->id,
        ]);

        return $event;
    }
}
