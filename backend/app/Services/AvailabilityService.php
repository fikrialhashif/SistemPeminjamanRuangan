<?php

namespace App\Services;

use App\Data\AvailabilityInterval;
use App\Data\AvailabilitySchedule;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Resource;
use Carbon\Carbon;

class AvailabilityService
{
    public function __construct(
        protected OperatingHoursResolver $operatingHoursResolver,
    ) {}

    public function schedule(Resource $resource, Carbon $date): AvailabilitySchedule
    {
        $resource->loadMissing('rules');
        $hours = $this->operatingHoursResolver->resolve($resource, $date);

        $slotHoldingStatuses = collect(BookingStatus::cases())
            ->filter(fn (BookingStatus $status) => $status->holdsSlot())
            ->map(fn (BookingStatus $status) => $status->value)
            ->values()
            ->all();

        $bookings = Booking::where('resource_id', $resource->id)
            ->whereIn('status', $slotHoldingStatuses)
            ->where('starts_at', '<', $hours->end)
            ->where('ends_at', '>', $hours->start)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'status']);

        $buffer = $resource->rules?->buffer_minutes ?? 0;
        $intervals = [];
        $cursor = $hours->start->copy();

        foreach ($bookings as $booking) {
            $bookedStart = $booking->starts_at->copy()->subMinutes($buffer);
            $bookedEnd = $booking->ends_at->copy()->addMinutes($buffer);

            if ($bookedStart->lt($hours->start)) {
                $bookedStart = $hours->start->copy();
            }
            if ($bookedEnd->gt($hours->end)) {
                $bookedEnd = $hours->end->copy();
            }
            if ($bookedStart->lt($cursor)) {
                $bookedStart = $cursor->copy();
            }
            if ($bookedEnd->lte($bookedStart)) {
                continue;
            }

            if ($cursor->lt($bookedStart)) {
                $intervals[] = new AvailabilityInterval($cursor->copy(), $bookedStart->copy(), true);
            }

            $intervals[] = new AvailabilityInterval(
                $bookedStart->copy(),
                $bookedEnd->copy(),
                false,
                'booked',
                $booking->status->value,
            );

            $cursor = $bookedEnd->copy();
        }

        if ($cursor->lt($hours->end)) {
            $intervals[] = new AvailabilityInterval($cursor->copy(), $hours->end->copy(), true);
        }

        return new AvailabilitySchedule($date, $hours, $intervals);
    }
}
