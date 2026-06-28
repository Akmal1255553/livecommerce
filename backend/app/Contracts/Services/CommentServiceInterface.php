<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CommentServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function listForVideo(string $videoId, int $page, int $perPage): LengthAwarePaginator;

    public function create(
        User $user,
        string $videoId,
        string $body,
        string $sessionId,
        ?int $parentId = null,
    ): Comment;

    public function deleteOwn(User $user, string $videoId, int $commentId): void;
}
