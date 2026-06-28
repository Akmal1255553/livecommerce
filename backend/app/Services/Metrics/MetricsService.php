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
            $type = EngagementEventType::tryFrom($event['type']);

            if ($type === null) {
                throw ValidationException::withMessages([
                    "events.{$index}.type" => ['Unsupported event type.'],
                ]);
            }

            if (! Str::isUuid($event['session_id'])) {
                throw ValidationException::withMessages([
                    "events.{$index}.session_id" => ['Must be a valid UUID.'],
                ]);
            }

            $this->validateEvent($type, $event, $index);

            $this->persist(
                $user,
                $type,
                $event['session_id'],
                $event['video_id'] ?? null,
                $event['payload'] ?? [],
            );

            $accepted++;
        }

        return $accepted;
    }

    public function record(
        ?User $user,
        EngagementEventType $type,
        string $sessionId,
        ?string $videoId = null,
        array $payload = [],
    ): void {
        if (! Str::isUuid($sessionId)) {
            throw ValidationException::withMessages([
                'session_id' => ['Must be a valid UUID.'],
            ]);
        }

        $this->validateEvent($type, [
            'video_id' => $videoId,
            'payload' => $payload,
        ], 0);

        $this->persist($user, $type, $sessionId, $videoId, $payload);
    }

    /**
     * @param  array{video_id?: string|null, payload?: array<string, mixed>}  $event
     */
    private function validateEvent(EngagementEventType $type, array $event, int $index): void
    {
        if ($type->requiresVideo()) {
            $videoId = $event['video_id'] ?? null;

            if (empty($videoId) || ! Str::isUuid($videoId)) {
                throw ValidationException::withMessages([
                    $this->fieldKey($index, 'video_id') => ['Required for this event type.'],
                ]);
            }

            if (! Video::query()->whereKey($videoId)->exists()) {
                throw ValidationException::withMessages([
                    $this->fieldKey($index, 'video_id') => ['Video not found.'],
                ]);
            }
        }

        $this->validatePayload($type, $event['payload'] ?? [], $index);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(EngagementEventType $type, array $payload, int $index): void
    {
        match ($type) {
            EngagementEventType::FeedOpen => $this->requireInPayload($payload, ['tab'], $index),
            EngagementEventType::VideoImpression => $this->requireNumericPayload($payload, ['position'], $index),
            EngagementEventType::VideoStart => null,
            EngagementEventType::VideoProgress25,
            EngagementEventType::VideoProgress50,
            EngagementEventType::VideoProgress75,
            EngagementEventType::VideoProgress100 => $this->validateProgressPayload($type, $payload, $index),
            EngagementEventType::WatchTime => $this->requireNumericPayload($payload, ['seconds', 'position'], $index),
            EngagementEventType::Skip => $this->requireNumericPayload($payload, ['watched_seconds'], $index),
            EngagementEventType::FollowAfterWatch => $this->validateFollowAfterWatch($payload, $index),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateProgressPayload(EngagementEventType $type, array $payload, int $index): void
    {
        $this->requireNumericPayload($payload, ['percent', 'position'], $index);

        $expected = $type->expectedProgressPercent();

        if ($expected !== null && (int) $payload['percent'] !== $expected) {
            throw ValidationException::withMessages([
                $this->fieldKey($index, 'payload.percent') => ["Must be {$expected} for {$type->value}."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateFollowAfterWatch(array $payload, int $index): void
    {
        $creatorId = $payload['creator_id'] ?? null;

        if (! is_string($creatorId) || ! Str::isUuid($creatorId)) {
            throw ValidationException::withMessages([
                $this->fieldKey($index, 'payload.creator_id') => ['Must be a valid creator UUID.'],
            ]);
        }

        if (! User::query()->whereKey($creatorId)->exists()) {
            throw ValidationException::withMessages([
                $this->fieldKey($index, 'payload.creator_id') => ['Creator not found.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function requireInPayload(array $payload, array $keys, int $index): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === '') {
                throw ValidationException::withMessages([
                    $this->fieldKey($index, "payload.{$key}") => ["The {$key} field is required."],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function requireNumericPayload(array $payload, array $keys, int $index): void
    {
        foreach ($keys as $key) {
            if (! isset($payload[$key]) || ! is_numeric($payload[$key])) {
                throw ValidationException::withMessages([
                    $this->fieldKey($index, "payload.{$key}") => ["The {$key} field is required and must be numeric."],
                ]);
            }
        }
    }

    private function fieldKey(int $index, string $field): string
    {
        return $index === 0 ? $field : "events.{$index}.{$field}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persist(
        ?User $user,
        EngagementEventType $type,
        string $sessionId,
        ?string $videoId,
        array $payload,
    ): void {
        EngagementEvent::query()->create([
            'event_type' => $type,
            'user_id' => $user?->id,
            'video_id' => $videoId,
            'session_id' => $sessionId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
