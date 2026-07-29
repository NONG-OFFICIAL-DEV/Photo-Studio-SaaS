<?php

namespace App\Http\Middleware;

use App\Services\ApiLogRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Logs tab's data source, for the success path — a response returned
 * normally by $next() (every successful request; error responses in this
 * app are almost always thrown exceptions instead, which bypass this
 * entirely and are logged from bootstrap/app.php's render() callbacks —
 * see ApiLogRecorder's docblock). Deliberately narrow: only mutating
 * requests (POST/PUT/PATCH/DELETE) are recorded here — routine GETs would
 * dwarf the signal with traffic nobody audits later.
 */
class LogApiRequest
{
    public function __construct(protected ApiLogRecorder $recorder)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->recorder->recordIfNeeded($request, $response->getStatusCode());

        return $response;
    }
}
