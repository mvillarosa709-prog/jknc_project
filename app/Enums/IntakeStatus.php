<?php

namespace App\Enums;

enum IntakeStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyToFile = 'ready_to_file';
    case NeedsReview = 'needs_review';
    case Filed = 'filed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::ReadyToFile => 'Ready to file',
            self::NeedsReview => 'Needs review',
            self::Filed => 'Filed',
            self::Discarded => 'Discarded',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ReadyToFile, self::Filed => 'green',
            self::NeedsReview => 'amber',
            self::Processing => 'blue',
            self::Pending, self::Discarded => 'neutral',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Filed, self::Discarded], true);
    }
}
