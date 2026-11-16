<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'user_id',
        'resource_id',
        'desired_start',
        'desired_end',
        'offered_at',
        'offer_expires_at',
        'claimed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'desired_start' => 'datetime',
            'desired_end' => 'datetime',
            'offered_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'claimed_at' => 'datetime',
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
}
