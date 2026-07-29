<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TenantRepositoryInterface extends RepositoryInterface
{
    public function slugExists(string $slug): bool;

    public function adminPaginate(array $filters): LengthAwarePaginator;
}
