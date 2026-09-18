<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUKTI LAPORAN OMZET.
 *
 * Peserta mengisi omzetnya sendiri lewat link publik tanpa login, dan
 * angkanya langsung masuk ke monitoring, laporan, dan perhitungan dampak.
 * Bukti — foto catatan penjualan, buku kas, tangkapan layar transaksi —
 * adalah satu-satunya cara pendamping bisa memverifikasi angka itu tanpa
 * harus datang ke tempat usahanya.
 *
 * ================================================================
 * DUA KOLOM, BUKAN SATU
 * ================================================================
 *
 * `bukti_path`  lokasi file di disk privat — dibangkitkan sistem, acak.
 * `bukti_asli`  nama file sebagaimana peserta mengunggahnya.
 *
 * Nama asli TIDAK dipakai sebagai nama file di disk. Nama yang datang dari
 * peserta bisa memuat "../", karakter yang bermakna di shell, atau ekstensi
 * ganda seperti "nota.jpg.php". Sistem menyimpannya sebagai teks biasa untuk
 * ditampilkan saja, dan file fisiknya memakai nama acak buatan Laravel.
 * Polanya sama persis dengan file_path/file_asli pada legalitas_records.
 *
 * ================================================================
 * DISK PRIVAT, BUKAN public/
 * ================================================================
 *
 * File disimpan di disk `local` (storage/app/private) dan hanya bisa dibuka
 * lewat route ber-otorisasi. Foto buku kas sebuah usaha adalah data keuangan
 * milik peserta; menaruhnya di storage/app/public berarti siapa pun yang
 * menebak URL-nya bisa mengunduhnya tanpa login.
 *
 * ================================================================
 * FILE TIDAK IKUT TERHAPUS
 * ================================================================
 *
 * Tabel ini memakai SoftDeletes, jadi laporan yang "dihapus" masih bisa
 * dipulihkan dari Arsip. Kalau filenya ikut dihapus dari disk, pemulihannya
 * menghasilkan laporan tanpa bukti — dan buktinya tidak bisa dibuat ulang.
 * Karena itu penghapusan laporan TIDAK PERNAH menyentuh file, dan mengganti
 * bukti pun menyimpan file lama (jejaknya dicatat di Activity Log).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->string('bukti_path')->nullable()->after('biaya_operasional');
            $table->string('bukti_asli')->nullable()->after('bukti_path');
        });
    }

    public function down(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->dropColumn(['bukti_path', 'bukti_asli']);
        });
    }
};
