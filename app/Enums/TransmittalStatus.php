<?php

namespace App\Enums;

enum TransmittalStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::Acknowledged => 'Acknowledged',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
