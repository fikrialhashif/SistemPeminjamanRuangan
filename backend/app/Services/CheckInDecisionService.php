<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CheckInResult;
use App\Enums\ResourceType;
use App\Models\Booking;
use App\Models\CheckInAttempt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CheckInDecisionService
{
    public function __construct(
        protected QrTokenService $qrTokenService,
        protected GpsValidator $gpsValidator,
        protected FaceVerificationService $faceVerificationService,
        protected PenaltyService $penaltyService,
    ) {}

    public function process(Booking $booking, User $user, array $evidence): CheckInAttempt
    {
        $this->validateOwnership($booking, $user);
        $this->validateTimeWindow($booking);

        $rejectionReasons = [];

        $qrResult = $this->qrTokenService->validate(
            $evidence['qr_token'],
            $booking->resource_id,
            $booking->id,
        );
        $qrValid = $qrResult['valid'];
        if (! $qrValid) {
            $rejectionReasons[] = 'qr: '.$qrResult['reason'];
        }

        $booking->loadMissing('resource.location');
        $location = $booking->resource->location;

        $gpsResult = $this->gpsValidator->validate(
            $location,
            (float) $evidence['gps_latitude'],
            (float) $evidence['gps_longitude'],
            (float) $evidence['gps_accuracy'],
            $evidence['gps_timestamp'],
            (bool) $evidence['gps_is_mock'],
            $user->id,
        );
        $gpsValid = $gpsResult['valid'];
        if (! $gpsValid) {
            $rejectionReasons = array_merge($rejectionReasons, array_map(fn ($r) => 'gps: '.$r, $gpsResult['reasons']));
        }

        $faceValid = true;
        $faceScore = null;
        $livenessPassed = null;
        $faceEnabled = $booking->resource->type === ResourceType::ROOM
            || Setting::get('face_verification_enabled', true);

        if ($faceEnabled) {
            $faceImage = $evidence['face_image'] ?? null;
            $challenge = $evidence['face_challenge'] ?? null;
            $challengeResponse = $evidence['face_challenge_response'] ?? null;

            $expectedChallenge = Cache::pull("face_challenge:{$user->id}");

            if (! $faceImage) {
                $faceValid = false;
                $rejectionReasons[] = 'face: image not provided';
            } elseif (! $expectedChallenge) {
                $faceValid = false;
                $rejectionReasons[] = 'face: challenge missing';
            } elseif ($challenge !== null && $challenge !== $expectedChallenge) {
                $faceValid = false;
                $rejectionReasons[] = 'face: challenge mismatch';
            } elseif (! $user->faceEnrollment || $user->faceEnrollment->status !== 'enrolled') {
                $faceValid = false;
                $rejectionReasons[] = 'face: user not enrolled';
            } else {
                try {
                    $faceResult = $this->faceVerificationService->verify(
                        $user,
                        $faceImage,
                        $expectedChallenge,
                        $challengeResponse,
                    );
                    $faceScore = $faceResult['score'] ?? null;
                    $livenessPassed = $faceResult['liveness_passed'] ?? null;

                    if (($faceResult['match'] ?? false) === false) {
                        $faceValid = false;
                        $rejectionReasons[] = $livenessPassed === false
                            ? 'face: liveness check failed'
                            : 'face: verification failed';
                    }

                    if ($faceResult['inconclusive'] ?? false) {
                        $faceValid = null;
                    }
                } catch (RequestException $error) {
                    if ($error->response?->status() === 404) {
                        $faceValid = false;
                        $rejectionReasons[] = 'face: user not enrolled';
                    } else {
                        $faceValid = null;
                        $rejectionReasons[] = 'face: service unavailable';
                    }
                } catch (ConnectionException) {
                    $faceValid = null;
                    $rejectionReasons[] = 'face: service unavailable';
                }
            }
        }

        $result = $this->determineResult($qrValid, $gpsValid, $faceValid);

        $attempt = CheckInAttempt::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'result' => $result,
            'qr_valid' => $qrValid,
            'gps_valid' => $gpsValid,
            'face_valid' => $faceValid,
            'gps_latitude' => $evidence['gps_latitude'],
            'gps_longitude' => $evidence['gps_longitude'],
            'gps_accuracy' => $evidence['gps_accuracy'],
            'gps_timestamp' => $evidence['gps_timestamp'],
            'face_score' => $faceScore,
            'liveness_passed' => $livenessPassed,
            'rejection_reasons' => ! empty($rejectionReasons) ? $rejectionReasons : null,
        ]);

        if ($result === CheckInResult::ACCEPTED) {
            $booking->update(['status' => BookingStatus::CHECKED_IN]);
        }

        if ($result === CheckInResult::REJECTED) {
            $this->handleRepeatedFailures($booking, $user);
        }

        return $attempt;
    }

    private function validateOwnership(Booking $booking, User $user): void
    {
        if ($booking->user_id !== $user->id) {
            throw ValidationException::withMessages(['booking' => 'This booking does not belong to you.']);
        }

        if ($booking->status !== BookingStatus::APPROVED) {
            $message = $booking->status === BookingStatus::PENDING
                ? 'Booking belum disetujui oleh approver.'
                : 'Booking is not in a check-in-able state.';

            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function validateTimeWindow(Booking $booking): void
    {
        $beforeMinutes = Setting::get('checkin_window_before_minutes', 15);
        $afterMinutes = Setting::get('checkin_window_after_minutes', 15);

        $windowStart = $booking->starts_at->copy()->subMinutes($beforeMinutes);
        $windowEnd = $booking->starts_at->copy()->addMinutes($afterMinutes);

        if (now()->lt($windowStart) || now()->gt($windowEnd)) {
            throw ValidationException::withMessages([
                'time' => 'Check-in is only allowed within the designated time window.',
            ]);
        }
    }

    private function determineResult(bool $qrValid, bool $gpsValid, ?bool $faceValid): CheckInResult
    {
        if ($faceValid === null) {
            return CheckInResult::NEEDS_MANUAL_REVIEW;
        }

        if ($qrValid && $gpsValid && $faceValid) {
            return CheckInResult::ACCEPTED;
        }

        return CheckInResult::REJECTED;
    }

    private function handleRepeatedFailures(Booking $booking, User $user): void
    {
        $threshold = Setting::get('checkin_failure_strike_threshold', 3);

        $recentFailures = CheckInAttempt::where('booking_id', $booking->id)
            ->where('user_id', $user->id)
            ->where('result', CheckInResult::REJECTED)
            ->count();

        if ($recentFailures >= $threshold) {
            $this->penaltyService->issueStrike(
                $user,
                "Repeated check-in failures for booking #{$booking->id}",
                $booking,
            );
        }
    }
}
