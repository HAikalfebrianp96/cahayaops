<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengeluaran', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            // NULL = Operasional Koperasi (bukan milik unit usaha manapun)
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            $table->string('kategori');
            $table->decimal('nominal', 15, 2);
            $table->enum('metode_pembayaran', ['Cash', 'Transfer', 'QRIS', 'Lainnya'])->default('Cash');
            $table->text('keterangan')->nullable();
            $table->string('bukti_path')->nullable();
            $table->string('bukti_asli')->nullable();
            // Soft-cancel, BUKAN hard delete — histori tetap ada, cuma tidak dihitung.
            $table->enum('status', ['Aktif', 'Dibatalkan'])->default('Aktif');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluaran');
    }
};
