<?php

use App\Enums\TenantRole;

/**
 * Platform-wide permission catalog (guard: api). Flat, dot-notation,
 * grouped by module for building the frontend's dynamic menu/button
 * permission checks later. Add new module keys as modules ship —
 * nothing here needs to change per tenant, only the role => permission
 * matrix below decides who gets what by default.
 */
return [

    'catalog' => [
        'dashboard' => ['dashboard.view'],
        'users' => ['users.view', 'users.create', 'users.update', 'users.delete'],
        'roles' => ['roles.view', 'roles.manage'],
        'tenant' => ['tenant.settings.manage', 'tenant.billing.manage'],
        'customers' => [
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'customers.export', 'customers.import', 'customers.blacklist',
        ],
        'bookings' => [
            'bookings.view', 'bookings.create', 'bookings.update', 'bookings.delete',
            'bookings.assign', 'bookings.cancel',
        ],
        'services' => ['services.view', 'services.create', 'services.update', 'services.delete'],
        'packages' => ['packages.view', 'packages.create', 'packages.update', 'packages.delete'],
        'orders' => ['orders.view', 'orders.create', 'orders.update', 'orders.delete'],
        'editing' => ['editing.view', 'editing.update'],
        'albums' => ['albums.view', 'albums.create', 'albums.update', 'albums.delete'],
        'invoices' => [
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'invoices.send', 'invoices.void',
        ],
        'payments' => ['payments.record', 'payments.delete'],
        'expenses' => ['expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete'],
        'inventory' => ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'inventory.adjust-stock'],
    ],

    /*
     * Default permissions granted to each baseline role when a tenant is
     * provisioned. Owner always gets the full current catalog. Tenants can
     * freely customize per-role grants afterwards (Dynamic Permission
     * Assignment) — this matrix only seeds sensible defaults.
     */
    'defaults' => [
        TenantRole::Owner->value => ['*'],
        TenantRole::Manager->value => [
            'dashboard.view', 'users.view', 'users.create', 'users.update',
            'roles.view', 'tenant.settings.manage',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'customers.export', 'customers.import', 'customers.blacklist',
            'bookings.view', 'bookings.create', 'bookings.update', 'bookings.delete',
            'bookings.assign', 'bookings.cancel',
            'services.view', 'services.create', 'services.update', 'services.delete',
            'packages.view', 'packages.create', 'packages.update', 'packages.delete',
            'orders.view', 'orders.create', 'orders.update', 'orders.delete',
            'editing.view', 'editing.update',
            'albums.view', 'albums.create', 'albums.update', 'albums.delete',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'invoices.send', 'invoices.void',
            'payments.record', 'payments.delete',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'inventory.adjust-stock',
        ],
        TenantRole::Photographer->value => [
            'dashboard.view', 'customers.view', 'bookings.view', 'bookings.update', 'services.view', 'packages.view', 'orders.view',
            'albums.view', 'albums.create', 'albums.update',
            'inventory.view', 'inventory.adjust-stock',
        ],
        TenantRole::Editor->value => [
            'dashboard.view', 'customers.view', 'bookings.view', 'services.view', 'packages.view', 'orders.view', 'editing.view', 'editing.update',
            'albums.view', 'albums.update',
            'inventory.view', 'inventory.adjust-stock',
        ],
        TenantRole::Cashier->value => [
            'dashboard.view', 'customers.view', 'customers.create', 'bookings.view', 'services.view', 'packages.view', 'orders.view', 'orders.create',
            'albums.view',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send', 'invoices.void',
            'payments.record', 'payments.delete',
            'expenses.view', 'expenses.create', 'expenses.update',
            'inventory.view',
        ],
        TenantRole::Receptionist->value => [
            'dashboard.view', 'users.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.export',
            'bookings.view', 'bookings.create', 'bookings.update', 'bookings.assign', 'bookings.cancel',
            'services.view', 'packages.view',
            'orders.view', 'orders.create', 'orders.update',
            'albums.view', 'invoices.view',
            'inventory.view',
        ],
        TenantRole::Viewer->value => [
            'dashboard.view', 'customers.view', 'bookings.view', 'services.view', 'packages.view', 'orders.view', 'editing.view',
            'albums.view', 'invoices.view',
            'expenses.view', 'inventory.view',
        ],
    ],
];
