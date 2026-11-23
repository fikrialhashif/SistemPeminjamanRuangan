<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'name' => RoleEnum::class,
            'permissions' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
