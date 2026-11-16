<?php

namespace App\Enums;

enum ApprovalPath: string
{
    case NONE = 'none';
    case ONE_LEVEL = 'one_level';
    case TWO_LEVEL = 'two_level';
}
