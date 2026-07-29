<?php

namespace App\Enums;

enum PayType: string
{
    case Salary = 'salary';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Hourly => 'Hourly',
        };
    }
}
