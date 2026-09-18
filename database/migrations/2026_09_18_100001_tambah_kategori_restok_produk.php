<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MENAMBAH KATEGORI PENGELUARAN "Re-Stok Produk".
 *
 * =========================================================================
 * KENAPA LEWAT MIGRATION, BUKAN DIKETIK LANGSUNG DI DATABASE
 * =========================================================================
 *
 * Kategori pengeluaran memang sudah dinamis — Admin dan Tim Koperasi bisa
 * menambah sendiri lewat halaman Kategori Pengeluaran. Jadi baris ini bisa
 * saja diketik manual.
 *
 * Tapi tujuh kategori bawaan yang sudah ada (Gaji, Listrik, BBM, Kemasan,
 * Transportasi, ATK, Lainnya) lahir dari migration pembuatan tabelnya.
 * Kalau yang kedelapan ditambahkan dengan cara lain, ia tidak akan ada di
 * database testing maupun pada pemasangan baru — dan perbedaannya baru
 * ketahuan ketika ada yang bertanya kenapa kategorinya hilang di sana.
 *
 * =========================================================================
 * AMAN DIJALANKAN BERULANG
 * =========================================================================
 *
 * insertOrIgnore dengan kolom `nama` yang UNIQUE: kalau kategorinya sudah
 * pernah dibuat manual oleh petugas, migration ini tidak membuat duplikat
 * dan tidak menimpa apa pun. Pola yang sama dengan migration pembuatan
 * tabelnya.
 *
 * Turunnya sengaja TIDAK menghapus barisnya: begitu ada pengeluaran yang
 * memakainya, menghapus kategorinya akan memutus riwayat pengeluaran itu.
 */
return new class extends Migration
{
    private const NAMA = 'Re-Stok Produk';

    public function up(): void
    {
        if (! Schema::hasTable('pengeluaran_categories')) {
            return;
        }

        DB::table('pengeluaran_categories')->insertOrIgnore([
            [
                'nama' => self::NAMA,
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        // Sengaja dibiarkan kosong.
        //
        // Menghapus kategori yang mungkin sudah dipakai pengeluaran akan
        // membuat baris pengeluaran itu kehilangan kategorinya — kerusakan
        // riwayat yang tidak sebanding dengan "kerapian" rollback.
    }
};
