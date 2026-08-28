<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExpenseService extends BaseService
{
    public function __construct(protected ExpenseRepositoryInterface $expenses, protected BranchResolutionService $branches)
    {
        parent::__construct($expenses);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->expenses->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): Expense
    {
        // No booking/order chain to inherit from — resolved the same way
        // Booking/User already are.
        $branchId = $this->branches->resolveForCreate($creator->tenant, $data['branch_id'] ?? null);

        /** @var Expense $expense */
        $expense = $this->expenses->create([
            ...$data,
            'branch_id' => $branchId,
            'created_by' => $creator?->id,
        ]);

        return $expense->load('category');
    }

    public function update(Expense $expense, array $data): Expense
    {
        $this->expenses->update($expense, $data);

        return $expense->fresh('category');
    }

    public function delete(Expense $expense): bool
    {
        return $this->expenses->delete($expense);
    }
}
