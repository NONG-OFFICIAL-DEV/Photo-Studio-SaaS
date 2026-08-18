<?php

namespace App\Services;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BranchService extends BaseService
{
    public function __construct(protected BranchRepositoryInterface $branches)
    {
        parent::__construct($branches);
    }

    /**
     * Defaults to active-only — every branch picker outside the Branches
     * management tab (Booking/InventoryItem/Employee create dialogs) uses
     * this list, and an inactive ("temporarily closed") branch shouldn't be
     * assignable there. Same convention as UserController::index()'s
     * include_inactive flag for employees.
     */
    public function all(bool $includeInactive = false): Collection
    {
        $query = $this->branches->query();

        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function create(array $data): Branch
    {
        return $this->branches->create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        return $this->branches->update($branch, $data);
    }

    public function delete(Branch $branch): bool
    {
        return $this->branches->delete($branch);
    }
}
