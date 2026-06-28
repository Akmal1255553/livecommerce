<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\VideoShareRepositoryInterface;
use App\Models\VideoShare;

class VideoShareRepository extends BaseEloquentRepository implements VideoShareRepositoryInterface
{
    public function __construct(VideoShare $model)
    {
        parent::__construct($model);
    }

    public function create(string $userId, string $videoId, string $channel): VideoShare
    {
        /** @var VideoShare */
        return $this->model->newQuery()->create([
            'user_id' => $userId,
            'video_id' => $videoId,
            'channel' => $channel,
            'created_at' => now(),
        ]);
    }
}
