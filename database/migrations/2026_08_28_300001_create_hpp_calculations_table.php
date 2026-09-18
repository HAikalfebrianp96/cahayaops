<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->string('nama_produk');
            $table->string('satuan_produksi');
            $table->unsignedInteger('jumlah_produksi');

            // SEMUA hasil di bawah ini adalah SNAPSHOT — dihitung SERVER-SIDE
            // sekali saat disimpan, TIDAK dihitung ulang tiap halaman dibuka.
            // Ini yang membuat histori lama tidak pernah berubah nilainya.
            $table->decimal('total_biaya_produksi', 15, 2);
            $table->decimal('hpp_per_unit', 15, 2);
            $table->decimal('harga_jual', 15, 2);
            $table->decimal('laba_per_unit', 15, 2);
            $table->decimal('margin_persen', 6, 2)->nullable(); // null kalau harga_jual = 0 (hindari division by zero)
            $table->decimal('estimasi_laba_batch', 15, 2);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['participant_id', 'nama_produk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_calculations');
    }
};
