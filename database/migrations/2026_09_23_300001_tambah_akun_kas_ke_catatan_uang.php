<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KAS & BANK — TAHAP 3: SETIAP CATATAN UANG MENYEBUT AKUNNYA.
 *
 * Pengeluaran, simpanan anggota, Dana Sosial, dan Dana Hibah diberi kolom
 * akun_kas_id: dari laci atau rekening mana uangnya keluar, ke mana uangnya
 * masuk. Dengan begitu saldo tiap akun kelak bisa dijumlahkan langsung dari
 * catatan aslinya — tanpa buku mutasi salinan yang harus disinkronkan.
 *
 * Kolomnya nullable: NULL berarti "akun belum ditentukan", dan laporan kas
 * akan menyebutnya terang-terangan, bukan diam-diam memasukkannya ke Kas.
 *
 * DATA LAMA diisi sekali: pengeluaran menurut metode pembayarannya
 * (Transfer/QRIS -> Bank aktif pertama, selain itu -> Kas aktif pertama);
 * simpanan dan dana tidak punya kolom metode, jadi ke Kas aktif pertama.
 * Hanya baris yang masih NULL yang diisi, jadi aman dijalankan ulang.
 */
return new class extends Migration
{
    private const TABEL = [
        'pengeluaran',
        'member_savings',
        'dana_sukarela_masuk',
        'dana_sukarela_keluar',
        'dana_hibah_masuk',
        'dana_hibah_keluar',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (Schema::hasColumn($tabel, 'akun_kas_id')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->foreignId('akun_kas_id')->nullable()->constrained('akun_kas')->nullOnDelete();
            });
        }

        $this->isiDataLama();
    }

    private function isiDataLama(): void
    {
        $kas = DB::table('akun_kas')->where('jenis', 'Kas')->where('status', 'Aktif')->orderBy('id')->value('id');
        $bank = DB::table('akun_kas')->where('jenis', 'Bank')->where('status', 'Aktif')->orderBy('id')->value('id');

        if ($bank) {
            DB::table('pengeluaran')
                ->whereNull('akun_kas_id')
                ->whereIn('metode_pembayaran', ['Transfer', 'QRIS'])
                ->update(['akun_kas_id' => $bank]);
        }

        if ($kas) {
            foreach (self::TABEL as $tabel) {
                $query = DB::table($tabel)->whereNull('akun_kas_id');

                // Transferan tanpa akun Bank dibiarkan NULL — lebih jujur
                // daripada memasukkannya ke laci.
                if ($tabel === 'pengeluaran') {
                    $query->whereNotIn('metode_pembayaran', ['Transfer', 'QRIS']);
                }

                $query->update(['akun_kas_id' => $kas]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasColumn($tabel, 'akun_kas_id')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->dropConstrainedForeignId('akun_kas_id');
            });
        }
    }
};
