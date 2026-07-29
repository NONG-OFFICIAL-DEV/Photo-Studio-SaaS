<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory.view') && $user->tenant_id === $item->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.create');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory.update') && $user->tenant_id === $item->tenant_id;
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory.delete') && $user->tenant_id === $item->tenant_id;
    }

    public function adjustStock(User $user, InventoryItem $item): bool
    {
        return $user->can('inventory.adjust-stock') && $user->tenant_id === $item->tenant_id;
    }
}
