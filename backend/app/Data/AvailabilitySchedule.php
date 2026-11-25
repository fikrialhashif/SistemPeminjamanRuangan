<?php

namespace App\Data;

use Carbon\Carbon;

readonly class AvailabilitySchedule
{
    public function __construct(
        public Carbon $date,
        public OperatingHours $hours,
        public array $intervals,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'open_time' => $this->hours->start->copy()->setTimezone('Asia/Jakarta')->format('H:i'),
            'close_time' => $this->hours->end->copy()->setTimezone('Asia/Jakarta')->format('H:i'),
            'hours_source' => $this->hours->source,
            'intervals' => array_map(
                fn (AvailabilityInterval $interval) => $interval->toArray(),
                $this->intervals,
            ),
        ];
    }
}
