<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Approval;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function approve(Approval $approval, User $approver, ?string $notes = null): void
    {
        $this->validatePending($approval);

        $approval->update([
            'approver_id' => $approver->id,
            'status' => 'approved',
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $booking = $approval->booking;
        $allApproved = $booking->approvals()
            ->where('status', '!=', 'approved')
            ->doesntExist();

        if ($allApproved) {
            $booking->update(['status' => BookingStatus::APPROVED]);
        }
    }

    public function reject(Approval $approval, User $approver, ?string $notes = null): void
    {
        $this->validatePending($approval);

        $approval->update([
            'approver_id' => $approver->id,
            'status' => 'rejected',
            'notes' => $notes,
            'decided_at' => now(),
        ]);

        $approval->booking->update(['status' => BookingStatus::REJECTED]);
    }

    public function checkExpiredApprovals(): void
    {
        $escalate = Setting::get('approval_escalate_on_expiry', false);

        Approval::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('booking')
            ->each(function (Approval $approval) use ($escalate) {
                if ($escalate) {
                    $expiryHours = Setting::get('approval_expiry_hours', 48);
                    $approval->update([
                        'expires_at' => now()->addHours($expiryHours),
                    ]);

                    return;
                }

                $approval->update([
                    'status' => 'expired',
                    'decided_at' => now(),
                ]);

                $approval->booking->update(['status' => BookingStatus::EXPIRED]);
            });
    }

    private function validatePending(Approval $approval): void
    {
        if ($approval->status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'This approval has already been decided.']);
        }
    }
}
