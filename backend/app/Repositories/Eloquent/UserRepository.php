<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;

/**
 * @extends BaseEloquentRepository<User>
 */
class UserRepository extends BaseEloquentRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        /** @var User */
        return $this->model->newQuery()->create($attributes);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function findByUsername(string $username): ?User
    {
        /** @var User|null */
        return $this->model->newQuery()->where('username', $username)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        /** @var User|null */
        return $this->model->newQuery()->where('phone', $phone)->first();
    }

    public function findByLogin(string $login): ?User
    {
        /** @var User|null */
        return $this->model->newQuery()
            ->where(function ($query) use ($login): void {
                $query->where('email', $login)
                    ->orWhere('username', $login)
                    ->orWhere('phone', $login);
            })
            ->first();
    }
}
