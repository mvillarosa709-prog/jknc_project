<?php

namespace App\Support;

class FileSize
{
    private const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    /**
     * Formats a byte count without relying on the intl extension, which is not
     * installed in this environment. Matches the ORDO mock's humanSize().
     */
    public static function humanize(?int $bytes): string
    {
        if (! $bytes || $bytes <= 0) {
            return '—';
        }

        $power = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $power < count(self::UNITS) - 1) {
            $value /= 1024;
            $power++;
        }

        return number_format($value, $power > 0 ? 1 : 0).' '.self::UNITS[$power];
    }
}
