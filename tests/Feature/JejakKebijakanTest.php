<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\FinancialProxy;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ATURAN YANG DIUBAH HARUS BISA DITELUSURI, BUKAN CUMA DATANYA.
 *
 * =====================================================================
 * CELAH YANG DITUTUP
 * =====================================================================
 *
 * Seluruh pencatatan jejak selama ini menyasar DATA: peserta, catatan
 * pendampingan, omzet, simpanan, penjualan. Tidak satu pun menyasar ATURAN
 * yang menentukan arti data itu.
 *
 * Padahal menggeser ambang "terlambat" dari 45 ke 90 hari mengubah status
 * SELURUH peserta dan isi daftar kerja SELURUH pendamping seketika — tanpa
 * satu baris data pun berubah. Begitu pula nilai acuan rupiah, yang
 * menentukan angka dampak yang dilaporkan ke pemberi dana.
 *
 * Perubahan semacam itu justru yang paling perlu bisa ditelusuri, karena
 * akibatnya paling luas dan paling tidak kelihatan.
 *
 * =====================================================================
 * YANG DICATAT: DARI BERAPA MENJADI BERAPA
 * =====================================================================
 *
 * "Pengaturan diubah" tidak menolong siapa pun. Pertanyaan yang sebenarnya
 * diajukan orang berbunyi: "kenapa bulan lalu semua binaan mendadak
 * berstatus merah?" — dan jawabannya bisa jadi bukan di data peserta,
 * melainkan di ambang yang digeser seseorang.
 */
class JejakKebijakanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@kebijakan.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);
    }

    private function jejak(string $modelType)
    {
        return ActivityLog::where('model_type', $modelType);
    }

    private function simpanPengaturan(array $ubah)
    {
        return $this->actingAs($this->admin)->post(route('pengaturan.update'), array_merge([
            'nama_sistem' => 'CahayaOS',
            'nama_lembaga' => 'YBM PLN UP3 Bengkulu',
            'nama_program' => 'Cahaya Preneur',
        ], $ubah));
    }

    // =================================================================
    // AMBANG
    // =================================================================

    public function test_perubahan_ambang_meninggalkan_jejak(): void
    {
        $this->simpanPengaturan([
            'followup_kuning_hari' => 14,
            'followup_merah_hari' => 21,
        ])->assertSessionHasNoErrors();

        $jejak = $this->jejak('SystemSetting')
            ->where('action', 'pengaturan_sistem_updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($jejak, 'Perubahan ambang tidak meninggalkan jejak.');
        $this->assertSame($this->admin->id, $jejak->user_id);
    }

    /**
     * Jejaknya harus menyebut NILAINYA, bukan sekadar "diubah".
     */
    public function test_jejak_menyebut_nilai_lama_dan_nilai_baru(): void
    {
        // Mulai dari bawaan (kolomnya masih kosong), lalu ditetapkan.
        $this->simpanPengaturan(['followup_merah_hari' => 40])->assertSessionHasNoErrors();

        $pertama = $this->jejak('SystemSetting')->latest('id')->firstOrFail();

        $this->assertStringContainsString('ambang terlambat', $pertama->description);
        $this->assertStringContainsString('bawaan -> 40', $pertama->description);

        // Lalu digeser dari 40 ke 90 — inilah perubahan yang akibatnya luas.
        $this->simpanPengaturan(['followup_merah_hari' => 90])->assertSessionHasNoErrors();

        $kedua = $this->jejak('SystemSetting')->latest('id')->firstOrFail();

        $this->assertStringContainsString('40 -> 90', $kedua->description);
    }

    /**
     * Mengubah nama lembaga bukan perubahan kebijakan — jejaknya tetap ada,
     * tapi tidak perlu mengarang daftar nilai yang tidak berubah.
     */
    public function test_perubahan_bukan_kebijakan_tidak_mengarang_daftar_nilai(): void
    {
        $this->simpanPengaturan(['nama_lembaga' => 'Nama Lembaga Baru'])
            ->assertSessionHasNoErrors();

        $jejak = $this->jejak('SystemSetting')->latest('id')->firstOrFail();

        $this->assertStringContainsString('Pengaturan Sistem', $jejak->description);
        $this->assertStringNotContainsString('->', $jejak->description);
    }

    // =================================================================
    // NILAI ACUAN RUPIAH
    // =================================================================

    /**
     * Nilai acuan menentukan angka dampak yang dilaporkan ke pemberi dana.
     */
    public function test_perubahan_nilai_acuan_meninggalkan_jejak_beserta_nominalnya(): void
    {
        $this->actingAs($this->admin);

        $proxy = FinancialProxy::create([
            'nama' => 'Kenaikan pendapatan UMKM',
            'nilai' => 1500000,
            'satuan' => 'per peserta per tahun',
            'sumber' => 'BPS 2024',
            'tahun' => 2024,
            'aktif' => true,
        ]);

        $dibuat = $this->jejak('FinancialProxy')->latest('id')->firstOrFail();

        $this->assertStringContainsString('Kenaikan pendapatan UMKM', $dibuat->description);
        $this->assertStringContainsString('Rp1.500.000', $dibuat->description);
        $this->assertStringContainsString('BPS 2024', $dibuat->description);

        $proxy->update(['nilai' => 3000000]);

        $diubah = $this->jejak('FinancialProxy')
            ->where('action', 'nilai_acuan_updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Rp3.000.000', $diubah->description);
    }

    // =================================================================
    // TIDAK MENGUBAH APA PUN SELAIN JEJAK
    // =================================================================

    /**
     * Pencatatan tidak boleh ikut mengubah nilai yang tersimpan.
     */
    public function test_pencatatan_tidak_mengubah_nilai_yang_disimpan(): void
    {
        $this->simpanPengaturan([
            'followup_kuning_hari' => 14,
            'followup_merah_hari' => 21,
        ])->assertSessionHasNoErrors();

        app()->forgetInstance('cahayaos.system_setting');

        $setting = SystemSetting::current();

        $this->assertSame(14, (int) $setting->followup_kuning_hari);
        $this->assertSame(21, (int) $setting->followup_merah_hari);
        $this->assertSame('YBM PLN UP3 Bengkulu', $setting->nama_lembaga);
    }
}
