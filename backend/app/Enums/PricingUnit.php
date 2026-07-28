<?php

namespace App\Enums;

enum PricingUnit: string
{
    case Fixed = 'fixed';
    case PerHour = 'per_hour';
    case PerPerson = 'per_person';
    case PerPhoto = 'per_photo';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed Price',
            self::PerHour => 'Per Hour',
            self::PerPerson => 'Per Person',
            self::PerPhoto => 'Per Photo',
        };
    }
}
