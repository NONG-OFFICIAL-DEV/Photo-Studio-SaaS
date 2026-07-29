<?php

namespace App\Repositories\Eloquent;

use App\Models\ServiceAddOn;
use App\Repositories\Contracts\ServiceAddOnRepositoryInterface;

class ServiceAddOnRepository extends BaseRepository implements ServiceAddOnRepositoryInterface
{
    protected array $searchable = ['name'];

    public function __construct(ServiceAddOn $model)
    {
        parent::__construct($model);
    }
}
