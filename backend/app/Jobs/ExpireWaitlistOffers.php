<?php

namespace App\Jobs;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireWaitlistOffers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * An unclaimed offer passes to the next person waiting for the same slot.
     */
    public function handle(): void
    {
        WaitlistEntry::where('status', 'offered')
            ->where('offer_expires_at', '<=', now())
            ->eachById(function (WaitlistEntry $entry): void {
                $entry->update(['status' => 'expired']);

                ProcessWaitlistOffer::dispatch(
                    $entry->resource_id,
                    $entry->desired_start->toDateTimeString(),
                    $entry->desired_end->toDateTimeString(),
                );
            });
    }
}
