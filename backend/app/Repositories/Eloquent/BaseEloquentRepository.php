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

    /**
     * @return TModel|null
     */
    public function findById(int|string $id): ?Model
    {
        /** @var TModel|null */
        return $this->model->newQuery()->find($id);
    }

    /**
     * @return TModel
     */
    public function findByIdOrFail(int|string $id): Model
    {
        /** @var TModel|null */
        $record = $this->findById($id);

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel($this->model::class, [$id]);
        }

        /** @var TModel */
        return $record;
    }
}
