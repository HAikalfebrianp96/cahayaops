<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OMZET HARIAN / MINGGUAN / BULANAN.
 *
 * Yang dijaga kelas ini, berurutan dari yang paling berbahaya:
 *
 * 1. TIDAK ADA DOUBLE COUNTING. Kalau satu peserta bisa mencatat harian
 *    SEKALIGUS bulanan untuk September, maka setiap penjumlahan omzet
 *    September menghitungnya dua kali — dan hasilnya tetap berupa angka yang
 *    masuk akal, jadi tidak akan ada yang curiga. Aturannya: satu bulan, satu
 *    mode.
 *
 * 2. KUERI LAMA TETAP BENAR. periode_bulan & periode_tahun tetap diisi untuk
 *    ketiga mode, sehingga "SUM omzet bulan ini" menjumlahkan seluruh hari di
 *    bulan itu dan hasilnya tetap total bulanan. Ini yang membuat dashboard,
 *    monitoring, laporan, Impact, dan SROI tidak perlu diubah sama sekali.
 *
 * 3. PERIODE TIDAK PERNAH MELUBER KE BULAN LAIN. Tanggal 29 Februari dan
 *    minggu ke-5 Februari tidak ada, dan sistem harus menolaknya — bukan
 *    menjepitnya diam-diam ke akhir bulan lalu menyimpan dua baris yang
 *    menunjuk hari yang sama.
 */
class OmzetModePeriodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@mode.test', 'status' => 'Aktif']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@mode.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2026-01-01',
            'status' => 'Aktif',
            'pendamping_id' => $profil->id,
        ])->fresh();
    }

    // ==================================================================
    // A. KETIGA MODE TERSIMPAN DENGAN BENAR
    // ==================================================================

    public function test_laporan_bulanan_mencakup_seluruh_bulan(): void
    {
        $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 5_000_000])
            ->assertSessionHasNoErrors();

        $r = OmzetReport::firstOrFail();

        $this->assertSame(OmzetReport::BULANAN, $r->jenis_periode);
        $this->assertSame(0, $r->periode_urut);
        $this->assertSame('2026-09-01', $r->tanggal_mulai->toDateString());
        $this->assertSame('2026-09-30', $r->tanggal_selesai->toDateString());
        $this->assertSame('September 2026', $r->periodeLabel());
    }

    public function test_laporan_harian_mencakup_satu_hari(): void
    {
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 3, 'omzet' => 250_000])
            ->assertSessionHasNoErrors();

        $r = OmzetReport::firstOrFail();

        $this->assertSame('2026-09-03', $r->tanggal_mulai->toDateString());
        $this->assertSame('2026-09-03', $r->tanggal_selesai->toDateString());
        $this->assertSame('03 September 2026', $r->periodeLabel());
        // Kolom lama TETAP terisi — inilah yang menjaga kueri existing benar.
        $this->assertSame(9, $r->periode_bulan);
        $this->assertSame(2026, $r->periode_tahun);
    }

    public function test_laporan_mingguan_adalah_blok_tujuh_hari_dari_tanggal_satu(): void
    {
        // Minggu ke-2 = tanggal 8-14. Definisi kalender (Senin-Minggu) akan
        // membuat minggu pertama menggantung ke Agustus, dan omzet September
        // jadi memuat hari-hari bulan sebelumnya.
        $this->kirim(['jenis_periode' => OmzetReport::MINGGUAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 2, 'omzet' => 1_200_000])
            ->assertSessionHasNoErrors();

        $r = OmzetReport::firstOrFail();

        $this->assertSame('2026-09-08', $r->tanggal_mulai->toDateString());
        $this->assertSame('2026-09-14', $r->tanggal_selesai->toDateString());
        $this->assertSame('Minggu ke-2 September 2026', $r->periodeLabel());
    }

    public function test_minggu_terakhir_dipotong_di_akhir_bulan(): void
    {
        // Minggu ke-5 September = tanggal 29-30, bukan 29 Sep - 5 Okt.
        // Kalau meluber, omzet Oktober ikut masuk ke September.
        $this->kirim(['jenis_periode' => OmzetReport::MINGGUAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 5, 'omzet' => 400_000])
            ->assertSessionHasNoErrors();

        $r = OmzetReport::firstOrFail();

        $this->assertSame('2026-09-29', $r->tanggal_mulai->toDateString());
        $this->assertSame('2026-09-30', $r->tanggal_selesai->toDateString());
    }

    public function test_seluruh_minggu_membagi_habis_bulan_tanpa_tumpang_tindih(): void
    {
        // Syarat matematis yang membuat SUM per bulan tetap benar.
        $hariTercakup = [];

        foreach (range(1, 5) as $minggu) {
            [$mulai, $selesai] = OmzetReport::rentang(OmzetReport::MINGGUAN, $minggu, 9, 2026);

            for ($t = $mulai->copy(); $t->lessThanOrEqualTo($selesai); $t->addDay()) {
                $hariTercakup[] = $t->toDateString();
            }
        }

        $this->assertCount(30, $hariTercakup, 'Gabungan seluruh minggu harus tepat 30 hari untuk September.');
        $this->assertCount(30, array_unique($hariTercakup), 'Tidak boleh ada hari yang tercakup dua minggu.');
    }

    // ==================================================================
    // B. ANTI DOUBLE COUNTING — bagian terpenting
    // ==================================================================

    public function test_bulan_yang_sudah_dicatat_harian_menolak_laporan_bulanan(): void
    {
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 1, 'omzet' => 100_000]);

        $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 5_000_000])
            ->assertSessionHasErrors('jenis_periode');

        $this->assertSame(1, OmzetReport::count());
        $this->assertSame(100_000, (int) OmzetReport::sum('omzet'));
    }

    public function test_bulan_yang_sudah_dicatat_bulanan_menolak_laporan_harian(): void
    {
        $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 5_000_000]);

        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 1, 'omzet' => 100_000])
            ->assertSessionHasErrors('jenis_periode');

        $this->assertSame(5_000_000, (int) OmzetReport::sum('omzet'));
    }

    public function test_harian_dan_mingguan_juga_tidak_boleh_dicampur(): void
    {
        // Keduanya sama-sama "sub-bulan", tapi mencampurnya tetap tumpang
        // tindih: tanggal 3 sudah termasuk minggu ke-1.
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 3, 'omzet' => 100_000]);

        $this->kirim(['jenis_periode' => OmzetReport::MINGGUAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 1, 'omzet' => 700_000])
            ->assertSessionHasErrors('jenis_periode');
    }

    public function test_bulan_berbeda_boleh_memakai_mode_berbeda(): void
    {
        // Aturannya per bulan, bukan per peserta seumur hidup. Peserta yang
        // mulai rajin mencatat harian bulan depan tidak boleh terhalang oleh
        // laporan bulanan bulan lalu.
        $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 8, 'periode_tahun' => 2026, 'omzet' => 4_000_000])
            ->assertSessionHasNoErrors();

        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 1, 'omzet' => 100_000])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, OmzetReport::count());
    }

    public function test_pesan_penolakan_menyebutkan_jalan_keluarnya(): void
    {
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 1, 'omzet' => 100_000]);

        $respons = $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 5_000_000]);

        $pesan = session('errors')->first('jenis_periode');

        $this->assertStringContainsString('September', $pesan);
        $this->assertStringContainsString('harian', $pesan);
        $this->assertStringContainsString('tidak terhitung dua kali', $pesan);
    }

    public function test_mode_bentrok_juga_ditolak_di_form_publik(): void
    {
        // Jalur publik adalah satu-satunya endpoint tulis tanpa login —
        // aturannya harus sama persis, bukan sekadar mirip.
        $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 5_000_000]);

        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'jenis_periode' => OmzetReport::HARIAN,
            'periode_bulan' => 9,
            'periode_tahun' => 2026,
            'periode_urut' => 5,
            'omzet' => 200_000,
        ])->assertSessionHasErrors('jenis_periode');

        $this->assertSame(1, OmzetReport::count());
    }

    // ==================================================================
    // C. KOMPATIBILITAS
    // ==================================================================

    public function test_jumlah_harian_satu_bulan_sama_dengan_total_bulanan(): void
    {
        // Inti kompatibilitas: kueri bulanan lama tidak berubah sama sekali,
        // dan hasilnya tetap total bulan itu.
        foreach ([1 => 100_000, 2 => 150_000, 3 => 250_000] as $tanggal => $nilai) {
            $this->kirim([
                'jenis_periode' => OmzetReport::HARIAN,
                'periode_bulan' => 9, 'periode_tahun' => 2026,
                'periode_urut' => $tanggal, 'omzet' => $nilai,
            ])->assertSessionHasNoErrors();
        }

        $totalBulan = OmzetReport::where('periode_bulan', 9)->where('periode_tahun', 2026)->sum('omzet');

        $this->assertSame(500_000, (int) $totalBulan);
    }

    public function test_request_tanpa_jenis_periode_tetap_tersimpan_sebagai_bulanan(): void
    {
        // Sebelum mode periode ada, setiap laporan adalah laporan bulanan.
        // Request lama tidak boleh mendadak ditolak.
        $this->kirim(['periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 5_000_000])
            ->assertSessionHasNoErrors();

        $this->assertSame(OmzetReport::BULANAN, OmzetReport::firstOrFail()->jenis_periode);
    }

    public function test_laporan_lama_di_database_terbaca_sebagai_bulanan(): void
    {
        // Baris yang dibuat langsung lewat model tanpa menyebut mode — mewakili
        // 271 baris yang sudah ada sebelum migration ini.
        $r = OmzetReport::create([
            'participant_id' => $this->peserta->id,
            'periode_bulan' => 5, 'periode_tahun' => 2026,
            'omzet' => 3_000_000, 'submitted_at' => now(),
        ]);

        $this->assertTrue($r->bulanan());
        $this->assertSame('Mei 2026', $r->periodeLabel());
        $this->assertSame('2026-05-01', $r->tanggal_mulai->toDateString());
        $this->assertSame('2026-05-31', $r->tanggal_selesai->toDateString());
    }

    // ==================================================================
    // D. BATAS PERIODE
    // ==================================================================

    public function test_tanggal_di_luar_jangkauan_bulan_ditolak(): void
    {
        // 29 Februari 2027 tidak ada. Kalau lolos, rentangnya dijepit ke 28
        // dan peserta punya dua baris yang menunjuk hari yang sama.
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 2, 'periode_tahun' => 2027, 'periode_urut' => 29, 'omzet' => 100_000])
            ->assertSessionHasErrors('periode_urut');

        $this->assertSame(0, OmzetReport::count());
    }

    public function test_minggu_kelima_ditolak_untuk_februari_biasa(): void
    {
        // Februari 2027 = 28 hari = tepat 4 minggu.
        $this->kirim(['jenis_periode' => OmzetReport::MINGGUAN, 'periode_bulan' => 2, 'periode_tahun' => 2027, 'periode_urut' => 5, 'omzet' => 100_000])
            ->assertSessionHasErrors('periode_urut');
    }

    public function test_tanggal_dua_puluh_sembilan_februari_kabisat_diterima(): void
    {
        // Tahun kabisat yang sudah lewat, bukan yang akan datang: aturan
        // periode_tahun yang sudah ada membatasi laporan sampai tahun depan
        // saja, dan pembatasan itu benar — omzet tidak dilaporkan untuk masa
        // depan yang jauh.
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 2, 'periode_tahun' => 2024, 'periode_urut' => 29, 'omzet' => 100_000])
            ->assertSessionHasNoErrors();

        $this->assertSame('2024-02-29', OmzetReport::firstOrFail()->tanggal_mulai->toDateString());
    }

    public function test_batas_periode_mengikuti_jumlah_hari_bulannya(): void
    {
        // Diuji langsung ke sumber aturannya, bukan lewat form — supaya
        // tahun kabisat mana pun bisa diperiksa tanpa bertabrakan dengan
        // batas "maksimal tahun depan" pada form.
        $this->assertSame(29, OmzetReport::urutMaksimal(OmzetReport::HARIAN, 2, 2028));
        $this->assertSame(28, OmzetReport::urutMaksimal(OmzetReport::HARIAN, 2, 2027));
        $this->assertSame(5, OmzetReport::urutMaksimal(OmzetReport::MINGGUAN, 2, 2028));
        $this->assertSame(4, OmzetReport::urutMaksimal(OmzetReport::MINGGUAN, 2, 2027));
        // Mode bulanan tidak punya nomor urut sama sekali.
        $this->assertSame(0, OmzetReport::urutMaksimal(OmzetReport::BULANAN, 2, 2028));
    }

    public function test_mode_bulanan_menolak_nomor_periode(): void
    {
        // Tanpa aturan ini, "September urut 0" dan "September urut 1" sama-sama
        // lolos unique index, dan keduanya mengaku mewakili seluruh September.
        $this->kirim(['jenis_periode' => OmzetReport::BULANAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 2, 'omzet' => 100_000])
            ->assertSessionHasErrors('periode_urut');
    }

    public function test_mode_karangan_ditolak(): void
    {
        $this->kirim(['jenis_periode' => 'Kuartalan', 'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 100_000])
            ->assertSessionHasErrors('jenis_periode');
    }

    public function test_periode_yang_sama_persis_tidak_bisa_dicatat_dua_kali(): void
    {
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 3, 'omzet' => 100_000])
            ->assertSessionHasNoErrors();

        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 3, 'omzet' => 999_000])
            ->assertSessionHasErrors('periode_bulan');

        $this->assertSame(100_000, (int) OmzetReport::sum('omzet'));
    }

    public function test_periode_harian_terhapus_bisa_diisi_ulang(): void
    {
        // Unique index tidak mempedulikan deleted_at — perilaku lama modul
        // ini, dan harus tetap berlaku untuk mode baru.
        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 3, 'omzet' => 100_000]);

        OmzetReport::firstOrFail()->delete();

        $this->kirim(['jenis_periode' => OmzetReport::HARIAN, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'periode_urut' => 3, 'omzet' => 175_000])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, OmzetReport::count());
        $this->assertSame(175_000, (int) OmzetReport::sum('omzet'));
    }

    // ==================================================================

    private function kirim(array $data)
    {
        return $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->peserta), $data);
    }
}
