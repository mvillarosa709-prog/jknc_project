<?php

namespace App\Enums;

/**
 * The formal Records Management outputs.
 *
 * Each case carries its own title and description so the card grid, the preview
 * heading and request validation all read from one place rather than repeating
 * six title/description pairs across a view and a controller.
 */
enum ReportType: string
{
    case Executive = 'executive';
    case Management = 'management';
    case Inventory = 'inventory';
    case Classification = 'classification';
    case Index = 'index';
    case Quality = 'quality';

    public function label(): string
    {
        return match ($this) {
            self::Executive => 'Executive Records Report',
            self::Management => 'Management Records Report',
            self::Inventory => 'Records Inventory Report',
            self::Classification => 'Classification Report',
            self::Index => 'Index Report',
            self::Quality => 'Records Quality Report',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Executive => 'Visual high-level summary for executives, directors and consultants.',
            self::Management => 'Operational records overview with classifications, sources and activity.',
            self::Inventory => 'Detailed register of classified records with selected columns.',
            self::Classification => 'Analyze records under one or more Classification Codes.',
            self::Index => 'Detailed reporting for a selected Index Code.',
            self::Quality => 'Completeness and quality of the records repository.',
        };
    }

    /**
     * A filename-safe stem, so a downloaded file is named after the report rather
     * than after its internal value.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Executive => 'executive-records-report',
            self::Management => 'management-records-report',
            self::Inventory => 'records-inventory-report',
            self::Classification => 'classification-report',
            self::Index => 'index-report',
            self::Quality => 'records-quality-report',
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
