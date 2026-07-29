<?php

namespace App\Policies;

use App\Models\PayrollEntry;
use App\Models\User;

class PayrollEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(User $user, PayrollEntry $entry): bool
    {
        return $user->can('payroll.view') && $user->tenant_id === $entry->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.create');
    }

    public function update(User $user, PayrollEntry $entry): bool
    {
        return $user->can('payroll.update') && $user->tenant_id === $entry->tenant_id;
    }

    public function delete(User $user, PayrollEntry $entry): bool
    {
        return $user->can('payroll.delete') && $user->tenant_id === $entry->tenant_id;
    }

    public function pay(User $user, PayrollEntry $entry): bool
    {
        return $user->can('payroll.pay') && $user->tenant_id === $entry->tenant_id;
    }
}
