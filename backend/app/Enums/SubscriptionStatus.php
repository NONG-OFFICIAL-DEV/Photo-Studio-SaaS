<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}
