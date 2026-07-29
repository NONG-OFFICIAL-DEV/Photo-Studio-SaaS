<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only API request audit trail (see the create_api_logs_table
 * migration). Deliberately has no BelongsToTenant trait / auto tenant
 * scope — AuditService explicitly decides per-query whether to filter by
 * tenant (tenant-scoped view) or not (Super Admin cross-tenant view).
 */
class ApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'method', 'path', 'status_code', 'duration_ms', 'ip', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
