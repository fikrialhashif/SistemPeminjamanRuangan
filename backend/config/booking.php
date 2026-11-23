<?php

return [
    'check_in_window_before' => env('CHECK_IN_WINDOW_BEFORE', 15),
    'check_in_window_after' => env('CHECK_IN_WINDOW_AFTER', 15),
    'gps_accuracy_threshold' => env('GPS_ACCURACY_THRESHOLD', 50),
    'gps_freshness_seconds' => env('GPS_FRESHNESS_SECONDS', 30),
    'gps_max_speed_kmh' => env('GPS_MAX_SPEED_KMH', 200),
    'face_check_enabled' => env('FACE_CHECK_ENABLED', true),
    'face_match_threshold' => env('FACE_MATCH_THRESHOLD', 0.6),
    'max_check_in_failures' => env('MAX_CHECK_IN_FAILURES', 5),
    'strike_threshold' => env('STRIKE_THRESHOLD', 3),
    'strike_window_days' => env('STRIKE_WINDOW_DAYS', 30),
    'suspension_days' => env('SUSPENSION_DAYS', 7),
    'waitlist_offer_minutes' => env('WAITLIST_OFFER_MINUTES', 30),
];
