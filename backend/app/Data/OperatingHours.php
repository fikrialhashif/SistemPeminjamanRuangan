<?php

namespace App\Data;

use Carbon\Carbon;

readonly class OperatingHours
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public string $source,
    ) {}
}
