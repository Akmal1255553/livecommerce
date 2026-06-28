<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Contracts\Repositories\CommentRepositoryInterface;
use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Contracts\Services\CommentServiceInterface;
use App\Contracts\Services\MetricsServiceInterface;
use App\Enums\EngagementEventType;
use App\Enums\VideoStatus;
use App\Events\CommentCreated;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\Comment;
use App\Models\User;
use App\Models\Video;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentService extends BaseService implements CommentServiceInterface
{
    public function __construct(
        private readonly CommentRepositoryInterface $comments,
        private readonly VideoRepositoryInterface $videos,
        private readonly MetricsServiceInterface $metrics,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function listForVideo(string $videoId, int $page, int $perPage): LengthAwarePaginator
    {
        $this->ensurePublishedVideo($videoId);

        return $this->comments->paginateTopLevel($videoId, $page, $perPage);
    }

    public function create(
        User $user,
        string $videoId,
        string $body,
        string $sessionId,
        ?int $parentId = null,
    ): Comment {
        $video = $this->ensurePublishedVideo($videoId);

        if ($parentId !== null) {
            $parent = $this->comments->findForVideo($parentId, $videoId);

            if ($parent === null || $parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Replies are only allowed on top-level comments.'],
                ]);
            }
        }

        return DB::transaction(function () use ($user, $video, $videoId, $body, $sessionId, $parentId): Comment {
            $comment = $this->comments->create([
                'user_id' => $user->id,
                'video_id' => $videoId,
                'parent_id' => $parentId,
                'body' => $body,
            ]);

            $locked = Video::query()
                ->whereKey($videoId)
                ->lockForUpdate()
                ->firstOrFail();
            $locked->increment('comment_count');

            $this->metrics->record(
                $user,
                EngagementEventType::Comment,
                $sessionId,
                $videoId,
                ['comment_id' => $comment->id],
            );

            event(new CommentCreated($comment->id, $videoId, $user->id, $video->user_id));

            return $comment->load(['user', 'replies.user']);
        });
    }

    public function deleteOwn(User $user, string $videoId, int $commentId): void
    {
        $this->ensurePublishedVideo($videoId);

        $comment = $this->comments->findForVideo($commentId, $videoId);

        if ($comment === null) {
            throw new ResourceNotFoundException('Comment not found.');
        }

        if ($comment->user_id !== $user->id) {
            throw new ForbiddenException('You cannot delete this comment.');
        }

        DB::transaction(function () use ($comment, $videoId): void {
            $this->comments->softDelete($comment);

            $locked = Video::query()
                ->whereKey($videoId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->comment_count > 0) {
                $locked->decrement('comment_count');
            }
        });
    }

    private function ensurePublishedVideo(string $videoId): Video
    {
        $video = $this->videos->findById($videoId);

        if (! $video instanceof Video || $video->status !== VideoStatus::Published) {
            throw new ResourceNotFoundException('Video not found.');
        }

        return $video;
    }
}
