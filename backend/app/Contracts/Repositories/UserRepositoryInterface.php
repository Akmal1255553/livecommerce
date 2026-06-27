<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function findByPhone(string $phone): ?User;

    public function findByLogin(string $login): ?User;
}
