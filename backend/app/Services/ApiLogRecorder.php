<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Http\Request;

/**
 * Shared by two call sites, since success and error responses take
 * different paths through Laravel:
 *  - LogApiRequest middleware, for responses returned normally by $next()
 *    (i.e. every successful request).
 *  - bootstrap/app.php's exception `render()` callbacks, for every error
 *    response — almost all of them are THROWN exceptions in this app
 *    (abort_unless, FormRequest validation, ...), which unwind straight
 *    past a middleware's post-$next() code without ever reaching it.
 * Between the two, every mutating request and every error response this
 * app can produce gets exactly one ApiLog row.
 */
class ApiLogRecorder
{
    protected const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function recordIfNeeded(Request $request, int $statusCode): void
    {
        $shouldLog = in_array($request->method(), self::LOGGED_METHODS, true) || $statusCode >= 400;

        if (! $shouldLog) {
            return;
        }

        $user = $request->user();
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        ApiLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status_code' => $statusCode,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
