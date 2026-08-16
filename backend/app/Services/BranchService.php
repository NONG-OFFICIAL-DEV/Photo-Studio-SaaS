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

    public function all(): Collection
    {
        return $this->branches->all();
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
