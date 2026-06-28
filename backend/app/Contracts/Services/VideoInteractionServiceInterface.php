<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Pagination\CursorPaginationData;
use App\Enums\ShareChannel;
use App\Models\User;
use App\Models\Video;

interface VideoInteractionServiceInterface
{
    /**
     * @return array{liked: bool, like_count: int, created: bool}
     */
    public function like(User $user, string $videoId, string $sessionId): array;

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function unlike(User $user, string $videoId): array;

    public function recordView(string $videoId, string $sessionId, ?User $user = null): bool;

    /**
     * @return array{share_count: int}
     */
    public function share(User $user, string $videoId, ShareChannel $channel, string $sessionId): array;

    /**
     * @return array{bookmarked: bool, created: bool}
     */
    public function bookmark(User $user, string $videoId, string $sessionId): array;

    public function unbookmark(User $user, string $videoId): void;

    /**
     * @return CursorPaginationData<Video>
     */
    public function listBookmarks(User $user, ?string $cursor, int $limit): CursorPaginationData;
}
