<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @template TModel of Model
 */
abstract class BaseEloquentRepository implements RepositoryInterface
{
    /** @param TModel $model */
    public function __construct(protected Model $model) {}

    public function findById(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    public function findByIdOrFail(int|string $id): Model
    {
        $record = $this->findById($id);

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel($this->model::class, [$id]);
        }

        return $record;
    }
}
