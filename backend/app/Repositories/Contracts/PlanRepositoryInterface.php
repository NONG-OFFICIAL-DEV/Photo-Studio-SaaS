<?php

namespace App\Repositories\Contracts;

interface PlanRepositoryInterface extends RepositoryInterface
{
    public function codeExists(string $code): bool;
}
