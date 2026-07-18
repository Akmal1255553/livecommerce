<?php

declare(strict_types=1);

namespace App\Services\Block;

use App\Contracts\Repositories\BlockRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Models\User;
use App\Services\BaseService;

class BlockService extends BaseService
{
    public function __construct(
        private readonly BlockRepositoryInterface $blocks,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function block(User $blocker, string $blockedId): void
    {
        if ($blocker->id === $blockedId) {
            throw new ConflictException('You cannot block yourself.');
        }

        $target = $this->users->findById($blockedId);
        if (! $target instanceof User) {
            throw new ResourceNotFoundException('User not found.');
        }

        if ($this->blocks->exists($blocker->id, $blockedId)) {
            throw new ConflictException('User already blocked.');
        }

        $this->blocks->create($blocker->id, $blockedId);
    }

    public function unblock(User $blocker, string $blockedId): void
    {
        if (! $this->blocks->delete($blocker->id, $blockedId)) {
            throw new ResourceNotFoundException('Block not found.');
        }
    }

    public function isBlockedEitherWay(string $userA, string $userB): bool
    {
        return $this->blocks->isBlockedEitherWay($userA, $userB);
    }
}
