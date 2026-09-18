<?php

namespace Tests\Feature;

use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\Pengeluaran;
use App\Services\PenghapusanPermanenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * BERKAS UNGGAHAN HARUS IKUT HILANG SAAT DATANYA DIHAPUS PERMANEN.
 *
 * =====================================================================
 * CELAH YANG DITUTUP
 * =====================================================================
 *
 * Pembersihan berkas dulu memeriksa JENIS MODEL:
 *
 *     if ($model instanceof LegalitasRecord && $model->file_path)
 *
 * Padahal Pengeluaran juga bisa dihapus permanen lewat alur yang sama dan
 * juga menyimpan berkas (bukti_path). Struk belanjanya tertinggal di disk
 * selamanya, tanpa satu pun baris yang menunjuknya lagi — dan tidak ada
 * error apa pun yang muncul.
 *
 * Sekarang disapu berdasarkan NAMA KOLOM, sehingga jenis unggahan yang
 * menyusul ikut terurus tanpa perlu ada yang mengingat tempat ini.
 *
 * =====================================================================
 * YANG TETAP TIDAK BOLEH TERJADI
 * =====================================================================
 *
 * Berkas TIDAK boleh hilang saat data sekadar diarsipkan. Pengarsipan
 * harus bisa dibatalkan, dan itu mustahil kalau dokumennya sudah lenyap.
 */
class BerkasUnggahanTest extends TestCase
{
    use RefreshDatabase;

    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@berkas.test', 'status' => 'Aktif',
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(3)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);
    }

    private function dokumenLegalitas(): LegalitasRecord
    {
        return LegalitasRecord::create([
            'participant_id' => $this->peserta->id,
            'jenis_dokumen' => 'NIB',
            'status' => LegalitasRecord::STATUS_SUDAH_ADA,
            'file_path' => UploadedFile::fake()->create('nib.pdf', 10, 'application/pdf')
                ->store('legalitas/' . $this->peserta->id),
            'file_asli' => 'nib.pdf',
        ]);
    }

    private function pengeluaranBerbukti(): Pengeluaran
    {
        return Pengeluaran::create([
            'tanggal' => now()->subMonth()->toDateString(),
            'kategori' => 'Operasional',
            'nominal' => 250000,
            'status' => Pengeluaran::STATUS_DIBATALKAN,
            'bukti_path' => UploadedFile::fake()->create('struk.jpg', 10, 'image/jpeg')
                ->store('bukti'),
        ]);
    }

    // =================================================================
    // HAPUS PERMANEN
    // =================================================================

    public function test_berkas_legalitas_ikut_hilang_saat_dihapus_permanen(): void
    {
        $dokumen = $this->dokumenLegalitas();
        $path = $dokumen->file_path;

        Storage::assertExists($path);

        $dokumen->delete();
        app(PenghapusanPermanenService::class)->hapus($dokumen->fresh());

        Storage::assertMissing($path);
    }

    /**
     * Inti celahnya: bukti pengeluaran dulu TERTINGGAL.
     */
    public function test_bukti_pengeluaran_ikut_hilang_saat_dihapus_permanen(): void
    {
        $pengeluaran = $this->pengeluaranBerbukti();
        $path = $pengeluaran->bukti_path;

        Storage::assertExists($path);

        app(PenghapusanPermanenService::class)->hapus($pengeluaran);

        Storage::assertMissing($path);
    }

    // =================================================================
    // PENGARSIPAN — BERKAS HARUS TETAP ADA
    // =================================================================

    /**
     * Diarsipkan bukan dihapus. Kalau berkasnya ikut hilang di sini,
     * pemulihan tidak akan pernah bisa mengembalikan dokumennya.
     */
    public function test_berkas_tetap_ada_saat_data_hanya_diarsipkan(): void
    {
        $dokumen = $this->dokumenLegalitas();
        $path = $dokumen->file_path;

        $dokumen->delete();

        Storage::assertExists($path);

        $dokumen->restore();

        Storage::assertExists($path);
        $this->assertSame($path, $dokumen->fresh()->file_path);
    }

    // =================================================================
    // PEMERIKSA KEWAJARAN DATA
    // =================================================================

    public function test_pemeriksa_menemukan_baris_yang_berkasnya_hilang(): void
    {
        $dokumen = $this->dokumenLegalitas();

        // Berkasnya hilang dari disk, barisnya tetap ada — persis keadaan
        // yang tidak menimbulkan error apa pun.
        Storage::delete($dokumen->file_path);

        \Illuminate\Support\Facades\Artisan::call('cahayaos:periksa-data');
        $keluaran = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('Baris menunjuk berkas yang hilang', $keluaran);
        $this->assertStringContainsString('tetap dihitung', $keluaran);
    }

    public function test_pemeriksa_menemukan_berkas_yatim(): void
    {
        UploadedFile::fake()->create('nyasar.pdf', 5, 'application/pdf')
            ->store('legalitas/' . $this->peserta->id);

        \Illuminate\Support\Facades\Artisan::call('cahayaos:periksa-data');
        $keluaran = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('Berkas tidak ditunjuk baris mana pun', $keluaran);
        $this->assertStringContainsString('dokumen pribadi peserta', $keluaran);
    }

    public function test_berkas_yang_cocok_dilaporkan_bersih(): void
    {
        $this->dokumenLegalitas();

        \Illuminate\Support\Facades\Artisan::call('cahayaos:periksa-data');
        $keluaran = \Illuminate\Support\Facades\Artisan::output();

        // Label bagiannya SELALU tercetak; yang membedakan keadaan bersih
        // adalah kata "bersih" di sebelahnya dan tidak adanya daftar temuan.
        $this->assertStringContainsString('BERKAS UNGGAHAN', $keluaran);
        $this->assertStringContainsString('Tidak ada yang perlu diperiksa.', $keluaran);
    }
}
