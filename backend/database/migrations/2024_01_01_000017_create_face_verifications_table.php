<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_in_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('score', 5, 4);
            $table->boolean('liveness_passed');
            $table->string('challenge_type')->nullable();
            $table->boolean('matched');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_verifications');
    }
};
