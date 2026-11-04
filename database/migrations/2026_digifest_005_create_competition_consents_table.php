<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_consents')) {
            return;
        }

        Schema::create('competition_consents', function (Blueprint $table): void {
            $table->id();
            $table->string('siswa_nisn');
            $table->string('status', 20);
            $table->string('text_version', 32);
            $table->char('text_hash', 64);
            $table->string('actor_type', 20);
            $table->string('actor_id')->nullable();
            $table->string('authority_basis', 40)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['siswa_nisn', 'id']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_consents');
    }
};
