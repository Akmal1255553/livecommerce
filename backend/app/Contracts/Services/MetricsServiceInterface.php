<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\EngagementEventType;
use App\Models\User;

interface MetricsServiceInterface
{
    /**
     * @param  list<array{type: string, session_id: string, video_id?: string|null, payload?: array<string, mixed>}>  $events
     */
    public function recordEvents(?User $user, array $events): int;

    /**
     * Record a single analytics event (interaction APIs and internal services).
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        ?User $user,
        EngagementEventType $type,
        string $sessionId,
        ?string $videoId = null,
        array $payload = [],
    ): void;
}
