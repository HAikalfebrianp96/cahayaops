<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OMZET HARIAN / MINGGUAN / BULANAN.
 *
 * ================================================================
 * KENAPA BUKAN TABEL BARU
 * ================================================================
 *
 * Harian, mingguan, dan bulanan mencatat HAL YANG SAMA — omzet sebuah usaha
 * pada suatu rentang waktu — hanya berbeda panjang rentangnya. Memisahkannya
 * jadi tabel sendiri berarti setiap penjumlahan omzet di seluruh aplikasi
 * (dashboard, monitoring, laporan, Impact, SROI) harus menggabungkan tiga
 * sumber lebih dulu, dan satu saja yang terlewat menghasilkan angka yang
 * salah tanpa terlihat salah.
 *
 * ================================================================
 * KOMPATIBILITAS: periode_bulan & periode_tahun TETAP DIISI SEMUA MODE
 * ================================================================
 *
 * Laporan harian 3 September 2026 tetap menyimpan periode_bulan=9,
 * periode_tahun=2026. Ini keputusan yang membuat SELURUH kueri existing
 * terus benar tanpa disentuh: "SUM(omzet) WHERE periode_bulan=9" kini
 * menjumlahkan seluruh hari di September dan hasilnya tetap total bulan itu.
 * Begitu juga "peserta mana yang sudah melapor bulan ini".
 *
 * ================================================================
 * ANTI DOUBLE COUNTING
 * ================================================================
 *
 * Bahaya terbesar fitur ini: satu peserta mencatat harian SEKALIGUS bulanan
 * untuk bulan yang sama, lalu keduanya terjumlah. Aturannya:
 *
 *     SATU PESERTA + SATU BULAN = SATU MODE PENCATATAN.
 *
 * Dengan aturan itu, baris-baris dalam satu bulan MEMBAGI HABIS bulan itu
 * tanpa tumpang tindih, sehingga SUM biasa selalu benar. Aturannya dijaga
 * aplikasi (OmzetReport::modeBulan + validasi di kedua jalur input), karena
 * unique index tidak bisa menyatakan "kolom ini harus sama untuk seluruh
 * baris dengan bulan yang sama".
 *
 * ================================================================
 * UNIQUE INDEX: DIPERLUAS, BUKAN DIHAPUS
 * ================================================================
 *
 * Index lama (participant_id, periode_bulan, periode_tahun) memblokir dua
 * laporan untuk bulan yang sama — benar untuk bulanan, tapi ia juga akan
 * memblokir tanggal 2 setelah tanggal 1 tercatat. Index barunya menambahkan
 * jenis_periode dan periode_urut di belakangnya.
 *
 * Untuk data lama jaminannya TIDAK BERKURANG sedikit pun: seluruh 271 baris
 * yang ada di-backfill sebagai Bulanan dengan periode_urut=0, sehingga
 * kombinasi (peserta, bulan, tahun, 'Bulanan', 0) tetap unik — persis
 * sekuat aturan lama.
 *
 * Seperti index lama, index baru juga TIDAK mempedulikan deleted_at. Itu
 * disengaja dan sudah jadi perilaku modul ini: baris ter-soft-delete tetap
 * memblokir periodenya, dan alur penyimpanan memulihkan baris lama alih-alih
 * menyisipkan baris baru (lihat OmzetReport::untukPeriode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            // 'Bulanan' | 'Mingguan' | 'Harian' — string biasa, mengikuti pola
            // kolom status di seluruh tabel lain (bukan enum database).
            $table->string('jenis_periode')->default('Bulanan')->after('periode_tahun');

            // Nomor urut di DALAM bulan: 0 untuk Bulanan (bulan utuh),
            // 1-5 untuk Mingguan (minggu ke berapa), 1-31 untuk Harian.
            //
            // Disimpan sebagai satu kolom, bukan dua (minggu_ke + tanggal_ke),
            // karena keduanya tidak pernah dipakai bersamaan — satu baris
            // hanya punya satu mode. Dua kolom berarti selalu ada satu yang
            // NULL dan satu aturan tambahan tentang mana yang boleh terisi.
            $table->unsignedTinyInteger('periode_urut')->default(0)->after('jenis_periode');

            // Rentang nyata yang dicakup baris ini. DITURUNKAN dari empat
            // kolom di atas, tapi disimpan supaya kueri berbasis tanggal
            // (grafik harian, rentang lintas bulan) tidak perlu menghitung
            // ulang di PHP untuk setiap baris.
            $table->date('tanggal_mulai')->nullable()->after('periode_urut');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
        });

        // ------------------------------------------------------------
        // BACKFILL — seluruh baris lama adalah laporan BULANAN.
        //
        // Itu memang benar secara historis: sampai hari ini form omzet hanya
        // pernah menanyakan bulan dan tahun. Jadi ini bukan tebakan,
        // melainkan pernyataan ulang tentang apa yang sudah tersimpan.
        //
        // Nilai default kolom sudah 'Bulanan'/0, jadi yang perlu diisi hanya
        // rentang tanggalnya.
        //
        // Dikerjakan SATU UPDATE PER BULAN YANG BENAR-BENAR ADA, bukan satu
        // update per baris dan bukan pula ekspresi SQL. Ekspresi tanggal
        // seperti LAST_DAY() hanya ada di MySQL, sementara test suite
        // CahayaOS berjalan di SQLite — migration yang hanya jalan di satu
        // mesin database adalah migration yang tidak pernah teruji.
        // ------------------------------------------------------------
        $bulanTersedia = DB::table('omzet_reports')
            ->select('periode_tahun', 'periode_bulan')
            ->distinct()
            ->get();

        foreach ($bulanTersedia as $b) {
            $awal = Carbon::create((int) $b->periode_tahun, (int) $b->periode_bulan, 1)->startOfMonth();

            DB::table('omzet_reports')
                ->where('periode_tahun', $b->periode_tahun)
                ->where('periode_bulan', $b->periode_bulan)
                ->update([
                    'tanggal_mulai' => $awal->toDateString(),
                    'tanggal_selesai' => $awal->copy()->endOfMonth()->toDateString(),
                ]);
        }

        // ------------------------------------------------------------
        // INDEX — yang baru dibuat DULU, yang lama dilepas SETELAHNYA.
        //
        // Urutannya disengaja: pada tidak satu pun momen tabel ini berada
        // tanpa jaminan keunikan periode.
        // ------------------------------------------------------------
        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->unique(
                ['participant_id', 'periode_tahun', 'periode_bulan', 'jenis_periode', 'periode_urut'],
                'omzet_reports_unique_periode_mode'
            );
        });

        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->dropUnique('omzet_reports_unique_periode');
        });
    }

    public function down(): void
    {
        // Membalik urutan up(): index lama dikembalikan lebih dulu.
        //
        // CATATAN: kalau sudah ada laporan harian/mingguan, index lama akan
        // ditolak database karena satu bulan kini punya banyak baris. Itu
        // perilaku yang benar — rollback tidak boleh diam-diam membuang
        // laporan agar indexnya muat.
        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->unique(['participant_id', 'periode_bulan', 'periode_tahun'], 'omzet_reports_unique_periode');
        });

        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->dropUnique('omzet_reports_unique_periode_mode');
            $table->dropColumn(['jenis_periode', 'periode_urut', 'tanggal_mulai', 'tanggal_selesai']);
        });
    }
};
