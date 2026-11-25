<?php

namespace App\Data;

use Carbon\Carbon;

readonly class AvailabilityInterval
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public bool $available,
        public ?string $status = null,
        public ?string $statusDetail = null,
    ) {}

    public function label(): string
    {
        return $this->available ? 'Tersedia' : 'Dipesan';
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'available' => $this->available,
            'status' => $this->status ?? ($this->available ? 'free' : 'booked'),
            'status_detail' => $this->statusDetail,
            'label' => $this->label(),
        ];
    }
}
