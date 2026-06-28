<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\VideoRepositoryInterface;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Video;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends BaseEloquentRepository<Video>
 */
class VideoRepository extends BaseEloquentRepository implements VideoRepositoryInterface
{
    public function __construct(Video $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Video
    {
        /** @var Video $video */
        $video = $this->model->newQuery()->create($attributes);

        return $video;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Video $video, array $attributes): Video
    {
        $video->fill($attributes);
        $video->save();

        return $video;
    }

    /**
     * @param  array{id: string, created_at: string}|null  $cursor
     * @param  list<string>|null  $userIds
     * @return Collection<int, Video>
     */
    public function cursorPaginateFeed(?array $cursor, int $limit, ?array $userIds = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('status', VideoStatus::Published)
            ->where('visibility', VideoVisibility::Public)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }

        if ($cursor !== null) {
            $createdAt = Carbon::parse($cursor['created_at']);

            $query->where(function ($builder) use ($cursor, $createdAt): void {
                $builder->where('created_at', '<', $createdAt)
                    ->orWhere(function ($nested) use ($cursor, $createdAt): void {
                        $nested->where('created_at', $createdAt)
                            ->where('id', '<', $cursor['id']);
                    });
            });
        }

        /** @var Collection<int, Video> */
        return $query->limit($limit + 1)->get();
    }
}
