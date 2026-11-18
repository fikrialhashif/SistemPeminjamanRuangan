<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            ['key' => 'face_verification_enabled', 'value' => 'true', 'type' => 'boolean'],
            ['key' => 'checkin_window_before_minutes', 'value' => '15', 'type' => 'integer'],
            ['key' => 'checkin_window_after_minutes', 'value' => '15', 'type' => 'integer'],
            ['key' => 'checkin_failure_strike_threshold', 'value' => '3', 'type' => 'integer'],
            ['key' => 'gps_accuracy_threshold_meters', 'value' => '50', 'type' => 'integer'],
            ['key' => 'gps_freshness_seconds', 'value' => '30', 'type' => 'integer'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore([
                ...$setting,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'face_verification_enabled',
            'checkin_window_before_minutes',
            'checkin_window_after_minutes',
            'checkin_failure_strike_threshold',
            'gps_accuracy_threshold_meters',
            'gps_freshness_seconds',
        ])->delete();
    }
};
