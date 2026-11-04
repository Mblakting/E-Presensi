<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [DIGIFEST 2026 - ADDITIVE ONLY]
 * Tabel audit log untuk Phase 4 (Bukti Keandalan & Privasi).
 * Menyimpan log akses data biometrik yang terenkripsi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_audit_logs')) return;

        Schema::create('competition_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);       // 'face_enroll'|'face_match'|'face_delete'|'data_access'
            $table->string('actor_type', 20);        // 'siswa'|'admin'|'system'
            $table->string('actor_id')->nullable();  // nisn atau user id
            $table->string('subject_nisn')->nullable();
            $table->text('payload_hash')->nullable(); // SHA-256 dari payload, bukan data asli
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_synthetic')->default(false); // true = data demo lomba
            $table->timestamps();

            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_audit_logs');
    }
};
