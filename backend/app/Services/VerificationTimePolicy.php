<?php

namespace App\Services;

use App\Data\VerificationStepOutcome;
use App\Enums\VerificationStage;
use App\Models\Booking;
use App\Models\Setting;

class VerificationTimePolicy
{
    public function evaluateForBooking(Booking $booking): VerificationStepOutcome
    {
        $beforeMinutes = Setting::get('checkin_window_before_minutes', 15);
        $afterMinutes = Setting::get('checkin_window_after_minutes', 15);

        $windowStart = $booking->starts_at->copy()->subMinutes($beforeMinutes);
        $windowEnd = $booking->starts_at->copy()->addMinutes($afterMinutes);
        $now = now();

        if ($now->lt($windowStart) || $now->gt($windowEnd)) {
            return new VerificationStepOutcome(
                VerificationStage::TIME,
                false,
                'Waktu verifikasi di luar rentang yang diizinkan.',
            );
        }

        return new VerificationStepOutcome(VerificationStage::TIME, true);
    }
}
