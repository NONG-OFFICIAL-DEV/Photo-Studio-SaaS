<?php

namespace App\Policies;

use App\Models\EditingTask;
use App\Models\User;

class EditingTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('editing.view');
    }

    public function view(User $user, EditingTask $task): bool
    {
        return $user->can('editing.view') && $user->tenant_id === $task->tenant_id;
    }

    /**
     * Status transitions (start/in-review/request-revision/complete).
     * Full order managers (orders.update) can update any task; an Editor
     * with only editing.update can update just the task assigned to
     * them — same row-level pattern as BookingPolicy for Photographers.
     */
    public function update(User $user, EditingTask $task): bool
    {
        if ($user->tenant_id !== $task->tenant_id) {
            return false;
        }

        if ($user->can('orders.update')) {
            return true;
        }

        return $user->can('editing.update') && $task->assigned_user_id === $user->id;
    }

    /**
     * Reassigning who owns a task is a management action, distinct from
     * performing the work — requires orders.update, not editing.update.
     */
    public function assign(User $user, EditingTask $task): bool
    {
        return $user->can('orders.update') && $user->tenant_id === $task->tenant_id;
    }
}
