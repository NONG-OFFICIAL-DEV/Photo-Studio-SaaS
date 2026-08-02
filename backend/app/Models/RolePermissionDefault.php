<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The admin-editable "which permissions does this baseline role get by
 * default" matrix — replaces config('permissions.defaults') as the live
 * source of truth for every non-Owner TenantRole. See
 * App\Services\RolePermissionDefaultsService.
 */
class RolePermissionDefault extends Model
{
    use HasUuids;

    protected $fillable = ['role', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
