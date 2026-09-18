<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengeluaran', function (Blueprint $table) {
            // NULLABLE — transaksi LAMA sengaja dibiarkan NULL di sini (tidak
            // di-backfill/mapping otomatis). Kolom 'kategori' (text) yang lama
            // TETAP ADA & TIDAK DIUBAH, tetap jadi sumber baca untuk histori.
            $table->foreignId('pengeluaran_category_id')
                ->nullable()
                ->after('kategori')
                ->constrained('pengeluaran_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengeluaran_category_id');
        });
    }
};
