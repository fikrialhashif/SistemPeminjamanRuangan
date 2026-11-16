<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaceEnrollment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'consent_given_at',
        'status',
        'samples_count',
        'embedding_reference',
    ];

    protected function casts(): array
    {
        return [
            'consent_given_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
