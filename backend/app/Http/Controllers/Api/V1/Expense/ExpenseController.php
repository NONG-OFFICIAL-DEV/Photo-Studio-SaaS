<?php

namespace App\Http\Controllers\Api\V1\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use ApiResponse;

    public function __construct(protected ExpenseService $expenses)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $paginator = $this->expenses->paginate($request->only([
            'search', 'sortBy', 'sortDesc', 'page', 'perPage',
            'category_id', 'payment_method', 'date_from', 'date_to',
        ]));

        return $this->success(
            ExpenseResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->user());

        return $this->created(new ExpenseResource($expense), 'Expense created successfully.');
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorize('view', $expense);

        return $this->success(new ExpenseResource($expense->load('category')));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenses->update($expense, $request->validated());

        return $this->success(new ExpenseResource($expense), 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $this->expenses->delete($expense);

        return $this->noContent('Expense deleted successfully.');
    }
}
