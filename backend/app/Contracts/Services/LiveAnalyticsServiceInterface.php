<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Live\LiveSessionAnalyticsData;
use App\DTOs\Live\SellerLiveAnalyticsOverviewData;
use App\Enums\LiveAnalyticsEventType;
use App\Models\LiveAnalyticsEvent;
use App\Models\LiveSession;
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

    public function summarizeSession(string $sessionId): LiveSessionAnalyticsData;

    public function overviewForSeller(User $seller, int $limit = 20): SellerLiveAnalyticsOverviewData;

    public function assertSellerOwnsSession(User $seller, string $sessionId): LiveSession;
}
