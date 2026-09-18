<?php

namespace Tests\Feature\Uat;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\User;
use App\Services\SroiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UAT — ALUR PENUH PESERTA TANPA AKUN, DARI LINK SAMPAI LAPORAN DAMPAK.
 *
 * Test lain memeriksa setiap tahap secara terpisah. Yang ini menjalankan
 * SELURUH rantainya sebagai satu perjalanan, persis seperti yang terjadi di
 * lapangan:
 *
 *   Pendamping membuka detail peserta
 *     -> menyalin link laporan omzet
 *       -> peserta membuka link itu tanpa login
 *         -> mengisi omzet, HPP, biaya, catatan, dan mengunggah bukti
 *           -> angkanya muncul di Monitoring Omzet
 *             -> ikut terhitung di laporan
 *               -> dan akhirnya masuk ke perhitungan dampak.
 *
 * Rantai yang setiap matanya benar belum tentu tersambung. Justru sambungan
 * antar modul itulah yang paling sering putus tanpa ketahuan, karena tidak
 * ada satu test pun yang berdiri di dua sisi sekaligus.
 */
class AlurPesertaTanpaAkunTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendamping;
    private Participant $peserta;
    private Participant $pesertaLain;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@alur.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@alur.test');
        $this->pendamping = $this->user('pendamping', 'pd@alur.test', $profil->id);

        $this->peserta = $this->buatPeserta('Bu Ani', 'Keripik Bawang', $profil->id);
        $this->pesertaLain = $this->buatPeserta('Pak Budi', 'Warung Kopi', $profil->id);
    }

    // ==================================================================
    // A. PERJALANAN PENUH
    // ==================================================================

    public function test_dari_link_sampai_masuk_monitoring_dan_laporan(): void
    {
        // --- 1. Pendamping membuka detail peserta dan menemukan linknya ---
        $this->actingAs($this->pendamping)
            ->get(route('participants.show', $this->peserta))
            ->assertOk()
            ->assertSee(route('omzet.public.show', $this->peserta->report_token), false);

        // --- 2. Peserta membuka link itu TANPA login ---
        $this->get(route('omzet.public.show', $this->peserta->report_token))
            ->assertOk()
            ->assertSee('Bu Ani')
            // Halaman ini TIDAK boleh menampilkan peserta lain.
            ->assertDontSee('Pak Budi');

        // --- 3. Peserta mengirim laporan lengkap beserta buktinya ---
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'jenis_periode' => OmzetReport::BULANAN,
            'periode_bulan' => now()->month,
            'periode_tahun' => now()->year,
            'omzet' => 5_000_000,
            'hpp' => 3_000_000,
            'biaya_operasional' => 500_000,
            'catatan' => 'Bulan ini ramai karena ada hajatan.',
            'bukti' => UploadedFile::fake()->image('buku-kas.jpg'),
        ])->assertSessionHasNoErrors();

        // --- 4. Datanya benar-benar masuk ke database ---
        $laporan = OmzetReport::firstOrFail();

        $this->assertSame($this->peserta->id, $laporan->participant_id);
        $this->assertSame(5_000_000, $laporan->omzet);
        $this->assertSame(1_500_000, $laporan->profitBersih());
        $this->assertTrue($laporan->punyaBukti());
        Storage::assertExists($laporan->bukti_path);

        // --- 5. Muncul di Monitoring Omzet milik pendamping ---
        $this->actingAs($this->pendamping)
            ->get(route('omzet.index'))
            ->assertOk()
            ->assertSee('Bu Ani')
            ->assertSee('Profit Usaha Binaan');

        // --- 6. Muncul di detail peserta ---
        $this->actingAs($this->pendamping)
            ->get(route('participants.show', $this->peserta))
            ->assertOk()
            ->assertSee('5.000.000');

        // --- 7. Ikut terhitung di laporan omzet ---
        $this->actingAs($this->admin)
            ->get(route('laporan.omzet'))
            ->assertOk()
            ->assertSee('Bu Ani');

        // --- 8. Buktinya bisa diunduh pendamping pemilik binaan ---
        //
        // Penolakan untuk tamu dan untuk pendamping lain SENGAJA tidak diuji
        // di sini: actingAs() di langkah-langkah sebelumnya masih melekat
        // pada request berikutnya dalam test yang sama, jadi "tamu" di titik
        // ini sebenarnya masih login — dan assertion-nya akan lulus tanpa
        // membuktikan apa pun. Keduanya diuji dengan sesi bersih di
        // BuktiOmzetTest.
        $this->actingAs($this->pendamping)->get(route('omzet.bukti', $laporan))->assertOk();
    }

    public function test_omzet_dari_link_publik_ikut_menggerakkan_perhitungan_dampak(): void
    {
        // Sambungan paling jauh dalam sistem: angka yang diketik peserta di
        // ponselnya harus sampai ke laporan SROI tanpa ada yang menyalin
        // ulang di tengah jalan.
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year,
            'omzet' => 5_000_000, 'hpp' => 3_000_000,
        ])->assertSessionHasNoErrors();

        // Baseline & endline dicatat pendamping seperti pekerjaan normalnya.
        $this->peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(),
            'omzet_bulanan' => 2_000_000,
        ]);
        $this->peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::ENDLINE,
            'tanggal' => now()->toDateString(),
            'omzet_bulanan' => 5_000_000,
        ]);

        $program = ImpactProgram::create([
            'nama' => 'Program Uji',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
        ]);

        $program->investments()->create([
            'kategori' => 'Pelatihan', 'uraian' => 'Pelatihan', 'tanggal' => now()->toDateString(),
            'nominal' => 10_000_000, 'jenis' => 'Tunai',
        ]);

        $proxy = FinancialProxy::create([
            'nama' => 'Proxy Uji', 'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => 1, 'sumber' => 'Dokumen UAT', 'tahun' => now()->year,
        ]);

        $program->outcomes()->create([
            'nama' => 'Pendapatan meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'financial_proxy_id' => $proxy->id,
            'deadweight_persen' => 20, 'attribution_persen' => 80,
            'displacement_persen' => 0, 'drop_off_persen' => 0,
        ]);

        $kesiapan = app(SroiService::class)->periksaKesiapan($program->fresh());

        $this->assertTrue($kesiapan['siap'], 'Data operasional peserta tidak sampai ke mesin SROI.');

        $hasil = app(SroiService::class)->hitung($program->fresh());

        // Kenaikan 3jt/bulan -> 36jt/tahun x proxy 1 = 36jt kotor
        // x (1-0,2) = 28,8jt  x 0,8 = 23,04jt
        $this->assertSame(23_040_000, $hasil->total_nilai_dampak);
        $this->assertNotNull($hasil->rasio);
    }

    // ==================================================================
    // B. ISOLASI ANTAR PESERTA
    // ==================================================================

    public function test_token_satu_peserta_tidak_bisa_dipakai_mengisi_peserta_lain(): void
    {
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year,
            'omzet' => 5_000_000,
            // Percobaan menyelipkan peserta lain lewat mass assignment.
            'participant_id' => $this->pesertaLain->id,
        ])->assertSessionHasNoErrors();

        $laporan = OmzetReport::firstOrFail();

        $this->assertSame(
            $this->peserta->id,
            $laporan->participant_id,
            'Laporan masuk ke peserta lain — token tidak lagi menentukan pemiliknya.'
        );
    }

    public function test_halaman_publik_tidak_membocorkan_data_peserta_lain(): void
    {
        $this->get(route('omzet.public.show', $this->peserta->report_token))
            ->assertOk()
            ->assertDontSee('Pak Budi')
            ->assertDontSee('Warung Kopi')
            ->assertDontSee($this->pesertaLain->report_token, false);
    }

    public function test_token_peserta_tidak_bisa_ditebak_dari_id(): void
    {
        // Token yang berupa angka urut membuat seluruh peserta bisa dibuka
        // dengan mencoba 1, 2, 3, dan seterusnya.
        $token = $this->peserta->report_token;

        $this->assertNotSame((string) $this->peserta->id, $token);
        $this->assertGreaterThanOrEqual(20, strlen($token));
        $this->get(route('omzet.public.show', (string) $this->peserta->id))->assertNotFound();
    }

    public function test_form_publik_tidak_bisa_mengubah_identitas_peserta(): void
    {
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year,
            'omzet' => 1_000_000,
            'nama_peserta' => 'Nama Diganti',
            'status' => 'Selesai',
        ])->assertSessionHasNoErrors();

        $segar = $this->peserta->fresh();

        $this->assertSame('Bu Ani', $segar->nama_peserta);
        $this->assertSame('Aktif', $segar->status);
    }

    // ==================================================================
    // C. TIGA MODE LEWAT JALUR PUBLIK
    // ==================================================================

    public function test_peserta_bisa_melapor_harian_lewat_link_publik(): void
    {
        foreach ([1 => 150_000, 2 => 200_000, 3 => 175_000] as $tanggal => $nilai) {
            $this->post(route('omzet.public.store', $this->peserta->report_token), [
                'jenis_periode' => OmzetReport::HARIAN,
                'periode_bulan' => 9, 'periode_tahun' => now()->year,
                'periode_urut' => $tanggal, 'omzet' => $nilai,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, OmzetReport::count());

        // Kueri bulanan lama tetap menghasilkan total yang benar.
        $this->assertSame(
            525_000,
            (int) OmzetReport::where('periode_bulan', 9)->where('periode_tahun', now()->year)->sum('omzet')
        );
    }

    public function test_peserta_bisa_melapor_mingguan_lewat_link_publik(): void
    {
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'jenis_periode' => OmzetReport::MINGGUAN,
            'periode_bulan' => 9, 'periode_tahun' => now()->year,
            'periode_urut' => 2, 'omzet' => 1_200_000,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Minggu ke-2 September ' . now()->year, OmzetReport::firstOrFail()->periodeLabel());
    }

    public function test_peserta_tidak_bisa_mencampur_mode_dalam_satu_bulan(): void
    {
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'jenis_periode' => OmzetReport::HARIAN,
            'periode_bulan' => 9, 'periode_tahun' => now()->year,
            'periode_urut' => 1, 'omzet' => 150_000,
        ])->assertSessionHasNoErrors();

        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'jenis_periode' => OmzetReport::BULANAN,
            'periode_bulan' => 9, 'periode_tahun' => now()->year,
            'omzet' => 5_000_000,
        ])->assertSessionHasErrors('jenis_periode');

        // Total bulan itu tetap hanya dari laporan harian — tidak berlipat.
        $this->assertSame(
            150_000,
            (int) OmzetReport::where('periode_bulan', 9)->where('periode_tahun', now()->year)->sum('omzet')
        );
    }

    // ==================================================================
    // D. VALIDASI DI JALUR TANPA LOGIN
    // ==================================================================

    public function test_omzet_negatif_ditolak(): void
    {
        $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => -5000,
        ])->assertSessionHasErrors('omzet');

        $this->assertSame(0, OmzetReport::count());
    }

    public function test_periode_yang_sudah_dikirim_tidak_bisa_ditimpa_diam_diam(): void
    {
        $kirim = fn (int $omzet) => $this->post(route('omzet.public.store', $this->peserta->report_token), [
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => $omzet,
        ]);

        $kirim(5_000_000)->assertSessionHasNoErrors();
        $kirim(9_000_000)->assertSessionHasErrors('periode_bulan');

        $this->assertSame(5_000_000, OmzetReport::firstOrFail()->omzet);
    }

    public function test_link_dengan_token_karangan_tidak_bisa_menulis_apa_pun(): void
    {
        $this->post(route('omzet.public.store', 'token-karangan'), [
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 1_000_000,
        ])->assertNotFound();

        $this->assertSame(0, OmzetReport::count());
    }

    // ==================================================================

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email, 'email' => $email,
            'password' => bcrypt('rahasia123'), 'role' => $role,
            'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function buatPeserta(string $nama, string $usaha, int $pendampingId): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0812' . random_int(10000000, 99999999),
            'nama_usaha' => $usaha,
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->startOfYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ])->fresh();
    }
}
