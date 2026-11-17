<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use Auditable;

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

    public function scopeActionable(Builder $query): Builder
    {
        return $query
            ->where('status', 'pending')
            ->whereNotExists(function ($subquery) {
                $subquery->selectRaw('1')
                    ->from('approvals as previous')
                    ->whereColumn('previous.booking_id', 'approvals.booking_id')
                    ->whereColumn('previous.level', '<', 'approvals.level')
                    ->where('previous.status', '!=', 'approved');
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
