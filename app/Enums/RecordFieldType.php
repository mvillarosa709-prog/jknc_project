<?php

namespace App\Enums;

enum RecordFieldType: string
{
    case Text = 'Text';
    case LongText = 'Long Text';
    case Number = 'Number';
    case Amount = 'Amount';
    case Currency = 'Currency';
    case Date = 'Date';
    case Dropdown = 'Dropdown';
    case YesNo = 'Yes / No';
    case Tags = 'Tags';

    /**
     * The values offered by the Record Fields setup form.
     *
     * Read from the enum rather than hardcoded in Blade, so a new type is added
     * in one place and the form follows it automatically.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return $this->value;
    }
}
