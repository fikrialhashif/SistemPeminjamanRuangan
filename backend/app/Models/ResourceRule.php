<?php

namespace App\Models;

use App\Enums\ApprovalPath;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceRule extends Model
{
    use Auditable;

    protected $fillable = [
        'resource_id',
        'buffer_minutes',
        'min_duration_minutes',
        'max_duration_minutes',
        'advance_booking_days',
        'approval_path',
        'cancellation_minutes_before',
    ];

    protected function casts(): array
    {
        return [
            'approval_path' => ApprovalPath::class,
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
