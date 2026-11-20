<?php

namespace App\Enums;

enum RecordStatus: string
{
    case Filed = 'filed';
    case Draft = 'draft';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Filed => 'Active',
            self::Draft => 'Draft',
            self::Archived => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Filed => 'green',
            self::Draft => 'amber',
            self::Archived => 'neutral',
        };
    }
}
