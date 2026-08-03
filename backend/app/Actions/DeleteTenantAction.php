<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently and irreversibly deletes a tenant and every piece of data
 * tied to it — the Super Admin's "remove tenant from the platform
 * entirely" action, distinct from suspend()/activate() (AdminTenantService),
 * which only ever toggle access without touching any data.
 *
 * Most tenant-owned tables already have an `ON DELETE CASCADE` foreign key
 * to `tenants` (see the individual migrations), so a hard delete on the
 * Tenant row itself removes the vast majority of rows automatically —
 * users, customers, bookings, orders, invoices, payments, subscriptions,
 * telegram_message_logs, etc. This action's job is everything that
 * DOESN'T cascade automatically:
 *
 *  - activity_log / api_logs: tenant_id has no FK constraint (audit trail
 *    tables are deliberately loose so a super admin's own actions, which
 *    have no tenant, can still be logged).
 *  - Spatie's roles / model_has_roles / model_has_permissions: the "team"
 *    (tenant_id) column has no FK to tenants either — deleting `roles`
 *    cascades to `model_has_roles` (that FK IS in place), but the team
 *    column itself needs an explicit sweep, as does model_has_permissions
 *    (permissions granted directly to a user, not via a role).
 *  - notifications: polymorphic (notifiable_type/notifiable_id), no FK to
 *    users at all — must be deleted by user id BEFORE those users are
 *    swept away by the tenant's cascade delete, or they're orphaned forever.
 *  - storage/app/public/tenants/{id}/*: physical files (logo, QR payment
 *    image) are outside the database entirely.
 *
 * A platform-level "audit" activity log entry is recorded with NO
 * tenant_id (i.e. it does NOT belong to the tenant being deleted) so it
 * survives this same action's activity_log cleanup step and remains
 * visible forever in the Super Admin's unfiltered Audit tab — the one
 * permanent record that this tenant ever existed and who removed it.
 */
class DeleteTenantAction
{
    public function execute(Tenant $tenant, ?User $actor): array
    {
        return DB::transaction(function () use ($tenant, $actor) {
            $tenantId = $tenant->id;
            $tenantName = $tenant->name;

            $summary = [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'users_count' => User::query()->where('tenant_id', $tenantId)->count(),
                'customers_count' => DB::table('customers')->where('tenant_id', $tenantId)->count(),
                'bookings_count' => DB::table('bookings')->where('tenant_id', $tenantId)->count(),
                'invoices_count' => DB::table('invoices')->where('tenant_id', $tenantId)->count(),
            ];

            $userIds = User::query()->where('tenant_id', $tenantId)->pluck('id');

            DatabaseNotification::query()
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->delete();

            $teamColumn = config('permission.column_names.team_foreign_key', 'tenant_id');
            DB::table(config('permission.table_names.model_has_permissions'))->where($teamColumn, $tenantId)->delete();
            DB::table(config('permission.table_names.model_has_roles'))->where($teamColumn, $tenantId)->delete();
            DB::table(config('permission.table_names.roles'))->where($teamColumn, $tenantId)->delete();

            DB::table('activity_log')->where('tenant_id', $tenantId)->delete();
            DB::table('api_logs')->where('tenant_id', $tenantId)->delete();

            Storage::disk('public')->deleteDirectory("tenants/{$tenantId}");

            $tenant->forceDelete();

            activity('audit')
                ->causedBy($actor)
                ->withProperties($summary)
                ->log("Tenant \"{$tenantName}\" and all of its data were permanently deleted");

            return $summary;
        });
    }
}
