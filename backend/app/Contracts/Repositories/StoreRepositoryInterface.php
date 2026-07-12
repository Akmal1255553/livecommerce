<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Store;
use App\Models\User;

interface StoreRepositoryInterface extends RepositoryInterface
{
    public function findByUserId(string $userId): ?Store;

    public function findActiveByUser(User $user): ?Store;

    public function findBySlug(string $slug): ?Store;

    public function findActiveBySlug(string $slug): ?Store;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Store;
}
