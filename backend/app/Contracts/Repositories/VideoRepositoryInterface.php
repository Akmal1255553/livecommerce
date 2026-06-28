<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Video;
use Illuminate\Support\Collection;

interface VideoRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  list<string>|null  $userIds
     * @return Collection<int, Video>
     */
    public function cursorPaginateFeed(?array $cursor, int $limit, ?array $userIds = null): Collection;
}
