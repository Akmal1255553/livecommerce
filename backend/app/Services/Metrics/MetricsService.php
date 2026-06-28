<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Contracts\Services\MetricsServiceInterface;
use App\Enums\EngagementEventType;
use App\Models\EngagementEvent;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MetricsService extends BaseService implements MetricsServiceInterface
{
    /**
     * @param  list<array{type: string, session_id: string, video_id?: string|null, payload?: array<string, mixed>}>  $events
     */
    public function recordEvents(?User $user, array $events): int
    {
        $accepted = 0;

        foreach ($events as $index => $event) {
            $type = EngagementEventType::tryFrom($event['type'] ?? '');

            if ($type === null) {
                throw ValidationException::withMessages([
                    "events.{$index}.type" => ['Unsupported event type.'],
                ]);
            }

            if (! Str::isUuid($event['session_id'] ?? '')) {
                throw ValidationException::withMessages([
                    "events.{$index}.session_id" => ['Must be a valid UUID.'],
                ]);
            }

            if ($type === EngagementEventType::VideoImpression) {
                if (empty($event['video_id']) || ! Str::isUuid($event['video_id'])) {
                    throw ValidationException::withMessages([
                        "events.{$index}.video_id" => ['Required for video_impression events.'],
                    ]);
                }

                if (! Video::query()->whereKey($event['video_id'])->exists()) {
                    throw ValidationException::withMessages([
                        "events.{$index}.video_id" => ['Video not found.'],
                    ]);
                }
            }

            EngagementEvent::query()->create([
                'event_type' => $type,
                'user_id' => $user?->id,
                'video_id' => $event['video_id'] ?? null,
                'session_id' => $event['session_id'],
                'payload' => $event['payload'] ?? [],
                'created_at' => now(),
            ]);

            $accepted++;
        }

        return $accepted;
    }
}
