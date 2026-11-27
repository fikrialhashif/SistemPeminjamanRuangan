<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $after = DB::table('settings')->where('key', 'checkin_window_after_minutes')->first();
        $legacy = DB::table('settings')->where('key', 'check_in_window_minutes')->first();

        if ($after || $legacy) {
            $minutes = max((int) ($after->value ?? 15), (int) ($legacy->value ?? 15));

            DB::table('settings')->upsert(
                [[
                    'key' => 'checkin_window_after_minutes',
                    'value' => (string) $minutes,
                    'type' => 'integer',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['key'],
                ['value', 'type', 'updated_at'],
            );
        }

        DB::table('settings')->where('key', 'check_in_window_minutes')->delete();

        $threshold = DB::table('settings')->where('key', 'checkin_failure_strike_threshold')->first();

        if ($threshold && (int) $threshold->value < 1) {
            DB::table('settings')->where('id', $threshold->id)->update([
                'value' => '1',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $after = DB::table('settings')->where('key', 'checkin_window_after_minutes')->value('value');

        if ($after !== null) {
            DB::table('settings')->insertOrIgnore([
                'key' => 'check_in_window_minutes',
                'value' => $after,
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
