<?php

namespace App\Enums;

/**
 * What the reader did with a report.
 *
 * Previewing a report is not recorded: configuring and looking at a report is
 * not taking it onward, so a list built from these two cases is a list of
 * outputs rather than a list of clicks. The distinction matters because the two
 * are not equivalent — a presented report was read on screen and a downloaded
 * one left the system in a file.
 */
enum ReportTrigger: string
{
    case Present = 'present';
    case Download = 'download';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Presented',
            self::Download => 'Downloaded',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
