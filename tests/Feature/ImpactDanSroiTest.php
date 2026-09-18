<?php

namespace Tests\Feature;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\SroiCalculation;
use App\Models\User;
use App\Services\SroiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MESIN DAMPAK & SROI.
 *
 * Sebagian besar berkas ini menguji hal yang TIDAK boleh terjadi, dan itu
 * disengaja. Data operasional CahayaOS berlimpah, jadi menghasilkan sebuah
 * rasio yang terlihat meyakinkan itu mudah — justru karena mudah itulah
 * penjaganya perlu diuji lebih keras daripada rumusnya sendiri.
 *
 * Empat hal yang dijaga:
 *
 *   1. TANPA DATA LENGKAP, TIDAK ADA ANGKA. Bukan angka nol, bukan estimasi:
 *      perhitungannya menolak berjalan dan menyebutkan apa yang kurang.
 *
 *   2. RUMUSNYA BENAR, termasuk keempat faktor penyesuai dan drop-off lintas
 *      tahun — dan indikator bulanan disetahunkan sebelum dikali proxy.
 *
 *   3. HASIL YANG SUDAH TERBIT TIDAK BERUBAH saat master proxy diperbarui.
 *
 *   4. Otorisasinya terpisah: menghitung (Admin) bukan hal yang sama dengan
 *      menyetujui (Pimpinan).
 */
class ImpactDanSroiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pimpinan;
    private User $pendamping;
    private User $timKoperasi;
    private ImpactProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@sroi.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@sroi.test');
        $this->pimpinan = $this->user('pimpinan', 'pim@sroi.test');
        $this->pendamping = $this->user('pendamping', 'pd@sroi.test', $profil->id);
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@sroi.test');

        $this->program = ImpactProgram::create([
            'nama' => 'Pemberdayaan UMKM 2026',
            'periode_mulai' => '2026-01-01',
            'periode_selesai' => '2026-12-31',
            'status' => ImpactProgram::STATUS_DRAFT,
        ]);
    }

    // ==================================================================
    // A. PENJAGA — tanpa data lengkap, tidak ada angka
    // ==================================================================

    public function test_program_kosong_dinyatakan_belum_ada_data(): void
    {
        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program);

        $this->assertSame(SroiService::BELUM_ADA_DATA, $kesiapan['status']);
        $this->assertFalse($kesiapan['siap']);
    }

    public function test_tanpa_investasi_sroi_menolak_dihitung(): void
    {
        $this->outcome($this->proxy());

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertFalse($kesiapan['siap']);
        $this->assertTrue(
            collect($kesiapan['kurang'])->contains(fn ($k) => str_contains($k, 'Investasi program belum dicatat')),
            'Tanpa investasi, rasio tidak punya penyebut dan itu harus dikatakan.'
        );

        $this->expectException(\RuntimeException::class);
        app(SroiService::class)->hitung($this->program->fresh());
    }

    public function test_tanpa_proxy_finansial_sroi_menolak_dihitung(): void
    {
        $this->investasi(10_000_000);
        $this->outcome(null);           // outcome tanpa proxy

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertFalse($kesiapan['siap']);
        $this->assertTrue(
            collect($kesiapan['kurang'])->contains(fn ($k) => str_contains($k, 'Proxy finansial belum tersedia')),
        );
    }

    public function test_outcome_tanpa_angka_perubahan_memblokir_perhitungan(): void
    {
        // Investasi ada, proxy ada, outcome ada — tapi belum ada satu pun
        // peserta dengan baseline + endline. Yang benar adalah MENOLAK, bukan
        // menghasilkan nilai dampak Rp 0 yang terlihat seperti hasil hitung.
        $this->investasi(10_000_000);
        $this->outcome($this->proxy());

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertFalse($kesiapan['siap']);
        $this->assertTrue(
            collect($kesiapan['kurang'])->contains(fn ($k) => str_contains($k, 'belum punya angka perubahan')),
        );
    }

    public function test_halaman_program_menampilkan_daftar_kekurangan_bukan_angka_estimasi(): void
    {
        $this->investasi(10_000_000);
        $this->outcome($this->proxy());

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Data Belum Lengkap')
            ->assertSee('belum punya angka perubahan', false);
    }

    public function test_tombol_hitung_yang_dipaksa_tetap_ditolak_dengan_alasan(): void
    {
        $this->investasi(10_000_000);
        $this->outcome($this->proxy());

        $this->actingAs($this->admin)
            ->post(route('impact.hitung', $this->program))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('sroi_calculations', 0);
    }

    // ==================================================================
    // B. RUMUS
    // ==================================================================

    public function test_rumus_lengkap_dengan_keempat_faktor_penyesuai(): void
    {
        // 2 peserta, masing-masing omzet naik Rp 500.000/bulan.
        // Perubahan bulanan  = 1.000.000
        // Disetahunkan       = 12.000.000
        // x proxy 2          = 24.000.000  (nilai kotor)
        // x (1 - 0,25)       = 18.000.000  (deadweight 25%)
        // x 0,80             = 14.400.000  (attribution 80%)
        // x (1 - 0,10)       = 12.960.000  (displacement 10%)
        $this->pesertaBerubah('A', 1_000_000, 1_500_000);
        $this->pesertaBerubah('B', 1_000_000, 1_500_000);

        $this->investasi(6_480_000);
        $this->outcome($this->proxy(2), [
            'deadweight_persen' => 25,
            'attribution_persen' => 80,
            'displacement_persen' => 10,
        ]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $baris = $hasil->rincian['outcomes'][0];
        $this->assertSame(12_000_000, $baris['jumlah_perubahan'], 'Indikator bulanan wajib disetahunkan sebelum dikali proxy.');
        $this->assertSame(24_000_000, $baris['langkah']['kotor']);
        $this->assertSame(18_000_000, $baris['langkah']['setelah_deadweight']);
        $this->assertSame(14_400_000, $baris['langkah']['setelah_attribution']);
        $this->assertSame(12_960_000, $baris['langkah']['setelah_displacement']);

        $this->assertSame(12_960_000, $hasil->total_nilai_dampak);
        $this->assertSame(6_480_000, $hasil->total_investasi);
        $this->assertSame(6_480_000, $hasil->nilai_sosial_bersih);
        $this->assertSame(2.0, $hasil->rasio);
    }

    public function test_drop_off_hanya_berlaku_setelah_tahun_pertama(): void
    {
        // 1 peserta, omzet naik 1.000.000/bulan -> 12.000.000/tahun.
        // proxy 1, tanpa penyesuai lain, durasi 3 tahun, drop-off 50%:
        //   Th1 12.000.000 (utuh) + Th2 6.000.000 + Th3 3.000.000 = 21.000.000
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);

        $this->investasi(21_000_000);
        $this->outcome($this->proxy(1), [
            'durasi_tahun' => 3,
            'drop_off_persen' => 50,
        ]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());
        $perTahun = $hasil->rincian['outcomes'][0]['per_tahun'];

        $this->assertSame(12_000_000, $perTahun[0]['nilai'], 'Tahun pertama tidak terkena drop-off.');
        $this->assertSame(6_000_000, $perTahun[1]['nilai']);
        $this->assertSame(3_000_000, $perTahun[2]['nilai']);
        $this->assertSame(21_000_000, $hasil->total_nilai_dampak);
    }

    public function test_attribution_nol_menghasilkan_nilai_dampak_nol(): void
    {
        // Mengklaim 0% perubahan milik program adalah pernyataan yang sah, dan
        // hasilnya harus benar-benar nol — bukan diam-diam dianggap 100%.
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);

        $this->investasi(5_000_000);
        $this->outcome($this->proxy(1), ['attribution_persen' => 0]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $this->assertSame(0, $hasil->total_nilai_dampak);
        $this->assertSame(0.0, $hasil->rasio);
        $this->assertSame(-5_000_000, $hasil->nilai_sosial_bersih);
    }

    public function test_outcome_manual_memakai_angka_yang_diisi_petugas(): void
    {
        $this->investasi(1_000_000);

        $this->program->outcomes()->create([
            'nama' => 'Kepercayaan diri peserta meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_MANUAL,
            'nilai_perubahan_manual' => 10,
            'jumlah_penerima_manual' => 10,
            'financial_proxy_id' => $this->proxy(250_000)->id,
            'bukti' => 'Survei pasca-pelatihan, Desember 2026',
        ]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        // Angka manual TIDAK disetahunkan — ia bukan indikator bulanan.
        $this->assertSame(2_500_000, $hasil->total_nilai_dampak);
    }

    public function test_indikator_non_bulanan_tidak_disetahunkan(): void
    {
        // Tenaga kerja bertambah 2 orang berarti 2 orang, bukan 24.
        $p = $this->peserta('A');
        $this->ukur($p, ParticipantMeasurement::BASELINE, ['jumlah_tenaga_kerja' => 1]);
        $this->ukur($p, ParticipantMeasurement::ENDLINE, ['jumlah_tenaga_kerja' => 3]);

        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1_000_000), ['indikator' => 'jumlah_tenaga_kerja']);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $this->assertSame(2, $hasil->rincian['outcomes'][0]['jumlah_perubahan']);
        $this->assertSame(2_000_000, $hasil->total_nilai_dampak);
    }

    // ==================================================================
    // C. SENSITIVITAS
    // ==================================================================

    public function test_sensitivitas_menghasilkan_tiga_skenario_yang_berurutan(): void
    {
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(5_000_000);
        $this->outcome($this->proxy(1), [
            'deadweight_persen' => 30,
            'attribution_persen' => 70,
            'displacement_persen' => 10,
        ]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());
        $s = $hasil->sensitivitas;

        $this->assertSame(['konservatif', 'dasar', 'optimistis'], array_keys($s));

        // Konservatif selalu <= dasar <= optimistis. Kalau urutannya bisa
        // terbalik, tanda geserannya salah dan tabelnya menyesatkan.
        $this->assertLessThanOrEqual($s['dasar']['rasio'], $s['konservatif']['rasio']);
        $this->assertGreaterThanOrEqual($s['dasar']['rasio'], $s['optimistis']['rasio']);
        $this->assertSame($hasil->rasio, $s['dasar']['rasio'], 'Skenario dasar harus sama dengan hasil utama.');
    }

    public function test_persen_tidak_pernah_melewati_batas_nol_seratus(): void
    {
        // Attribution 90% digeser +20 pada skenario optimistis akan menjadi
        // 110% kalau tidak dibatasi — artinya program mengklaim lebih dari
        // seluruh perubahan yang terjadi.
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1), ['attribution_persen' => 90]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $kotor = $hasil->rincian['outcomes'][0]['langkah']['kotor'];
        $this->assertLessThanOrEqual($kotor, $hasil->sensitivitas['optimistis']['total_nilai_dampak']);
    }

    // ==================================================================
    // D. HASIL YANG DIBEKUKAN
    // ==================================================================

    public function test_memperbarui_proxy_tidak_mengubah_hasil_yang_sudah_terbit(): void
    {
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $proxy = $this->proxy(1);
        $this->outcome($proxy);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());
        $dampakAwal = $hasil->total_nilai_dampak;

        // Master proxy dinaikkan sepuluh kali lipat setelah laporan terbit.
        $proxy->update(['nilai' => 10]);

        $this->assertSame($dampakAwal, $hasil->fresh()->total_nilai_dampak);
        $this->assertSame(1, $hasil->fresh()->rincian['outcomes'][0]['proxy']['nilai'],
            'Nilai proxy yang dipakai harus dibekukan di hasilnya, seperti snapshot harga di transaksi penjualan.');
    }

    public function test_menghitung_ulang_melahirkan_baris_baru_bukan_menimpa(): void
    {
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1));

        app(SroiService::class)->hitung($this->program->fresh());
        app(SroiService::class)->hitung($this->program->fresh());

        $this->assertDatabaseCount('sroi_calculations', 2);
    }

    public function test_hasil_dengan_peringatan_ditandai_perlu_review(): void
    {
        // Faktor penyesuai masih bawaan (deadweight 0, attribution 100) —
        // seluruh perubahan diklaim milik program. Sistem tidak berwenang
        // menyatakan angka itu layak dilaporkan.
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1));

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $this->assertSame(SroiCalculation::STATUS_PERLU_REVIEW, $hasil->status);
        $this->assertNotEmpty($hasil->rincian['peringatan']);
    }

    public function test_perhitungan_tercatat_di_activity_log(): void
    {
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1));

        $this->actingAs($this->admin)->post(route('impact.hitung', $this->program));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'sroi_calculated',
            'user_id' => $this->admin->id,
        ]);
    }

    // ==================================================================
    // E. OTORISASI & PERSETUJUAN
    // ==================================================================

    public function test_pendamping_dan_tim_koperasi_tidak_bisa_membuka_modul_dampak(): void
    {
        foreach ([$this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('impact.index'))->assertForbidden();
            $this->actingAs($user)->get(route('impact.show', $this->program))->assertForbidden();
            $this->actingAs($user)->get(route('impact.proxy.index'))->assertForbidden();
        }
    }

    public function test_pimpinan_boleh_membaca_tapi_tidak_boleh_menyusun(): void
    {
        $this->actingAs($this->pimpinan)->get(route('impact.index'))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('impact.show', $this->program))->assertOk();

        $this->actingAs($this->pimpinan)->get(route('impact.create'))->assertForbidden();
        $this->actingAs($this->pimpinan)
            ->post(route('impact.investasi.store', $this->program), [
                'kategori' => 'Pelatihan', 'uraian' => 'X',
                'tanggal' => '2026-02-01', 'nominal' => 100000, 'jenis' => 'Tunai',
            ])
            ->assertForbidden();
    }

    public function test_pimpinan_menyetujui_hasil_dan_program_ikut_terkunci(): void
    {
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1));
        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $this->actingAs($this->pimpinan)
            ->post(route('impact.setujui', [$this->program, $hasil]), ['catatan_review' => 'Sudah dicek.'])
            ->assertRedirect();

        $this->assertSame(SroiCalculation::STATUS_FINAL, $hasil->fresh()->status);
        $this->assertSame($this->pimpinan->id, $hasil->fresh()->disetujui_oleh);
        $this->assertSame(ImpactProgram::STATUS_FINAL, $this->program->fresh()->status);
    }

    public function test_program_final_terkunci_bahkan_untuk_admin(): void
    {
        $this->program->update(['status' => ImpactProgram::STATUS_FINAL]);

        // Angkanya sudah dipakai melapor keluar; kalau masih bisa diubah,
        // laporan yang sudah terkirim tidak lagi cocok dengan sistem.
        $this->actingAs($this->admin)->get(route('impact.edit', $this->program))->assertForbidden();
        $this->actingAs($this->admin)->post(route('impact.hitung', $this->program))->assertForbidden();
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), ['nama' => 'X'])
            ->assertForbidden();
    }

    public function test_pendamping_tidak_bisa_menyetujui(): void
    {
        $this->pesertaBerubah('A', 1_000_000, 2_000_000);
        $this->investasi(1_000_000);
        $this->outcome($this->proxy(1));
        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $this->actingAs($this->pendamping)
            ->post(route('impact.setujui', [$this->program, $hasil]))
            ->assertForbidden();
    }

    // ==================================================================
    // F. PROXY FINANSIAL
    // ==================================================================

    public function test_proxy_tanpa_sumber_atau_tahun_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.proxy.store'), [
                'nama' => 'Nilai kenaikan pendapatan',
                'satuan' => 'per UMKM per tahun',
                'nilai' => 12_000_000,
            ])
            ->assertSessionHasErrors(['sumber', 'tahun']);
    }

    public function test_proxy_yang_masih_dipakai_outcome_tidak_bisa_diarsipkan(): void
    {
        $proxy = $this->proxy();
        $this->outcome($proxy);

        $this->actingAs($this->admin)
            ->delete(route('impact.proxy.destroy', $proxy))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('financial_proxies', ['id' => $proxy->id, 'deleted_at' => null]);
    }

    public function test_proxy_nonaktif_tidak_ditawarkan_di_form_outcome(): void
    {
        $proxy = $this->proxy();
        $proxy->update(['aktif' => false]);

        $this->actingAs($this->admin)
            ->get(route('impact.outcome.create', $this->program))
            ->assertOk()
            ->assertDontSee($proxy->nama);
    }

    public function test_outcome_manual_wajib_menyertakan_bukti(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), [
                'nama' => 'Perubahan dari survei',
                'sumber_nilai' => ImpactOutcome::SUMBER_MANUAL,
                'nilai_perubahan_manual' => 10,
                'deadweight_persen' => 0, 'attribution_persen' => 100,
                'displacement_persen' => 0, 'drop_off_persen' => 0,
                'durasi_tahun' => 1,
            ])
            ->assertSessionHasErrors('bukti');
    }

    public function test_faktor_penyesuai_di_luar_nol_seratus_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), [
                'nama' => 'X',
                'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
                'indikator' => 'omzet_bulanan',
                'deadweight_persen' => 150,
                'attribution_persen' => 100,
                'displacement_persen' => 0,
                'drop_off_persen' => 0,
                'durasi_tahun' => 1,
            ])
            ->assertSessionHasErrors('deadweight_persen');
    }

    public function test_indikator_karangan_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), [
                'nama' => 'X',
                'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
                'indikator' => 'kebahagiaan_peserta',
                'deadweight_persen' => 0, 'attribution_persen' => 100,
                'displacement_persen' => 0, 'drop_off_persen' => 0,
                'durasi_tahun' => 1,
            ])
            ->assertSessionHasErrors('indikator');
    }

    // ==================================================================
    // PEMBANTU
    // ==================================================================

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => true,
        ]);
    }

    private function peserta(string $nama): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0800',
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2026-01-15',
            'status' => 'Aktif',
        ]);
    }

    private function ukur(Participant $p, string $jenis, array $data): ParticipantMeasurement
    {
        return $p->measurements()->create($data + [
            'jenis' => $jenis,
            'tanggal' => $jenis === ParticipantMeasurement::BASELINE ? '2026-01-20' : '2026-12-20',
        ]);
    }

    /** Peserta dengan baseline & endline omzet — bahan baku sebagian besar test di atas. */
    private function pesertaBerubah(string $nama, int $awal, int $akhir): Participant
    {
        $p = $this->peserta($nama);
        $this->ukur($p, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => $awal]);
        $this->ukur($p, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => $akhir]);

        return $p;
    }

    private function proxy(int $nilai = 12_000_000): FinancialProxy
    {
        return FinancialProxy::create([
            'nama' => 'Proxy Uji ' . $nilai,
            'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => $nilai,
            'sumber' => 'Dokumen Uji',
            'tahun' => 2026,
        ]);
    }

    private function investasi(int $nominal): void
    {
        $this->program->investments()->create([
            'kategori' => 'Pelatihan',
            'uraian' => 'Pelatihan uji',
            'tanggal' => '2026-02-01',
            'nominal' => $nominal,
            'jenis' => 'Tunai',
        ]);
    }

    private function outcome(?FinancialProxy $proxy, array $ubah = []): ImpactOutcome
    {
        return $this->program->outcomes()->create($ubah + [
            'nama' => 'Pendapatan usaha meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'financial_proxy_id' => $proxy?->id,
        ]);
    }
}
