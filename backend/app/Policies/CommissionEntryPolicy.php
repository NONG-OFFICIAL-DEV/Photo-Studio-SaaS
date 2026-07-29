<?php

namespace App\Policies;

use App\Models\CommissionEntry;
use App\Models\User;

class CommissionEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('commissions.view');
    }

    public function view(User $user, CommissionEntry $entry): bool
    {
        return $user->can('commissions.view') && $user->tenant_id === $entry->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('commissions.record');
    }

    public function update(User $user, CommissionEntry $entry): bool
    {
        return $user->can('commissions.record') && $user->tenant_id === $entry->tenant_id;
    }

    public function delete(User $user, CommissionEntry $entry): bool
    {
        return $user->can('commissions.delete') && $user->tenant_id === $entry->tenant_id;
    }
}
