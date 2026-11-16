<?php

namespace App\Models;

use App\Enums\CheckInResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckInAttempt extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'result',
        'qr_valid',
        'gps_valid',
        'face_valid',
        'gps_latitude',
        'gps_longitude',
        'gps_accuracy',
        'gps_timestamp',
        'face_score',
        'liveness_passed',
        'rejection_reasons',
        'override_by',
        'override_reason',
    ];

    protected function casts(): array
    {
        return [
            'result' => CheckInResult::class,
            'qr_valid' => 'boolean',
            'gps_valid' => 'boolean',
            'face_valid' => 'boolean',
            'liveness_passed' => 'boolean',
            'gps_timestamp' => 'datetime',
            'rejection_reasons' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }
}
