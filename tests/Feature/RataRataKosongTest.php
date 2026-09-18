<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RATA-RATA DARI NOL LAPORAN BUKAN NOL — IA TIDAK ADA.
 *
 * =====================================================================
 * BEDANYA DENGAN JUMLAH
 * =====================================================================
 *
 * Jumlah dari himpunan kosong memang nol, dan menampilkan "Rp0" di sana
 * benar. Rata-rata dari himpunan kosong TIDAK terdefinisi — menampilkannya
 * sebagai "Rp0" mengarang sebuah fakta.
 *
 * Bedanya penting karena kalimatnya dibaca sebagai pernyataan tentang orang:
 *
 *   "Rata-rata Omzet: Rp0"  -> peserta ini rata-rata tidak berpenghasilan
 *   "Rata-rata Omzet: —"    -> belum ada laporan pada filter ini
 *
 * Keadaan kosong itu sangat mudah dicapai: cukup menyaring bulan yang belum
 * terisi, atau membuka laporan peserta yang belum pernah melapor.
 *
 * KpiService::ringkasan() sejak awal sudah mengembalikan null untuk kasus
 * ini dan halamannya menampilkan "—". Enam rata-rata lain tidak mengikuti
 * pola itu; test ini menjaga supaya keduanya tetap seragam.
 */
class RataRataKosongTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@ratarata.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '081200000001',
            'email' => 'p@ratarata.test', 'status' => 'Aktif',
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(6)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);
    }

    private function laporkan(int $omzet): void
    {
        OmzetReport::create([
            'participant_id' => $this->peserta->id,
            'periode_bulan' => now()->month,
            'periode_tahun' => now()->year,
            'omzet' => $omzet,
        ]);
    }

    // =================================================================
    // TANPA DATA
    // =================================================================

    public function test_laporan_omzet_tanpa_data_menampilkan_strip_bukan_nol_rupiah(): void
    {
        $respons = $this->actingAs($this->admin)
            ->get(route('laporan.omzet', ['bulan' => 1, 'tahun' => 2020]))
            ->assertOk();

        $this->assertNull($respons->viewData('rataRata'));
        $this->assertSame(0, $respons->viewData('jumlahLaporan'));

        // Jumlah tetap boleh nol — yang tidak boleh rata-ratanya.
        $this->assertSame(0, (int) $respons->viewData('totalOmzet'));

        $respons->assertSee('—');
    }

    public function test_monitoring_omzet_tanpa_data_menampilkan_strip(): void
    {
        $summary = $this->actingAs($this->admin)
            ->get(route('omzet.index', ['bulan' => 1, 'tahun' => 2020]))
            ->assertOk()
            ->viewData('summary');

        $this->assertNull($summary['rata_rata']);
        $this->assertSame(0, $summary['jumlah_lapor']);
    }

    // =================================================================
    // DENGAN DATA
    // =================================================================

    /**
     * Kalau ada datanya, angkanya harus tetap tampil seperti biasa.
     *
     * Tanpa test ini, "—" bisa saja muncul di mana-mana karena bug lain dan
     * test di atas tetap lulus.
     */
    public function test_rata_rata_tetap_dihitung_kalau_datanya_ada(): void
    {
        $this->laporkan(1000000);

        $respons = $this->actingAs($this->admin)
            ->get(route('laporan.omzet', ['bulan' => now()->month, 'tahun' => now()->year]))
            ->assertOk();

        $this->assertSame(1000000, $respons->viewData('rataRata'));
        $this->assertSame(1, $respons->viewData('jumlahLaporan'));

        $summary = $this->actingAs($this->admin)
            ->get(route('omzet.index', ['bulan' => now()->month, 'tahun' => now()->year]))
            ->assertOk()
            ->viewData('summary');

        $this->assertSame(1000000, $summary['rata_rata']);
    }

    /**
     * Omzet nol yang BENAR-BENAR dilaporkan tetap nol, bukan "—".
     *
     * Peserta yang melapor "bulan ini saya tidak berjualan" adalah fakta,
     * dan fakta itu tidak boleh ikut disembunyikan bersama keadaan kosong.
     */
    public function test_omzet_nol_yang_dilaporkan_tetap_terbaca_nol(): void
    {
        $this->laporkan(0);

        $respons = $this->actingAs($this->admin)
            ->get(route('laporan.omzet', ['bulan' => now()->month, 'tahun' => now()->year]))
            ->assertOk();

        $this->assertSame(0, $respons->viewData('rataRata'));
        $this->assertNotNull($respons->viewData('rataRata'));
        $this->assertSame(1, $respons->viewData('jumlahLaporan'));
    }
}
