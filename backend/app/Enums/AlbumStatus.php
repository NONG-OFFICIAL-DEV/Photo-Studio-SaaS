<?php

namespace App\Enums;

enum AlbumStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProgress => 'In Progress',
            self::Ready => 'Ready',
            self::Delivered => 'Delivered',
            self::Archived => 'Archived',
        };
    }
}
