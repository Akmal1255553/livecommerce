<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\LiveAnalyticsEventType;
use App\Models\LiveAnalyticsEvent;
use App\Models\User;

interface LiveAnalyticsServiceInterface
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function record(
        string $liveSessionId,
        LiveAnalyticsEventType $type,
        ?User $user = null,
        ?array $payload = null,
    ): LiveAnalyticsEvent;
}
