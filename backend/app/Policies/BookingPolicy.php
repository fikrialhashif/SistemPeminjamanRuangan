<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id || $user->isAdmin();
    }

    public function create(User $user, Resource $resource): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $hasAccessRules = $resource->accessRules()->exists();

        if (! $hasAccessRules) {
            return true;
        }

        return $resource->accessRules()
            ->where(function ($q) use ($user) {
                $q->whereIn('role_id', $user->roles()->pluck('roles.id'))
                    ->orWhere('department_id', $user->department_id);
            })
            ->exists();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->id !== $booking->user_id) {
            return false;
        }

        $rule = $booking->resource->rules;
        $minutesBefore = $rule?->cancellation_minutes_before ?? 0;

        return $booking->starts_at->subMinutes($minutesBefore)->isFuture();
    }

    public function checkIn(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id;
    }
}
