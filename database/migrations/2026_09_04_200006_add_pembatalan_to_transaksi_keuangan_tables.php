<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PEMBATALAN BERJEJAK untuk transaksi keuangan koperasi.
 *
 * Penjualan dan arus Dana Sosial/Hibah adalah UANG. Keduanya tidak boleh
 * dihapus — laporan laba rugi, buku dana, dan invoice yang sudah terbit akan
 * ikut berubah tanpa jejak. Tapi selama ini juga tidak ada cara membatalkan
 * transaksi yang salah, sehingga kesalahan input hanya bisa "dibiarkan".
 *
 * Polanya SENGAJA disamakan persis dengan koreksi simpanan
 * (`member_savings`, migration 2026_09_04_100005) supaya operator tidak perlu
 * mempelajari dua mekanisme berbeda untuk hal yang sama:
 *
 *   barisnya TETAP ADA -> statusnya jadi "Dibatalkan" -> berhenti dihitung
 *   ke laporan -> alasan, waktu, dan pelakunya ikut tersimpan.
 *
 * Untuk `sales_transactions`, kolom `status_transaksi` SUDAH ADA (bernilai
 * 'Aktif' pada seluruh 3 baris existing) dan TIDAK diubah — nilai
 * 'Dibatalkan' hanyalah nilai tambahan yang dipakai alur ini.
 *
 * Tabel dana TIDAK punya kolom status, jadi keempatnya diberi `status`
 * dengan default 'Tercatat'. Baris existing otomatis ikut bernilai itu, jadi
 * seluruhnya tetap dihitung persis seperti sebelumnya.
 */
return new class extends Migration
{
    private const TABEL_DANA = [
        'dana_sukarela_masuk',
        'dana_sukarela_keluar',
        'dana_hibah_masuk',
        'dana_hibah_keluar',
    ];

    public function up(): void
    {
        foreach (self::TABEL_DANA as $nama) {
            if (! Schema::hasTable($nama)) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) use ($nama) {
                if (! Schema::hasColumn($nama, 'status')) {
                    $table->string('status')->default('Tercatat');
                }
            });
        }

        foreach (array_merge(self::TABEL_DANA, ['sales_transactions']) as $nama) {
            if (! Schema::hasTable($nama)) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) use ($nama) {
                if (! Schema::hasColumn($nama, 'alasan_pembatalan')) {
                    $table->text('alasan_pembatalan')->nullable();
                }
                if (! Schema::hasColumn($nama, 'dibatalkan_at')) {
                    $table->timestamp('dibatalkan_at')->nullable();
                }
                if (! Schema::hasColumn($nama, 'dibatalkan_oleh')) {
                    $table->foreignId('dibatalkan_oleh')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_merge(self::TABEL_DANA, ['sales_transactions']) as $nama) {
            if (! Schema::hasTable($nama)) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) use ($nama) {
                if (Schema::hasColumn($nama, 'dibatalkan_oleh')) {
                    $table->dropForeign(['dibatalkan_oleh']);
                    $table->dropColumn('dibatalkan_oleh');
                }
                foreach (['alasan_pembatalan', 'dibatalkan_at'] as $kolom) {
                    if (Schema::hasColumn($nama, $kolom)) {
                        $table->dropColumn($kolom);
                    }
                }
            });
        }

        foreach (self::TABEL_DANA as $nama) {
            if (Schema::hasTable($nama) && Schema::hasColumn($nama, 'status')) {
                Schema::table($nama, fn (Blueprint $table) => $table->dropColumn('status'));
            }
        }
    }
};
