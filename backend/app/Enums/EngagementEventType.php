<?php

declare(strict_types=1);

namespace App\Enums;

enum EngagementEventType: string
{
    // Discovery funnel (client → POST /metrics/events)
    case FeedOpen = 'feed_open';
    case VideoImpression = 'video_impression';

    // Playback funnel (client → POST /metrics/events)
    case VideoStart = 'video_start';
    case VideoProgress25 = 'video_progress_25';
    case VideoProgress50 = 'video_progress_50';
    case VideoProgress75 = 'video_progress_75';
    case VideoProgress100 = 'video_progress_100';
    case WatchTime = 'watch_time';
    case Skip = 'skip';

    // Interaction signals (API action and/or client metrics)
    case View = 'view';
    case Like = 'like';
    case Comment = 'comment';
    case Share = 'share';
    case Save = 'save';
    case FollowAfterWatch = 'follow_after_watch';

    /**
     * Ordered funnel for recommendation scoring (Sprint 3.4 / 9).
     *
     * @return list<self>
     */
    public static function funnel(): array
    {
        return [
            self::FeedOpen,
            self::VideoImpression,
            self::VideoStart,
            self::VideoProgress25,
            self::VideoProgress50,
            self::VideoProgress75,
            self::VideoProgress100,
            self::WatchTime,
            self::Like,
            self::Comment,
            self::Share,
            self::Save,
            self::FollowAfterWatch,
        ];
    }

    /**
     * @return list<self>
     */
    public static function clientPlaybackEvents(): array
    {
        return [
            self::VideoStart,
            self::VideoProgress25,
            self::VideoProgress50,
            self::VideoProgress75,
            self::VideoProgress100,
            self::WatchTime,
            self::Skip,
            self::FollowAfterWatch,
        ];
    }

    /**
     * @return list<self>
     */
    public static function interactionEvents(): array
    {
        return [
            self::View,
            self::Like,
            self::Comment,
            self::Share,
            self::Save,
        ];
    }

    public function requiresVideo(): bool
    {
        return $this !== self::FeedOpen;
    }

    public function isProgressMilestone(): bool
    {
        return in_array($this, [
            self::VideoProgress25,
            self::VideoProgress50,
            self::VideoProgress75,
            self::VideoProgress100,
        ], true);
    }

    public function expectedProgressPercent(): ?int
    {
        return match ($this) {
            self::VideoProgress25 => 25,
            self::VideoProgress50 => 50,
            self::VideoProgress75 => 75,
            self::VideoProgress100 => 100,
            default => null,
        };
    }
}
