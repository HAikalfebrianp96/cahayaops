<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AMBANG YANG DIISI SEBAGIAN TIDAK BOLEH MEMATIKAN SEBUAH STATUS.
 *
 * =====================================================================
 * LUBANGNYA
 * =====================================================================
 *
 * Ketiga pasang ambang di Pengaturan sudah divalidasi silang dengan aturan
 * `gt:` — "terlambat" harus lebih besar daripada "perlu perhatian", dan
 * seterusnya. Tapi `gt:` membandingkan NILAI YANG DIKIRIM, sedangkan yang
 * benar-benar dipakai sistem adalah campuran: nilai yang diisi Admin, dan
 * nilai bawaan untuk yang dikosongkan.
 *
 * Akibatnya satu kombinasi lolos validasi tapi merusak klasifikasi:
 *
 *   Admin mengisi "perlu perhatian" = 50 hari, dan MENGOSONGKAN "terlambat".
 *   Validasi lolos (tidak ada pembanding yang dikirim).
 *   Yang berlaku: kuning = 50, merah = 45 (bawaan) -> terbalik.
 *
 * FollowUpService memeriksa merah lebih dulu, jadi setiap binaan >= 45 hari
 * langsung MERAH dan status KUNING tidak akan pernah tercapai. Keranjang
 * "Perlu Ditindaklanjuti" kosong selamanya — tanpa pesan error, tanpa ada
 * yang tahu sebabnya.
 */
class AmbangSilangTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@ambang.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);
    }

    /**
     * Kirim form pengaturan dengan hanya sebagian ambang terisi.
     *
     * POST, bukan PUT — rute pengaturan.update memang POST. Ini penting:
     * request dengan metode yang salah tidak pernah sampai ke validator,
     * dan assertSessionHasErrors() lalu gagal karena TIDAK ADA APA-APA yang
     * terjadi, bukan karena validasinya lolos. Keduanya terlihat sama di
     * layar dan mudah sekali disalahartikan sebagai temuan.
     *
     * Ketiga kolom wajib ikut dikirim, kalau tidak validasi gagal karena
     * alasan yang sama sekali berbeda dari yang sedang diuji.
     */
    private function simpan(array $ubah)
    {
        return $this->actingAs($this->admin)->post(route('pengaturan.update'), array_merge([
            'nama_sistem' => 'CahayaOS',
            'nama_lembaga' => 'YBM PLN UP3 Bengkulu',
            'nama_program' => 'Cahaya Preneur',
        ], $ubah));
    }

    /** Lupakan simpanan SystemSetting supaya nilai baru benar-benar terbaca. */
    private function segarkanPengaturan(): void
    {
        // Kuncinya ditulis apa adanya: konstanta KUNCI_SIMPANAN di model
        // bersifat private, dan membukanya hanya demi test bukan alasan yang
        // cukup untuk melonggarkan enkapsulasi.
        app()->forgetInstance('cahayaos.system_setting');
    }

    // =================================================================
    // FOLLOW UP
    // =================================================================

    public function test_kuning_saja_yang_diisi_tidak_boleh_melampaui_merah_bawaan(): void
    {
        $bawaanMerah = (int) config('cahayaos.followup.merah_hari');

        $this->simpan(['followup_kuning_hari' => $bawaanMerah + 5])
            ->assertSessionHasErrors('followup_kuning_hari');

        // Yang berlaku harus tetap masuk akal: kuning selalu di bawah merah.
        $this->segarkanPengaturan();
        $ambang = app(FollowUpService::class)->ambang();

        $this->assertLessThan(
            $ambang['merah'],
            $ambang['kuning'],
            'Ambang kuning tidak boleh berada di atas merah — status KUNING jadi mustahil tercapai.'
        );
    }

    public function test_merah_saja_yang_diisi_tidak_boleh_di_bawah_kuning_bawaan(): void
    {
        $bawaanKuning = (int) config('cahayaos.followup.kuning_hari');

        $this->simpan(['followup_merah_hari' => max(2, $bawaanKuning - 5)])
            ->assertSessionHasErrors('followup_merah_hari');
    }

    public function test_kombinasi_yang_wajar_tetap_bisa_disimpan(): void
    {
        $this->simpan([
            'followup_kuning_hari' => 14,
            'followup_merah_hari' => 21,
        ])->assertSessionHasNoErrors();

        $this->segarkanPengaturan();
        $ambang = app(FollowUpService::class)->ambang();

        $this->assertSame(14, $ambang['kuning']);
        $this->assertSame(21, $ambang['merah']);
    }

    /**
     * MENGISI SEBAGIAN HARUS BOLEH — formnya sendiri menjanjikan itu.
     *
     * Formulir Pengaturan berbunyi "Kosongkan untuk memakai nilai bawaan
     * (30 dan 45 hari)". Tapi dulu mengisi "terlambat" = 40 sambil
     * mengosongkan "perlu perhatian" DITOLAK, dengan pesan yang menuduh
     * angka yang sudah benar: 40 memang lebih besar daripada 30 yang
     * berlaku.
     *
     * Sebabnya aturan bawaan gt:, yang membandingkan dengan masukan lain di
     * form dan gagal begitu pembandingnya kosong. Aturan itu kini digantikan
     * pemeriksaan nilai yang BERLAKU.
     */
    public function test_mengisi_satu_ambang_saja_tetap_boleh_disimpan(): void
    {
        $bawaanKuning = (int) config('cahayaos.followup.kuning_hari');

        $this->simpan([
            'followup_kuning_hari' => '',
            'followup_merah_hari' => $bawaanKuning + 10,
        ])->assertSessionHasNoErrors();

        $this->segarkanPengaturan();
        $ambang = app(FollowUpService::class)->ambang();

        $this->assertSame($bawaanKuning, $ambang['kuning'], 'Yang dikosongkan tetap memakai bawaan.');
        $this->assertSame($bawaanKuning + 10, $ambang['merah']);
    }

    /**
     * Sisi sebaliknya: yang benar-benar terbalik tetap ditolak walau
     * salah satunya dikosongkan.
     */
    public function test_pengisian_sebagian_yang_terbalik_tetap_ditolak(): void
    {
        $bawaanKuning = (int) config('cahayaos.followup.kuning_hari');

        $this->simpan([
            'followup_kuning_hari' => '',
            'followup_merah_hari' => max(2, $bawaanKuning - 5),
        ])->assertSessionHasErrors('followup_merah_hari');
    }

    // =================================================================
    // KEAKTIFAN PESERTA
    // =================================================================

    public function test_aktif_saja_yang_diisi_tidak_boleh_melampaui_batas_tidak_aktif(): void
    {
        $bawaan = (int) config('cahayaos.keaktifan_peserta.perlu_follow_up_hari');

        $this->simpan(['peserta_aktif_hari' => $bawaan + 10])
            ->assertSessionHasErrors('peserta_aktif_hari');
    }

    // =================================================================
    // BEBAN PENDAMPING
    // =================================================================

    public function test_beban_ringan_saja_yang_diisi_tidak_boleh_melampaui_normal_bawaan(): void
    {
        $bawaan = (int) config('cahayaos.beban_pendamping.normal_maks');

        $this->simpan(['beban_ringan_maks' => $bawaan + 5])
            ->assertSessionHasErrors('beban_ringan_maks');
    }

    public function test_beban_normal_saja_yang_diisi_tidak_boleh_melampaui_tinggi_bawaan(): void
    {
        $bawaan = (int) config('cahayaos.beban_pendamping.tinggi_maks');

        $this->simpan(['beban_normal_maks' => $bawaan + 5])
            ->assertSessionHasErrors('beban_normal_maks');
    }

    /**
     * Ambang yang berlaku harus selalu naik: ringan < normal < tinggi.
     *
     * Kalau terbalik, beban() akan melewati salah satu kategori sepenuhnya.
     */
    public function test_ambang_beban_yang_berlaku_selalu_menaik(): void
    {
        $this->segarkanPengaturan();
        $ambang = app(KpiService::class)->ambangBeban();

        $this->assertLessThan($ambang['normal'], $ambang['ringan']);
        $this->assertLessThan($ambang['tinggi'], $ambang['normal']);
    }
}
