<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'created',
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'old_values' => null,
                'new_values' => $model->auditableValues(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });

        static::updated(function ($model) {
            $oldValues = collect($model->getOriginal())
                ->only(array_keys($model->getDirty()))
                ->except($model->getHidden())
                ->toArray();

            $newValues = collect($model->getDirty())
                ->except($model->getHidden())
                ->toArray();

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'updated',
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });

        static::deleted(function ($model) {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'deleted',
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'old_values' => $model->auditableValues(),
                'new_values' => null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        });
    }

    public function auditableValues(): array
    {
        return collect($this->getAttributes())
            ->except($this->getHidden())
            ->all();
    }
}
