<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Block;
use Illuminate\Support\Collection;

interface BlockRepositoryInterface
{
    public function create(string $blockerId, string $blockedId): Block;

    public function delete(string $blockerId, string $blockedId): bool;

    public function exists(string $blockerId, string $blockedId): bool;

    public function isBlockedEitherWay(string $userA, string $userB): bool;

    /**
     * @return Collection<int, Block>
     */
    public function listBlockedBy(string $blockerId): Collection;
}
