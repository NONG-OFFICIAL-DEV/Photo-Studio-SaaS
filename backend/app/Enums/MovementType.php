<?php

namespace App\Enums;

enum MovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::StockOut => 'Stock Out',
        };
    }
}
