<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_liveness_challenges')) {
            return;
        }

        Schema::create('competition_liveness_challenges', function (Blueprint $table): void {
            $table->id();
            $table->char('nonce_hash', 64)->unique();
            $table->string('siswa_nisn');
            $table->char('session_hash', 64);
            $table->string('instruction', 20);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('liveness_status', 20)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedTinyInteger('score_face')->nullable();
            $table->unsignedTinyInteger('score_gps')->nullable();
            $table->unsignedTinyInteger('score_consistency')->nullable();
            $table->unsignedTinyInteger('score_liveness')->nullable();
            $table->string('verdict', 20)->nullable();
            $table->string('reason_code', 50)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
            $table->timestamps();

            $table->index(['siswa_nisn', 'created_at']);
            $table->index(['siswa_nisn', 'reason_code', 'used_at'], 'competition_liveness_daily_fallback_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_liveness_challenges');
    }
};
