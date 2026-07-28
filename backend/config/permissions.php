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
        ],
        TenantRole::Photographer->value => ['dashboard.view', 'customers.view', 'bookings.view', 'bookings.update'],
        TenantRole::Editor->value => ['dashboard.view', 'customers.view', 'bookings.view'],
        TenantRole::Cashier->value => ['dashboard.view', 'customers.view', 'customers.create', 'bookings.view'],
        TenantRole::Receptionist->value => [
            'dashboard.view', 'users.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.export',
            'bookings.view', 'bookings.create', 'bookings.update', 'bookings.assign', 'bookings.cancel',
        ],
        TenantRole::Viewer->value => ['dashboard.view', 'customers.view', 'bookings.view'],
    ],
];
