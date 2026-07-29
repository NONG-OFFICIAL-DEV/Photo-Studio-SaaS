<?php

namespace App\Services;

use App\Models\ServiceCategory;
use App\Repositories\Contracts\ServiceCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceCategoryService extends BaseService
{
    public function __construct(protected ServiceCategoryRepositoryInterface $categories)
    {
        parent::__construct($categories);
    }

    public function all(): Collection
    {
        return $this->categories->all();
    }

    public function create(array $data): ServiceCategory
    {
        return $this->categories->create($data);
    }

    public function update(ServiceCategory $category, array $data): ServiceCategory
    {
        return $this->categories->update($category, $data);
    }

    public function delete(ServiceCategory $category): bool
    {
        return $this->categories->delete($category);
    }
}
