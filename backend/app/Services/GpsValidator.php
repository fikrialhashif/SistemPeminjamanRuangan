<?php

namespace App\Services;

use App\Models\CheckInAttempt;
use App\Models\Location;
use App\Models\Setting;
use Carbon\Carbon;

class GpsValidator
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function validate(
        Location $location,
        float $lat,
        float $lng,
        float $accuracy,
        string $timestamp,
        bool $isMock,
        ?int $userId = null,
    ): array {
        $reasons = [];

        if ($isMock) {
            $reasons[] = 'Mock GPS detected';
        }

        $accuracyThreshold = Setting::get('gps_accuracy_threshold_meters', 50);
        if ($accuracy > $accuracyThreshold) {
            $reasons[] = "GPS accuracy {$accuracy}m exceeds threshold of {$accuracyThreshold}m";
        }

        $freshnessSeconds = Setting::get('gps_freshness_seconds', 30);
        $gpsTime = Carbon::parse($timestamp);
        if (abs($gpsTime->diffInSeconds(now())) > $freshnessSeconds) {
            $reasons[] = 'GPS reading is stale';
        }

        $distance = $this->distanceFromCircle($location, $lat, $lng);

        if ($this->usesPolygon($location)) {
            if (! $this->pointInPolygon($lat, $lng, $location->polygon)) {
                $reasons[] = 'Outside geofence polygon';
            }
        } elseif ($distance !== null) {
            $radius = $location->radius ?? 100;
            if ($distance > $radius) {
                $reasons[] = "Distance {$distance}m exceeds geofence radius of {$radius}m";
            }
        }

        if ($userId) {
            $velocityReason = $this->checkVelocity($userId, $lat, $lng, $gpsTime);
            if ($velocityReason) {
                $reasons[] = $velocityReason;
            }
        }

        return [
            'valid' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Metres from the centre of a circular area; null when the area is a polygon or has no centre.
     */
    public function distanceFromCircle(Location $location, float $lat, float $lng): ?float
    {
        if ($this->usesPolygon($location) || ! $location->latitude || ! $location->longitude) {
            return null;
        }

        return $this->haversineDistance($lat, $lng, (float) $location->latitude, (float) $location->longitude);
    }

    private function usesPolygon(Location $location): bool
    {
        return is_array($location->polygon) && count($location->polygon) >= 3;
    }

    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round(self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $n = count($polygon);
        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i]['lat'] ?? $polygon[$i][0];
            $yi = $polygon[$i]['lng'] ?? $polygon[$i][1];
            $xj = $polygon[$j]['lat'] ?? $polygon[$j][0];
            $yj = $polygon[$j]['lng'] ?? $polygon[$j][1];

            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function checkVelocity(int $userId, float $lat, float $lng, Carbon $timestamp): ?string
    {
        $lastAttempt = CheckInAttempt::where('user_id', $userId)
            ->whereNotNull('gps_latitude')
            ->whereNotNull('gps_longitude')
            ->latest()
            ->first();

        if (! $lastAttempt || ! $lastAttempt->gps_timestamp) {
            return null;
        }

        $timeDiffSeconds = $lastAttempt->gps_timestamp->diffInSeconds($timestamp);
        if ($timeDiffSeconds < 1) {
            return null;
        }

        $distance = $this->haversineDistance(
            (float) $lastAttempt->gps_latitude,
            (float) $lastAttempt->gps_longitude,
            $lat,
            $lng,
        );

        $speedMps = $distance / $timeDiffSeconds;
        $maxSpeedMps = Setting::get('gps_max_speed_mps', 100);

        if ($speedMps > $maxSpeedMps) {
            return 'Suspicious velocity: '.round($speedMps, 1).' m/s';
        }

        return null;
    }
}
