<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Strike;
use App\Models\Suspension;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PenaltyService
{
    public function issueStrike(User $user, string $reason, ?Model $source = null): Strike
    {
        $strike = Strike::create([
            'user_id' => $user->id,
            'reason' => $reason,
            'source_type' => $source ? $source->getMorphClass() : null,
            'source_id' => $source?->getKey(),
        ]);

        $this->checkAndSuspend($user);

        return $strike;
    }

    public function checkAndSuspend(User $user): ?Suspension
    {
        $windowDays = Setting::get('strike_window_days', 30);
        $threshold = Setting::get('strike_threshold', 3);
        $suspensionDays = Setting::get('suspension_duration_days', 7);

        $activeStrikes = Strike::where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->count();

        if ($activeStrikes < $threshold) {
            return null;
        }

        $alreadySuspended = Suspension::where('user_id', $user->id)
            ->where('ends_at', '>=', now())
            ->exists();

        if ($alreadySuspended) {
            return null;
        }

        return Suspension::create([
            'user_id' => $user->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays($suspensionDays),
            'reason' => "Accumulated {$activeStrikes} strikes within {$windowDays} days",
        ]);
    }

    public function cancelStrike(Strike $strike, User $admin, string $reason): void
    {
        $strike->update([
            'cancelled_at' => now(),
            'cancelled_by' => $admin->id,
            'cancel_reason' => $reason,
        ]);
    }
}
