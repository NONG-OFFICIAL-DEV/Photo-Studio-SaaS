<?php

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\IdentifyTenant;
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

        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'subscription.active' => EnsureSubscriptionActive::class,
            'super-admin' => EnsureSuperAdmin::class,
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

            public function make(string $message, int $status, array $errors = [])
            {
                return $this->error($message, $status, $errors);
            }
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('The given data was invalid.', 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($e->getMessage() ?: 'This action is unauthorized.', 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('Resource not found.', 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('Endpoint not found.', 404);
            }
        });

        $exceptions->render(function (TokenExpiredException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('Access token has expired.', 401);
            }
        });

        $exceptions->render(function (TokenInvalidException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('Access token is invalid.', 401);
            }
        });

        $exceptions->render(function (JWTException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make('Access token is missing.', 401);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($respond) {
            if ($request->is('api/*')) {
                return $respond->make($e->getMessage() ?: 'Request failed.', $e->getStatusCode());
            }
        });
    })->create();
