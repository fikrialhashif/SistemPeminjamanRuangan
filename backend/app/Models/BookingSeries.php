<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingSeries extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'recurrence_rule',
        'conflict_policy',
        'ends_at',
        'occurrences',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_rule' => 'array',
            'ends_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
