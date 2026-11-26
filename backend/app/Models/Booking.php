<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Jobs\ProcessWaitlistOffer;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use Auditable;

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

    protected static function booted(): void
    {
        // Covers every way a slot is released: cancel, reject, approval expiry and no-show.
        static::updated(function (Booking $booking): void {
            if (! $booking->wasChanged('status') || ! $booking->wasReleased()) {
                return;
            }

            $booking->assetLoan()
                ->where('status', LoanStatus::RESERVED)
                ->get()
                ->each(fn (AssetLoan $loan) => $loan->update(['status' => LoanStatus::CLOSED]));

            if ($booking->getOriginal('status')->holdsSlot()) {
                ProcessWaitlistOffer::dispatch(
                    $booking->resource_id,
                    $booking->starts_at->toDateTimeString(),
                    $booking->ends_at->toDateTimeString(),
                )->afterCommit();
            }
        });
    }

    private function wasReleased(): bool
    {
        return in_array($this->status, [
            BookingStatus::CANCELLED,
            BookingStatus::REJECTED,
            BookingStatus::EXPIRED,
            BookingStatus::NO_SHOW,
        ], true);
    }

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
