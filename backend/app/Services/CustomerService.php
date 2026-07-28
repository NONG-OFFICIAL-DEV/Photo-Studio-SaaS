<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService extends BaseService
{
    public function __construct(protected CustomerRepositoryInterface $customers)
    {
        parent::__construct($customers);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->customers->paginateServer($filters);
    }

    public function create(array $data, ?User $creator = null): Customer
    {
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        /** @var Customer $customer */
        $customer = $this->customers->create([...$data, 'created_by' => $creator?->id]);

        if ($tagIds) {
            $customer->tags()->sync($tagIds);
        }

        return $customer->load('tags');
    }

    public function update(Customer $customer, array $data): Customer
    {
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['tag_ids']);

        $this->customers->update($customer, $data);

        if ($tagIds !== null) {
            $customer->tags()->sync($tagIds);
        }

        return $customer->fresh('tags');
    }

    public function delete(Customer $customer): bool
    {
        return $this->customers->delete($customer);
    }

    public function toggleFavorite(Customer $customer): Customer
    {
        $customer->update(['is_favorite' => ! $customer->is_favorite]);

        return $customer;
    }

    public function blacklist(Customer $customer, string $reason): Customer
    {
        $customer->update(['is_blacklisted' => true, 'blacklist_reason' => $reason]);

        return $customer;
    }

    public function unblacklist(Customer $customer): Customer
    {
        $customer->update(['is_blacklisted' => false, 'blacklist_reason' => null]);

        return $customer;
    }

    public function addNote(Customer $customer, string $note, ?User $author = null): CustomerNote
    {
        return $customer->notes()->create([
            'note' => $note,
            'user_id' => $author?->id,
        ]);
    }

    public function deleteNote(CustomerNote $note): bool
    {
        return $note->delete();
    }
}
