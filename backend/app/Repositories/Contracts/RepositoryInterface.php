<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function query(): Builder;

    public function all(array $columns = ['*']): Collection;

    public function find(string $id, array $columns = ['*']): ?Model;

    public function findOrFail(string $id, array $columns = ['*']): Model;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;

    public function paginateServer(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
