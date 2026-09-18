<?php

namespace Tests\Feature;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\ProgramInvestment;
use App\Models\User;
use App\Services\SroiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * KESIAPAN ADMIN MENGISI DATA NYATA IMPACT & SROI.
 *
 * ==========================================================================
 * MASALAH YANG DISELESAIKAN TAHAP INI
 * ==========================================================================
 *
 * Mesin SROI-nya sudah benar dan teruji, tetapi Admin belum punya jalan untuk
 * mengisinya. Dua celah yang paling menghambat:
 *
 * 1. TIDAK ADA JALAN KE BASELINE DARI HALAMAN PROGRAM.
 *    Baseline dicatat per peserta, di modul lain. Padahal justru DI HALAMAN
 *    PROGRAM orang menyadari baselinenya kurang — pesan "belum ada peserta
 *    dengan baseline dan endline yang lengkap" muncul tepat saat SROI menolak
 *    dihitung. Tanpa daftar dan tautannya, Admin harus menebak peserta mana
 *    yang perlu dilengkapi lalu mencarinya satu per satu.
 *
 * 2. TIDAK ADA URUTAN KERJA YANG TERLIHAT.
 *    Menyusun laporan dampak punya urutan yang tidak bisa dibalik. Halaman
 *    berisi kartu-kartu sejajar membiarkan Admin mengisi dari mana saja, lalu
 *    menemukan urutannya salah setelah pekerjaan menumpuk.
 *
 * Test di bawah menjaga keduanya tetap ada, dan menjaga bahwa statusnya
 * DITURUNKAN dari data — bukan disimpan, sehingga tidak bisa basi.
 */
class KesiapanEntriImpactTest extends TestCase
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

        Storage::fake('local');

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@ke.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@ke.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@ke.test');
        $this->pendamping = $this->user('pendamping', 'pd@ke.test', $profil->id);
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@ke.test');

        $this->program = ImpactProgram::create([
            'nama' => 'Program Uji',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
        ]);
    }

    // ==================================================================
    // A. ALUR PENYUSUNAN TERLIHAT
    // ==================================================================

    public function test_halaman_program_menampilkan_sebelas_langkah_penyusunan(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('impact.show', $this->program));

        $respons->assertOk()
            ->assertSee('Alur Penyusunan')
            ->assertSee('Investment')
            ->assertSee('Baseline &amp; Endline Peserta', false)
            ->assertSee('Financial Proxy')
            ->assertSee('Outcome &amp; Indicator', false)
            ->assertSee('Adjustment')
            ->assertSee('Hitung SROI')
            ->assertSee('Review &amp; Approval', false)
            ->assertSee('Laporan PDF');
    }

    public function test_kemajuan_langkah_diturunkan_dari_data_bukan_disimpan(): void
    {
        // Program kosong: hanya langkah "Program & Periode" dan "Output"
        // (yang memang otomatis) yang selesai.
        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('2 dari 10 langkah selesai');

        // Begitu investasi dicatat, kemajuannya ikut naik — tanpa ada kolom
        // status yang perlu diperbarui.
        $this->investasi();

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('3 dari 10 langkah selesai');
    }

    public function test_langkah_kembali_belum_selesai_saat_datanya_dihapus(): void
    {
        $this->investasi();
        $this->actingAs($this->admin)->get(route('impact.show', $this->program))->assertSee('3 dari 10');

        ProgramInvestment::firstOrFail()->delete();

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('2 dari 10 langkah selesai');
    }

    // ==================================================================
    // B. JALAN KE BASELINE — celah terbesar yang ditutup
    // ==================================================================

    public function test_halaman_program_menautkan_peserta_yang_belum_punya_baseline(): void
    {
        $peserta = $this->peserta('Bu Ani');

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Belum punya baseline')
            ->assertSee('Bu Ani')
            // Tautannya langsung ke form pencatatan baseline peserta itu.
            ->assertSee(route('perkembangan.create', $peserta), false);
    }

    public function test_peserta_yang_baru_punya_baseline_ditautkan_untuk_dilengkapi(): void
    {
        $peserta = $this->peserta('Pak Budi');
        $peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(),
            'omzet_bulanan' => 1_000_000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Punya baseline, belum punya pembanding')
            ->assertSee(route('perkembangan.index', $peserta), false);
    }

    public function test_hitungan_peserta_terukur_benar(): void
    {
        $lengkap = $this->peserta('Lengkap');
        $lengkap->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(), 'omzet_bulanan' => 1_000_000,
        ]);
        $lengkap->measurements()->create([
            'jenis' => ParticipantMeasurement::ENDLINE,
            'tanggal' => now()->toDateString(), 'omzet_bulanan' => 2_000_000,
        ]);

        $this->peserta('Baseline Saja')->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(), 'omzet_bulanan' => 900_000,
        ]);

        $this->peserta('Belum Diukur');

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Peserta program')
            ->assertSee('Punya baseline')
            ->assertSee('Terukur penuh');
    }

    public function test_program_tanpa_peserta_menjelaskan_kenapa(): void
    {
        // Program yang periodenya tidak mencakup peserta mana pun harus
        // mengatakan itu, bukan menampilkan nol tanpa penjelasan.
        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Belum ada peserta yang tercakup periode program ini');
    }

    // ==================================================================
    // C. BUKTI INVESTASI
    // ==================================================================

    public function test_admin_mengunggah_kuitansi_saat_mencatat_investasi(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.investasi.store', $this->program), $this->payloadInvestasi([
                'bukti' => UploadedFile::fake()->image('kuitansi.jpg'),
            ]))
            ->assertSessionHasNoErrors();

        $inv = ProgramInvestment::firstOrFail();

        $this->assertTrue($inv->punyaBukti());
        $this->assertSame('kuitansi.jpg', $inv->bukti_asli);
        Storage::assertExists($inv->bukti_path);
    }

    public function test_bukti_investasi_boleh_kosong(): void
    {
        // Pos in-kind seperti waktu relawan memang tidak berkuitansi.
        // Mewajibkannya hanya akan membuat pos itu tidak dicatat sama sekali,
        // dan penyebut rasio jadi lebih kecil daripada sebenarnya.
        $this->actingAs($this->admin)
            ->post(route('impact.investasi.store', $this->program), $this->payloadInvestasi([
                'jenis' => 'In-kind', 'uraian' => 'Waktu relawan pendampingan',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(ProgramInvestment::firstOrFail()->punyaBukti());
    }

    public function test_berkas_berbahaya_ditolak_sebagai_bukti_investasi(): void
    {
        $this->actingAs($this->admin)
            ->post(route('impact.investasi.store', $this->program), $this->payloadInvestasi([
                'bukti' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('bukti');

        $this->assertSame(0, ProgramInvestment::count());
    }

    public function test_bukti_investasi_disimpan_di_disk_privat(): void
    {
        $this->actingAs($this->admin)->post(
            route('impact.investasi.store', $this->program),
            $this->payloadInvestasi(['bukti' => UploadedFile::fake()->image('nota.jpg')])
        );

        $inv = ProgramInvestment::firstOrFail();

        Storage::disk('local')->assertExists($inv->bukti_path);
        Storage::disk('public')->assertMissing($inv->bukti_path);
        $this->assertStringStartsWith('bukti-investasi/' . $this->program->id . '/', $inv->bukti_path);
    }

    public function test_mengganti_bukti_investasi_tidak_menghapus_berkas_lama(): void
    {
        // Investasi adalah penyebut rasio SROI. Kalau kuitansinya bisa ditimpa
        // tanpa sisa, seseorang bisa memperbaiki nominal sekaligus menghapus
        // dasar nominal sebelumnya.
        $this->actingAs($this->admin)->post(
            route('impact.investasi.store', $this->program),
            $this->payloadInvestasi(['bukti' => UploadedFile::fake()->image('lama.jpg')])
        );

        $inv = ProgramInvestment::firstOrFail();
        $lama = $inv->bukti_path;

        $this->actingAs($this->admin)->put(
            route('impact.investasi.update', $inv),
            $this->payloadInvestasi(['bukti' => UploadedFile::fake()->image('baru.jpg')])
        );

        Storage::assertExists($lama);
        Storage::assertExists($inv->fresh()->bukti_path);
        $this->assertNotSame($lama, $inv->fresh()->bukti_path);
    }

    public function test_menyimpan_tanpa_berkas_tidak_menghapus_bukti_yang_ada(): void
    {
        $this->actingAs($this->admin)->post(
            route('impact.investasi.store', $this->program),
            $this->payloadInvestasi(['bukti' => UploadedFile::fake()->image('kuitansi.jpg')])
        );

        $inv = ProgramInvestment::firstOrFail();
        $lama = $inv->bukti_path;

        $this->actingAs($this->admin)->put(
            route('impact.investasi.update', $inv),
            $this->payloadInvestasi(['nominal' => 7_500_000])
        );

        $this->assertSame($lama, $inv->fresh()->bukti_path);
        $this->assertSame(7_500_000, $inv->fresh()->nominal);
    }

    public function test_bukti_investasi_tidak_bisa_ditentukan_lewat_mass_assignment(): void
    {
        $this->actingAs($this->admin)->post(
            route('impact.investasi.store', $this->program),
            $this->payloadInvestasi(['bukti_path' => '../../.env', 'bukti_asli' => '.env'])
        );

        $this->assertNull(ProgramInvestment::firstOrFail()->bukti_path);
    }

    // ==================================================================
    // D. OTORISASI UNDUH BUKTI
    // ==================================================================

    public function test_pimpinan_bisa_memeriksa_kuitansi_investasi(): void
    {
        $inv = $this->investasiBerbukti();

        $this->actingAs($this->pimpinan)
            ->get(route('impact.investasi.bukti', $inv))
            ->assertOk();
    }

    public function test_pendamping_dan_tim_koperasi_tidak_bisa_mengunduh_bukti_investasi(): void
    {
        $inv = $this->investasiBerbukti();

        foreach ([$this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)
                ->get(route('impact.investasi.bukti', $inv))
                ->assertForbidden();
        }
    }

    public function test_berkas_yang_hilang_menghasilkan_404_bukan_error(): void
    {
        $inv = $this->investasiBerbukti();
        Storage::delete($inv->bukti_path);

        $this->actingAs($this->admin)
            ->get(route('impact.investasi.bukti', $inv))
            ->assertNotFound();
    }

    // ==================================================================
    // E. ALUR TETAP MENOLAK ANGKA PALSU
    // ==================================================================

    public function test_langkah_hitung_menyebut_status_kesiapan_apa_adanya(): void
    {
        // Program kosong: langkah "Hitung SROI" harus menyebutkan alasannya,
        // bukan menawarkan tombol yang akan gagal.
        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('Belum Ada Data');
    }

    public function test_seluruh_langkah_selesai_hanya_setelah_disetujui(): void
    {
        $this->siapkanLengkap();
        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        // Sudah dihitung, tapi belum disetujui — belum 10 dari 10.
        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertDontSee('10 dari 10 langkah selesai');

        $this->actingAs($this->pimpinan)->post(
            route('impact.setujui', [$this->program, $hasil]),
            ['catatan_review' => 'Sudah diperiksa.']
        );

        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee('10 dari 10 langkah selesai');
    }

    // ==================================================================
    // PEMBANTU
    // ==================================================================

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email, 'email' => $email,
            'password' => bcrypt('rahasia123'), 'role' => $role,
            'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0812' . random_int(10000000, 99999999),
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->startOfYear()->toDateString(),
            'status' => 'Aktif',
        ]);
    }

    private function payloadInvestasi(array $ubah = []): array
    {
        return $ubah + [
            'kategori' => 'Pelatihan',
            'uraian' => 'Pelatihan pembukuan',
            'tanggal' => now()->toDateString(),
            'nominal' => 10_000_000,
            'jenis' => 'Tunai',
        ];
    }

    private function investasi(): ProgramInvestment
    {
        return $this->program->investments()->create([
            'kategori' => 'Pelatihan', 'uraian' => 'Pelatihan',
            'tanggal' => now()->toDateString(), 'nominal' => 10_000_000, 'jenis' => 'Tunai',
        ]);
    }

    private function investasiBerbukti(): ProgramInvestment
    {
        $this->actingAs($this->admin)->post(
            route('impact.investasi.store', $this->program),
            $this->payloadInvestasi(['bukti' => UploadedFile::fake()->image('kuitansi.jpg')])
        );

        return ProgramInvestment::firstOrFail();
    }

    /** Program yang benar-benar siap dihitung, tanpa peringatan tersisa. */
    private function siapkanLengkap(): void
    {
        $p = $this->peserta('Bu Ani');
        $p->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(), 'omzet_bulanan' => 1_000_000,
        ]);
        $p->measurements()->create([
            'jenis' => ParticipantMeasurement::ENDLINE,
            'tanggal' => now()->toDateString(), 'omzet_bulanan' => 2_000_000,
        ]);

        $this->investasi();

        $this->program->investments()->create([
            'kategori' => 'Pendampingan', 'uraian' => 'Waktu relawan',
            'tanggal' => now()->toDateString(), 'nominal' => 1_000_000, 'jenis' => 'In-kind',
        ]);

        $proxy = FinancialProxy::create([
            'nama' => 'Proxy Uji', 'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => 1, 'sumber' => 'Dokumen Uji', 'tahun' => now()->year,
        ]);

        $this->program->outcomes()->create([
            'nama' => 'Pendapatan meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'financial_proxy_id' => $proxy->id,
            'deadweight_persen' => 20,
            'attribution_persen' => 80,
            'attribution_catatan' => 'Sebagian dari usaha peserta sendiri.',
            'displacement_relevan' => false,
            'displacement_catatan' => 'Pasarnya tidak bersinggungan.',
        ]);
    }
}
