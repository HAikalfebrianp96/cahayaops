<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();

            $table->date('tanggal');
            $table->string('nama_pelatihan');
            $table->string('penyelenggara')->nullable();
            $table->string('status')->default('Terdaftar'); // Terdaftar | Mengikuti | Selesai | Tidak Mengikuti
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};
