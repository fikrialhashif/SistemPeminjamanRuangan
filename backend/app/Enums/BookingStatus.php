<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case CHECKED_IN = 'checked_in';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case NO_SHOW = 'no_show';
    case WAITLISTED = 'waitlisted';

    public function holdsSlot(): bool
    {
        return match ($this) {
            self::PENDING, self::APPROVED, self::CHECKED_IN => true,
            default => false,
        };
    }
}
