<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\LiveSession;
use Illuminate\Support\Collection;

interface LiveSessionRepositoryInterface extends RepositoryInterface
{
    public function create(array $attributes): LiveSession;

    public function save(LiveSession $session): LiveSession;

    /**
     * @return Collection<int, LiveSession>
     */
    public function listLive(int $limit = 20): Collection;

    public function findLiveBySeller(string $sellerId): ?LiveSession;

    public function findWithRelations(string $id): ?LiveSession;
}
