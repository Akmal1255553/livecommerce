<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function findById(int|string $id): ?Model;

    public function findByIdOrFail(int|string $id): Model;
}
