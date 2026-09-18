<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PEMERIKSA KEWAJARAN DATA HARUS MENEMUKAN — DAN TIDAK BOLEH MENYENTUH.
 *
 * =====================================================================
 * DUA HAL YANG DIJAGA
 * =====================================================================
 *
 * 1. Ia benar-benar menemukan kejanggalan. Pemeriksa yang selalu berkata
 *    "bersih" lebih berbahaya daripada tidak ada pemeriksa sama sekali,
 *    karena ia memberi rasa aman yang tidak berdasar.
 *
 * 2. Ia TIDAK mengubah apa pun. Perintah yang dijalankan orang untuk
 *    "memeriksa" tidak boleh sekali-kali memperbaiki sendiri: sebagian
 *    kejanggalan ternyata wajar begitu dilihat isinya, dan perbaikan
 *    otomatis akan membuang data sungguhan.
 */
class PeriksaKewajaranDataTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profil;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        // ============================================================
        // STORAGE DIPALSUKAN — kalau tidak, test ini membaca berkas
        // SUNGGUHAN milik mesin yang menjalankannya.
        //
        // Perintahnya memang harus membaca storage nyata; itu tugasnya.
        // Tapi database test selalu kosong, sehingga setiap dokumen
        // legalitas yang benar-benar ada di disk terbaca sebagai "yatim"
        // — dan test-nya lulus atau gagal tergantung isi komputer siapa
        // yang menjalankannya.
        // ============================================================
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->profil = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001',
            'email' => 'rosa@periksa.test', 'status' => 'Aktif',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function peserta(array $ubah = []): Participant
    {
        return Participant::create(array_merge([
            'nama_peserta' => 'Peserta Wajar',
            'nomor_hp' => '0812' . random_int(10000000, 99999999),
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ], $ubah));
    }

    /**
     * Keluaran ditangkap sendiri, bukan lewat expectsOutputToContain().
     *
     * Beberapa versi Laravel memperlakukan ekspektasi beruntun sebagai
     * URUTAN yang harus dikonsumsi, sehingga dua ekspektasi pada satu
     * keluaran bisa gagal walau kedua frasanya jelas-jelas ada. Menangkap
     * keluarannya membuat yang diuji isinya, bukan tata cara pemanggilannya.
     */
    private function keluaran(): string
    {
        \Illuminate\Support\Facades\Artisan::call('cahayaos:periksa-data');

        return \Illuminate\Support\Facades\Artisan::output();
    }

    public function test_data_wajar_dilaporkan_bersih(): void
    {
        $this->peserta();

        $keluaran = $this->keluaran();

        $this->assertStringContainsString('Tidak ada yang perlu diperiksa.', $keluaran);
    }

    public function test_peserta_tanpa_pendamping_ditemukan(): void
    {
        $this->peserta(['pendamping_id' => null]);

        $keluaran = $this->keluaran();

        $this->assertStringContainsString('Peserta tanpa pendamping', $keluaran);
        $this->assertStringContainsString('daftar kerja siapa pun', $keluaran);
    }

    public function test_tanggal_masa_depan_ditemukan(): void
    {
        $this->peserta(['tanggal_mulai_program' => '2027-01-10']);

        $keluaran = $this->keluaran();

        $this->assertStringContainsString('Peserta mulai program di masa depan', $keluaran);
    }

    public function test_hpp_melebihi_omzet_ditemukan(): void
    {
        $peserta = $this->peserta();

        // Dibuat lewat query langsung: validasi form memang menolaknya,
        // jadi baris semacam ini hanya bisa lahir dari data lama atau impor.
        DB::table('omzet_reports')->insert([
            'participant_id' => $peserta->id,
            'periode_bulan' => 6, 'periode_tahun' => 2026,
            'omzet' => 1000000, 'hpp' => 1500000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $keluaran = $this->keluaran();

        $this->assertStringContainsString('HPP melebihi omzet', $keluaran);
    }

    public function test_nama_sisa_uji_coba_ditemukan(): void
    {
        Customer::create(['nama' => 'dsvbdfbfdb', 'nomor_hp' => 'bngfnnn']);

        $keluaran = $this->keluaran();

        $this->assertStringContainsString('dsvbdfbfdb', $keluaran);
    }

    /**
     * Nama Indonesia yang wajar TIDAK boleh ikut ditandai.
     *
     * Pemeriksa yang berisik akan dimatikan orang, dan begitu dimatikan ia
     * berhenti menolong sama sekali.
     */
    public function test_nama_wajar_tidak_ikut_ditandai(): void
    {
        foreach ([
            'Siti Azizah', 'Efi Zahara', 'Arifatunnisa', 'Rosa Linda',
            'Warung Bu Sri', 'Toko Berkah Jaya', 'Nur Aini', 'Abdul Rahman',
        ] as $nama) {
            Customer::create(['nama' => $nama, 'nomor_hp' => '0813' . random_int(10000000, 99999999)]);
        }

        $keluaran = $this->keluaran();

        $this->assertStringNotContainsString('Siti Azizah', $keluaran);
        $this->assertStringNotContainsString('Arifatunnisa', $keluaran);
        $this->assertStringNotContainsString('Toko Berkah Jaya', $keluaran);
    }

    /**
     * Berbagi nomor telepon dilaporkan, tapi disebut sebagai hal yang
     * sering kali wajar — bukan sebagai kesalahan.
     */
    public function test_nomor_hp_yang_dipakai_bersama_disebut_wajar(): void
    {
        $this->peserta(['nama_peserta' => 'Siti Azizah', 'nomor_hp' => '083862353779']);
        $this->peserta(['nama_peserta' => 'Efi Zahara', 'nomor_hp' => '083862353779']);

        $keluaran = $this->keluaran();

        $this->assertStringContainsString('Peserta berbagi nomor HP', $keluaran);
        $this->assertStringContainsString('WAJAR', $keluaran);
    }

    /**
     * Inti kedua: menjalankan pemeriksa tidak boleh mengubah satu baris pun.
     */
    public function test_pemeriksaan_tidak_mengubah_data_apa_pun(): void
    {
        $peserta = $this->peserta(['pendamping_id' => null, 'tanggal_mulai_program' => '2027-01-10']);
        Customer::create(['nama' => 'dsvbdfbfdb', 'nomor_hp' => 'bngfnnn']);

        PendampinganRecord::create([
            'participant_id' => $peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => '2026-06-15',
            'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
            'materi' => 'Materi uji',
        ]);

        $sebelum = [
            'participants' => Participant::count(),
            'customers' => Customer::count(),
            'pendampingan' => PendampinganRecord::count(),
            'omzet' => OmzetReport::count(),
            'nama' => $peserta->nama_peserta,
            'diubah' => $peserta->updated_at?->toDateTimeString(),
        ];

        $this->keluaran();

        $this->assertSame($sebelum['participants'], Participant::count());
        $this->assertSame($sebelum['customers'], Customer::count());
        $this->assertSame($sebelum['pendampingan'], PendampinganRecord::count());
        $this->assertSame($sebelum['omzet'], OmzetReport::count());

        $segar = $peserta->fresh();
        $this->assertSame($sebelum['nama'], $segar->nama_peserta);
        $this->assertSame(
            $sebelum['diubah'],
            $segar->updated_at?->toDateTimeString(),
            'Pemeriksa menyentuh baris yang seharusnya hanya dibaca.'
        );
    }
}
