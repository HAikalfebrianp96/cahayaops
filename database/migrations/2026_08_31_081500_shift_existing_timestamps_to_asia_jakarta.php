<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Geser data waktu LAMA +7 jam: UTC -> WIB.
 *
 * LATAR BELAKANG
 * config/app.php dulu meng-hardcode 'UTC' sehingga APP_TIMEZONE di .env
 * (yang sejak awal menulis Asia/Jakarta) tidak pernah terbaca. Akibatnya
 * SEMUA created_at/updated_at dan kolom datetime lain ditulis memakai jam
 * UTC. Setelah timezone aplikasi diperbaiki jadi Asia/Jakarta, nilai-nilai
 * lama itu dibaca sebagai WIB — jadi tampil 7 jam LEBIH AWAL dari kejadian
 * sebenarnya. Migration ini merapikan data lama supaya cocok.
 *
 * BATAS WAKTU (cutoff)
 * Saat migration ini dibuat, datanya terbelah bersih:
 *   - nilai UTC terakhir  : 2026-08-31 01:03:40
 *   - nilai WIB pertama   : 2026-08-31 08:12:35
 * Ada jeda kosong ~7 jam di antaranya, jadi cutoff 2026-08-31 08:00:00
 * memisahkan keduanya tanpa ambiguitas. Baris yang ditulis SETELAH perbaikan
 * timezone sudah benar dan SENGAJA tidak disentuh.
 *
 * CAKUPAN
 * Hanya kolom bertipe datetime/timestamp. Kolom bertipe `date` (mis. kolom
 * `tanggal` pada transaksi/pengeluaran) TIDAK disentuh karena tidak menyimpan
 * jam — menggesernya justru berisiko memindahkan tanggal.
 */
return new class extends Migration
{
    /** Batas pemisah data lama (UTC) vs data baru (WIB). */
    private const CUTOFF = '2026-08-31 08:00:00';

    private const JAM = 7;

    public function up(): void
    {
        $this->geser(self::JAM);
    }

    /**
     * Membatalkan: geser balik -7 jam. Hanya menyentuh baris yang tadi
     * digeser (yaitu yang sekarang berada di bawah cutoff + 7 jam).
     */
    public function down(): void
    {
        $this->geser(-self::JAM);
    }

    private function geser(int $jam): void
    {
        // Saat maju (+7) targetnya nilai di bawah cutoff. Saat mundur (-7)
        // nilai itu sudah pindah ke bawah (cutoff + 7 jam), jadi batasnya
        // ikut digeser supaya baris yang sama yang kena.
        $batas = $jam > 0
            ? self::CUTOFF
            : date('Y-m-d H:i:s', strtotime(self::CUTOFF) + self::JAM * 3600);

        $arah = $jam > 0 ? '+' : '-';
        $abs = abs($jam);

        foreach ($this->kolomWaktu() as [$tabel, $kolom]) {
            DB::table($tabel)
                ->whereNotNull($kolom)
                ->where($kolom, '<', $batas)
                ->update([
                    $kolom => DB::raw("DATE_ADD(`{$kolom}`, INTERVAL {$arah}{$abs} HOUR)"),
                ]);
        }
    }

    /**
     * Semua kolom datetime/timestamp di database ini.
     *
     * Sengaja dibaca dari information_schema (bukan daftar tabel yang
     * ditulis manual) supaya tabel apa pun yang ada saat migration ini
     * dijalankan ikut terurus — termasuk di database produksi yang isinya
     * bisa berbeda dari lokal.
     */
    private function kolomWaktu(): array
    {
        $driver = DB::connection()->getDriverName();

        // information_schema hanya ada di MySQL/MariaDB. Di driver lain
        // (mis. SQLite yang dipakai environment test) migration ini tidak
        // melakukan apa-apa — memang tidak ada data lama untuk dirapikan.
        if ($driver !== 'mysql') {
            return [];
        }

        $rows = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ?
                AND DATA_TYPE IN ('datetime', 'timestamp')
              ORDER BY TABLE_NAME, COLUMN_NAME",
            [DB::connection()->getDatabaseName()]
        );

        return array_map(fn ($r) => [$r->t, $r->c], $rows);
    }
};
