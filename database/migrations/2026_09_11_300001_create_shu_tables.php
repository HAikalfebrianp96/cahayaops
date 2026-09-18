<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SHU — SISA HASIL USAHA.
 *
 * Dua tabel: satu periode penetapan, dan alokasi per anggota di dalamnya.
 *
 * ================================================================
 * PERSENTASE PEMBAGIAN TIDAK DI-HARD-CODE
 * ================================================================
 *
 * Pembagian SHU ditetapkan Rapat Anggota, berbeda antar koperasi, dan berubah
 * dari tahun ke tahun. Menuliskannya sebagai konstanta di kode berarti setiap
 * keputusan rapat harus menunggu programmer. Karena itu komponennya disimpan
 * sebagai JSON PER PERIODE (kolom `komponen`), bukan sebagai pengaturan global
 * tunggal: penetapan tahun lalu tetap memperlihatkan persentase yang berlaku
 * WAKTU ITU, meski aturannya sudah diubah untuk tahun ini.
 *
 * Ini pola yang sama dengan snapshot harga di transaksi penjualan dan dengan
 * rincian di sroi_calculations: yang sudah ditetapkan tidak berubah ketika
 * aturannya diperbarui.
 *
 * ================================================================
 * DASAR PEMBAGIAN JUGA DIBEKUKAN
 * ================================================================
 *
 * Saldo simpanan dan nilai transaksi seorang anggota terus bergerak setiap
 * hari. Kalau alokasi SHU dihitung ulang dari data hari ini, pembagian yang
 * sudah disahkan rapat akan berubah sendiri beberapa minggu kemudian. Jadi
 * dasar_simpanan dan dasar_transaksi disimpan sebagai angka di tabel alokasi,
 * bukan dihitung saat ditampilkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_periods', function (Blueprint $table) {
            $table->id();
            $table->year('tahun');
            $table->date('tanggal_penetapan');

            // 'laba_rugi' = diambil dari perhitungan Laba Rugi koperasi pada
            // periode itu; 'manual' = angka hasil keputusan rapat.
            $table->string('sumber')->default('laba_rugi');
            $table->unsignedBigInteger('total_shu');

            // Komponen pembagian yang BERLAKU untuk periode ini, dibekukan.
            // Bentuk: [{nama, persen, ke_anggota, dasar}] — `dasar` bernilai
            // 'simpanan' atau 'transaksi' hanya untuk komponen ke_anggota.
            $table->json('komponen');

            $table->string('status')->default('Draft');   // Draft | Final
            $table->text('catatan')->nullable();

            $table->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Satu tahun satu penetapan. Unique index ini SENGAJA tidak
            // mempedulikan deleted_at — sama seperti omzet_reports, alur
            // penyimpanannya yang memeriksa lebih dulu (lihat model).
            $table->unique('tahun');
        });

        Schema::create('shu_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shu_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('koperasi_member_id')->constrained()->cascadeOnDelete();

            // Dasar pembagian, DIBEKUKAN pada saat penetapan.
            $table->unsignedBigInteger('dasar_simpanan')->default(0);
            $table->unsignedBigInteger('dasar_transaksi')->default(0);

            $table->unsignedBigInteger('jasa_modal')->default(0);
            $table->unsignedBigInteger('jasa_usaha')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            $table->timestamps();

            $table->unique(['shu_period_id', 'koperasi_member_id']);
        });

        // ============================================================
        // SATU DATA, TIDAK INPUT ULANG.
        //
        // Jasa usaha dibagi menurut seberapa banyak anggota BERTRANSAKSI di
        // koperasi. Nilainya ada di sales_transactions, tapi transaksi
        // menunjuk ke `customers`, sementara keanggotaan ada di
        // `koperasi_members` — dan keduanya belum pernah terhubung.
        //
        // Tautan ini yang menghubungkannya, dan SENGAJA nullable: sebagian
        // besar pelanggan koperasi memang bukan anggota, dan itu wajar.
        // Pelanggan tanpa tautan tidak ikut dalam pembagian jasa usaha —
        // bukan dianggap anggota dengan transaksi nol.
        // ============================================================
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('koperasi_member_id')->nullable()->after('email')
                ->constrained('koperasi_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['koperasi_member_id']);
            $table->dropColumn('koperasi_member_id');
        });

        Schema::dropIfExists('shu_allocations');
        Schema::dropIfExists('shu_periods');
    }
};
