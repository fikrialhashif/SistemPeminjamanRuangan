<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->time('open_time')->nullable()->after('radius');
            $table->time('close_time')->nullable()->after('open_time');
        });

        DB::table('locations')->whereNull('open_time')->update(['open_time' => '07:00:00']);
        DB::table('locations')->whereNull('close_time')->update(['close_time' => '22:00:00']);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['open_time', 'close_time']);
        });
    }
};
