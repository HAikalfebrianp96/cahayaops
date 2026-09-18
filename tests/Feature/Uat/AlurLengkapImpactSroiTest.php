<?php

namespace Tests\Feature\Uat;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\SroiCalculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ALUR PENUH IMPACT & SROI — SEBAGAIMANA ADMIN AKAN MENJALANKANNYA.
 *
 * ==========================================================================
 * KENAPA TEST INI ADA, PADAHAL SETIAP BAGIANNYA SUDAH DIUJI
 * ==========================================================================
 *
 * Test lain memeriksa satu potong sekaligus, dan hampir semuanya memakai SATU
 * outcome. Program yang sebenarnya punya beberapa outcome dengan indikator
 * berbeda — pendapatan, tenaga kerja, legalitas — dan di situlah tiga hal
 * baru bisa salah:
 *
 *   1. PENJUMLAHAN LINTAS OUTCOME. Nilai dampak total adalah jumlah dari
 *      beberapa outcome yang masing-masing punya proxy, satuan, dan faktor
 *      penyesuai sendiri. Satu outcome yang salah satuan tidak akan terlihat
 *      dari totalnya — angkanya tetap masuk akal.
 *
 *   2. SENSITIVITAS AGREGAT. Skenario menggeser asumsi SETIAP outcome lalu
 *      menjumlahkannya. Urutan konservatif ≤ dasar ≤ optimistis harus tetap
 *      berlaku untuk gabungannya, bukan hanya per outcome.
 *
 *   3. KESIAPAN PARSIAL. Program dengan tiga outcome yang dua-duanya siap dan
 *      satu belum harus TETAP menolak dihitung. Menghitung "yang bisa saja
 *      dulu" menghasilkan rasio yang terlihat sah padahal kurang satu bagian.
 *
 * Seluruhnya dijalankan lewat route sungguhan, sebagai Admin dan Pimpinan,
 * dengan data berbentuk seperti data nyata Cahaya Preneur.
 */
class AlurLengkapImpactSroiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@alur.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@alur.test');
    }

    // ==================================================================
    // A. PERJALANAN PENUH, SEPULUH LANGKAH
    // ==================================================================

    public function test_admin_menyusun_program_dari_nol_sampai_disetujui(): void
    {
        // --- 1. Modul masih kosong: proxy diumumkan belum dikonfigurasi ---
        $this->actingAs($this->admin)
            ->get(route('impact.index'))
            ->assertOk()
            ->assertSee('Nilai acuan rupiah belum diisi');

        // --- 2. Admin membuat program ---
        $this->actingAs($this->admin)->post(route('impact.store'), [
            'nama' => 'Akselerator UMKM 2026',
            'periode_mulai' => '2026-01-01',
            'periode_selesai' => '2026-12-31',
            'status' => ImpactProgram::STATUS_DRAFT,
        ])->assertSessionHasNoErrors();

        $program = ImpactProgram::firstOrFail();

        // Program ada, tapi SROI belum boleh dihitung.
        $this->actingAs($this->admin)
            ->get(route('impact.show', $program))
            ->assertOk()
            ->assertSee('Belum Ada Data');

        // --- 3. Investasi, termasuk in-kind ---
        $this->catatInvestasi($program, 'Pelatihan', 'Pelatihan pembukuan 3 angkatan', 24_000_000, 'Tunai');
        $this->catatInvestasi($program, 'Pendampingan', 'Waktu pendamping & transport', 6_000_000, 'In-kind');

        // --- 4. Baseline & endline peserta, dicatat lewat modul Peserta ---
        $this->pesertaTerukur('Bu Ani', ['omzet_bulanan' => 1_500_000, 'jumlah_tenaga_kerja' => 1, 'punya_legalitas' => false],
                                        ['omzet_bulanan' => 2_500_000, 'jumlah_tenaga_kerja' => 2, 'punya_legalitas' => true]);
        $this->pesertaTerukur('Pak Budi', ['omzet_bulanan' => 2_000_000, 'jumlah_tenaga_kerja' => 2, 'punya_legalitas' => false],
                                          ['omzet_bulanan' => 3_000_000, 'jumlah_tenaga_kerja' => 3, 'punya_legalitas' => true]);
        $this->pesertaTerukur('Bu Sri', ['omzet_bulanan' => 1_000_000, 'jumlah_tenaga_kerja' => 1, 'punya_legalitas' => false],
                                        ['omzet_bulanan' => 1_800_000, 'jumlah_tenaga_kerja' => 1, 'punya_legalitas' => false]);

        // Halaman program kini melaporkan keadaan pengukurannya. Ketiga
        // peserta sudah terukur penuh, jadi yang muncul adalah pernyataan
        // itu — BUKAN daftar nama, karena tidak ada lagi yang perlu
        // dilengkapi. Daftar nama beserta tautannya diuji terpisah di
        // KesiapanEntriImpactTest, pada program yang memang belum lengkap.
        $this->actingAs($this->admin)
            ->get(route('impact.show', $program))
            ->assertOk()
            ->assertSee('Terukur penuh')
            ->assertSee('Seluruh peserta program ini sudah punya baseline dan pembanding');

        // --- 5. Proxy finansial, masing-masing bersumber ---
        $proxyOmzet = $this->buatProxy('Nilai kenaikan pendapatan UMKM', 'omzet_bulanan', 1, 'per rupiah kenaikan omzet/tahun');
        $proxyKerja = $this->buatProxy('Nilai satu lapangan kerja mikro', 'jumlah_tenaga_kerja', 18_000_000, 'per orang per tahun');
        $proxyLegal = $this->buatProxy('Nilai kepastian usaha berlegalitas', 'punya_legalitas', 4_000_000, 'per UMKM per tahun');

        // --- 6. Tiga outcome dengan indikator berbeda ---
        $this->catatOutcome($program, 'Pendapatan usaha meningkat', 'omzet_bulanan', $proxyOmzet, [
            'deadweight_persen' => 25, 'attribution_persen' => 75,
            'attribution_catatan' => 'Sebagian kenaikan dari usaha peserta sendiri dan membaiknya pasar.',
            'displacement_relevan' => '1', 'displacement_persen' => 10,
        ]);

        $this->catatOutcome($program, 'Tenaga kerja bertambah', 'jumlah_tenaga_kerja', $proxyKerja, [
            'deadweight_persen' => 15, 'attribution_persen' => 80,
            'attribution_catatan' => 'Sebagian perekrutan atas inisiatif peserta sendiri.',
            'displacement_relevan' => '0',
            'displacement_catatan' => 'Pekerja direkrut dari lingkungan yang sebelumnya tidak bekerja.',
        ]);

        $this->catatOutcome($program, 'Usaha memperoleh legalitas', 'punya_legalitas', $proxyLegal, [
            'deadweight_persen' => 10, 'attribution_persen' => 90,
            'attribution_catatan' => 'Pengurusan didampingi program; sebagian biaya ditanggung peserta.',
            'displacement_relevan' => '0',
            'displacement_catatan' => 'Legalitas tidak mengambil manfaat dari pihak mana pun.',
        ]);

        // --- 7. Sekarang barulah siap dihitung ---
        $this->actingAs($this->admin)
            ->get(route('impact.show', $program))
            ->assertOk()
            ->assertSee('Siap Dihitung');

        // --- 8. Hitung ---
        $this->actingAs($this->admin)
            ->post(route('impact.hitung', $program))
            ->assertRedirect()
            ->assertSessionHas('success');

        $hasil = SroiCalculation::firstOrFail();

        $this->assertCount(3, $hasil->rincian['outcomes'], 'Ketiga outcome harus ikut terhitung.');
        $this->assertNotNull($hasil->rasio);
        $this->assertGreaterThan(0, $hasil->total_nilai_dampak);
        $this->assertSame(30_000_000, $hasil->total_investasi);

        // --- 9. Pimpinan menyetujui ---
        $this->actingAs($this->pimpinan)
            ->post(route('impact.setujui', [$program, $hasil]), [
                'catatan_review' => 'Angka dan asumsinya sudah diperiksa bersama pendamping.',
            ])
            ->assertRedirect();

        $this->assertSame(SroiCalculation::STATUS_FINAL, $hasil->fresh()->status);
        $this->assertSame(ImpactProgram::STATUS_FINAL, $program->fresh()->status);

        // --- 10. Laporan bisa diunduh, dan programnya terkunci ---
        $this->actingAs($this->pimpinan)->get(route('impact.export-pdf', $program))->assertOk();
        $this->actingAs($this->admin)->get(route('impact.edit', $program))->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('impact.show', $program))
            ->assertOk()
            ->assertSee('10 dari 10 langkah selesai');
    }

    // ==================================================================
    // B. PENJUMLAHAN LINTAS OUTCOME
    // ==================================================================

    public function test_nilai_dampak_adalah_jumlah_seluruh_outcome(): void
    {
        $program = $this->programLengkap();
        $hasil = $this->hitung($program);

        $jumlahBaris = collect($hasil->rincian['outcomes'])->sum('nilai_bersih');

        $this->assertSame(
            $hasil->total_nilai_dampak,
            (int) $jumlahBaris,
            'Total nilai dampak harus sama dengan jumlah nilai bersih tiap outcome.'
        );
    }

    public function test_setiap_outcome_memakai_proxy_dan_penyesuainya_sendiri(): void
    {
        $program = $this->programLengkap();
        $hasil = $this->hitung($program);

        $baris = collect($hasil->rincian['outcomes'])->keyBy('nama');

        // Proxy yang dibekukan per baris memang berbeda-beda.
        $this->assertSame(1, $baris['Pendapatan usaha meningkat']['proxy']['nilai']);
        $this->assertSame(18_000_000, $baris['Tenaga kerja bertambah']['proxy']['nilai']);

        // Begitu juga faktor penyesuainya. Dibandingkan dengan assertEquals,
        // bukan assertSame: nilainya dibekukan lewat kolom JSON, dan 25.0
        // kembali sebagai int 25 setelah round-trip. Yang diuji angkanya,
        // bukan tipe PHP-nya sesudah decode.
        $this->assertEquals(25, $baris['Pendapatan usaha meningkat']['penyesuai']['deadweight_persen']);
        $this->assertEquals(15, $baris['Tenaga kerja bertambah']['penyesuai']['deadweight_persen']);

        // Dan keputusan displacement-nya tercatat apa adanya per outcome.
        $this->assertTrue($baris['Pendapatan usaha meningkat']['penyesuai']['displacement_relevan']);
        $this->assertFalse($baris['Tenaga kerja bertambah']['penyesuai']['displacement_relevan']);
    }

    public function test_indikator_bulanan_disetahunkan_indikator_lain_tidak(): void
    {
        // Kesalahan yang paling sulit terlihat: satu outcome disetahunkan,
        // yang lain tidak — hasilnya tetap berupa angka yang masuk akal.
        $program = $this->programLengkap();
        $hasil = $this->hitung($program);

        $baris = collect($hasil->rincian['outcomes'])->keyBy('nama');

        // Omzet naik 1jt + 1jt + 800rb = 2,8jt per bulan -> 33,6jt per tahun.
        $this->assertSame(33_600_000, $baris['Pendapatan usaha meningkat']['jumlah_perubahan']);

        // Tenaga kerja bertambah 1 + 1 + 0 = 2 orang. Bukan 24.
        $this->assertSame(2, $baris['Tenaga kerja bertambah']['jumlah_perubahan']);
    }

    // ==================================================================
    // C. SENSITIVITAS AGREGAT
    // ==================================================================

    public function test_urutan_skenario_tetap_berlaku_untuk_gabungan_outcome(): void
    {
        $program = $this->programLengkap();
        $hasil = $this->hitung($program);
        $s = $hasil->sensitivitas;

        $this->assertLessThanOrEqual($s['dasar']['rasio'], $s['konservatif']['rasio']);
        $this->assertGreaterThanOrEqual($s['dasar']['rasio'], $s['optimistis']['rasio']);
        $this->assertSame($hasil->rasio, $s['dasar']['rasio']);

        // Investasi tidak bergeser antar skenario — yang digeser hanya asumsi.
        $this->assertSame($hasil->total_investasi, $s['konservatif']['total_investasi']);
        $this->assertSame($hasil->total_investasi, $s['optimistis']['total_investasi']);
    }

    // ==================================================================
    // D. KESIAPAN PARSIAL TETAP DITOLAK
    // ==================================================================

    public function test_satu_outcome_yang_belum_lengkap_memblokir_seluruh_perhitungan(): void
    {
        $program = $this->programLengkap();

        // Outcome keempat yang indikatornya belum punya data pengukuran.
        $program->outcomes()->create([
            'nama' => 'Pelanggan rutin bertambah',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'jumlah_pelanggan',
            'financial_proxy_id' => $this->buatProxy('Proxy Pelanggan', 'jumlah_pelanggan', 100_000, 'per pelanggan')->id,
            'displacement_relevan' => false,
            'displacement_catatan' => 'Tidak relevan.',
        ]);

        $this->actingAs($this->admin)
            ->post(route('impact.hitung', $program->fresh()))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Menghitung "yang bisa saja dulu" akan menghasilkan rasio yang
        // terlihat sah padahal kurang satu bagian.
        $this->assertSame(0, SroiCalculation::count());
    }

    public function test_outcome_tanpa_proxy_memblokir_walau_yang_lain_sudah_berproxy(): void
    {
        $program = $this->programLengkap();

        $program->outcomes()->create([
            'nama' => 'Kapasitas produksi meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_MANUAL,
            'nilai_perubahan_manual' => 500,
            'bukti' => 'Survei produksi Desember 2026.',
            'financial_proxy_id' => null,
        ]);

        $kesiapan = app(\App\Services\SroiService::class)->periksaKesiapan($program->fresh());

        $this->assertFalse($kesiapan['siap']);
        $this->assertTrue(
            collect($kesiapan['kurang'])->contains(fn ($k) => str_contains($k, 'Kapasitas produksi meningkat')),
        );
    }

    // ==================================================================
    // E. CAKUPAN PESERTA MENGIKUTI PERIODE PROGRAM
    // ==================================================================

    public function test_peserta_yang_masuk_setelah_periode_berakhir_tidak_ikut_terhitung(): void
    {
        $program = $this->programLengkap();

        // Peserta yang baru bergabung setelah program berakhir jelas bukan
        // bagian dari dampak periode itu.
        $luar = Participant::create([
            'nama_peserta' => 'Peserta Baru', 'nomor_hp' => '0899',
            'nama_usaha' => 'Warung Baru', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2027-03-01',
            'status' => 'Aktif',
        ]);
        $this->ukur($luar, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 500_000], '2027-03-05');
        $this->ukur($luar, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 9_000_000], '2027-06-05');

        $hasil = $this->hitung($program);
        $baris = collect($hasil->rincian['outcomes'])->keyBy('nama');

        // Angkanya tetap 33,6jt — kenaikan 8,5jt milik peserta di luar
        // periode tidak boleh ikut masuk.
        $this->assertSame(33_600_000, $baris['Pendapatan usaha meningkat']['jumlah_perubahan']);
        $this->assertSame(3, $baris['Pendapatan usaha meningkat']['jumlah_peserta_terukur']);
    }

    // ==================================================================
    // F. LAPORAN MEMBAWA SELURUHNYA
    // ==================================================================

    public function test_laporan_pdf_memuat_ketiga_outcome_dan_sumber_proxynya(): void
    {
        $program = $this->programLengkap();
        $this->hitung($program);

        $data = app(\App\Http\Controllers\ImpactProgramController::class)->dataPdf($program->fresh());
        $isi = view('impact.pdf.sroi', $data)->render();

        $this->assertStringContainsString('Pendapatan usaha meningkat', $isi);
        $this->assertStringContainsString('Tenaga kerja bertambah', $isi);
        $this->assertStringContainsString('Usaha memperoleh legalitas', $isi);
        // Sumber setiap proxy ikut tercetak — tanpa itu angkanya tidak bisa
        // diperiksa pembaca laporan.
        $this->assertStringContainsString('BPS Uji', $isi);
        $this->assertStringContainsString('SROI Ratio', $isi);
        // Displacement yang tidak relevan tidak boleh tercetak sebagai "0%".
        $this->assertStringContainsString('n/r', $isi);
    }

    // ==================================================================
    // PEMBANTU
    // ==================================================================

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => $email, 'email' => $email,
            'password' => bcrypt('rahasia123'), 'role' => $role, 'is_active' => true,
        ]);
    }

    private function catatInvestasi(ImpactProgram $program, string $kategori, string $uraian, int $nominal, string $jenis): void
    {
        $this->actingAs($this->admin)->post(route('impact.investasi.store', $program), [
            'kategori' => $kategori, 'uraian' => $uraian,
            'tanggal' => '2026-03-01', 'nominal' => $nominal, 'jenis' => $jenis,
        ])->assertSessionHasNoErrors();
    }

    private function buatProxy(string $nama, string $indikator, int $nilai, string $satuan): FinancialProxy
    {
        $this->actingAs($this->admin)->post(route('impact.proxy.store'), [
            'nama' => $nama,
            'outcome' => 'Outcome untuk ' . $indikator,
            'indikator' => $indikator,
            'satuan' => $satuan,
            'nilai' => $nilai,
            'sumber' => 'BPS Uji',
            'tahun' => 2026,
            'alasan' => 'Dipilih karena paling mendekati konteks UMKM binaan.',
        ])->assertSessionHasNoErrors();

        return FinancialProxy::where('nama', $nama)->firstOrFail();
    }

    private function catatOutcome(ImpactProgram $program, string $nama, string $indikator, FinancialProxy $proxy, array $penyesuai): void
    {
        $this->actingAs($this->admin)->post(route('impact.outcome.store', $program), $penyesuai + [
            'nama' => $nama,
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => $indikator,
            'financial_proxy_id' => $proxy->id,
            'deadweight_persen' => 0,
            'attribution_persen' => 100,
            'displacement_persen' => 0,
            'drop_off_persen' => 0,
            'durasi_tahun' => 1,
        ])->assertSessionHasNoErrors();
    }

    private function pesertaTerukur(string $nama, array $baseline, array $endline): Participant
    {
        $p = Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0812' . random_int(10000000, 99999999),
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2026-01-15',
            'status' => 'Aktif',
        ]);

        $this->ukur($p, ParticipantMeasurement::BASELINE, $baseline, '2026-01-20');
        $this->ukur($p, ParticipantMeasurement::ENDLINE, $endline, '2026-12-20');

        return $p;
    }

    private function ukur(Participant $p, string $jenis, array $data, string $tanggal): void
    {
        $p->measurements()->create($data + ['jenis' => $jenis, 'tanggal' => $tanggal]);
    }

    /** Program tiga-outcome yang benar-benar siap dihitung. */
    private function programLengkap(): ImpactProgram
    {
        $program = ImpactProgram::create([
            'nama' => 'Akselerator UMKM 2026',
            'periode_mulai' => '2026-01-01',
            'periode_selesai' => '2026-12-31',
        ]);

        $this->catatInvestasi($program, 'Pelatihan', 'Pelatihan pembukuan', 24_000_000, 'Tunai');
        $this->catatInvestasi($program, 'Pendampingan', 'Waktu pendamping', 6_000_000, 'In-kind');

        $this->pesertaTerukur('Bu Ani', ['omzet_bulanan' => 1_500_000, 'jumlah_tenaga_kerja' => 1, 'punya_legalitas' => false],
                                        ['omzet_bulanan' => 2_500_000, 'jumlah_tenaga_kerja' => 2, 'punya_legalitas' => true]);
        $this->pesertaTerukur('Pak Budi', ['omzet_bulanan' => 2_000_000, 'jumlah_tenaga_kerja' => 2, 'punya_legalitas' => false],
                                          ['omzet_bulanan' => 3_000_000, 'jumlah_tenaga_kerja' => 3, 'punya_legalitas' => true]);
        $this->pesertaTerukur('Bu Sri', ['omzet_bulanan' => 1_000_000, 'jumlah_tenaga_kerja' => 1, 'punya_legalitas' => false],
                                        ['omzet_bulanan' => 1_800_000, 'jumlah_tenaga_kerja' => 1, 'punya_legalitas' => false]);

        $this->catatOutcome($program, 'Pendapatan usaha meningkat', 'omzet_bulanan',
            $this->buatProxy('Nilai kenaikan pendapatan UMKM', 'omzet_bulanan', 1, 'per rupiah kenaikan omzet/tahun'), [
                'deadweight_persen' => 25, 'attribution_persen' => 75,
                'attribution_catatan' => 'Sebagian dari usaha peserta sendiri.',
                'displacement_relevan' => '1', 'displacement_persen' => 10,
            ]);

        $this->catatOutcome($program, 'Tenaga kerja bertambah', 'jumlah_tenaga_kerja',
            $this->buatProxy('Nilai satu lapangan kerja mikro', 'jumlah_tenaga_kerja', 18_000_000, 'per orang per tahun'), [
                'deadweight_persen' => 15, 'attribution_persen' => 80,
                'attribution_catatan' => 'Sebagian perekrutan atas inisiatif peserta.',
                'displacement_relevan' => '0',
                'displacement_catatan' => 'Pekerja direkrut dari yang sebelumnya tidak bekerja.',
            ]);

        $this->catatOutcome($program, 'Usaha memperoleh legalitas', 'punya_legalitas',
            $this->buatProxy('Nilai kepastian usaha berlegalitas', 'punya_legalitas', 4_000_000, 'per UMKM per tahun'), [
                'deadweight_persen' => 10, 'attribution_persen' => 90,
                'attribution_catatan' => 'Pengurusan didampingi program.',
                'displacement_relevan' => '0',
                'displacement_catatan' => 'Tidak mengambil manfaat pihak lain.',
            ]);

        return $program->fresh();
    }

    private function hitung(ImpactProgram $program): SroiCalculation
    {
        $this->actingAs($this->admin)
            ->post(route('impact.hitung', $program))
            ->assertSessionHas('success');

        return SroiCalculation::latest('id')->firstOrFail();
    }
}
