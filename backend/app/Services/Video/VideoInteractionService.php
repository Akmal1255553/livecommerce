<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\BookmarkRepositoryInterface;
use App\Contracts\Repositories\VideoLikeRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Repositories\VideoShareRepositoryInterface;
use App\Contracts\Services\MetricsServiceInterface;
use App\Contracts\Services\VideoInteractionServiceInterface;
use App\DTOs\Pagination\CursorPaginationData;
use App\Enums\EngagementEventType;
use App\Enums\ShareChannel;
use App\Enums\VideoStatus;
use App\Events\VideoBookmarked;
use App\Events\VideoLiked;
use App\Events\VideoShared;
use App\Events\VideoUnliked;
use App\Events\VideoViewRecorded;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class VideoInteractionService extends BaseService implements VideoInteractionServiceInterface
{
    private const VIEW_DEDUP_TTL = 86400;

    private const LIKE_LOCK_TTL = 5;

    public function __construct(
        private readonly VideoRepositoryInterface $videos,
        private readonly VideoLikeRepositoryInterface $likes,
        private readonly BookmarkRepositoryInterface $bookmarks,
        private readonly VideoShareRepositoryInterface $shares,
        private readonly MetricsServiceInterface $metrics,
    ) {}

    /**
     * @return array{liked: bool, like_count: int, created: bool}
     */
    public function like(User $user, string $videoId, string $sessionId): array
    {
        $video = $this->findPublishedVideo($videoId);
        $lockKey = "like:lock:{$videoId}:{$user->id}";

        Redis::set($lockKey, '1', 'EX', self::LIKE_LOCK_TTL, 'NX');

        try {
            if ($this->likes->exists($user->id, $videoId)) {
                return [
                    'liked' => true,
                    'like_count' => $video->fresh()->like_count,
                    'created' => false,
                ];
            }

            return DB::transaction(function () use ($user, $video, $videoId, $sessionId): array {
                if ($this->likes->exists($user->id, $videoId)) {
                    $fresh = $this->lockVideoRow($videoId);

                    return [
                        'liked' => true,
                        'like_count' => $fresh->like_count,
                        'created' => false,
                    ];
                }

                $this->likes->create($user->id, $videoId);
                $fresh = $this->lockVideoRow($videoId);
                $fresh->increment('like_count');

                event(new VideoLiked($videoId, $user->id, $video->user_id));

                $this->metrics->record($user, EngagementEventType::Like, $sessionId, $videoId);

                return [
                    'liked' => true,
                    'like_count' => $fresh->fresh()->like_count,
                    'created' => true,
                ];
            });
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function unlike(User $user, string $videoId): array
    {
        $this->findPublishedVideo($videoId);

        if (! $this->likes->exists($user->id, $videoId)) {
            throw new ResourceNotFoundException('Like not found.');
        }

        return DB::transaction(function () use ($user, $videoId): array {
            if (! $this->likes->delete($user->id, $videoId)) {
                throw new ResourceNotFoundException('Like not found.');
            }

            $video = $this->lockVideoRow($videoId);

            if ($video->like_count > 0) {
                $video->decrement('like_count');
            }

            event(new VideoUnliked($videoId, $user->id));

            return [
                'liked' => false,
                'like_count' => $video->fresh()->like_count,
            ];
        });
    }

    public function recordView(string $videoId, string $sessionId, ?User $user = null): bool
    {
        $this->findPublishedVideo($videoId);

        $dedupKey = "view:{$videoId}:{$sessionId}";
        $wasNew = (bool) Redis::set($dedupKey, '1', 'EX', self::VIEW_DEDUP_TTL, 'NX');

        if (! $wasNew) {
            return false;
        }

        Redis::incr("views:pending:{$videoId}");
        Redis::sadd('views:pending:index', $videoId);

        $this->metrics->record($user, EngagementEventType::View, $sessionId, $videoId);

        event(new VideoViewRecorded($videoId, $sessionId));

        return true;
    }

    /**
     * @return array{share_count: int}
     */
    public function share(User $user, string $videoId, ShareChannel $channel, string $sessionId): array
    {
        $video = $this->findPublishedVideo($videoId);

        return DB::transaction(function () use ($user, $video, $videoId, $channel, $sessionId): array {
            $this->shares->create($user->id, $videoId, $channel->value);

            $locked = $this->lockVideoRow($videoId);
            $locked->increment('share_count');

            $this->metrics->record(
                $user,
                EngagementEventType::Share,
                $sessionId,
                $videoId,
                ['channel' => $channel->value],
            );

            event(new VideoShared($videoId, $user->id, $channel->value));

            return [
                'share_count' => $locked->fresh()->share_count,
            ];
        });
    }

    /**
     * @return array{bookmarked: bool, created: bool}
     */
    public function bookmark(User $user, string $videoId, string $sessionId): array
    {
        $this->findPublishedVideo($videoId);

        if ($this->bookmarks->exists($user->id, $videoId)) {
            return [
                'bookmarked' => true,
                'created' => false,
            ];
        }

        $this->bookmarks->create($user->id, $videoId);

        $this->metrics->record($user, EngagementEventType::Save, $sessionId, $videoId);

        event(new VideoBookmarked($videoId, $user->id));

        return [
            'bookmarked' => true,
            'created' => true,
        ];
    }

    public function unbookmark(User $user, string $videoId): void
    {
        $this->findPublishedVideo($videoId);

        if (! $this->bookmarks->delete($user->id, $videoId)) {
            throw new ResourceNotFoundException('Bookmark not found.');
        }
    }

    /**
     * @return CursorPaginationData<Video>
     */
    public function listBookmarks(User $user, ?string $cursor, int $limit): CursorPaginationData
    {
        $cursorId = CursorPaginationData::decodeCursor($cursor);
        $bookmarks = $this->bookmarks->cursorPaginateForUser($user->id, $cursorId, $limit);
        $hasMore = $bookmarks->count() > $limit;

        if ($hasMore) {
            $bookmarks = $bookmarks->take($limit);
        }

        /** @var \Illuminate\Support\Collection<int, Video> $videos */
        $videos = $bookmarks
            ->map(fn ($bookmark) => $bookmark->video)
            ->filter(fn (?Video $video) => $video !== null && $video->status === VideoStatus::Published)
            ->values();

        $nextCursor = null;

        if ($hasMore && $bookmarks->isNotEmpty()) {
            $nextCursor = CursorPaginationData::encodeCursor($bookmarks->last()->id);
        }

        return new CursorPaginationData(
            items: $videos,
            nextCursor: $nextCursor,
            hasMore: $hasMore,
            limit: $limit,
        );
    }

    private function findPublishedVideo(string $videoId): Video
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video || $video->status !== VideoStatus::Published) {
            throw new ResourceNotFoundException('Video not found.');
        }

        return $video;
    }

    private function lockVideoRow(string $videoId): Video
    {
        /** @var Video */
        return Video::query()
            ->whereKey($videoId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
