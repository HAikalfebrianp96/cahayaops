<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendampingan_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();

            $table->foreignId('pendamping_id')
                ->nullable()
                ->constrained('pendampings')
                ->nullOnDelete();

            $table->date('tanggal');
            $table->string('jenis_kegiatan');
            $table->text('materi');
            $table->text('catatan')->nullable();
            $table->text('kendala')->nullable();
            $table->text('tindak_lanjut')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendampingan_records');
    }
};
