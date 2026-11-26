<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use Auditable;

    /**
     * Statuses that no longer block the next level. "escalated" means the level
     * timed out and the decision moved up.
     */
    public const CLEARED_STATUSES = ['approved', 'escalated'];

    protected $fillable = [
        'booking_id',
        'level',
        'approver_id',
        'status',
        'notes',
        'decided_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Undecided approvals of bookings that are still waiting; cancelled or expired bookings drop out.
     */
    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query
            ->where('status', 'pending')
            ->whereRelation('booking', 'status', BookingStatus::PENDING);
    }

    /**
     * Awaiting approvals whose earlier levels no longer block them.
     */
    public function scopeActionable(Builder $query): Builder
    {
        return $query
            ->awaitingDecision()
            ->whereNotExists(function ($subquery) {
                $subquery->selectRaw('1')
                    ->from('approvals as previous')
                    ->whereColumn('previous.booking_id', 'approvals.booking_id')
                    ->whereColumn('previous.level', '<', 'approvals.level')
                    ->whereNotIn('previous.status', self::CLEARED_STATUSES);
            });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
