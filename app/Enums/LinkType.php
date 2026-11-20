<?php

namespace App\Enums;

enum LinkType: string
{
    case Related = 'related';
    case Supporting = 'supporting';
    case Origin = 'origin';

    public function label(): string
    {
        return match ($this) {
            self::Related => 'Related Record',
            self::Supporting => 'Supporting Record',
            self::Origin => 'Origin Record',
        };
    }
}
