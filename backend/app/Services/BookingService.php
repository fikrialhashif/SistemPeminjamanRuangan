<?php

namespace App\Services;

use App\Enums\ApprovalPath;
use App\Enums\BookingStatus;
use App\Enums\ResourceType;
use App\Exceptions\ConflictException;
use App\Exceptions\FaceEnrollmentRequiredException;
use App\Models\Approval;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Setting;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    private const MAX_RETRIES = 3;

    public function __construct(
        protected OperatingHoursResolver $operatingHoursResolver,
    ) {}

    public function create(User $user, array $data): Booking
    {
        $resource = Resource::with(['rules', 'location'])->findOrFail($data['resource_id']);

        $this->validateResourceActive($resource);
        $this->validateUserAccess($user, $resource);
        $this->validateFaceEnrollment($user, $resource);
        $this->validateDuration($resource, $data);
        $this->validateOperatingHours($resource, $data);

        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                return DB::transaction(function () use ($user, $data, $resource) {
                    DB::table('resources')->where('id', $resource->id)->lockForUpdate()->first();

                    // Stored times are UTC; an offset in the request must not shift them.
                    $startsAt = Carbon::parse($data['starts_at'])->utc();
                    $endsAt = Carbon::parse($data['ends_at'])->utc();
                    $buffer = $resource->rules?->buffer_minutes ?? 0;

                    $slotHoldingStatuses = collect(BookingStatus::cases())
                        ->filter(fn (BookingStatus $s) => $s->holdsSlot())
                        ->map(fn (BookingStatus $s) => $s->value)
                        ->values()
                        ->all();

                    $conflict = Booking::where('resource_id', $resource->id)
                        ->whereIn('status', $slotHoldingStatuses)
                        ->where('starts_at', '<', $endsAt->copy()->addMinutes($buffer))
                        ->where('ends_at', '>', $startsAt->copy()->subMinutes($buffer))
                        ->exists();

                    if ($conflict) {
                        throw new ConflictException('Time slot is already booked.');
                    }

                    $approvalPath = $resource->rules?->approval_path ?? ApprovalPath::NONE;
                    $effectiveApprovalPath = $approvalPath === ApprovalPath::NONE
                        ? ApprovalPath::ONE_LEVEL
                        : $approvalPath;
                    $status = BookingStatus::PENDING;

                    $booking = Booking::create([
                        'user_id' => $user->id,
                        'resource_id' => $resource->id,
                        'booking_series_id' => $data['booking_series_id'] ?? null,
                        'title' => $data['title'] ?? null,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => $status,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $this->createApprovals($booking, $effectiveApprovalPath);

                    return $booking;
                });
            } catch (ConflictException $e) {
                throw $e;
            } catch (DeadlockException $e) {
                if ($attempt >= self::MAX_RETRIES) {
                    throw $e;
                }
                usleep($attempt * 100_000);
            }
        }
    }

    public function cancel(User $user, Booking $booking): void
    {
        if ($booking->user_id !== $user->id && ! $user->isAdmin()) {
            throw ValidationException::withMessages(['booking' => 'You cannot cancel this booking.']);
        }

        if (! in_array($booking->status, [BookingStatus::PENDING, BookingStatus::APPROVED])) {
            throw ValidationException::withMessages(['status' => 'Booking cannot be cancelled in its current state.']);
        }

        if (! $user->isAdmin() && $booking->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'deadline' => 'Pemesanan yang sudah dimulai tidak bisa dibatalkan.',
            ]);
        }

        $rules = $booking->resource?->rules;
        $deadline = $rules?->cancellation_minutes_before;

        if ($deadline && ! $user->isAdmin() && $booking->starts_at->diffInMinutes(now(), false) > -$deadline) {
            throw ValidationException::withMessages([
                'deadline' => "Cancellation must be at least {$deadline} minutes before start.",
            ]);
        }

        $booking->update([
            'status' => BookingStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Claims a waitlist offer by booking the offered slot.
     */
    public function claimWaitlistOffer(User $user, WaitlistEntry $entry): void
    {
        // A conditional update lets only one of two simultaneous claims go on to book.
        $claimed = WaitlistEntry::whereKey($entry->id)
            ->where('status', 'offered')
            ->update(['status' => 'claimed', 'claimed_at' => now()]);

        if (! $claimed) {
            throw ValidationException::withMessages(['waitlist' => 'Waitlist offer is not claimable.']);
        }

        try {
            $this->create($user, [
                'resource_id' => $entry->resource_id,
                'starts_at' => $entry->desired_start->toIso8601String(),
                'ends_at' => $entry->desired_end->toIso8601String(),
            ]);
        } catch (\Throwable $error) {
            WaitlistEntry::whereKey($entry->id)->update(['status' => 'offered', 'claimed_at' => null]);

            throw $error;
        }
    }

    private function validateResourceActive(Resource $resource): void
    {
        if (! $resource->is_active) {
            throw ValidationException::withMessages(['resource' => 'Resource is not active.']);
        }
    }

    private function validateUserAccess(User $user, Resource $resource): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['user' => 'Your account is currently suspended.']);
        }

        if (! $resource->isAccessibleBy($user)) {
            throw ValidationException::withMessages(['access' => 'You do not have access to this resource.']);
        }
    }

    private function validateFaceEnrollment(User $user, Resource $resource): void
    {
        if ($resource->type !== ResourceType::ROOM) {
            return;
        }

        // Room check-in always verifies the face, whatever the asset setting says.
        if ($user->faceEnrollment?->status !== 'enrolled') {
            throw new FaceEnrollmentRequiredException;
        }
    }

    private function validateDuration(Resource $resource, array $data): void
    {
        $rules = $resource->rules;
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $durationMinutes = $startsAt->diffInMinutes($endsAt);

        if ($endsAt <= $startsAt) {
            throw ValidationException::withMessages(['duration' => 'End time must be after start time.']);
        }

        if ($rules?->min_duration_minutes && $durationMinutes < $rules->min_duration_minutes) {
            throw ValidationException::withMessages([
                'duration' => "Minimum duration is {$rules->min_duration_minutes} minutes.",
            ]);
        }

        if ($rules?->max_duration_minutes && $durationMinutes > $rules->max_duration_minutes) {
            throw ValidationException::withMessages([
                'duration' => "Maximum duration is {$rules->max_duration_minutes} minutes.",
            ]);
        }

        if ($rules?->advance_booking_days) {
            $maxAdvance = now()->addDays($rules->advance_booking_days);
            if ($startsAt->gt($maxAdvance)) {
                throw ValidationException::withMessages([
                    'advance' => "Bookings can only be made up to {$rules->advance_booking_days} days in advance.",
                ]);
            }
        }

        if ($startsAt->lt(now())) {
            throw ValidationException::withMessages(['starts_at' => 'Cannot book in the past.']);
        }
    }

    /**
     * Operating hours apply to rooms only: an asset loan may span several days.
     */
    private function validateOperatingHours(Resource $resource, array $data): void
    {
        if ($resource->type !== ResourceType::ROOM) {
            return;
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $hours = $this->operatingHoursResolver->find($resource, $startsAt->copy()->setTimezone('Asia/Jakarta'));

        if ($hours === null) {
            return;
        }

        if ($startsAt->lt($hours->start) || Carbon::parse($data['ends_at'])->gt($hours->end)) {
            $open = $hours->start->copy()->setTimezone('Asia/Jakarta')->format('H:i');
            $close = $hours->end->copy()->setTimezone('Asia/Jakarta')->format('H:i');

            throw ValidationException::withMessages([
                'starts_at' => "Waktu pemesanan harus di dalam jam operasional ({$open}-{$close} WIB).",
            ]);
        }
    }

    private function createApprovals(Booking $booking, ApprovalPath $path): void
    {
        $expiryHours = Setting::get('approval_expiry_hours', 48);

        Approval::create([
            'booking_id' => $booking->id,
            'level' => 1,
            'status' => 'pending',
            'expires_at' => now()->addHours($expiryHours),
        ]);

        if ($path === ApprovalPath::TWO_LEVEL) {
            Approval::create([
                'booking_id' => $booking->id,
                'level' => 2,
                'status' => 'pending',
                'expires_at' => now()->addHours($expiryHours * 2),
            ]);
        }
    }
}
