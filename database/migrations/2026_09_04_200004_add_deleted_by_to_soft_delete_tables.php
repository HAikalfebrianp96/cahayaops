<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIAPA yang menghapus — dilengkapi untuk Tab Terhapus.
 *
 * `deleted_at` sudah menjawab "kapan", tapi Admin yang membuka arsip juga
 * perlu tahu "oleh siapa" sebelum memutuskan memulihkan. Informasi itu
 * sebenarnya ada di Activity Log, TAPI mengambilnya dari sana berarti
 * mencocokkan model_type + model_id + rentang waktu untuk setiap baris arsip
 * — mahal, dan salah begitu ada dua penghapusan berurutan.
 *
 * Satu kolom FK di barisnya sendiri jauh lebih jujur dan murah. Activity Log
 * TIDAK diganti; ia tetap jadi jejak lengkapnya, sedangkan kolom ini yang
 * dipakai tampilan.
 *
 * nullOnDelete menyamai pola FK ke `users` di seluruh CahayaOS: menghapus
 * akun tidak boleh ikut menghapus data yang pernah disentuhnya.
 */
return new class extends Migration
{
    private const TABEL = [
        'participants',
        'pendampings',
        'pendampingan_records',
        'training_records',
        'omzet_reports',
        'koperasi_members',
        'business_units',
        'products_services',
        'pengeluaran_categories',
        'hpp_calculations',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $nama) {
            if (! Schema::hasTable($nama) || Schema::hasColumn($nama, 'deleted_by')) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $nama) {
            if (! Schema::hasTable($nama) || ! Schema::hasColumn($nama, 'deleted_by')) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            });
        }
    }
};
