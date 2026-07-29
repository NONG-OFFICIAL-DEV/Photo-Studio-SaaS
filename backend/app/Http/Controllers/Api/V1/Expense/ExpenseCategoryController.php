<?php

namespace App\Http\Controllers\Api\V1\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseCategoryRequest;
use App\Http\Requests\Expense\UpdateExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(protected ExpenseCategoryService $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('expenses.view'), 403);

        return $this->success(ExpenseCategoryResource::collection($this->categories->all()));
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = $this->categories->create($request->validated());

        return $this->created(new ExpenseCategoryResource($category), 'Category created successfully.');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): JsonResponse
    {
        $category = $this->categories->update($category, $request->validated());

        return $this->success(new ExpenseCategoryResource($category), 'Category updated successfully.');
    }

    public function destroy(Request $request, ExpenseCategory $category): JsonResponse
    {
        abort_unless($request->user()->can('expenses.delete'), 403);

        $this->categories->delete($category);

        return $this->noContent('Category deleted successfully.');
    }
}
