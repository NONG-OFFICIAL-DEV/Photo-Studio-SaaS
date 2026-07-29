<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Writes to the same activity_log table every domain model already logs
 * to (see e.g. Customer::getActivitylogOptions()), under two dedicated
 * log names that back the Audit page's Login History and Security
 * Events tabs:
 *
 *  - 'login'    — every login attempt, success or failure.
 *  - 'security' — permission-denied (403) events.
 *
 * Security Events also surfaces failed logins by querying 'login' entries
 * where properties.success = false (see AuditService), rather than
 * writing them twice.
 */
class SecurityEventLogger
{
    public function loginAttempt(string $email, ?User $user, bool $success, ?string $reason, Request $request): void
    {
        $log = activity('login')
            ->tap(fn ($activity) => $activity->tenant_id = $user?->tenant_id)
            ->causedBy($user)
            ->withProperties([
                'email' => $email,
                'success' => $success,
                'reason' => $reason,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

        if ($user) {
            $log->performedOn($user);
        }

        $log->log($success ? 'User logged in' : 'Login attempt failed');
    }

    public function permissionDenied(Request $request, string $message): void
    {
        $user = $request->user();

        activity('security')
            ->tap(fn ($activity) => $activity->tenant_id = $user?->tenant_id)
            ->causedBy($user)
            ->withProperties([
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'message' => $message,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ])
            ->log('Permission denied: '.$message);
    }
}
