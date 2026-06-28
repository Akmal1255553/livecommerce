<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CommentRepositoryInterface;
use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentRepository extends BaseEloquentRepository implements CommentRepositoryInterface
{
    public function __construct(Comment $model)
    {
        parent::__construct($model);
    }

    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function paginateTopLevel(string $videoId, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('video_id', $videoId)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Comment
    {
        /** @var Comment */
        return $this->model->newQuery()->create($attributes);
    }

    public function findForVideo(int $commentId, string $videoId): ?Comment
    {
        return $this->model->newQuery()
            ->whereKey($commentId)
            ->where('video_id', $videoId)
            ->first();
    }

    public function softDelete(Comment $comment): void
    {
        $comment->delete();
    }
}
