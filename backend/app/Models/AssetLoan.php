<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetLoan extends Model
{
    use Auditable;

    protected $fillable = [
        'booking_id',
        'asset_id',
        'user_id',
        'status',
        'handed_over_at',
        'returned_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'handed_over_at' => 'datetime',
            'returned_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * An asset may only leave for a booking that is still approved or already checked in.
     */
    public function bookingAllowsHandover(): bool
    {
        return in_array($this->booking?->status, [BookingStatus::APPROVED, BookingStatus::CHECKED_IN], true);
    }

    public function markHandedOver(): void
    {
        $this->update([
            'status' => LoanStatus::HANDED_OVER,
            'handed_over_at' => $this->handed_over_at ?? now(),
        ]);

        $this->asset->update(['status' => 'in_use']);

        if ($this->booking?->status === BookingStatus::APPROVED) {
            $this->booking->update(['status' => BookingStatus::CHECKED_IN]);
        }
    }

    public function markReturned(string $condition): void
    {
        $this->update([
            'status' => LoanStatus::RETURNED,
            'returned_at' => now(),
        ]);

        $this->asset->update([
            'condition' => $condition,
            'status' => $condition === 'good' ? 'available' : $condition,
        ]);

        // Loans handed over before bookings were checked in on handover are still "approved".
        if (in_array($this->booking?->status, [BookingStatus::APPROVED, BookingStatus::CHECKED_IN], true)) {
            $this->booking->update(['status' => BookingStatus::COMPLETED]);
        }
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoanEvent::class);
    }

    public function conditionReports(): HasMany
    {
        return $this->hasMany(ConditionReport::class);
    }
}
