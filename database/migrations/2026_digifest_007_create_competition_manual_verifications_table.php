<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_manual_verifications')) {
            return;
        }

        Schema::create('competition_manual_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')
                ->unique()
                ->constrained('competition_liveness_challenges');
            $table->foreignId('trust_score_id')
                ->nullable()
                ->constrained('competition_trust_scores')
                ->nullOnDelete();
            $table->string('siswa_nisn');
            $table->unsignedTinyInteger('score');
            $table->string('reason_code', 50);
            $table->text('reason');
            $table->string('absen_type', 10);
            $table->date('attendance_date');
            $table->time('attendance_time');
            $table->string('lokasi');
            $table->decimal('confidence', 8, 6);
            $table->string('foto_tangkapan', 255);
            $table->string('status', 20)->default('pending');
            $table->string('admin_id')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempted_at']);
            $table->index(['siswa_nisn', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_manual_verifications');
    }
};
