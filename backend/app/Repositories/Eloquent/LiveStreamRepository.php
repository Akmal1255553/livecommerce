<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LiveStreamRepositoryInterface;
use App\Models\LiveStream;

class LiveStreamRepository extends BaseEloquentRepository implements LiveStreamRepositoryInterface
{
    public function __construct(LiveStream $model)
    {
        parent::__construct($model);
    }
}
