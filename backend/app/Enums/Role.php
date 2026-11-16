<?php

namespace App\Enums;

enum Role: string
{
    case MEMBER = 'member';
    case APPROVER = 'approver';
    case RESOURCE_MANAGER = 'resource_manager';
    case ADMIN = 'admin';
}
