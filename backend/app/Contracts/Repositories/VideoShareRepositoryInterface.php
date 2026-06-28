<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\VideoShare;

interface VideoShareRepositoryInterface extends RepositoryInterface
{
    public function create(string $userId, string $videoId, string $channel): VideoShare;
}
