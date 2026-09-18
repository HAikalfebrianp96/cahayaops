<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MESIN DAMPAK & SROI.
 *
 * Lima tabel, satu tujuan: membuat setiap angka SROI bisa ditelusuri sampai
 * ke sumbernya. Rantainya:
 *
 *   impact_programs      satu program/periode yang dinilai
 *   program_investments  uang & sumber daya yang MASUK (penyebut rasio)
 *   financial_proxies    berapa nilai rupiah satu satuan outcome, DAN dari mana
 *   impact_outcomes      perubahan yang diklaim + faktor pengurangnya
 *   sroi_calculations    hasil hitung yang DIBEKUKAN beserta seluruh asumsinya
 *
 * ================================================================
 * KENAPA HASILNYA DISIMPAN, PADAHAL BISA DIHITUNG ULANG
 * ================================================================
 *
 * Di seluruh CahayaOS, yang bisa dihitung memang dihitung, tidak disimpan
 * (lihat PerkembanganService dan OmzetReport::profitBersih). SROI adalah
 * pengecualiannya, dan alasannya bukan performa:
 *
 * Angka SROI dipakai untuk melapor ke pemberi dana. Laporan yang sudah
 * dikirim harus tetap bisa dijelaskan bertahun-tahun kemudian — termasuk saat
 * proxy finansialnya sudah diperbarui, outcome-nya diperbaiki, dan pesertanya
 * bertambah. Kalau hasilnya selalu dihitung ulang dari data hari ini, laporan
 * lama akan berubah sendiri dan tidak ada yang bisa menunjukkan angka apa
 * yang sebenarnya dilaporkan waktu itu. Karena itu sroi_calculations
 * menyimpan hasil BESERTA seluruh asumsi yang dipakainya (kolom rincian),
 * persis seperti harga_modal_snapshot pada transaksi penjualan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. PROGRAM — wadah penilaian dampak untuk satu periode.
        // ============================================================
        Schema::create('impact_programs', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->text('deskripsi')->nullable();

            // Siapa yang dinilai. Kosong = SELURUH peserta yang programnya
            // beririsan dengan periode ini. Diisi = hanya binaan pendamping
            // tersebut, untuk program yang memang dijalankan per wilayah.
            $table->foreignId('pendamping_id')->nullable()->constrained()->nullOnDelete();

            // Draft -> Review -> Final. Final berarti angkanya sudah dipakai
            // melapor keluar dan tidak boleh berubah diam-diam.
            $table->string('status')->default('Draft');
            $table->text('catatan')->nullable();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // ============================================================
        // 2. INVESTASI PROGRAM — penyebut rasio SROI.
        //
        // Tanpa tabel ini, SROI tidak bisa dihitung sama sekali. Sengaja
        // memisahkan tunai dan in-kind: sumbangan tenaga/tempat/barang adalah
        // investasi yang nyata, dan mengabaikannya membuat rasio terlihat
        // jauh lebih baik daripada sebenarnya.
        // ============================================================
        Schema::create('program_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_program_id')->constrained()->cascadeOnDelete();
            $table->string('kategori');          // Pelatihan | Pendampingan | Bantuan Modal | ...
            $table->string('uraian');
            $table->date('tanggal');
            $table->unsignedBigInteger('nominal');
            $table->string('jenis')->default('Tunai');   // Tunai | In-kind
            $table->string('sumber_dana')->nullable();
            $table->text('catatan')->nullable();

            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['impact_program_id', 'kategori']);
        });

        // ============================================================
        // 3. PROXY FINANSIAL — nilai rupiah satu satuan outcome.
        //
        // MASTER TERSENDIRI, bukan kolom di outcome. Proxy yang sama dipakai
        // banyak program dan banyak periode; menyalinnya ke setiap outcome
        // berarti memperbaiki satu sumber yang keliru harus dilakukan puluhan
        // kali. Nilai yang DIPAKAI sebuah perhitungan tetap dibekukan di
        // sroi_calculations, jadi memperbarui master tidak mengubah laporan
        // yang sudah terbit.
        //
        // Kolom sumber dan tahun TIDAK nullable dengan sengaja. Proxy tanpa
        // sumber adalah angka karangan, dan angka karangan yang rapi jauh
        // lebih berbahaya daripada kolom yang jujur kosong.
        // ============================================================
        Schema::create('financial_proxies', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('satuan');            // "per UMKM per tahun", "per orang", ...
            $table->unsignedBigInteger('nilai');
            $table->string('sumber');            // lembaga/dokumen asal angka
            $table->year('tahun');
            $table->text('alasan')->nullable();  // kenapa proxy ini yang dipakai
            $table->text('catatan')->nullable();
            $table->boolean('aktif')->default(true);

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        // ============================================================
        // 4. OUTCOME — perubahan yang diklaim, beserta yang menguranginya.
        //
        // Empat faktor penyesuai disimpan sebagai PERSEN (0-100) supaya sama
        // dengan yang diketik petugas dan sama dengan yang tertulis di
        // laporan SROI; pembagian 100-nya dilakukan di SroiService, satu
        // tempat saja.
        //
        // Nilai bawaannya SENGAJA tidak netral:
        //   deadweight 0     - belum tentu benar, tapi wajib diisi sadar
        //   attribution 100  - mengklaim seluruh perubahan milik program
        // Keduanya adalah asumsi PALING MENGUNTUNGKAN, dan itulah gunanya:
        // laporan yang memakai nilai bawaan akan terlihat mencurigakan bagus,
        // dan halaman SROI memang menandainya sebagai belum ditinjau.
        // ============================================================
        Schema::create('impact_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_program_id')->constrained()->cascadeOnDelete();
            $table->string('nama');
            $table->text('deskripsi')->nullable();

            // Dari mana angka perubahannya diambil. "otomatis" = dihitung
            // sistem dari baseline/endline peserta lewat PerbandinganService;
            // "manual" = diisi petugas (mis. hasil survei di luar sistem).
            $table->string('sumber_nilai')->default('otomatis');
            // Kunci indikator di ParticipantMeasurement::INDIKATOR saat
            // sumber_nilai = otomatis. Null saat manual.
            $table->string('indikator')->nullable();

            $table->string('satuan')->nullable();
            // Dipakai HANYA saat sumber_nilai = manual.
            $table->bigInteger('nilai_perubahan_manual')->nullable();
            $table->unsignedInteger('jumlah_penerima_manual')->nullable();

            $table->foreignId('financial_proxy_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('deadweight_persen', 5, 2)->default(0);
            $table->decimal('attribution_persen', 5, 2)->default(100);
            $table->decimal('displacement_persen', 5, 2)->default(0);
            $table->decimal('drop_off_persen', 5, 2)->default(0);
            // Berapa tahun manfaatnya diperhitungkan. 1 = hanya tahun berjalan.
            $table->unsignedTinyInteger('durasi_tahun')->default(1);

            $table->text('bukti')->nullable();   // audit trail: dari mana angkanya
            $table->text('catatan')->nullable();

            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index('impact_program_id');
        });

        // ============================================================
        // 5. HASIL PERHITUNGAN — dibekukan beserta asumsinya.
        // ============================================================
        Schema::create('sroi_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_program_id')->constrained()->cascadeOnDelete();

            $table->string('status');            // Terhitung | Perlu Review | Final
            $table->unsignedBigInteger('total_investasi');
            $table->bigInteger('total_nilai_dampak');
            $table->bigInteger('nilai_sosial_bersih');
            // Rasio bisa 0 dan bisa besar; 4 desimal cukup untuk "3,2145".
            $table->decimal('rasio', 12, 4)->nullable();

            // SELURUH rincian perhitungan: outcome apa saja, proxy mana dengan
            // nilai berapa, faktor penyesuai berapa, dan hasil per outcome.
            // Inilah yang membuat laporan lama tetap bisa dijelaskan setelah
            // master proxy diperbarui.
            $table->json('rincian');
            // Skenario konservatif & optimistis pada saat dihitung.
            $table->json('sensitivitas')->nullable();

            $table->foreignId('dihitung_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dihitung_at');
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();
            $table->text('catatan_review')->nullable();

            $table->timestamps();

            $table->index(['impact_program_id', 'dihitung_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sroi_calculations');
        Schema::dropIfExists('impact_outcomes');
        Schema::dropIfExists('financial_proxies');
        Schema::dropIfExists('program_investments');
        Schema::dropIfExists('impact_programs');
    }
};
