<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [DIGIFEST 2026 - ADDITIVE ONLY]
 * Tabel baru untuk menyimpan Trust Score tiap percobaan absen.
 * Tidak mengubah tabel presensi, siswa, atau tabel core lainnya.
 * Bisa di-rollback penuh tanpa efek ke data klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_trust_scores')) return;

        Schema::create('competition_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->string('nisn');                          // referensi ke siswa (soft, no FK agar rollback aman)
            $table->unsignedTinyInteger('score');            // 0-100
            $table->string('absen_type', 10)->default('in'); // 'in' | 'out'
            $table->string('method', 20)->default('face');   // 'face' | 'gps'

            // Komponen skor (untuk audit & evaluasi juri)
            $table->unsignedTinyInteger('score_face_distance')->default(0);  // 0-40
            $table->unsignedTinyInteger('score_gps_accuracy')->default(0);   // 0-40
            $table->unsignedTinyInteger('score_consistency')->default(0);    // 0-20

            $table->string('verdict', 20)->default('pending'); // 'accepted'|'queued'|'rejected'
            $table->text('reason')->nullable();                // alasan penolakan/antrean
            $table->json('raw_signals')->nullable();           // data mentah untuk audit

            // Apakah skor ini mempengaruhi hasil absen (enforce mode) atau hanya shadow
            $table->boolean('is_shadow')->default(true);

            $table->timestamps();

            $table->index('nisn');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_trust_scores');
    }
};
