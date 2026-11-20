<?php

namespace App\Models;

use App\Enums\ResourceType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'location_id',
        'category_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function room(): HasOne
    {
        return $this->hasOne(Room::class);
    }

    public function asset(): HasOne
    {
        return $this->hasOne(Asset::class);
    }

    public function rules(): HasOne
    {
        return $this->hasOne(ResourceRule::class);
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(ResourceAccess::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
