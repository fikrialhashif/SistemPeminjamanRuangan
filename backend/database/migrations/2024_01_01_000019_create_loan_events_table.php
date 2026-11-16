<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_loan_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_events');
    }
};
