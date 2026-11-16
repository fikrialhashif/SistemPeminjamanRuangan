<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanEvent extends Model
{
    protected $fillable = [
        'asset_loan_id',
        'event_type',
        'actor_id',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function assetLoan(): BelongsTo
    {
        return $this->belongsTo(AssetLoan::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
