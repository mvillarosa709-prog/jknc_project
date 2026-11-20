<?php

namespace App\Enums;

enum SourceType: string
{
    case GovernmentAgency = 'Government Agency';
    case Bank = 'Bank';
    case Internal = 'Internal';
    case ExternalParty = 'External Party';
    case ProfessionalServiceProvider = 'Professional Service Provider';
    case Other = 'Other';

    /**
     * The values offered by the Sources setup form.
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
