<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft Delete untuk LAPORAN OMZET (perkembangan usaha peserta).
 *
 * CATATAN PENTING soal unique index.
 *
 * Tabel ini punya unique ['participant_id','periode_bulan','periode_tahun']
 * dari migration 2026_08_23_000002 — satu peserta, satu bulan, satu laporan.
 * Unique index MySQL TIDAK mempedulikan `deleted_at`, jadi baris yang
 * di-soft-delete TETAP memblokir pengisian ulang periode yang sama.
 *
 * Index itu SENGAJA tidak diubah — ia justru pengaman anti-duplikasi yang
 * berharga. Yang menyesuaikan diri adalah alur penyimpanannya:
 * OmzetMonitoringController::store() dan OmzetReportPublicController::store()
 * mencari lebih dulu dengan withTrashed(), lalu memulihkan & memperbarui
 * baris lama alih-alih menyisipkan baris baru. Hasilnya periode yang pernah
 * dihapus tetap bisa diisi lagi, tanpa melonggarkan aturan anti-duplikasi
 * dan tanpa menyentuh migration lama.
 *
 * 15 laporan yang sudah ada bernilai NULL (tidak terhapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('omzet_reports', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            if (Schema::hasColumn('omzet_reports', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
