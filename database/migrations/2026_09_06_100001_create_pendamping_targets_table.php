<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TARGET KINERJA PENDAMPING.
 *
 * KENAPA TABEL BARU (dan ini satu-satunya yang dibuat tahap ini).
 *
 * Target adalah data yang BELUM PERNAH ADA di CahayaOS: ia bukan turunan
 * dari catatan lapangan manapun, melainkan keputusan program yang ditetapkan
 * Admin di muka. Tidak ada tabel existing yang bisa menampungnya tanpa
 * dipaksakan — `pendampings` menyimpan identitas (satu baris per orang),
 * sementara target berulang per PERIODE, jadi menempelkannya di sana akan
 * langsung salah begitu ada target bulan kedua.
 *
 * Sebaliknya, REALISASI-nya TIDAK disimpan di sini. Angka aktual dihitung
 * saat dibutuhkan dari pendampingan_records, participants, dan omzet_reports
 * (lihat App\Services\KpiService). Menyimpan realisasi berarti ia harus
 * diperbarui setiap kali satu catatan ditambah/diubah/dihapus/dipulihkan —
 * dan pasti akan basi begitu satu jalur lupa memperbaruinya.
 *
 * SELURUH kolom target NULLABLE dengan sengaja. Target harus fleksibel:
 * program boleh menetapkan hanya target kunjungan tanpa target pelatihan,
 * dan indikator yang tidak ditargetkan TIDAK ikut dihitung ke KPI
 * (lihat KpiService::hitung()). Memaksa semuanya terisi akan membuat Admin
 * mengarang angka hanya supaya formnya bisa disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendamping_targets', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete SENGAJA TIDAK dipakai: Pendamping memakai Soft
            // Delete, jadi FK tidak pernah terpicu. Kalaupun suatu saat ada
            // penghapusan permanen, targetnya memang tidak berguna lagi.
            $table->foreignId('pendamping_id')
                ->constrained('pendampings')
                ->cascadeOnDelete();

            // Bulanan | Triwulan | Tahunan
            $table->string('periode');
            $table->unsignedSmallInteger('tahun');

            // NULL untuk periode Tahunan. Untuk Triwulan diisi bulan AWAL
            // triwulannya (1, 4, 7, 10) — lihat PendampingTarget::rentang().
            $table->unsignedTinyInteger('bulan')->nullable();

            $table->unsignedSmallInteger('target_jumlah_binaan')->nullable();
            $table->unsignedSmallInteger('target_kunjungan')->nullable();
            $table->unsignedSmallInteger('target_pendampingan')->nullable();
            $table->unsignedSmallInteger('target_follow_up')->nullable();
            $table->unsignedSmallInteger('target_input_omzet')->nullable();
            $table->unsignedSmallInteger('target_pelatihan')->nullable();
            $table->unsignedSmallInteger('target_legalitas')->nullable();
            $table->unsignedSmallInteger('target_perkembangan_peserta')->nullable();

            $table->text('catatan')->nullable();

            // Pola FK ke users di seluruh CahayaOS: menghapus akun tidak
            // boleh ikut menghapus data yang pernah disentuhnya.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // Satu pendamping hanya boleh punya SATU target per periode.
            // Tanpa ini, dua target bulan yang sama akan membuat KPI-nya
            // bergantung pada baris mana yang kebetulan terambil duluan.
            //
            // deleted_at ikut masuk index supaya target yang diarsipkan tidak
            // memblokir pembuatan target baru untuk periode yang sama —
            // jebakan yang sama sudah pernah ditemui di omzet_reports.
            $table->unique(
                ['pendamping_id', 'periode', 'tahun', 'bulan', 'deleted_at'],
                'pendamping_targets_unique_periode'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendamping_targets');
    }
};
