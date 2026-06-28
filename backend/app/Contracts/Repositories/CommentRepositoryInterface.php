<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function paginateTopLevel(string $videoId, int $page, int $perPage): LengthAwarePaginator;

    public function create(array $attributes): Comment;

    public function findForVideo(int $commentId, string $videoId): ?Comment;

    public function softDelete(Comment $comment): void;
}
