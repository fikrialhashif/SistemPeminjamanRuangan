<?php

namespace App\Jobs;

use App\Models\Approval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpirePendingApprovals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Approval::awaitingDecision()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->eachById(function (Approval $approval) {
                $nextLevel = Approval::where('booking_id', $approval->booking_id)
                    ->where('level', '>', $approval->level)
                    ->where('status', 'pending')
                    ->exists();

                if ($nextLevel) {
                    $approval->update([
                        'status' => 'escalated',
                        'decided_at' => now(),
                    ]);
                } else {
                    $approval->update([
                        'status' => 'expired',
                        'decided_at' => now(),
                    ]);

                    $approval->booking->update([
                        'status' => 'expired',
                    ]);
                }
            });
    }
}
