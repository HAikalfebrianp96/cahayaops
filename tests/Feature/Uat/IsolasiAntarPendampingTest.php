<?php

namespace Tests\Feature\Uat;

use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SESAMA PENDAMPING TIDAK BOLEH SALING MEMBACA BINAAN.
 *
 * =====================================================================
 * YANG BELUM DIJAGA SEBELUM INI
 * =====================================================================
 *
 * Matriks hak akses sudah menguji batas ANTAR PERAN dengan menyeluruh:
 * Pendamping tidak bisa masuk modul koperasi, tidak bisa mengangkat dirinya
 * jadi admin, tamu ditolak, akun nonaktif ditolak.
 *
 * Yang tidak pernah diuji adalah batas DI DALAM satu peran. Dua pendamping
 * punya hak yang sama persis di mata sistem; yang membedakan hanya binaan
 * siapa yang boleh mereka sentuh. Batas itu tidak dijaga oleh role, melainkan
 * oleh pemeriksaan kepemilikan di tiap jalur — dan jalur yang terlewat tidak
 * akan pernah menampakkan diri lewat error.
 *
 * Data peserta di sini bukan data biasa: nomor telepon, alamat rumah, omzet
 * usaha, dan dokumen legalitas milik pelaku UMKM sungguhan.
 *
 * Test ini BERUSAHA menembus, bukan sekadar memastikan halaman terbuka untuk
 * yang berhak.
 */
class IsolasiAntarPendampingTest extends TestCase
{
    use RefreshDatabase;

    private User $rosa;
    private User $dewi;
    private Participant $binaanDewi;
    private LegalitasRecord $dokumenDewi;
    private PendampinganRecord $catatanDewi;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $profilRosa = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@isolasi.test', 'status' => 'Aktif',
        ]);

        $profilDewi = Pendamping::create([
            'nama' => 'Dewi', 'nomor_hp' => '081200000002',
            'email' => 'dewi@isolasi.test', 'status' => 'Aktif',
        ]);

        $this->rosa = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@isolasi.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $profilRosa->id, 'is_active' => true,
        ]);

        $this->dewi = User::create([
            'name' => 'Dewi', 'email' => 'dewi.akun@isolasi.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $profilDewi->id, 'is_active' => true,
        ]);

        // Binaan milik DEWI — Rosa tidak boleh menyentuhnya sama sekali.
        $this->binaanDewi = Participant::create([
            'nama_peserta' => 'Binaan Dewi', 'nomor_hp' => '081200000003',
            'alamat' => 'Alamat rumah rahasia', 'nama_usaha' => 'Usaha Dewi',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(3)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profilDewi->id,
        ]);

        $this->dokumenDewi = LegalitasRecord::create([
            'participant_id' => $this->binaanDewi->id,
            'jenis_dokumen' => 'NIB',
            'status' => LegalitasRecord::STATUS_SUDAH_ADA,
            'file_path' => UploadedFile::fake()->create('nib.pdf', 10, 'application/pdf')
                ->store('legalitas/' . $this->binaanDewi->id),
            'file_asli' => 'nib.pdf',
        ]);

        $this->catatanDewi = PendampinganRecord::create([
            'participant_id' => $this->binaanDewi->id,
            'pendamping_id' => $profilDewi->id,
            'tanggal' => now()->subDays(5)->toDateString(),
            'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
            'materi' => 'Catatan milik Dewi',
        ]);
    }

    // =================================================================
    // MEMBACA
    // =================================================================

    public function test_pendamping_tidak_bisa_membuka_detail_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->rosa)
            ->get(route('participants.show', $this->binaanDewi))
            ->assertForbidden();
    }

    public function test_pendamping_tidak_bisa_mengunduh_dokumen_binaan_pendamping_lain(): void
    {
        // Dokumen legalitas memuat data identitas usaha. Pemilik sahnya bisa
        // membukanya; yang lain tidak, walau tahu id-nya.
        $this->actingAs($this->dewi)
            ->get(route('legalitas.download', $this->dokumenDewi))
            ->assertOk();

        $this->actingAs($this->rosa)
            ->get(route('legalitas.download', $this->dokumenDewi))
            ->assertForbidden();
    }

    public function test_pendamping_tidak_bisa_membuka_perkembangan_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->rosa)
            ->get(route('perkembangan.index', $this->binaanDewi))
            ->assertForbidden();
    }

    /**
     * Filter di URL tidak boleh dipakai mengintip binaan orang lain.
     *
     * Daftar Tugas Saya dan Prioritas menerima ?pendamping_id untuk Admin.
     * Untuk Pendamping, parameter itu harus DIABAIKAN — bukan dihormati.
     */
    public function test_parameter_filter_tidak_membocorkan_binaan_pendamping_lain(): void
    {
        foreach ([
            route('follow-up.index', ['pendamping_id' => $this->dewi->pendamping_id]),
            route('prioritas.index', ['pendamping_id' => $this->dewi->pendamping_id]),
        ] as $url) {
            $kelompok = $this->actingAs($this->rosa)
                ->get($url)
                ->assertOk()
                ->viewData('kelompok');

            $ditemukan = collect($kelompok)->flatten(1)
                ->contains(fn ($b) => $b['peserta']->id === $this->binaanDewi->id);

            $this->assertFalse($ditemukan, "Binaan pendamping lain bocor lewat {$url}");
        }
    }

    /**
     * KPI orang lain bukan urusan sesama pendamping.
     */
    public function test_pendamping_tidak_bisa_membuka_kpi_pendamping_lain(): void
    {
        $this->actingAs($this->rosa)
            ->get(route('kpi-pendamping.show', $this->dewi->pendamping_id))
            ->assertForbidden();
    }

    // =================================================================
    // MENULIS
    // =================================================================

    public function test_pendamping_tidak_bisa_mengubah_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->rosa)
            ->put(route('participants.update', $this->binaanDewi), [
                'nama_peserta' => 'Diubah Rosa',
                'nomor_hp' => '081299999999',
                'alamat' => 'Alamat baru',
                'nama_usaha' => 'Usaha diubah',
                'kategori_usaha' => 'Jasa',
                'tanggal_mulai_program' => now()->toDateString(),
                'status' => 'Aktif',
            ])
            ->assertForbidden();

        $this->assertSame('Binaan Dewi', $this->binaanDewi->fresh()->nama_peserta);
    }

    public function test_pendamping_tidak_bisa_mencatat_pendampingan_untuk_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->rosa)
            ->post(route('pendampingan.store', $this->binaanDewi), [
                'tanggal' => now()->toDateString(),
                'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
                'materi' => 'Disisipkan Rosa',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('pendampingan_records', ['materi' => 'Disisipkan Rosa']);
    }

    public function test_pendamping_tidak_bisa_menghapus_catatan_pendamping_lain(): void
    {
        $this->actingAs($this->rosa)
            ->delete(route('pendampingan.destroy', $this->catatanDewi))
            ->assertForbidden();

        $this->assertDatabaseHas('pendampingan_records', [
            'id' => $this->catatanDewi->id,
            'deleted_at' => null,
        ]);
    }

    // =================================================================
    // KONTROL PEMBANDING
    // =================================================================

    /**
     * Rosa HARUS bisa melakukan semuanya pada binaannya sendiri.
     *
     * Tanpa test ini, seluruh test penolakan di atas bisa lulus karena
     * alasan yang sama sekali salah — misalnya kalau ada bug yang membuat
     * setiap pendamping ditolak di mana-mana. Penolakan baru berarti kalau
     * yang berhak memang diterima.
     */
    public function test_pendamping_tetap_bisa_mengerjakan_binaannya_sendiri(): void
    {
        $binaanRosa = Participant::create([
            'nama_peserta' => 'Binaan Rosa', 'nomor_hp' => '081200000005',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Rosa',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonth()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->rosa->pendamping_id,
        ]);

        $this->actingAs($this->rosa)
            ->get(route('participants.show', $binaanRosa))
            ->assertOk();

        $this->actingAs($this->rosa)
            ->get(route('perkembangan.index', $binaanRosa))
            ->assertOk();

        $this->actingAs($this->rosa)
            ->get(route('kpi-pendamping.show', $this->rosa->pendamping_id))
            ->assertOk();

        $this->actingAs($this->rosa)
            ->post(route('pendampingan.store', $binaanRosa), [
                'tanggal' => now()->toDateString(),
                'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
                'materi' => 'Catatan sah milik Rosa',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pendampingan_records', ['materi' => 'Catatan sah milik Rosa']);
    }

    // =================================================================
    // UNGGAHAN BERBAHAYA
    // =================================================================

    /**
     * Berkas yang bisa dieksekusi harus ditolak, apa pun namanya.
     *
     * Dua bentuk diuji: .php terang-terangan, dan .php yang menyamar dengan
     * tipe PDF di header-nya. Validasi `mimes:` memeriksa isi berkas, bukan
     * sekadar nama — test ini memastikan itu memang berlaku.
     */
    public function test_berkas_yang_bisa_dieksekusi_ditolak(): void
    {
        $peserta = Participant::create([
            'nama_peserta' => 'Binaan Rosa', 'nomor_hp' => '081200000004',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Rosa',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonth()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->rosa->pendamping_id,
        ]);

        foreach ([
            UploadedFile::fake()->create('jahat.php', 10, 'application/x-httpd-php'),
            UploadedFile::fake()->create('jahat.php', 10, 'application/pdf'),
        ] as $berkas) {
            $this->actingAs($this->rosa)
                ->post(route('legalitas.store', $peserta), [
                    'jenis_dokumen' => 'NIB',
                    'status' => LegalitasRecord::STATUS_BELUM_ADA,
                    'file' => $berkas,
                ])
                ->assertSessionHasErrors('file');
        }

        $this->assertSame(0, LegalitasRecord::where('participant_id', $peserta->id)->count());
    }
}
