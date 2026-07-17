<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BlockRepositoryInterface;
use App\Models\Block;
use Illuminate\Support\Collection;

class BlockRepository implements BlockRepositoryInterface
{
    public function create(string $blockerId, string $blockedId): Block
    {
        return Block::query()->create([
            'blocker_id' => $blockerId,
            'blocked_id' => $blockedId,
            'created_at' => now(),
        ]);
    }

    public function delete(string $blockerId, string $blockedId): bool
    {
        return Block::query()
            ->where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->delete() > 0;
    }

    public function exists(string $blockerId, string $blockedId): bool
    {
        return Block::query()
            ->where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();
    }

    public function isBlockedEitherWay(string $userA, string $userB): bool
    {
        return Block::query()
            ->where(function ($q) use ($userA, $userB): void {
                $q->where('blocker_id', $userA)->where('blocked_id', $userB);
            })
            ->orWhere(function ($q) use ($userA, $userB): void {
                $q->where('blocker_id', $userB)->where('blocked_id', $userA);
            })
            ->exists();
    }

    public function listBlockedBy(string $blockerId): Collection
    {
        return Block::query()
            ->where('blocker_id', $blockerId)
            ->orderByDesc('created_at')
            ->get();
    }
}
