<?php

namespace App\Enums;

enum EditingStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case RevisionRequested = 'revision_requested';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::InReview => 'In Review',
            self::RevisionRequested => 'Revision Requested',
            self::Completed => 'Completed',
        };
    }
}
