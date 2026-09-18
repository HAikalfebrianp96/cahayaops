<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AMBANG BATAS FOLLOW UP — agar bisa diubah Admin, bukan tertanam di kode.
 *
 * Aturan "30 hari kuning, 45 hari merah" adalah kebijakan program, bukan
 * aturan teknis: ia wajar berubah saat jumlah binaan per pendamping berubah,
 * atau saat program masuk fase yang berbeda. Menanamkannya di kode berarti
 * setiap perubahan kebijakan butuh deploy.
 *
 * MEMAKAI TABEL YANG SUDAH ADA. `system_settings` memang tabel pengaturan
 * Cahaya Preneur berbaris tunggal (lihat SystemSetting::current()), jadi dua
 * kolom ini menempel di tempat yang semestinya — tidak ada tabel konfigurasi
 * baru yang dibuat.
 *
 * NULLABLE, tanpa default. Baris pengaturan yang sudah ada dibiarkan kosong,
 * dan FollowUpService jatuh ke nilai bawaan di config/cahayaos.php. Artinya
 * perilaku sistem TIDAK berubah sedikit pun sampai Admin benar-benar mengisi
 * angkanya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'followup_kuning_hari')) {
                $table->unsignedSmallInteger('followup_kuning_hari')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'followup_merah_hari')) {
                $table->unsignedSmallInteger('followup_merah_hari')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['followup_kuning_hari', 'followup_merah_hari'] as $kolom) {
                if (Schema::hasColumn('system_settings', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
