<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use App\Services\CheckInDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected CheckInDecisionService $checkInDecisionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Booking::with(['resource', 'user']);

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
        return response()->json($booking->load(['resource', 'user', 'approvals', 'checkInAttempts']));
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
