<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\PenaltyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireNoShowBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PenaltyService $penaltyService): void
    {
        $checkInWindow = Setting::get('check_in_window_minutes', 15);

        Booking::where('status', BookingStatus::APPROVED)
            ->where('starts_at', '<=', now()->subMinutes($checkInWindow))
            ->each(function (Booking $booking) use ($penaltyService) {
                $booking->update(['status' => BookingStatus::NO_SHOW]);
                $penaltyService->issueStrike($booking->user, 'No-show for booking #'.$booking->id, $booking);
            });
    }
}
