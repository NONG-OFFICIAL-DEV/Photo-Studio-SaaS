<?php

namespace App\Repositories\Contracts;

interface TenantRepositoryInterface extends RepositoryInterface
{
    public function slugExists(string $slug): bool;
}
