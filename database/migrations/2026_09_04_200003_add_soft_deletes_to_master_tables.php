<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOFT DELETE untuk sisa data MASTER yang belum punya.
 *
 * Empat tabel ini semuanya dirujuk tabel lain, jadi menghapusnya permanen
 * berarti memutus riwayat:
 *
 *   pendampings            <- participants, pendampingan_records, users
 *   products_services      <- sales_transaction_items
 *   pengeluaran_categories <- pengeluaran
 *   hpp_calculations       <- hpp_components (dan histori peserta)
 *
 * CATATAN KHUSUS `pendampings`: destroy()-nya selama ini HARD DELETE. Karena
 * FK dari participants ber-nullOnDelete, menghapus seorang Pendamping membuat
 * seluruh binaannya kehilangan pemilik SECARA PERMANEN — tidak ada cara
 * mengembalikannya. Dengan soft delete, penghapusan tidak lagi memicu FK sama
 * sekali, sehingga tautan binaannya tetap utuh dan bisa dipulihkan.
 *
 * Seluruh baris existing bernilai NULL (tidak terhapus) dan tetap tampil
 * persis seperti sebelumnya.
 */
return new class extends Migration
{
    private const TABEL = [
        'pendampings',
        'products_services',
        'pengeluaran_categories',
        'hpp_calculations',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $nama) {
            if (! Schema::hasTable($nama) || Schema::hasColumn($nama, 'deleted_at')) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $nama) {
            if (! Schema::hasTable($nama) || ! Schema::hasColumn($nama, 'deleted_at')) {
                continue;
            }

            Schema::table($nama, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
