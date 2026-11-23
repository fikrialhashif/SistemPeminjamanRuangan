<?php

namespace App\Http\Controllers;

use App\Enums\Permission;

abstract class Controller
{
    protected function ensurePermission(Permission|string $permission): void
    {
        abort_unless(
            auth()->user()->hasPermission($permission),
            403,
            'Anda tidak punya izin untuk melakukan aksi ini.',
        );
    }
}
