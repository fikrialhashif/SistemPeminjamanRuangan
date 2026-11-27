<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
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
        // Never mark a no-show while the user is still allowed to check in.
        $noShowAfter = Setting::get('checkin_window_after_minutes', 15);

        Booking::where('status', BookingStatus::APPROVED)
            ->where('starts_at', '<', now()->subMinutes($noShowAfter))
            ->whereDoesntHave('assetLoan', fn ($query) => $query->where('status', '!=', LoanStatus::RESERVED))
            ->eachById(function (Booking $booking) use ($penaltyService) {
                $booking->update(['status' => BookingStatus::NO_SHOW]);
                $penaltyService->issueStrike($booking->user, 'No-show for booking #'.$booking->id, $booking);
            });
    }
}
