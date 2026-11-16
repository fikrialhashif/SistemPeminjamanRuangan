<?php

namespace App\Enums;

enum CheckInResult: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case NEEDS_MANUAL_REVIEW = 'needs_manual_review';
}
