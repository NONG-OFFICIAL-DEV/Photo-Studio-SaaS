<?php

namespace App\Enums;

enum BookingType: string
{
    case Wedding = 'wedding';
    case Portrait = 'portrait';
    case Family = 'family';
    case Product = 'product';
    case Passport = 'passport';
    case Event = 'event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wedding => 'Wedding',
            self::Portrait => 'Portrait',
            self::Family => 'Family',
            self::Product => 'Product',
            self::Passport => 'Passport',
            self::Event => 'Event',
            self::Other => 'Other',
        };
    }
}
