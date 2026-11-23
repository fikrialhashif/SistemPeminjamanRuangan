<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Enums\ResourceType;
use App\Models\Approval;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function approve(Approval $approval, User $approver, ?string $notes = null): void
    {
        $this->validatePending($approval);
        $this->validatePreviousLevel($approval);

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
            $this->ensureAssetLoan($booking);
        }
    }

    private function ensureAssetLoan(Booking $booking): void
    {
        $resource = $booking->resource;

        if ($resource === null || $resource->type !== ResourceType::ASSET || $resource->asset === null) {
            return;
        }

        AssetLoan::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'asset_id' => $resource->asset->id,
                'user_id' => $booking->user_id,
                'status' => LoanStatus::RESERVED,
                'due_at' => $booking->ends_at,
            ],
        );
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

    private function validatePreviousLevel(Approval $approval): void
    {
        $hasUnapprovedPreviousLevel = $approval->booking->approvals()
            ->where('level', '<', $approval->level)
            ->where('status', '!=', 'approved')
            ->exists();

        if ($hasUnapprovedPreviousLevel) {
            throw ValidationException::withMessages([
                'approval' => 'Setujui tingkat sebelumnya terlebih dahulu.',
            ]);
        }
    }
}
