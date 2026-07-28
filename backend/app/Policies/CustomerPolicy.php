<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view') && $user->tenant_id === $customer->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update') && $user->tenant_id === $customer->tenant_id;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete') && $user->tenant_id === $customer->tenant_id;
    }

    public function blacklist(User $user, Customer $customer): bool
    {
        return $user->can('customers.blacklist') && $user->tenant_id === $customer->tenant_id;
    }

    public function export(User $user): bool
    {
        return $user->can('customers.export');
    }

    public function import(User $user): bool
    {
        return $user->can('customers.import');
    }
}
