<?php

namespace App\Enums;

enum OcrStatus: string
{
    case Queued = 'queued';
    case Reading = 'reading';
    case Detected = 'detected';
    case NeedsReview = 'needs_review';
    case NotRead = 'not_read';
    case Unsupported = 'unsupported';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Reading => 'Processing',
            self::Detected, self::Ready => 'Completed',
            self::NeedsReview, self::Unsupported => 'Failed',
            self::NotRead => 'Not read',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Detected, self::Ready => 'green',
            self::NeedsReview, self::Unsupported => 'amber',
            self::Reading, self::Queued => 'neutral',
            self::NotRead => 'neutral',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Detected, self::NeedsReview, self::Unsupported, self::NotRead, self::Ready => true,
            self::Queued, self::Reading => false,
        };
    }
}
