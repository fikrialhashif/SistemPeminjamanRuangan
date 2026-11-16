<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('buffer_minutes')->default(0);
            $table->integer('min_duration_minutes')->default(30);
            $table->integer('max_duration_minutes')->default(480);
            $table->integer('advance_booking_days')->default(30);
            $table->enum('approval_path', ['none', 'one_level', 'two_level'])->default('none');
            $table->integer('cancellation_minutes_before')->default(60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_rules');
    }
};
