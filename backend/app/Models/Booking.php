<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'booking_series_id',
        'title',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => BookingStatus::class,
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

    public function series(): BelongsTo
    {
        return $this->belongsTo(BookingSeries::class, 'booking_series_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function checkInAttempts(): HasMany
    {
        return $this->hasMany(CheckInAttempt::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function assetLoan(): HasOne
    {
        return $this->hasOne(AssetLoan::class);
    }

    public function holdsSlot(): bool
    {
        return $this->status->holdsSlot();
    }
}
