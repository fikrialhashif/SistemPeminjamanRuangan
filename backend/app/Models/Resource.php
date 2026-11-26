<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\ResourceType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'location_id',
        'category_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resources without access rules are open to everyone; admins see everything.
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->whereDoesntHave('accessRules')
                ->orWhereHas('accessRules', function (Builder $rules) use ($user): void {
                    $rules->where(function (Builder $rules) use ($user): void {
                        $rules->whereIn('role_id', $user->roles()->pluck('roles.id'));

                        // Matching a null department would compare with IS NULL and open every role rule.
                        if ($user->department_id !== null) {
                            $rules->orWhere('department_id', $user->department_id);
                        }
                    });
                });
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->accessibleBy($user)->exists();
    }

    /**
     * Bookings that still hold a slot now or later.
     */
    public function hasActiveBookings(): bool
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::PENDING, BookingStatus::APPROVED, BookingStatus::CHECKED_IN])
            ->where('ends_at', '>=', now())
            ->exists();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function room(): HasOne
    {
        return $this->hasOne(Room::class);
    }

    public function asset(): HasOne
    {
        return $this->hasOne(Asset::class);
    }

    public function rules(): HasOne
    {
        return $this->hasOne(ResourceRule::class);
    }

    public function accessRules(): HasMany
    {
        return $this->hasMany(ResourceAccess::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
