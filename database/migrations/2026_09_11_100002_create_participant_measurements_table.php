<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BASELINE -> MONITORING -> ENDLINE.
 *
 * Laporan omzet menjawab "berapa bulan ini". Tabel ini menjawab pertanyaan
 * yang berbeda dan tidak bisa diturunkan darinya: "usaha ini berubah apa
 * sejak didampingi?" — jumlah pekerja, jumlah produk, pelanggan, kapasitas,
 * jangkauan pasar, pembukuan. Tanpa titik awal yang tercatat, setiap klaim
 * peningkatan hanyalah tebakan, dan seluruh perhitungan SROI di atasnya
 * kehilangan dasar.
 *
 * SATU TABEL UNTUK TIGA JENIS, bukan tiga tabel.
 *
 * Baseline, monitoring, dan endline mengukur hal yang PERSIS SAMA pada waktu
 * yang berbeda — itulah yang membuatnya bisa dibandingkan. Memisahkannya jadi
 * tiga tabel akan membuat setiap perbandingan harus menyamakan kolom lebih
 * dulu, dan penambahan satu indikator baru harus dilakukan tiga kali.
 *
 * SELURUH KOLOM UKURAN NULLABLE.
 *
 * Pendataan lapangan jarang lengkap dalam satu kunjungan. Nol dan "belum
 * terdata" adalah dua hal berbeda; hanya NULL yang bisa mengatakan yang
 * kedua, dan perhitungan perubahan di atasnya melewati indikator yang belum
 * punya pasangan baseline/endline alih-alih mengarang kenaikan dari nol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();

            // 'Baseline' | 'Monitoring' | 'Endline' — string biasa, mengikuti
            // pola kolom status di seluruh tabel lain (bukan enum database),
            // supaya nilainya bisa bertambah tanpa migration pengubah tipe.
            $table->string('jenis');
            $table->date('tanggal');

            // --- Keuangan bulanan (rata-rata pada saat pengukuran) ---
            $table->unsignedBigInteger('omzet_bulanan')->nullable();
            $table->unsignedBigInteger('hpp_bulanan')->nullable();
            $table->unsignedBigInteger('biaya_operasional_bulanan')->nullable();

            // --- Kapasitas usaha ---
            $table->unsignedInteger('jumlah_tenaga_kerja')->nullable();
            $table->unsignedInteger('jumlah_produk')->nullable();
            $table->unsignedInteger('jumlah_pelanggan')->nullable();
            $table->unsignedInteger('kapasitas_produksi')->nullable();
            $table->string('satuan_produksi')->nullable();

            // --- Kualitatif: dicatat sebagai ya/tidak/belum terdata ---
            $table->string('jangkauan_pasar')->nullable();   // Lingkungan | Kecamatan | Kota | Provinsi | Nasional
            $table->boolean('punya_legalitas')->nullable();
            $table->boolean('punya_pembukuan')->nullable();
            $table->boolean('punya_rekening_usaha')->nullable();
            $table->boolean('jualan_online')->nullable();

            $table->text('catatan')->nullable();
            $table->text('sumber_data')->nullable(); // bukti/asal angka — dipakai audit trail Impact
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Daftar & perbandingan selalu mencari "pengukuran peserta X
            // berjenis Y", jadi indeksnya disusun persis seperti itu.
            $table->index(['participant_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_measurements');
    }
};
