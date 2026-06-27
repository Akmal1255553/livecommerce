<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Models\Notification;

class NotificationRepository extends BaseEloquentRepository implements NotificationRepositoryInterface
{
    public function __construct(Notification $model)
    {
        parent::__construct($model);
    }
}
