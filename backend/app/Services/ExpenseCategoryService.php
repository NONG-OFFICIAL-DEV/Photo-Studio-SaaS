<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryService extends BaseService
{
    public function __construct(protected ExpenseCategoryRepositoryInterface $categories)
    {
        parent::__construct($categories);
    }

    public function all(): Collection
    {
        return $this->categories->all();
    }

    public function create(array $data): ExpenseCategory
    {
        return $this->categories->create($data);
    }

    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        return $this->categories->update($category, $data);
    }

    public function delete(ExpenseCategory $category): bool
    {
        return $this->categories->delete($category);
    }
}
