<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft Delete untuk UNIT USAHA KOPERASI.
 *
 * Unit usaha adalah data MASTER: produk/jasa, transaksi penjualan,
 * pengeluaran, dan keanggotaan menunjuk ke sini. Menghapusnya permanen akan
 * memutus seluruh riwayat itu sekaligus, jadi penghapusannya ditandai saja.
 *
 * 3 unit yang sudah ada bernilai NULL (tidak terhapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            if (! Schema::hasColumn('business_units', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            if (Schema::hasColumn('business_units', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
