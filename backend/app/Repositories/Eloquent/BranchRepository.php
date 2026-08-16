<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface
{
    protected array $searchable = ['name', 'address'];

    public function __construct(Branch $model)
    {
        parent::__construct($model);
    }
}
