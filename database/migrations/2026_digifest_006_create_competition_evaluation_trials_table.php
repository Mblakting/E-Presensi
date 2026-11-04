<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_evaluation_trials')) {
            return;
        }

        Schema::create('competition_evaluation_trials', function (Blueprint $table): void {
            $table->id();
            $table->string('trial_type', 12);
            $table->string('outcome', 12);
            $table->string('reason', 500);
            $table->string('tester_code', 8);
            $table->date('tested_at');
            $table->text('notes')->nullable();
            $table->char('fingerprint', 64)->unique();
            $table->timestamps();

            $table->index(['trial_type', 'outcome']);
            $table->index('tested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_evaluation_trials');
    }
};
