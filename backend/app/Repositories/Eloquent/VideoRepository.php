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

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Video>
     */
    public function findPublishedByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        /** @var Collection<int, Video> $videos */
        $videos = $this->model->newQuery()
            ->whereIn('id', $ids)
            ->where('status', VideoStatus::Published)
            ->where('visibility', VideoVisibility::Public)
            ->with('user')
            ->get()
            ->keyBy('id');

        /** @var Collection<int, Video> */
        return collect($ids)
            ->map(static fn (string $id): ?Video => $videos->get($id))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Video>
     */
    public function listExplorationCandidates(int $limit): Collection
    {
        $maxViews = (int) config('recommendation.exploration.max_views', 1000);
        $maxAgeDays = (int) config('recommendation.exploration.max_age_days', 7);
        $since = Carbon::now()->subDays($maxAgeDays);

        /** @var Collection<int, Video> */
        return $this->model->newQuery()
            ->where('status', VideoStatus::Published)
            ->where('visibility', VideoVisibility::Public)
            ->where('view_count', '<', $maxViews)
            ->where(function ($query) use ($since): void {
                $query->where('published_at', '>=', $since)
                    ->orWhere(function ($nested) use ($since): void {
                        $nested->whereNull('published_at')
                            ->where('created_at', '>=', $since);
                    });
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{id: string, created_at: string}|null  $cursor
     * @return Collection<int, Video>
     */
    public function listNewCandidates(int $limit, ?array $cursor = null): Collection
    {
        return $this->cursorPaginateFeed($cursor, $limit);
    }
}
