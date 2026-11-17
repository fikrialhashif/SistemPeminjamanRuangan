<?php

namespace App\Models;

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
