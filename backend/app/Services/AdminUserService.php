<?php

namespace App\Services;

use App\Enums\TenantRole;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Platform-support scoped: view a tenant's staff, deactivate/reactivate an
 * account, or trigger a password-reset email. Deliberately narrow — no
 * name/email/role/pay editing, those stay the tenant's own business
 * decisions (see UserController for the tenant-side equivalent).
 */
class AdminUserService
{
    public function usersFor(Tenant $tenant): Collection
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->with('roles')
            ->get();
    }

    public function deactivate(Tenant $tenant, User $user): User
    {
        $this->assertBelongsToTenant($tenant, $user);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        if ($user->hasRole(TenantRole::Owner->value) && $this->activeOwnerCount($tenant->id) <= 1) {
            throw new ApiException(422, 'A studio must have at least one active owner.', 'CANNOT_DEACTIVATE_LAST_OWNER');
        }

        $user->update(['status' => UserStatus::Inactive]);

        activity('audit')->performedOn($user)->tap(fn ($a) => $a->tenant_id = $tenant->id)
            ->log("Employee \"{$user->name}\" deactivated by platform support");

        return $user->fresh('roles');
    }

    public function reactivate(Tenant $tenant, User $user): User
    {
        $this->assertBelongsToTenant($tenant, $user);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $user->update(['status' => UserStatus::Active]);

        activity('audit')->performedOn($user)->tap(fn ($a) => $a->tenant_id = $tenant->id)
            ->log("Employee \"{$user->name}\" reactivated by platform support");

        return $user->fresh('roles');
    }

    public function sendPasswordReset(Tenant $tenant, User $user): void
    {
        $this->assertBelongsToTenant($tenant, $user);

        // Default ResetPassword notification is correct here — this is a
        // real reset for an existing, already-active account, not an
        // invite (see EmployeeInvitedNotification for that distinction).
        Password::sendResetLink(['email' => $user->email]);

        activity('audit')->performedOn($user)->tap(fn ($a) => $a->tenant_id = $tenant->id)
            ->log("Password reset link sent to \"{$user->name}\" by platform support");
    }

    protected function activeOwnerCount(string $tenantId): int
    {
        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', UserStatus::Active)
            ->whereHas('roles', fn ($query) => $query->where('name', TenantRole::Owner->value))
            ->count();
    }

    /**
     * Admin routes carry no `tenant` middleware, so TenantScope never
     * filters {user}'s route-model-binding — without this, a super admin
     * could act on a user from a different tenant than the one in the URL.
     */
    protected function assertBelongsToTenant(Tenant $tenant, User $user): void
    {
        if ($user->tenant_id !== $tenant->id) {
            throw new NotFoundHttpException;
        }
    }
}
