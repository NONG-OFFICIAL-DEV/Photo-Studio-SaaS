<?php

namespace App\Repositories\Eloquent;

use App\Models\CustomerTag;
use App\Repositories\Contracts\CustomerTagRepositoryInterface;

class CustomerTagRepository extends BaseRepository implements CustomerTagRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(CustomerTag $model)
    {
        parent::__construct($model);
    }
}
