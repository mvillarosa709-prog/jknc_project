<?php

namespace App\Enums;

enum FileRole: string
{
    case Master = 'master';
    case Supporting = 'supporting';

    public function label(): string
    {
        return match ($this) {
            self::Master => 'Master File',
            self::Supporting => 'Supporting File',
        };
    }

    /**
     * Supporting files and versions add storage but never add another Record Unit.
     */
    public function recordUnits(): int
    {
        return 0;
    }
}
