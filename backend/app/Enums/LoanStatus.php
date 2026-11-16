<?php

namespace App\Enums;

enum LoanStatus: string
{
    case RESERVED = 'reserved';
    case HANDED_OVER = 'handed_over';
    case IN_USE = 'in_use';
    case RETURNED = 'returned';
    case CLOSED = 'closed';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RESERVED => [self::HANDED_OVER, self::CLOSED],
            self::HANDED_OVER => [self::IN_USE, self::RETURNED],
            self::IN_USE => [self::RETURNED],
            self::RETURNED => [self::CLOSED],
            self::CLOSED => [],
        };
    }
}
