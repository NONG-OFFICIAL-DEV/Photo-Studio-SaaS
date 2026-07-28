<?php

namespace App\Providers;

use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CustomerTagRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\CustomerTagRepository;
use App\Repositories\Eloquent\TenantRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\TenantContext;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface => implementation bindings. Add one line per new
     * repository as modules (bookings, invoices, ...) come online.
     *
     * Named $repositoryBindings (not $bindings) because Laravel's
     * ServiceProvider auto-registers any public `$bindings` property
     * itself — reusing that name here would double-register / conflict.
     */
    protected array $repositoryBindings = [
        UserRepositoryInterface::class => UserRepository::class,
        TenantRepositoryInterface::class => TenantRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
        CustomerTagRepositoryInterface::class => CustomerTagRepository::class,
        BookingRepositoryInterface::class => BookingRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        foreach ($this->repositoryBindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
