<?php

namespace App\Providers;

use App\Repositories\Contracts\AlbumRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\CustomerTagRepositoryInterface;
use App\Repositories\Contracts\EditingTaskRepositoryInterface;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use App\Repositories\Contracts\ExpenseRepositoryInterface;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\ServiceAddOnRepositoryInterface;
use App\Repositories\Contracts\ServiceCategoryRepositoryInterface;
use App\Repositories\Contracts\ServiceRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AlbumRepository;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\CustomerTagRepository;
use App\Repositories\Eloquent\EditingTaskRepository;
use App\Repositories\Eloquent\ExpenseCategoryRepository;
use App\Repositories\Eloquent\ExpenseRepository;
use App\Repositories\Eloquent\InventoryItemRepository;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\PackageRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\ServiceAddOnRepository;
use App\Repositories\Eloquent\ServiceCategoryRepository;
use App\Repositories\Eloquent\ServiceRepository;
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
        ServiceRepositoryInterface::class => ServiceRepository::class,
        ServiceCategoryRepositoryInterface::class => ServiceCategoryRepository::class,
        ServiceAddOnRepositoryInterface::class => ServiceAddOnRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        EditingTaskRepositoryInterface::class => EditingTaskRepository::class,
        AlbumRepositoryInterface::class => AlbumRepository::class,
        InvoiceRepositoryInterface::class => InvoiceRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        PackageRepositoryInterface::class => PackageRepository::class,
        ExpenseCategoryRepositoryInterface::class => ExpenseCategoryRepository::class,
        ExpenseRepositoryInterface::class => ExpenseRepository::class,
        InventoryItemRepositoryInterface::class => InventoryItemRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        foreach ($this->repositoryBindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
