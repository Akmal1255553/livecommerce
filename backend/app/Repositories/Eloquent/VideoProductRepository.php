<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\VideoProductRepositoryInterface;
use App\Models\VideoProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseEloquentRepository<VideoProduct>
 */
class VideoProductRepository extends BaseEloquentRepository implements VideoProductRepositoryInterface
{
    public function __construct(VideoProduct $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncForVideo(string $videoId, array $rows): void
    {
        DB::transaction(function () use ($videoId, $rows): void {
            $this->model->newQuery()->where('video_id', $videoId)->delete();

            if ($rows === []) {
                return;
            }

            $now = now();

            foreach ($rows as &$row) {
                $row['video_id'] = $videoId;
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            unset($row);

            $this->model->newQuery()->insert($rows);
        });
    }

    /**
     * @param  list<string>  $videoIds
     * @return Collection<int, VideoProduct>
     */
    public function findByVideoIds(array $videoIds): Collection
    {
        if ($videoIds === []) {
            return collect();
        }

        return $this->model->newQuery()
            ->whereIn('video_id', $videoIds)
            ->with(['product.images', 'product.store'])
            ->orderBy('sort_order')
            ->get();
    }

    public function countByVideo(string $videoId): int
    {
        return $this->model->newQuery()
            ->where('video_id', $videoId)
            ->count();
    }
}
