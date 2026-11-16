<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceVerification extends Model
{
    protected $fillable = [
        'user_id',
        'check_in_attempt_id',
        'score',
        'liveness_passed',
        'challenge_type',
        'matched',
    ];

    protected function casts(): array
    {
        return [
            'liveness_passed' => 'boolean',
            'matched' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkInAttempt(): BelongsTo
    {
        return $this->belongsTo(CheckInAttempt::class);
    }
}
