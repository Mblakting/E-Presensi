<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_verification_queue')) {
            return;
        }

        Schema::create('competition_verification_queue', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('challenge_id')->unique();
            $table->string('siswa_nisn');
            $table->string('absen_type', 10);
            $table->unsignedTinyInteger('score');
            $table->string('reason_code', 50);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->string('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('siswa_nisn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_verification_queue');
    }
};
