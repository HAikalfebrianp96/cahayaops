<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KODE PRODUK UNIK DI SELURUH KOPERASI, BUKAN HANYA DALAM SATU UNIT.
 *
 * =========================================================================
 * KEADAAN SEBELUMNYA
 * =========================================================================
 *
 * Yang ada baru unique gabungan (unit_id, kode). Artinya dua unit usaha
 * boleh memakai kode yang sama persis untuk barang yang berbeda.
 *
 * Selama kode diketik petugas per unit, itu masuk akal. Sejak kodenya
 * dibuat sistem secara berurut (PRD-000001, PRD-000002, ...) dan ikut
 * tercetak di nota, kode yang sama untuk dua barang berbeda berhenti bisa
 * dipercaya: orang yang menyebut "PRD-000007" tidak lagi menunjuk satu
 * barang tertentu.
 *
 * =========================================================================
 * AMAN DIJALANKAN
 * =========================================================================
 *
 * Diperiksa lebih dulu: kalau ada kode yang kembar lintas unit, indeksnya
 * TIDAK dipasang dan migration berhenti dengan tenang. Memasangnya secara
 * paksa akan menggagalkan seluruh migration di tengah jalan pada database
 * yang datanya terlanjur kembar — dan itu jauh lebih buruk daripada
 * kehilangan satu penjagaan.
 *
 * Unique gabungan yang lama sengaja DIBIARKAN: melepasnya tidak menambah
 * apa pun, dan indeks itu masih berguna untuk pencarian per unit.
 */
return new class extends Migration
{
    private const INDEKS = 'products_services_kode_unique';

    public function up(): void
    {
        if (! Schema::hasTable('products_services') || ! Schema::hasColumn('products_services', 'kode')) {
            return;
        }

        if ($this->sudahAda()) {
            return;
        }

        $kembar = DB::table('products_services')
            ->select('kode')
            ->groupBy('kode')
            ->havingRaw('count(*) > 1')
            ->count();

        if ($kembar > 0) {
            // Dibiarkan apa adanya — data yang sudah terlanjur kembar adalah
            // fakta yang harus diputuskan manusia, bukan dipaksakan skema.
            return;
        }

        Schema::table('products_services', function (Blueprint $table) {
            $table->unique('kode', self::INDEKS);
        });
    }

    public function down(): void
    {
        if (! $this->sudahAda()) {
            return;
        }

        Schema::table('products_services', function (Blueprint $table) {
            $table->dropUnique(self::INDEKS);
        });
    }

    /**
     * Diperiksa lewat Schema::getIndexes, BUKAN "SHOW INDEX".
     *
     * SHOW INDEX hanya dikenal MySQL. Database pengujian memakai SQLite, dan
     * migration yang gagal di sana membuat SELURUH test berhenti sebelum
     * satu assertion pun berjalan — gejala yang jauh dari penyebabnya.
     */
    private function sudahAda(): bool
    {
        return collect(Schema::getIndexes('products_services'))
            ->contains(fn ($i) => ($i['name'] ?? null) === self::INDEKS);
    }
};
