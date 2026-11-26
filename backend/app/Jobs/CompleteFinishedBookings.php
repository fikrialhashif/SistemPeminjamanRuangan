<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompleteFinishedBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Asset bookings stay open until the asset is returned.
     */
    public function handle(): void
    {
        Booking::where('status', BookingStatus::CHECKED_IN)
            ->where('ends_at', '<=', now())
            ->whereDoesntHave('assetLoan', fn ($query) => $query->whereIn('status', [LoanStatus::HANDED_OVER, LoanStatus::IN_USE]))
            ->eachById(function (Booking $booking): void {
                $booking->update(['status' => BookingStatus::COMPLETED]);
                // Checked in but never collected: it can no longer be handed over.
                $booking->assetLoan()->where('status', LoanStatus::RESERVED)->first()?->update(['status' => LoanStatus::CLOSED]);
            });
    }
}
