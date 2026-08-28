<?php

namespace App\Enums;

enum PlanFeatureValueType: string
{
    case Boolean = 'boolean';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Yes / No',
            self::Text => 'Text',
        };
    }
}
