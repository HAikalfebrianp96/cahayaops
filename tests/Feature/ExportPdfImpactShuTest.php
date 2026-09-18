<?php

namespace Tests\Feature;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\ShuPeriod;
use App\Models\User;
use App\Services\ShuService;
use App\Services\SroiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EXPORT PDF — DAMPAK & SROI, DAN SHU.
 *
 * Yang diuji di sini bukan "PDF-nya jadi", melainkan APA YANG TERCETAK di
 * dalamnya. Sebuah berkas PDF yang berhasil diunduh tapi memuat rasio palsu
 * jauh lebih berbahaya daripada berkas yang gagal dibuat — dokumen ini dibawa
 * ke pemberi dana dan ke Rapat Anggota, dan angka yang tercetak di atas kop
 * resmi akan dibaca sebagai angka resmi.
 *
 * Karena itu isi PDF diperiksa sebagai teks mentah, bukan hanya status 200.
 */
class ExportPdfImpactShuTest extends TestCase
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

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@pdf.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@pdf.test');
        $this->pimpinan = $this->user('pimpinan', 'pim@pdf.test');
        $this->pendamping = $this->user('pendamping', 'pd@pdf.test', $profil->id);
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@pdf.test');

        $this->program = ImpactProgram::create([
            'nama' => 'Pemberdayaan UMKM 2026',
            'periode_mulai' => '2026-01-01',
            'periode_selesai' => '2026-12-31',
            'status' => ImpactProgram::STATUS_DRAFT,
        ]);
    }

    // ==================================================================
    // A. PDF IMPACT & SROI — PENJAGA DATA BELUM CUKUP
    // ==================================================================

    public function test_pdf_program_kosong_menyatakan_belum_cukup_data_tanpa_rasio(): void
    {
        $isi = $this->unduhPdfImpact($this->admin);

        $this->assertStringContainsString('SROI BELUM DAPAT DITERBITKAN', $isi);
        $this->assertStringContainsString('Belum Ada Data', $isi);

        // Yang paling penting: TIDAK ADA angka rasio di dokumen ini.
        $this->assertStringNotContainsString('SROI Ratio', $isi);
        $this->assertStringNotContainsString('Net Social Value', $isi);
    }

    public function test_pdf_data_belum_lengkap_mencetak_daftar_kekurangan_bukan_estimasi(): void
    {
        // Investasi ada, outcome ada, tapi belum ada peserta terukur.
        $this->investasi(10_000_000);
        $this->outcome($this->proxy());

        $isi = $this->unduhPdfImpact($this->admin);

        $this->assertStringContainsString('SROI BELUM DAPAT DITERBITKAN', $isi);
        $this->assertStringContainsString('Data Belum Lengkap', $isi);
        $this->assertStringContainsString('belum punya angka perubahan', $isi);
        $this->assertStringNotContainsString('SROI Ratio', $isi);
    }

    public function test_pdf_tetap_mencetak_investasi_walau_sroi_belum_bisa_diterbitkan(): void
    {
        // Dokumennya harus tetap berguna sebagai daftar kerja, bukan halaman
        // kosong berisi penolakan.
        $this->investasi(10_000_000, 'Pelatihan pembukuan');

        $isi = $this->unduhPdfImpact($this->admin);

        $this->assertStringContainsString('Pelatihan pembukuan', $isi);
        $this->assertStringContainsString('TOTAL INVESTASI', $isi);
    }

    public function test_pdf_lengkap_mencetak_seluruh_komponen_wajib(): void
    {
        $this->siapkanProgramLengkap();
        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $isi = $this->unduhPdfImpact($this->admin);

        // Identitas & periode
        $this->assertStringContainsString('Pemberdayaan UMKM 2026', $isi);
        // Investment
        $this->assertStringContainsString('TOTAL INVESTASI', $isi);
        // Output & outcome
        $this->assertStringContainsString('Output', $isi);
        $this->assertStringContainsString('Peserta Terukur', $isi);
        // Proxy & keempat adjustment
        $this->assertStringContainsString('Financial Proxy', $isi);
        $this->assertStringContainsString('weight', $isi);       // Deadweight (dipecah <br> di kop tabel)
        $this->assertStringContainsString('bution', $isi);       // Attribution
        $this->assertStringContainsString('ment', $isi);         // Displacement
        $this->assertStringContainsString('off', $isi);          // Drop-off
        // Hasil
        $this->assertStringContainsString('Gross Social Value', $isi);
        $this->assertStringContainsString('Net Social Value', $isi);
        $this->assertStringContainsString('SROI Ratio', $isi);
        // Sensitivitas & status & evidence
        $this->assertStringContainsString('Sensitivity Analysis', $isi);
        $this->assertStringContainsString('Konservatif', $isi);
        $this->assertStringContainsString('Optimistis', $isi);
        $this->assertStringContainsString($hasil->status, $isi);
        $this->assertStringContainsString('Evidence', $isi);
    }

    public function test_angka_pdf_berasal_dari_database_bukan_hard_code(): void
    {
        $this->siapkanProgramLengkap();
        $hasil = app(SroiService::class)->hitung($this->program->fresh());

        $isi = $this->unduhPdfImpact($this->admin);

        // Rasio yang tercetak harus sama persis dengan yang tersimpan.
        $this->assertStringContainsString(number_format($hasil->rasio, 2, ',', '.'), $isi);
        $this->assertStringContainsString(number_format($hasil->total_investasi, 0, ',', '.'), $isi);
    }

    public function test_pdf_menyebutkan_sumber_proxy_yang_dipakai(): void
    {
        $this->siapkanProgramLengkap();
        app(SroiService::class)->hitung($this->program->fresh());

        $isi = $this->unduhPdfImpact($this->admin);

        // Proxy tanpa sumber tidak bisa diperiksa siapa pun — sumbernya harus
        // ikut tercetak, bukan hanya angkanya.
        $this->assertStringContainsString('Dokumen Uji', $isi);
        $this->assertStringContainsString('2026', $isi);
    }

    // ==================================================================
    // B. OTORISASI EXPORT IMPACT
    // ==================================================================

    public function test_pimpinan_boleh_mengunduh_pdf_sroi(): void
    {
        // Pimpinan-lah yang paling sering membawa dokumen ini keluar.
        $this->actingAs($this->pimpinan)
            ->get(route('impact.export-pdf', $this->program))
            ->assertOk();
    }

    public function test_pendamping_dan_tim_koperasi_tidak_bisa_mengunduh_pdf_sroi(): void
    {
        foreach ([$this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)
                ->get(route('impact.export-pdf', $this->program))
                ->assertForbidden();
        }
    }

    public function test_tamu_tidak_bisa_mengunduh_pdf_sroi(): void
    {
        $this->get(route('impact.export-pdf', $this->program))->assertRedirect(route('login'));
    }

    public function test_tombol_export_pdf_ada_di_halaman_program(): void
    {
        $this->actingAs($this->admin)
            ->get(route('impact.show', $this->program))
            ->assertOk()
            ->assertSee(route('impact.export-pdf', $this->program), false)
            ->assertSee('Export PDF');
    }

    // ==================================================================
    // C. PDF SHU
    // ==================================================================

    public function test_pdf_shu_mencetak_seluruh_komponen_wajib(): void
    {
        $periode = $this->siapkanShu();

        $isi = $this->unduhPdfShu($this->admin, $periode);

        $this->assertStringContainsString('Sisa Hasil Usaha', $isi);
        $this->assertStringContainsString('Tahun Buku', $isi);
        $this->assertStringContainsString('2026', $isi);
        // Total SHU & sumbernya
        $this->assertStringContainsString(number_format(4_000_000, 0, ',', '.'), $isi);
        // Dasar pembagian
        $this->assertStringContainsString('Dasar Pembagian', $isi);
        $this->assertStringContainsString('Jasa Modal', $isi);
        // Daftar anggota & nominalnya
        $this->assertStringContainsString('Pembagian per Anggota', $isi);
        $this->assertStringContainsString('Anggota A', $isi);
        // Nomor anggota DIGENERATE sistem (KOP-tahun-urut), bukan diisi form —
        // jadi yang diperiksa nilai yang benar-benar tersimpan, bukan nilai
        // yang dikira test. Ini sekaligus membuktikan PDF-nya membaca dari
        // basis data, bukan dari angka yang ditulis di template.
        $this->assertStringContainsString(
            $periode->allocations->first()->member->nomor_anggota,
            $isi
        );
        // Total pembagian & status
        $this->assertStringContainsString('TOTAL DIBAGIKAN KE ANGGOTA', $isi);
        $this->assertStringContainsString($periode->status, $isi);
    }

    public function test_nominal_per_anggota_di_pdf_sama_dengan_yang_tersimpan(): void
    {
        $periode = $this->siapkanShu();

        $alokasi = $periode->allocations()->orderByDesc('total')->first();
        $isi = $this->unduhPdfShu($this->admin, $periode);

        $this->assertStringContainsString(number_format($alokasi->total, 0, ',', '.'), $isi);
        $this->assertStringContainsString(number_format($alokasi->dasar_simpanan, 0, ',', '.'), $isi);
    }

    public function test_pdf_shu_ikut_mencetak_peringatan(): void
    {
        // Dokumen ini ditandatangani di Rapat Anggota. Kalau persentasenya
        // bukan 100%, yang mengesahkan berhak melihatnya di kertas yang
        // sedang ia tandatangani — bukan hanya di layar penyusunnya.
        $periode = ShuPeriod::create([
            'tahun' => 2026,
            'tanggal_penetapan' => '2027-01-15',
            'sumber' => ShuPeriod::SUMBER_MANUAL,
            'total_shu' => 1_000_000,
            'komponen' => [['nama' => 'Cadangan', 'persen' => 40, 'ke_anggota' => false, 'dasar' => null]],
        ]);

        $isi = $this->unduhPdfShu($this->admin, $periode);

        $this->assertStringContainsString('Perlu diperhatikan', $isi);
        $this->assertStringContainsString('bukan 100%', $isi);
    }

    public function test_tim_koperasi_boleh_mengunduh_pdf_shu(): void
    {
        $periode = $this->siapkanShu();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.shu.export-pdf', $periode))
            ->assertOk();
    }

    public function test_pendamping_tidak_bisa_mengunduh_pdf_shu(): void
    {
        $periode = $this->siapkanShu();

        $this->actingAs($this->pendamping)
            ->get(route('koperasi.shu.export-pdf', $periode))
            ->assertForbidden();
    }

    public function test_tamu_tidak_bisa_mengunduh_pdf_shu(): void
    {
        $periode = $this->siapkanShu();

        $this->get(route('koperasi.shu.export-pdf', $periode))->assertRedirect(route('login'));
    }

    public function test_tombol_export_pdf_ada_di_halaman_shu(): void
    {
        $periode = $this->siapkanShu();

        $this->actingAs($this->admin)
            ->get(route('koperasi.shu.show', $periode))
            ->assertOk()
            ->assertSee(route('koperasi.shu.export-pdf', $periode), false)
            ->assertSee('Export PDF');
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

    /**
     * ISI laporan sebagaimana ia akan tercetak.
     *
     * Blade-nya dirender langsung, bukan byte PDF-nya, karena keluaran dompdf
     * terkompresi — teks di dalamnya tidak bisa dibaca dari berkas jadi.
     * Yang dirender adalah template DAN payload yang PERSIS SAMA dengan yang
     * dipakai route-nya (ImpactProgramController::dataPdf), jadi yang diuji
     * tetap dokumen sungguhan, bukan tiruannya.
     *
     * Route-nya sendiri diuji terpisah untuk status & otorisasi.
     */
    private function isiPdfImpact(): string
    {
        $data = app(\App\Http\Controllers\ImpactProgramController::class)
            ->dataPdf($this->program->fresh());

        return view('impact.pdf.sroi', $data)->render();
    }

    private function isiPdfShu(ShuPeriod $periode): string
    {
        $data = app(\App\Http\Controllers\ShuController::class)->dataPdf($periode->fresh());

        return view('koperasi.shu.pdf.shu', $data)->render();
    }

    /** Berkas PDF sungguhan — dipakai menguji status & otorisasi route. */
    private function unduhPdfImpact(User $user): string
    {
        $this->actingAs($user)
            ->get(route('impact.export-pdf', $this->program))
            ->assertOk();

        return $this->isiPdfImpact();
    }

    private function unduhPdfShu(User $user, ShuPeriod $periode): string
    {
        $this->actingAs($user)
            ->get(route('koperasi.shu.export-pdf', $periode))
            ->assertOk();

        return $this->isiPdfShu($periode);
    }

    private function proxy(int $nilai = 1): FinancialProxy
    {
        return FinancialProxy::create([
            'nama' => 'Proxy Uji',
            'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => $nilai,
            'sumber' => 'Dokumen Uji',
            'tahun' => 2026,
        ]);
    }

    private function investasi(int $nominal, string $uraian = 'Pelatihan uji'): void
    {
        $this->program->investments()->create([
            'kategori' => 'Pelatihan',
            'uraian' => $uraian,
            'tanggal' => '2026-02-01',
            'nominal' => $nominal,
            'jenis' => 'Tunai',
        ]);
    }

    private function outcome(?FinancialProxy $proxy): ImpactOutcome
    {
        return $this->program->outcomes()->create([
            'nama' => 'Pendapatan usaha meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'financial_proxy_id' => $proxy?->id,
            'deadweight_persen' => 25,
            'attribution_persen' => 80,
            'displacement_persen' => 10,
            'bukti' => 'Laporan omzet peserta Jan-Des 2026',
        ]);
    }

    private function siapkanProgramLengkap(): void
    {
        $p = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2026-01-15',
            'status' => 'Aktif',
        ]);

        $p->measurements()->create(['jenis' => ParticipantMeasurement::BASELINE, 'tanggal' => '2026-01-20', 'omzet_bulanan' => 1_000_000]);
        $p->measurements()->create(['jenis' => ParticipantMeasurement::ENDLINE, 'tanggal' => '2026-12-20', 'omzet_bulanan' => 2_000_000]);

        $this->investasi(6_000_000);
        $this->outcome($this->proxy());
    }

    private function siapkanShu(): ShuPeriod
    {
        // nomor_anggota sengaja TIDAK diisi: modelnya membangkitkan sendiri
        // (KOP-tahun-urut) dan kolomnya memang tidak fillable.
        $a = KoperasiMember::create([
            'nama' => 'Anggota A', 'nomor_hp' => '0800',
            'tanggal_bergabung' => '2025-01-01', 'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $a->id, 'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-03-01', 'nominal' => 300_000, 'status' => MemberSaving::STATUS_TERCATAT,
        ]);

        $periode = ShuPeriod::create([
            'tahun' => 2026,
            'tanggal_penetapan' => '2027-01-15',
            'sumber' => ShuPeriod::SUMBER_MANUAL,
            'total_shu' => 4_000_000,
            'komponen' => [
                ['nama' => 'Cadangan Koperasi', 'persen' => 75, 'ke_anggota' => false, 'dasar' => null],
                ['nama' => 'Jasa Modal', 'persen' => 25, 'ke_anggota' => true, 'dasar' => 'simpanan'],
            ],
        ]);

        app(ShuService::class)->susunAlokasi($periode);

        return $periode->fresh();
    }
}
