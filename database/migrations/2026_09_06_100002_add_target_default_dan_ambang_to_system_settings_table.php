<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TARGET DEFAULT + AMBANG BEBAN & KEAKTIFAN PESERTA.
 *
 * MEMAKAI TABEL YANG SUDAH ADA. `system_settings` memang tabel pengaturan
 * berbaris tunggal (SystemSetting::current()) dan sudah menampung ambang
 * follow up dari tahap sebelumnya — jadi kebijakan program yang baru
 * menempel di tempat yang semestinya, bukan di tabel konfigurasi baru.
 *
 * Tiga kelompok nilai:
 *
 * 1. TARGET DEFAULT — dipakai kalau seorang pendamping belum punya target
 *    khusus untuk periode berjalan. Target individual SELALU menang; default
 *    hanya jaring pengaman supaya KPI tidak kosong di awal program.
 *
 * 2. AMBANG KEAKTIFAN PESERTA — batas Aktif / Perlu Follow Up / Tidak Aktif.
 *    SENGAJA terpisah dari ambang follow up pendamping yang sudah ada:
 *    yang itu mengukur kinerja PENDAMPING, yang ini mengukur kesehatan
 *    PESERTA. Menggabungkannya berarti mengubah salah satu ikut mengubah
 *    yang lain tanpa disadari.
 *
 * 3. AMBANG BEBAN PENDAMPING — batas Ringan/Normal/Tinggi/Sangat Tinggi,
 *    dipakai Admin untuk menimbang redistribusi peserta.
 *
 * SELURUHNYA NULLABLE tanpa default. Baris pengaturan yang sudah ada
 * dibiarkan kosong dan sistem jatuh ke nilai bawaan di config/cahayaos.php,
 * jadi perilaku TIDAK berubah sedikit pun sampai Admin mengisinya sendiri.
 */
return new class extends Migration
{
    private const KOLOM = [
        // Target default
        'target_default_binaan',
        'target_default_kunjungan',
        'target_default_pendampingan',
        'target_default_follow_up',
        // Ambang keaktifan peserta (hari)
        'peserta_aktif_hari',
        'peserta_perlu_follow_up_hari',
        // Ambang beban pendamping (jumlah binaan)
        'beban_ringan_maks',
        'beban_normal_maks',
        'beban_tinggi_maks',
    ];

    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (self::KOLOM as $kolom) {
                if (! Schema::hasColumn('system_settings', $kolom)) {
                    $table->unsignedSmallInteger($kolom)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (self::KOLOM as $kolom) {
                if (Schema::hasColumn('system_settings', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
