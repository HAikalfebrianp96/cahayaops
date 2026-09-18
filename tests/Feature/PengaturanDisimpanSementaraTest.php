<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BARIS PENGATURAN DISIMPAN SEMENTARA SELAMA SATU PERMINTAAN.
 *
 * ==========================================================================
 * APA YANG DIPERBAIKI
 * ==========================================================================
 *
 * SystemSetting::current() dulu menembak query SETIAP KALI dipanggil, dan ia
 * dipanggil sekali untuk setiap binaan (FollowUpService::ambang()) serta
 * sekali untuk setiap bulan (KpiService).
 *
 * Terukur pada data sungguhan sebelum perbaikan — satu baris yang sama,
 * diambil berulang kali:
 *
 *     halaman Prioritas   40 query
 *     KPI Pendamping      27 query
 *     Tugas Saya          21 query
 *
 * Jumlahnya tumbuh seiring jumlah binaan, jadi pada 21 peserta belum terasa
 * dan tidak kelihatan. Yang melambat belakangan justru halaman yang dipakai
 * menyusun laporan.
 *
 * ==========================================================================
 * YANG PALING BERISIKO DARI PERBAIKAN INI
 * ==========================================================================
 *
 * Bukan kecepatannya, melainkan KEBASIAN. Kalau simpanannya tidak dibuang
 * saat pengaturan diubah, Admin yang menaikkan ambang follow up akan melihat
 * angka LAMA setelah menyimpan — tampak seperti "perubahannya tidak
 * tersimpan", padahal tersimpan. Itulah yang paling banyak diuji di sini.
 */
class PengaturanDisimpanSementaraTest extends TestCase
{
    use RefreshDatabase;

    // ==================================================================
    // A. HEMAT QUERY
    // ==================================================================

    public function test_dipanggil_berkali_kali_hanya_sekali_menembak_database(): void
    {
        SystemSetting::current();

        DB::enableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 20; $i++) {
            SystemSetting::current();
        }

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $jumlah, 'Pemanggilan berikutnya seharusnya tidak menembak database lagi.');
    }

    public function test_nilainya_tetap_benar_walau_disimpan_sementara(): void
    {
        $setting = SystemSetting::current();
        $setting->followup_kuning_hari = 17;
        $setting->save();

        // Ambil ulang dari awal — harus membaca nilai yang baru, bukan bawaan.
        $this->assertSame(17, (int) SystemSetting::current()->followup_kuning_hari);
    }

    // ==================================================================
    // B. TIDAK BOLEH BASI — bagian paling berisiko
    // ==================================================================

    public function test_simpanan_dibuang_begitu_pengaturan_diubah(): void
    {
        SystemSetting::current(); // isi simpanannya lebih dulu

        $setting = SystemSetting::current();
        $setting->followup_merah_hari = 99;
        $setting->save();

        $this->assertSame(99, (int) SystemSetting::current()->followup_merah_hari);
    }

    public function test_ambang_follow_up_ikut_berubah_dalam_permintaan_yang_sama(): void
    {
        // Inti masalahnya: FollowUpService membaca pengaturan lewat
        // current(). Kalau simpanannya basi, ambang yang dipakai menentukan
        // status binaan masih memakai kebijakan lama.
        $svc = app(FollowUpService::class);

        $this->assertSame(['kuning' => 30, 'merah' => 45], $svc->ambang());

        $setting = SystemSetting::current();
        $setting->followup_kuning_hari = 10;
        $setting->followup_merah_hari = 20;
        $setting->save();

        $this->assertSame(['kuning' => 10, 'merah' => 20], $svc->ambang());
    }

    public function test_perubahan_lewat_halaman_pengaturan_langsung_berlaku(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@setting.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('pengaturan.update'), [
            'nama_sistem' => 'CahayaOS',
            'nama_lembaga' => 'YBM PLN UP3 Bengkulu',
            'nama_program' => 'Cahaya Preneur',
            'followup_kuning_hari' => 14,
            'followup_merah_hari' => 28,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['kuning' => 14, 'merah' => 28],
            app(FollowUpService::class)->ambang(),
        );
    }

    // ==================================================================
    // C. JUMLAH BINAAN DI KPI — simpanan sementara yang kedua
    // ==================================================================

    public function test_jumlah_binaan_kpi_tetap_benar(): void
    {
        // Angka ini dipakai menilai beban kerja pendamping, jadi salah
        // sedikit pun berarti penilaian kinerja yang keliru.
        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'status' => 'Aktif']);

        foreach (range(1, 4) as $i) {
            Participant::create([
                'nama_peserta' => 'Peserta ' . $i, 'nomor_hp' => '08120000000' . $i,
                'nama_usaha' => 'Usaha ' . $i, 'kategori_usaha' => 'Kuliner',
                'tanggal_mulai_program' => now()->subYear()->toDateString(),
                'status' => 'Aktif', 'pendamping_id' => $profil->id,
            ]);
        }

        $rapor = app(\App\Services\KpiService::class)->untukSatu(
            $profil, now()->startOfMonth(), now()->endOfMonth(),
        );

        $this->assertSame(4, (int) $rapor['indikator']['target_jumlah_binaan']['aktual']);
    }

    public function test_simpanan_tidak_bocor_antar_test(): void
    {
        // Penjaga terhadap cara perbaikan ini SALAH diterapkan.
        //
        // Kalau simpanannya memakai properti statis, ia bertahan sepanjang
        // proses PHPUnit dan test berikutnya akan membaca baris pengaturan
        // milik test sebelumnya — yang databasenya sudah digulung balik.
        // Container dibuat ulang tiap test, jadi ini harus selalu bawaan.
        // Diperiksa lewat ambang(), bukan kolomnya langsung: kolom di database
        // memang kosong sampai Admin mengisinya, dan angka 30/45 berasal dari
        // config/cahayaos.php sebagai nilai bawaan yang berlaku.
        $this->assertSame(['kuning' => 30, 'merah' => 45], app(FollowUpService::class)->ambang());
    }
}
