<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Models\Follow;

class FollowRepository extends BaseEloquentRepository implements FollowRepositoryInterface
{
    public function __construct(Follow $model)
    {
        parent::__construct($model);
    }
}
