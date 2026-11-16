<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConditionReport extends Model
{
    protected $fillable = [
        'asset_loan_id',
        'reporter_id',
        'condition',
        'notes',
        'photo_path',
    ];

    public function assetLoan(): BelongsTo
    {
        return $this->belongsTo(AssetLoan::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
