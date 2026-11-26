<?php

namespace App\Jobs;

use App\Enums\LoanStatus;
use App\Models\AssetLoan;
use App\Services\PenaltyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOverdueLoans implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PenaltyService $penaltyService): void
    {
        AssetLoan::whereIn('status', [LoanStatus::HANDED_OVER, LoanStatus::IN_USE])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->each(function (AssetLoan $loan) use ($penaltyService) {
                $alreadyStruck = $loan->user->strikes()
                    ->where('source_type', $loan->getMorphClass())
                    ->where('source_id', $loan->id)
                    ->where('reason', 'like', 'Overdue%')
                    ->exists();

                if ($alreadyStruck) {
                    return;
                }

                $penaltyService->issueStrike($loan->user, 'Overdue asset loan #'.$loan->id, $loan);

                SendNotification::dispatch(
                    $loan->user_id,
                    'loan_overdue',
                    'email',
                    [
                        'loan_id' => $loan->id,
                        'due_at' => $loan->due_at->toIso8601String(),
                    ],
                );
            });
    }
}
