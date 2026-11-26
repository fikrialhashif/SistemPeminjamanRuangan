<?php

namespace App\Services;

use App\Data\OperatingHours;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class OperatingHoursResolver
{
    public function resolve(Resource $resource, Carbon $date): OperatingHours
    {
        return $this->find($resource, $date) ?? throw ValidationException::withMessages([
            'date' => 'Jam operasional resource belum diatur.',
        ]);
    }

    /**
     * Returns null when neither the room nor its location has operating hours.
     */
    public function find(Resource $resource, Carbon $date): ?OperatingHours
    {
        $resource->loadMissing('room', 'location');

        if ($resource->room && $resource->room->open_time && $resource->room->close_time) {
            return $this->hours(
                $date,
                (string) $resource->room->open_time,
                (string) $resource->room->close_time,
                'room',
            );
        }

        if ($resource->location && $resource->location->open_time && $resource->location->close_time) {
            return $this->hours(
                $date,
                (string) $resource->location->open_time,
                (string) $resource->location->close_time,
                'location',
            );
        }

        return null;
    }

    private function hours(Carbon $date, string $open, string $close, string $source): OperatingHours
    {
        $start = $this->at($date, $open);
        $end = $this->at($date, $close);

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'date' => 'Jam tutup harus setelah jam buka.',
            ]);
        }

        return new OperatingHours($start, $end, $source);
    }

    private function at(Carbon $date, string $time): Carbon
    {
        return Carbon::parse($date->toDateString().' '.$time, 'Asia/Jakarta')->utc();
    }
}
