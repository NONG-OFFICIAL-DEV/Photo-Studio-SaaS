<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    protected array $searchable = ['name', 'slug', 'email', 'domain'];

    public function __construct(Tenant $model)
    {
        parent::__construct($model);
    }

    public function slugExists(string $slug): bool
    {
        return $this->model->newQuery()->where('slug', $slug)->exists();
    }
}
