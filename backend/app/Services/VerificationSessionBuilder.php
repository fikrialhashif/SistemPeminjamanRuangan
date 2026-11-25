<?php

namespace App\Services;

use App\Data\VerificationSession;
use App\Data\VerificationStepOutcome;
use App\Enums\VerificationStage;
use App\Models\CheckInAttempt;

class VerificationSessionBuilder
{
    public function forHandover(int $loanId, array $outcomes): VerificationSession
    {
        return new VerificationSession('asset_handover', $loanId, [
            VerificationStage::LOCATION->value => $outcomes['location'],
            VerificationStage::QR->value => $outcomes['qr'],
            VerificationStage::FACE->value => $outcomes['face'],
            VerificationStage::TIME->value => $outcomes['time'],
        ]);
    }

    public function forCheckInAttempt(CheckInAttempt $attempt): VerificationSession
    {
        $timeReason = null;

        foreach ($attempt->rejection_reasons ?? [] as $reason) {
            $reason = (string) $reason;

            if (str_starts_with(strtolower($reason), 'time:')) {
                $timeReason = trim(substr($reason, 5));

                break;
            }
        }

        $faceValid = $attempt->face_valid;

        return new VerificationSession('booking_check_in', $attempt->booking_id, [
            VerificationStage::LOCATION->value => new VerificationStepOutcome(
                VerificationStage::LOCATION,
                (bool) $attempt->gps_valid,
                $attempt->gps_valid ? null : 'Lokasi tidak memenuhi pemeriksaan server.',
            ),
            VerificationStage::QR->value => new VerificationStepOutcome(
                VerificationStage::QR,
                (bool) $attempt->qr_valid,
                $attempt->qr_valid ? null : 'QR tidak sesuai dengan booking.',
            ),
            VerificationStage::FACE->value => new VerificationStepOutcome(
                VerificationStage::FACE,
                $faceValid === true,
                $faceValid === null ? 'Verifikasi wajah memerlukan tinjauan petugas.' : null,
            ),
            VerificationStage::TIME->value => new VerificationStepOutcome(
                VerificationStage::TIME,
                $timeReason === null,
                $timeReason,
            ),
        ]);
    }
}
