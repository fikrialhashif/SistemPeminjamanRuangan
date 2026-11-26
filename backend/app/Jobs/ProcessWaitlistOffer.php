<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWaitlistOffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $resourceId,
        public string $desiredStart,
        public string $desiredEnd,
    ) {}

    public function handle(): void
    {
        // A slot that already started cannot be booked, so offering it is pointless.
        if (Carbon::parse($this->desiredStart)->isPast()) {
            return;
        }

        $entry = WaitlistEntry::where('resource_id', $this->resourceId)
            ->where('desired_start', $this->desiredStart)
            ->where('desired_end', $this->desiredEnd)
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->first();

        if (! $entry) {
            return;
        }

        if ($entry->status !== 'waiting') {
            return;
        }

        $offerMinutes = Setting::get('waitlist_offer_minutes', 30);

        $entry->update([
            'status' => 'offered',
            'offered_at' => now(),
            'offer_expires_at' => now()->addMinutes($offerMinutes),
        ]);

        SendNotification::dispatch(
            $entry->user_id,
            'waitlist_offer',
            'email',
            [
                'resource_id' => $entry->resource_id,
                'desired_start' => $entry->desired_start,
                'desired_end' => $entry->desired_end,
                'offer_expires_at' => $entry->offer_expires_at,
            ],
        );
    }
}
