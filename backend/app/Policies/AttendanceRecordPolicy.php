<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.view') && $user->tenant_id === $record->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.manage') && $user->tenant_id === $record->tenant_id;
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.manage') && $user->tenant_id === $record->tenant_id;
    }
}
