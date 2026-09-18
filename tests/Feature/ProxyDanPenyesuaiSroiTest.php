<?php

namespace Tests\Feature;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\User;
use App\Services\SroiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MASTER PROXY & DOKUMENTASI FAKTOR PENYESUAI.
 *
 * ==========================================================================
 * SATU TEMA YANG MENYATUKAN SELURUH TEST DI BAWAH
 * ==========================================================================
 *
 * Angka nol yang tidak bisa dijelaskan asalnya. Sebelum perubahan ini,
 * displacement 0% memuat tiga arti sekaligus — "sudah diperiksa, memang nol",
 * "tidak relevan untuk outcome jenis ini", dan "belum pernah dipikirkan" —
 * dan yang ketiga justru yang paling mungkin terjadi sekaligus paling
 * berbahaya. Hal serupa berlaku untuk attribution 70% tanpa keterangan siapa
 * pemilik 30% sisanya, dan untuk drop-off yang diisi padahal durasinya satu
 * tahun sehingga tidak pernah terpakai.
 *
 * Seluruh test di bawah menjaga satu prinsip yang sama: setiap angka dalam
 * perhitungan SROI harus bisa ditanyakan dari mana asalnya.
 */
class ProxyDanPenyesuaiSroiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ImpactProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@px.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->program = ImpactProgram::create([
            'nama' => 'Program Uji',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
        ]);
    }

    // ==================================================================
    // A. MASTER PROXY — outcome & indikator
    // ==================================================================

    public function test_proxy_menyimpan_outcome_dan_indikator_yang_dilayaninya(): void
    {
        $this->actingAs($this->admin)->post(route('impact.proxy.store'), [
            'nama' => 'Nilai kenaikan pendapatan UMKM',
            'outcome' => 'Peningkatan pendapatan usaha',
            'indikator' => 'profit_bulanan',
            'satuan' => 'per rupiah kenaikan profit',
            'nilai' => 1,
            'sumber' => 'BPS Statistik UMKM',
            'tahun' => now()->year,
        ])->assertSessionHasNoErrors();

        $px = FinancialProxy::firstOrFail();

        $this->assertSame('Peningkatan pendapatan usaha', $px->outcome);
        $this->assertSame('profit_bulanan', $px->indikator);
    }

    public function test_outcome_dan_indikator_proxy_boleh_kosong(): void
    {
        // Satu proxy memang bisa dipakai lintas outcome. Mewajibkannya hanya
        // akan memaksa Admin mengarang keterkaitan yang belum tentu ada.
        $this->actingAs($this->admin)->post(route('impact.proxy.store'), [
            'nama' => 'Proxy Umum', 'satuan' => 'per orang', 'nilai' => 500_000,
            'sumber' => 'Dokumen Uji', 'tahun' => now()->year,
        ])->assertSessionHasNoErrors();

        $px = FinancialProxy::firstOrFail();

        $this->assertNull($px->indikator);
        $this->assertSame('(belum dinyatakan)', $px->untukOutcome());
    }

    public function test_sumber_dan_tahun_tetap_wajib(): void
    {
        // Aturan lama yang tidak boleh longgar karena ada kolom baru.
        $this->actingAs($this->admin)->post(route('impact.proxy.store'), [
            'nama' => 'Tanpa Sumber', 'outcome' => 'Apa saja',
            'satuan' => 'per orang', 'nilai' => 1_000_000,
        ])->assertSessionHasErrors(['sumber', 'tahun']);

        $this->assertSame(0, FinancialProxy::count());
    }

    public function test_proxy_tanpa_indikator_cocok_untuk_indikator_apa_pun(): void
    {
        $px = $this->proxy(['indikator' => null]);

        $this->assertTrue($px->cocokUntuk('omzet_bulanan'));
        $this->assertTrue($px->cocokUntuk('jumlah_tenaga_kerja'));
    }

    public function test_proxy_yang_dipasang_ke_indikator_lain_diperingatkan(): void
    {
        // Salah pasang proxy menghasilkan nilai dampak yang keliru TANPA satu
        // pun tanda bahaya — inilah tandanya.
        $this->pesertaBerubah();
        $this->investasi();

        $px = $this->proxy(['indikator' => 'jumlah_tenaga_kerja']);
        $this->outcome(['financial_proxy_id' => $px->id, 'indikator' => 'omzet_bulanan']);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertTrue(
            collect($kesiapan['peringatan'])->contains(fn ($w) => str_contains($w, 'Pastikan proxy-nya memang tepat')),
            'Proxy yang menyebut indikator lain harus diperingatkan.'
        );
    }

    // ==================================================================
    // B. DISPLACEMENT — tiga keadaan, bukan dua
    // ==================================================================

    public function test_displacement_belum_ditentukan_berbeda_dari_nol(): void
    {
        $o = $this->outcome();

        $this->assertTrue($o->displacementBelumDitentukan());
        $this->assertFalse($o->displacementTidakRelevan());
        // Perhitungan tetap memakai 0 — tapi keadaannya bisa dikenali.
        $this->assertSame(0.0, $o->displacementBerlaku());
    }

    public function test_displacement_belum_ditentukan_memicu_peringatan(): void
    {
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome(['financial_proxy_id' => $this->proxy()->id]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertTrue(
            collect($kesiapan['peringatan'])->contains(fn ($w) => str_contains($w, 'belum menyatakan apakah displacement relevan')),
        );
    }

    public function test_displacement_dinyatakan_tidak_relevan_tidak_lagi_diperingatkan(): void
    {
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'displacement_relevan' => false,
            'displacement_catatan' => 'Produk peserta tidak bersaing dengan usaha lain di wilayah yang sama.',
            'deadweight_persen' => 20,
            'attribution_persen' => 80,
            'attribution_catatan' => 'Sebagian kenaikan berasal dari usaha peserta sendiri.',
        ]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertFalse(
            collect($kesiapan['peringatan'])->contains(fn ($w) => str_contains($w, 'displacement')),
            'Displacement yang sudah diputuskan beserta alasannya tidak perlu diperingatkan lagi.'
        );
    }

    public function test_menyatakan_tidak_relevan_wajib_menyertakan_alasan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
                'displacement_relevan' => '0',
                'displacement_catatan' => '',
            ]))
            ->assertSessionHasErrors('displacement_catatan');
    }

    public function test_displacement_tidak_relevan_disimpan_dengan_persen_nol(): void
    {
        // Kalau persentasenya tetap disimpan, angkanya akan muncul kembali
        // begitu outcome-nya kelak ditandai relevan — tanpa ada yang
        // memutuskannya.
        $this->actingAs($this->admin)->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
            'displacement_relevan' => '0',
            'displacement_persen' => 35,
            'displacement_catatan' => 'Tidak ada pasar yang bersinggungan.',
        ]))->assertSessionHasNoErrors();

        $o = ImpactOutcome::firstOrFail();

        $this->assertSame(0.0, (float) $o->displacement_persen);
        $this->assertTrue($o->displacementTidakRelevan());
    }

    public function test_displacement_relevan_memakai_persentasenya(): void
    {
        $this->actingAs($this->admin)->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
            'displacement_relevan' => '1',
            'displacement_persen' => 15,
        ]))->assertSessionHasNoErrors();

        $o = ImpactOutcome::firstOrFail();

        $this->assertSame(15.0, $o->displacementBerlaku());
    }

    // ==================================================================
    // C. ATTRIBUTION — angka tanpa keterangan tidak bisa diperiksa
    // ==================================================================

    public function test_attribution_di_bawah_seratus_wajib_menyebut_pihak_lain(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
                'attribution_persen' => 70,
                'attribution_catatan' => '',
            ]))
            ->assertSessionHasErrors('attribution_catatan');
    }

    public function test_attribution_seratus_persen_tidak_butuh_keterangan(): void
    {
        // Mengklaim seluruh perubahan tidak menyisakan pihak lain untuk
        // disebut — jadi tidak ada yang perlu dijelaskan di sini.
        $this->actingAs($this->admin)
            ->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
                'attribution_persen' => 100,
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_attribution_sangat_rendah_diperingatkan_kemungkinan_terbalik(): void
    {
        // Kolom attribution berarti "porsi MILIK program ini" (bawaan 100),
        // dan sangat mudah terbaca terbalik sebagai "porsi milik pihak lain".
        // Kalau terbalik, nilai dampaknya meleset berkali lipat TANPA satu pun
        // tanda bahaya — angkanya tetap wajar dan rasionya tetap tercetak.
        //
        // Peringatan ini tetap muncul walau keterangannya sudah diisi, karena
        // justru itulah bentuk kekeliruan yang lolos dari pemeriksaan lain.
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'attribution_persen' => 20,
            'attribution_catatan' => 'Sebagian kenaikan berasal dari program lain.',
            'displacement_relevan' => false,
            'displacement_catatan' => 'Tidak relevan.',
        ]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertTrue(
            collect($kesiapan['peringatan'])->contains(fn ($w) => str_contains($w, 'arahnya tidak terbalik')),
            'Attribution 20% seharusnya memicu peringatan kemungkinan terbalik.',
        );
    }

    public function test_attribution_wajar_tidak_diperingatkan_terbalik(): void
    {
        // 80% milik program adalah nilai yang wajar — tidak boleh ikut
        // diperingatkan, kalau tidak peringatannya berhenti dibaca.
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'attribution_persen' => 80,
            'attribution_catatan' => 'Sebagian kecil dari program lain.',
            'displacement_relevan' => false,
            'displacement_catatan' => 'Tidak relevan.',
        ]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertFalse(
            collect($kesiapan['peringatan'])->contains(fn ($w) => str_contains($w, 'arahnya tidak terbalik')),
        );
    }

    public function test_peringatan_terbalik_tidak_memblokir_perhitungan(): void
    {
        // Program yang memang hanya menyumbang sebagian kecil perubahan itu
        // SAH. Peringatan ini meminta konfirmasi, bukan menolak.
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'attribution_persen' => 20,
            'attribution_catatan' => 'Program ini memang pelengkap program lain.',
            'displacement_relevan' => false,
            'displacement_catatan' => 'Tidak relevan.',
        ]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertSame([], $kesiapan['kurang'], 'Peringatan tidak boleh menjadi penghalang.');
    }

    public function test_attribution_tanpa_keterangan_memicu_peringatan_di_kesiapan(): void
    {
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'attribution_persen' => 60,
            'attribution_catatan' => null,
            'displacement_relevan' => false,
            'displacement_catatan' => 'Tidak relevan.',
        ]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($this->program->fresh());

        $this->assertTrue(
            collect($kesiapan['peringatan'])->contains(fn ($w) => str_contains($w, 'belum menyebutkan pihak lain itu siapa')),
        );
    }

    // ==================================================================
    // D. DROP-OFF — hanya berarti untuk manfaat lintas tahun
    // ==================================================================

    public function test_drop_off_dinolkan_untuk_manfaat_satu_tahun(): void
    {
        $this->actingAs($this->admin)->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
            'durasi_tahun' => 1,
            'drop_off_persen' => 40,
        ]))->assertSessionHasNoErrors();

        $o = ImpactOutcome::firstOrFail();

        $this->assertSame(0.0, (float) $o->drop_off_persen);
        $this->assertFalse($o->dropOffRelevan());
    }

    public function test_drop_off_dipertahankan_untuk_manfaat_lintas_tahun(): void
    {
        $this->actingAs($this->admin)->post(route('impact.outcome.store', $this->program), $this->payloadOutcome([
            'durasi_tahun' => 3,
            'drop_off_persen' => 40,
        ]))->assertSessionHasNoErrors();

        $o = ImpactOutcome::firstOrFail();

        $this->assertSame(40.0, (float) $o->drop_off_persen);
        $this->assertTrue($o->dropOffRelevan());
    }

    // ==================================================================
    // E. SELURUHNYA IKUT TERBEKUKAN DI HASIL PERHITUNGAN
    // ==================================================================

    public function test_keputusan_penyesuai_dibekukan_bersama_hasilnya(): void
    {
        // Pembaca laporan dua tahun lagi harus bisa membedakan "diperiksa,
        // hasilnya nol" dari "dinyatakan tidak relevan" tanpa membuka sistem.
        $this->pesertaBerubah();
        $this->investasi();
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'deadweight_persen' => 25,
            'deadweight_catatan' => 'Tren UMKM sejenis di wilayah yang sama.',
            'attribution_persen' => 80,
            'attribution_catatan' => 'Sebagian dari usaha peserta sendiri dan dukungan keluarga.',
            'displacement_relevan' => false,
            'displacement_catatan' => 'Pasarnya tidak bersinggungan dengan usaha lain.',
        ]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());
        $penyesuai = $hasil->rincian['outcomes'][0]['penyesuai'];

        $this->assertFalse($penyesuai['displacement_relevan']);
        $this->assertSame('Pasarnya tidak bersinggungan dengan usaha lain.', $penyesuai['displacement_catatan']);
        $this->assertSame('Tren UMKM sejenis di wilayah yang sama.', $penyesuai['deadweight_catatan']);
        $this->assertStringContainsString('keluarga', $penyesuai['attribution_catatan']);
    }

    public function test_sensitivitas_membawa_investasi_di_tiap_skenario(): void
    {
        $this->pesertaBerubah();
        $this->investasi(5_000_000);
        $this->outcome(['financial_proxy_id' => $this->proxy()->id]);

        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        foreach (['konservatif', 'dasar', 'optimistis'] as $skenario) {
            $this->assertSame(
                5_000_000,
                $hasil->sensitivitas[$skenario]['total_investasi'],
                'Investasi tidak boleh bergeser antar skenario — yang digeser hanyalah asumsi.'
            );
        }
    }

    public function test_halaman_program_membedakan_tidak_relevan_dari_nol_persen(): void
    {
        $this->outcome([
            'financial_proxy_id' => $this->proxy()->id,
            'displacement_relevan' => false,
            'displacement_catatan' => 'Tidak bersinggungan.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Tidak relevan')
            ->assertSee('Tidak berlaku');   // drop-off, durasi 1 tahun
    }

    public function test_halaman_program_menandai_displacement_yang_belum_ditentukan(): void
    {
        $this->outcome(['financial_proxy_id' => $this->proxy()->id]);

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Belum ditentukan');
    }

    public function test_jejak_pencatat_outcome_tampil_di_halaman_program(): void
    {
        $this->actingAs($this->admin)->post(route('impact.outcome.store', $this->program), $this->payloadOutcome());

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Dicatat Admin');
    }

    // ==================================================================
    // PEMBANTU
    // ==================================================================

    private function proxy(array $ubah = []): FinancialProxy
    {
        return FinancialProxy::create($ubah + [
            'nama' => 'Proxy Uji',
            'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => 1,
            'sumber' => 'Dokumen Uji',
            'tahun' => now()->year,
        ]);
    }

    private function investasi(int $nominal = 10_000_000): void
    {
        $this->program->investments()->create([
            'kategori' => 'Pelatihan', 'uraian' => 'Pelatihan uji',
            'tanggal' => now()->toDateString(), 'nominal' => $nominal, 'jenis' => 'Tunai',
        ]);
    }

    private function outcome(array $ubah = []): ImpactOutcome
    {
        return $this->program->outcomes()->create($ubah + [
            'nama' => 'Pendapatan usaha meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
        ]);
    }

    private function payloadOutcome(array $ubah = []): array
    {
        return $ubah + [
            'nama' => 'Pendapatan usaha meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'deadweight_persen' => 0,
            'attribution_persen' => 100,
            'displacement_persen' => 0,
            'drop_off_persen' => 0,
            'durasi_tahun' => 1,
        ];
    }

    private function pesertaBerubah(): void
    {
        $p = Participant::create([
            'nama_peserta' => 'Bu Ani', 'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->startOfYear()->toDateString(),
            'status' => 'Aktif',
        ]);

        $p->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(),
            'omzet_bulanan' => 1_000_000,
        ]);
        $p->measurements()->create([
            'jenis' => ParticipantMeasurement::ENDLINE,
            'tanggal' => now()->toDateString(),
            'omzet_bulanan' => 2_000_000,
        ]);
    }
}
