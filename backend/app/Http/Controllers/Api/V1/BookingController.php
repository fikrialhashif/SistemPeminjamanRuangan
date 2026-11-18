<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\StoreBundledBookingRequest;
use App\Http\Requests\StoreRecurringBookingRequest;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use App\Services\CheckInDecisionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected CheckInDecisionService $checkInDecisionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Booking::with(['resource.location', 'user']);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->orderByDesc('starts_at');

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create($request->user(), $request->validated());

        return response()->json($booking->load(['resource', 'approvals']), 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json($booking->load(['resource.location', 'user', 'approvals', 'checkInAttempts']));
    }

    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        $this->bookingService->cancel($request->user(), $booking);

        return response()->json(['message' => 'Booking cancelled.']);
    }

    public function checkIn(CheckInRequest $request, Booking $booking): JsonResponse
    {
        $attempt = $this->checkInDecisionService->process(
            $booking,
            $request->user(),
            $request->validated(),
        );

        return response()->json($attempt);
    }

    public function bundled(StoreBundledBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $bookings = DB::transaction(function () use ($request, $data) {
            return collect($data['resource_ids'])->map(fn (int $resourceId) => $this->bookingService->create(
                $request->user(),
                [...$data, 'resource_id' => $resourceId],
            ));
        });

        return response()->json(['data' => $bookings->load('resource')], 201);
    }

    public function recurring(StoreRecurringBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $interval = $data['interval_weeks'] ?? 1;
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        $duration = $start->diffInSeconds($end);
        $series = DB::transaction(function () use ($request, $data, $start, $duration, $interval) {
            $series = BookingSeries::create([
                'user_id' => $request->user()->id,
                'resource_id' => $data['resource_id'],
                'recurrence_rule' => ['interval_weeks' => $interval],
                'conflict_policy' => $data['conflict_policy'],
                'occurrences' => $data['occurrences'],
            ]);
            $created = [];
            for ($i = 0; $i < $data['occurrences']; $i++) {
                $occurrenceStart = $start->copy()->addWeeks($i * $interval);
                try {
                    $created[] = $this->bookingService->create($request->user(), [
                        ...$data,
                        'starts_at' => $occurrenceStart->toDateTimeString(),
                        'ends_at' => $occurrenceStart->copy()->addSeconds($duration)->toDateTimeString(),
                        'booking_series_id' => $series->id,
                    ]);
                } catch (\Throwable $error) {
                    if ($data['conflict_policy'] === 'reject') {
                        throw $error;
                    }
                }
            }

            return $series->load('bookings');
        });

        return response()->json($series, 201);
    }

    public function claimWaitlist(Request $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        if ($waitlistEntry->user_id !== $request->user()->id || $waitlistEntry->status !== 'offered' || $waitlistEntry->offer_expires_at?->isPast()) {
            return response()->json(['message' => 'Waitlist offer is not claimable.'], 422);
        }
        $waitlistEntry->update(['status' => 'claimed', 'claimed_at' => now()]);

        return response()->json($waitlistEntry);
    }

    public function declineWaitlist(Request $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        if ($waitlistEntry->user_id !== $request->user()->id || $waitlistEntry->status !== 'offered') {
            return response()->json(['message' => 'Waitlist offer cannot be declined.'], 422);
        }
        $waitlistEntry->update(['status' => 'declined']);

        return response()->json($waitlistEntry);
    }

    public function waitlist(Request $request, Booking $booking): JsonResponse
    {
        $entry = WaitlistEntry::create([
            'user_id' => $request->user()->id,
            'resource_id' => $booking->resource_id,
            'desired_start' => $booking->starts_at,
            'desired_end' => $booking->ends_at,
            'status' => 'waiting',
        ]);

        return response()->json($entry, 201);
    }
}
