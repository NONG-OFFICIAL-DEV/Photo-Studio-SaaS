<?php

namespace App\Services;

use App\Models\ServiceAddOn;
use App\Repositories\Contracts\ServiceAddOnRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceAddOnService extends BaseService
{
    public function __construct(protected ServiceAddOnRepositoryInterface $addOns)
    {
        parent::__construct($addOns);
    }

    public function all(): Collection
    {
        return $this->addOns->all();
    }

    public function create(array $data): ServiceAddOn
    {
        return $this->addOns->create($data);
    }

    public function update(ServiceAddOn $addOn, array $data): ServiceAddOn
    {
        return $this->addOns->update($addOn, $data);
    }

    public function delete(ServiceAddOn $addOn): bool
    {
        return $this->addOns->delete($addOn);
    }
}
