<?php

namespace App\Enums;

/**
 * Baseline roles seeded for every tenant. Additional custom roles can be
 * created per tenant on top of these (Dynamic Permission Assignment).
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Photographer = 'photographer';
    case Editor = 'editor';
    case Cashier = 'cashier';
    case Receptionist = 'receptionist';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Photographer => 'Photographer',
            self::Editor => 'Editor',
            self::Cashier => 'Cashier',
            self::Receptionist => 'Receptionist',
            self::Viewer => 'Viewer',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
