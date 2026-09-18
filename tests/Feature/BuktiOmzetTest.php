<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * BUKTI LAPORAN OMZET.
 *
 * Empat hal yang dijaga, dan tiga di antaranya adalah soal keamanan:
 *
 * 1. BERKAS TIDAK PUNYA URL PUBLIK. Satu-satunya jalan keluarnya adalah route
 *    ber-otorisasi. Foto buku kas sebuah usaha adalah data keuangan milik
 *    peserta — kalau tersimpan di storage/app/public, siapa pun yang menebak
 *    URL-nya bisa mengunduhnya tanpa login.
 *
 * 2. NAMA BERKAS TIDAK PERNAH BERASAL DARI PESERTA. Nama unggahan hanya
 *    disimpan sebagai teks; berkas fisiknya memakai nama acak.
 *
 * 3. TIPE & UKURAN DIVALIDASI DI KEDUA JALUR — termasuk jalur publik, satu-
 *    satunya endpoint tulis tanpa login.
 *
 * 4. BUKTI TIDAK HILANG TANPA JEJAK. Mengganti bukti menyimpan berkas lama;
 *    mengarsipkan laporan tidak menyentuh berkasnya sama sekali, karena
 *    laporannya masih bisa dipulihkan dan buktinya tidak bisa dibuat ulang.
 */
class BuktiOmzetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendampingA;
    private User $pendampingB;
    private User $pimpinan;
    private Participant $binaanA;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk palsu: berkas uji tidak menyentuh storage sungguhan.
        Storage::fake('local');

        $profilA = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'a@bk.test', 'status' => 'Aktif']);
        $profilB = Pendamping::create(['nama' => 'Budi', 'nomor_hp' => '0813', 'email' => 'b@bk.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@bk.test');
        $this->pendampingA = $this->user('pendamping', 'pa@bk.test', $profilA->id);
        $this->pendampingB = $this->user('pendamping', 'pb@bk.test', $profilB->id);
        $this->pimpinan = $this->user('pimpinan', 'pim@bk.test');

        $this->binaanA = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2026-01-01',
            'status' => 'Aktif',
            'pendamping_id' => $profilA->id,
        ])->fresh();
    }

    // ==================================================================
    // A. UNGGAH
    // ==================================================================

    public function test_pendamping_mengunggah_bukti_saat_mencatat_omzet(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 2_000_000,
                'bukti' => UploadedFile::fake()->image('buku-kas.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $r = OmzetReport::firstOrFail();

        $this->assertTrue($r->punyaBukti());
        $this->assertSame('buku-kas.jpg', $r->bukti_asli);
        Storage::assertExists($r->bukti_path);
    }

    public function test_peserta_mengunggah_bukti_lewat_form_publik(): void
    {
        $this->post(route('omzet.public.store', $this->binaanA->report_token), [
            'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_500_000,
            'bukti' => UploadedFile::fake()->image('nota.png'),
        ])->assertSessionHasNoErrors();

        $r = OmzetReport::firstOrFail();

        $this->assertTrue($r->punyaBukti());
        Storage::assertExists($r->bukti_path);
    }

    public function test_bukti_boleh_kosong(): void
    {
        // Mewajibkan bukti adalah cara tercepat membuat laporan omzet berhenti
        // masuk — banyak peserta tidak punya catatan tertulis sama sekali.
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 2_000_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(OmzetReport::firstOrFail()->punyaBukti());
    }

    public function test_nama_berkas_di_disk_tidak_memakai_nama_dari_peserta(): void
    {
        // Nama dari luar bisa memuat "../", karakter bermakna di shell, atau
        // ekstensi ganda. Yang tersimpan di disk harus nama buatan sistem.
        $this->post(route('omzet.public.store', $this->binaanA->report_token), [
            'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
            'bukti' => UploadedFile::fake()->image('../../etc/passwd.jpg'),
        ]);

        $r = OmzetReport::firstOrFail();

        $this->assertStringNotContainsString('..', $r->bukti_path);
        $this->assertStringNotContainsString('passwd', $r->bukti_path);
        $this->assertStringStartsWith('bukti-omzet/' . $this->binaanA->id . '/', $r->bukti_path);
    }

    public function test_bukti_disimpan_di_disk_privat_bukan_folder_publik(): void
    {
        $this->actingAs($this->admin)->post(route('omzet.store', $this->binaanA), [
            'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $r = OmzetReport::firstOrFail();

        // Disk `local` di aplikasi ini berakar di storage/app/private.
        Storage::disk('local')->assertExists($r->bukti_path);
        Storage::disk('public')->assertMissing($r->bukti_path);
    }

    // ==================================================================
    // B. VALIDASI BERKAS
    // ==================================================================

    public function test_berkas_bertipe_berbahaya_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
                'bukti' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('bukti');

        $this->assertSame(0, OmzetReport::count());
    }

    public function test_berkas_php_yang_menyamar_sebagai_gambar_tetap_ditolak(): void
    {
        // mimes memeriksa tipe berkasnya, bukan sekadar nama yang dikirim
        // peramban — ekstensi yang diganti tidak lolos.
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
                'bukti' => UploadedFile::fake()->create('gambar.jpg', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('bukti');
    }

    public function test_berkas_terlalu_besar_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
                'bukti' => UploadedFile::fake()->create('besar.jpg', OmzetReport::BUKTI_MAKS_KB + 1, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('bukti');
    }

    public function test_validasi_berkas_juga_berlaku_di_form_publik(): void
    {
        // Jalur publik adalah satu-satunya endpoint tulis tanpa login.
        // Aturannya harus sama persis, bukan sekadar mirip.
        $this->post(route('omzet.public.store', $this->binaanA->report_token), [
            'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
            'bukti' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('bukti');

        $this->assertSame(0, OmzetReport::count());
    }

    public function test_pdf_diterima_sebagai_bukti(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
                'bukti' => UploadedFile::fake()->create('rekap.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(OmzetReport::firstOrFail()->punyaBukti());
    }

    // ==================================================================
    // C. OTORISASI UNDUH
    // ==================================================================

    public function test_pendamping_pemilik_binaan_bisa_mengunduh_bukti(): void
    {
        $r = $this->laporanBerbukti();

        $this->actingAs($this->pendampingA)
            ->get(route('omzet.bukti', $r))
            ->assertOk();
    }

    public function test_pimpinan_bisa_mengunduh_bukti_read_only(): void
    {
        $r = $this->laporanBerbukti();

        $this->actingAs($this->pimpinan)->get(route('omzet.bukti', $r))->assertOk();
    }

    public function test_pendamping_lain_tidak_bisa_mengunduh_bukti_binaan_orang(): void
    {
        $r = $this->laporanBerbukti();

        $this->actingAs($this->pendampingB)
            ->get(route('omzet.bukti', $r))
            ->assertForbidden();
    }

    public function test_tamu_tanpa_login_tidak_bisa_mengunduh_bukti(): void
    {
        // Laporannya dibuat LANGSUNG lewat model, bukan lewat form.
        // actingAs() di helper laporanBerbukti() akan tetap melekat pada
        // request berikutnya dalam test yang sama, sehingga "tamu" di sini
        // sebenarnya masih login sebagai Admin — dan testnya lulus tanpa
        // membuktikan apa pun.
        $r = $this->laporanLangsung();

        $this->get(route('omzet.bukti', $r))->assertRedirect(route('login'));
    }

    public function test_laporan_tanpa_bukti_menghasilkan_404(): void
    {
        $r = OmzetReport::create([
            'participant_id' => $this->binaanA->id,
            'periode_bulan' => 9, 'periode_tahun' => 2026,
            'omzet' => 1_000_000, 'submitted_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('omzet.bukti', $r))->assertNotFound();
    }

    public function test_berkas_yang_hilang_dari_disk_menghasilkan_404_bukan_error(): void
    {
        $r = $this->laporanBerbukti();
        Storage::delete($r->bukti_path);

        $this->actingAs($this->admin)->get(route('omzet.bukti', $r))->assertNotFound();
    }

    // ==================================================================
    // D. BUKTI TIDAK HILANG TANPA JEJAK
    // ==================================================================

    public function test_mengganti_bukti_tidak_menghapus_berkas_lama(): void
    {
        $r = $this->laporanBerbukti();
        $lama = $r->bukti_path;

        $this->actingAs($this->admin)->put(route('omzet.update', $r), [
            'omzet' => 1_500_000,
            'bukti' => UploadedFile::fake()->image('bukti-baru.jpg'),
        ])->assertRedirect();

        $baru = $r->fresh()->bukti_path;

        $this->assertNotSame($lama, $baru);
        Storage::assertExists($baru);
        // Bukti adalah jejak audit atas angka yang sudah masuk laporan.
        // Kalau bisa ditimpa tanpa sisa, seseorang bisa memperbaiki angka
        // sekaligus menghapus dasar angka sebelumnya.
        Storage::assertExists($lama);
    }

    public function test_penggantian_bukti_tercatat_di_activity_log_beserta_lokasi_lamanya(): void
    {
        $r = $this->laporanBerbukti();
        $lama = $r->bukti_path;

        $this->actingAs($this->admin)->put(route('omzet.update', $r), [
            'omzet' => 1_500_000,
            'bukti' => UploadedFile::fake()->image('bukti-baru.jpg'),
        ]);

        $log = \App\Models\ActivityLog::where('action', 'omzet_updated')
            ->where('description', 'like', '%Mengganti bukti%')
            ->first();

        $this->assertNotNull($log, 'Penggantian bukti harus meninggalkan jejak.');
        $this->assertStringContainsString($lama, $log->description);
    }

    public function test_menyimpan_tanpa_berkas_tidak_menghapus_bukti_yang_sudah_ada(): void
    {
        // Request tanpa berkas berarti "tidak mengubah bukti", bukan "hapus".
        $r = $this->laporanBerbukti();
        $lama = $r->bukti_path;

        $this->actingAs($this->admin)->put(route('omzet.update', $r), ['omzet' => 1_750_000]);

        $this->assertSame($lama, $r->fresh()->bukti_path);
        $this->assertSame(1_750_000, $r->fresh()->omzet);
    }

    /**
     * Menghapus laporan ikut membuang berkas buktinya.
     *
     * Dulu berkas sengaja ditinggal supaya laporan yang diarsipkan masih bisa
     * dipulihkan lengkap dengan buktinya. Pemulihan sudah tidak ada, jadi
     * berkas yang ditinggal tidak bisa dijangkau siapa pun lagi — ia hanya
     * menumpuk di penyimpanan tanpa satu baris pun yang menunjuknya.
     */
    public function test_menghapus_laporan_ikut_membuang_berkas_buktinya(): void
    {
        $r = $this->laporanBerbukti();
        $path = $r->bukti_path;

        Storage::assertExists($path);

        $this->actingAs($this->admin)->delete(route('omzet.destroy', $r))->assertRedirect();

        $this->assertDatabaseMissing('omzet_reports', ['id' => $r->id]);
        Storage::assertMissing($path);
    }

    public function test_bukti_tidak_bisa_ditentukan_lewat_mass_assignment(): void
    {
        // bukti_path sengaja tidak fillable. Kalau bisa diisi lewat request,
        // seseorang bisa menunjuk berkas apa pun di disk lalu mengunduhnya
        // lewat route yang justru sudah ber-otorisasi.
        $this->actingAs($this->admin)->post(route('omzet.store', $this->binaanA), [
            'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
            'bukti_path' => '../../.env',
            'bukti_asli' => '.env',
        ]);

        $this->assertNull(OmzetReport::firstOrFail()->bukti_path);
    }

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

    /** Laporan berbukti TANPA menyentuh sesi — dipakai test tamu. */
    private function laporanLangsung(): OmzetReport
    {
        $r = OmzetReport::create([
            'participant_id' => $this->binaanA->id,
            'periode_bulan' => 9, 'periode_tahun' => 2026,
            'omzet' => 1_000_000, 'submitted_at' => now(),
        ]);

        Storage::put('bukti-omzet/' . $this->binaanA->id . '/uji.jpg', 'isi-berkas');
        $r->forceFill([
            'bukti_path' => 'bukti-omzet/' . $this->binaanA->id . '/uji.jpg',
            'bukti_asli' => 'uji.jpg',
        ])->save();

        return $r;
    }

    private function laporanBerbukti(): OmzetReport
    {
        $this->actingAs($this->admin)->post(route('omzet.store', $this->binaanA), [
            'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
            'bukti' => UploadedFile::fake()->image('bukti-awal.jpg'),
        ]);

        return OmzetReport::firstOrFail();
    }
}
