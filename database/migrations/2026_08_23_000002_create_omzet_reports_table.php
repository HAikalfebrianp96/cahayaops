<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omzet_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('periode_bulan'); // 1-12
            $table->unsignedSmallInteger('periode_tahun');
            $table->unsignedBigInteger('omzet');
            $table->text('catatan')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            // Aturan: 1 peserta + 1 bulan + 1 tahun = 1 laporan (anti duplikasi diam-diam)
            $table->unique(['participant_id', 'periode_bulan', 'periode_tahun'], 'omzet_reports_unique_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omzet_reports');
    }
};
