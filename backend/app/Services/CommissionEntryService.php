<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\User;
use App\Repositories\Contracts\CommissionEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommissionEntryService extends BaseService
{
    public function __construct(protected CommissionEntryRepositoryInterface $commissions)
    {
        parent::__construct($commissions);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->commissions->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): CommissionEntry
    {
        $entry = $this->commissions->create([
            ...$data,
            'created_by' => $creator?->id,
        ]);

        return $entry->load('user', 'order');
    }

    public function update(CommissionEntry $entry, array $data): CommissionEntry
    {
        $this->commissions->update($entry, $data);

        return $entry->fresh(['user', 'order']);
    }

    public function delete(CommissionEntry $entry): bool
    {
        return $this->commissions->delete($entry);
    }
}
