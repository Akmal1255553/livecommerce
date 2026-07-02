<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\VideoProduct;
use Illuminate\Support\Collection;

interface VideoProductRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncForVideo(string $videoId, array $rows): void;

    /**
     * @param  list<string>  $videoIds
     * @return Collection<int, VideoProduct>
     */
    public function findByVideoIds(array $videoIds): Collection;

    public function countByVideo(string $videoId): int;
}
