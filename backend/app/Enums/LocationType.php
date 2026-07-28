<?php

namespace App\Enums;

enum LocationType: string
{
    case Studio = 'studio';
    case OnLocation = 'on_location';

    public function label(): string
    {
        return match ($this) {
            self::Studio => 'Studio',
            self::OnLocation => 'On Location',
        };
    }
}
