<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('status_transaksi')->default('Aktif')->after('nomor_transaksi'); // Draft | Aktif | Selesai | Dibatalkan
            $table->decimal('ongkir', 15, 2)->default(0)->after('diskon');
            $table->string('metode_pengiriman')->nullable()->after('metode_pembayaran'); // Ambil di Tempat | Diantar | COD
            $table->text('alamat_pengiriman')->nullable()->after('metode_pengiriman');
            $table->string('hp_penerima')->nullable()->after('alamat_pengiriman');
            $table->text('catatan_pengiriman')->nullable()->after('hp_penerima');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['status_transaksi', 'ongkir', 'metode_pengiriman', 'alamat_pengiriman', 'hp_penerima', 'catatan_pengiriman']);
        });
    }
};
