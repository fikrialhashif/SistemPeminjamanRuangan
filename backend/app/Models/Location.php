<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use Auditable;

    public const DEFAULT_OPEN_TIME = '07:00';

    public const DEFAULT_CLOSE_TIME = '22:00';

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'radius',
        'polygon',
        'open_time',
        'close_time',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'polygon' => 'array',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
