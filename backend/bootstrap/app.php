<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\LogApiRequest;
use App\Services\ApiLogRecorder;
use App\Services\SecurityEventLogger;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Appended (not prepended): runs after routing/auth resolve the
        // request, so $request->user() is populated when a valid token
        // was sent, but still fires for every route (login, admin,
        // tenant-scoped) since it's on the global api group.
        $middleware->api(append: [
            LogApiRequest::class,
        ]);

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'email.verified' => EnsureEmailIsVerified::class,
            'super-admin' => EnsureSuperAdmin::class,
            'plan.feature' => EnsurePlanFeature::class,
        ]);

        // IdentifyTenant must run before SubstituteBindings — otherwise
        // route-model-bound params (e.g. {customer}) resolve before the
        // TenantScope global scope is active, and cross-tenant access is
        // only caught by the policy layer instead of being structurally
        // impossible at the query level (defense in depth would collapse
        // to a single layer).
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: IdentifyTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $respond = new class
        {
            use ApiResponse;

            /**
             * Every render() callback below funnels through here, which
             * doubles as the single choke point for the API Logs tab's
             * error-response recording — almost every error in this app is
             * a THROWN exception, so LogApiRequest middleware's normal
             * post-$next() logging never sees them (see ApiLogRecorder's
             * docblock). This is the one place that already knows both the
             * request and the resolved status code for all of them.
             */
            public function make(Request $request, string $message, int $status, array $errors = [], ?string $code = null, array $params = [])
            {
                app(ApiLogRecorder::class)->recordIfNeeded($request, $status);

                return $this->error($message, $status, $errors, $code, $params);
            }
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'The given data was invalid.', 422, $e->errors(), 'VALIDATION_ERROR');
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'Unauthenticated.', 401, [], 'UNAUTHENTICATED');
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                $message = $e->getMessage() ?: 'This action is unauthorized.';
                app(SecurityEventLogger::class)->permissionDenied($request, $message);

                return $respond->make($request, $message, 403, [], 'FORBIDDEN');
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'Resource not found.', 404, [], 'NOT_FOUND');
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'Endpoint not found.', 404, [], 'ENDPOINT_NOT_FOUND');
            }
        });

        $exceptions->render(function (TokenExpiredException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'Access token has expired.', 401, [], 'TOKEN_EXPIRED');
            }
        });

        $exceptions->render(function (TokenInvalidException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'Access token is invalid.', 401, [], 'TOKEN_INVALID');
            }
        });

        $exceptions->render(function (JWTException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($request, 'Access token is missing.', 401, [], 'TOKEN_MISSING');
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                $message = $e->getMessage() ?: 'Request failed.';

                if ($e->getStatusCode() === 403) {
                    app(SecurityEventLogger::class)->permissionDenied($request, $message);
                }

                $code = $e instanceof ApiException ? $e->errorCode : null;
                $params = $e instanceof ApiException ? $e->params : [];

                return $respond->make($request, $message, $e->getStatusCode(), [], $code, $params);
            }
        });
    })->create();
