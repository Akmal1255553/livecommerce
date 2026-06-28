<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BookmarkRepositoryInterface;
use App\Models\Bookmark;
use Illuminate\Support\Collection;

class BookmarkRepository extends BaseEloquentRepository implements BookmarkRepositoryInterface
{
    public function __construct(Bookmark $model)
    {
        parent::__construct($model);
    }

    public function exists(string $userId, string $videoId): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('video_id', $videoId)
            ->exists();
    }

    public function create(string $userId, string $videoId): Bookmark
    {
        /** @var Bookmark */
        return $this->model->newQuery()->create([
            'user_id' => $userId,
            'video_id' => $videoId,
            'created_at' => now(),
        ]);
    }

    public function delete(string $userId, string $videoId): bool
    {
        return (bool) $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('video_id', $videoId)
            ->delete();
    }

    /**
     * @param  list<string>  $videoIds
     * @return list<string>
     */
    public function bookmarkedVideoIdsForUser(string $userId, array $videoIds): array
    {
        if ($videoIds === []) {
            return [];
        }

        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereIn('video_id', $videoIds)
            ->pluck('video_id')
            ->all();
    }

    /**
     * @return Collection<int, Bookmark>
     */
    public function cursorPaginateForUser(string $userId, ?int $cursorId, int $limit): Collection
    {
        $query = $this->model->newQuery()
            ->where('user_id', $userId)
            ->with(['video.user'])
            ->orderByDesc('id');

        if ($cursorId !== null) {
            $query->where('id', '<', $cursorId);
        }

        return $query->limit($limit + 1)->get();
    }
}
