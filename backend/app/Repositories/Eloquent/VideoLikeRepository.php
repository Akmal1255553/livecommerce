<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\VideoLikeRepositoryInterface;
use App\Models\VideoLike;

/**
 * @extends BaseEloquentRepository<VideoLike>
 */
class VideoLikeRepository extends BaseEloquentRepository implements VideoLikeRepositoryInterface
{
    public function __construct(VideoLike $model)
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

    public function create(string $userId, string $videoId): VideoLike
    {
        /** @var VideoLike */
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
    public function likedVideoIdsForUser(string $userId, array $videoIds): array
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
}
