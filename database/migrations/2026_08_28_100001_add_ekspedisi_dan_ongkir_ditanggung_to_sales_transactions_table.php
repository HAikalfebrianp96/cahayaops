<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('nama_ekspedisi')->nullable()->after('metode_pengiriman');
            $table->string('nomor_resi')->nullable()->after('nama_ekspedisi');
            // 'Pelanggan' atau 'Koperasi' — siapa yang menanggung ongkir.
            // Default 'Pelanggan' supaya transaksi LAMA (kolom ini kosong) tetap
            // dihitung persis seperti behavior sebelumnya (ongkir masuk Total).
            $table->string('ongkir_ditanggung')->default('Pelanggan')->after('ongkir');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn(['nama_ekspedisi', 'nomor_resi', 'ongkir_ditanggung']);
        });
    }
};
