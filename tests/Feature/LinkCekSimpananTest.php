<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\KoperasiMember;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LINK "CEK SIMPANAN" DI HALAMAN DETAIL ANGGOTA.
 *
 * =====================================================================
 * APA YANG SEBENARNYA DITAMBAHKAN
 * =====================================================================
 *
 * Halaman publik Cek Simpanan, mekanisme PIN, rate limit, dan penyaringan
 * per anggota SUDAH ADA sejak sebelumnya — dijaga oleh
 * [Uat\CekSimpananTanpaAkunTest] dengan 19 test.
 *
 * Yang hilang cuma satu: Tim Koperasi tidak punya cara mendapatkan
 * alamatnya. Halamannya ada, tapi tidak ada satu pun tombol yang
 * memberikan linknya — sehingga petugas harus mengingat atau mengetik
 * ulang alamat itu setiap kali membagikannya lewat WhatsApp.
 *
 * =====================================================================
 * KENAPA LINKNYA TIDAK MEMUAT APA-APA
 * =====================================================================
 *
 * Linknya SAMA untuk semua anggota dan tidak memuat nomor, token, maupun
 * rahasia apa pun. Yang menentukan siapa yang masuk adalah Nomor Anggota
 * + PIN yang diketik di halaman itu.
 *
 * Itu keputusan yang disengaja: link ber-token yang beredar di WhatsApp
 * akan terus berlaku setelah diteruskan ke orang lain, sedangkan link ini
 * tidak berguna tanpa PIN pemiliknya.
 */
class LinkCekSimpananTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private KoperasiMember $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@link.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@link.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'tanggal_bergabung' => '2026-01-01',
            'status' => 'Aktif',
        ]);
    }

    private function detail(User $sebagai)
    {
        return $this->actingAs($sebagai)
            ->get(route('koperasi.anggota.show', $this->anggota));
    }

    // =================================================================
    // TOMBOLNYA ADA, DAN LINKNYA BENAR
    // =================================================================

    public function test_detail_anggota_menampilkan_tombol_salin_link(): void
    {
        $this->detail($this->timKoperasi)
            ->assertOk()
            ->assertSee('Salin Link Cek Simpanan')
            ->assertSee('Buka Cek Simpanan');
    }

    public function test_admin_juga_melihat_tombolnya(): void
    {
        $this->detail($this->admin)
            ->assertOk()
            ->assertSee('Salin Link Cek Simpanan');
    }

    /**
     * Link yang disalin adalah URL publik yang sah — dan dibangun dari
     * route(), bukan ditulis tetap.
     *
     * Alamat aplikasi berbeda antara komputer kantor, jaringan lokal, dan
     * nanti server. Link yang di-hard-code akan salah di salah satunya
     * tanpa ada yang menyadarinya sampai ada anggota yang mengeluh.
     */
    public function test_link_yang_disalin_adalah_url_publik_cek_simpanan(): void
    {
        $this->detail($this->timKoperasi)
            ->assertOk()
            ->assertSee('value="' . route('anggota-akses.login') . '"', false);

        // Dan halaman yang ditunjuknya memang terbuka tanpa login.
        $this->get(route('anggota-akses.login'))->assertOk();
    }

    /** Linknya tidak memuat identitas anggota mana pun. */
    public function test_link_tidak_memuat_nomor_anggota_atau_rahasia(): void
    {
        $url = route('anggota-akses.login');

        $this->assertStringNotContainsString((string) $this->anggota->id, parse_url($url, PHP_URL_PATH));
        $this->assertStringNotContainsString($this->anggota->nomor_anggota, $url);
        $this->assertNull(parse_url($url, PHP_URL_QUERY));
    }

    // =================================================================
    // KEADAAN "PIN BELUM DIATUR"
    // =================================================================

    /**
     * Anggota tanpa PIN: linknya tetap ditampilkan, tetapi diterangkan
     * bahwa belum bisa dipakai.
     *
     * Membagikan link ke orang yang belum punya PIN hanya menghasilkan
     * kegagalan masuk yang tidak dimengerti siapa pun.
     */
    public function test_anggota_tanpa_pin_diberi_keterangan(): void
    {
        $this->assertFalse($this->anggota->hasPin(), 'Prasyarat: belum punya PIN.');

        $this->detail($this->timKoperasi)
            ->assertOk()
            ->assertSee('PIN belum diatur')
            ->assertSee('belum bisa dipakai');
    }

    /** PIN tidak dibuat diam-diam hanya karena halamannya dibuka. */
    public function test_membuka_halaman_tidak_membuat_pin_diam_diam(): void
    {
        $this->detail($this->timKoperasi)->assertOk();

        $this->assertNull($this->anggota->fresh()->pin_hash);
    }

    public function test_setelah_pin_diatur_keterangan_itu_hilang(): void
    {
        $this->anggota->setPin('740285');

        $this->detail($this->timKoperasi)
            ->assertOk()
            ->assertDontSee('PIN belum diatur')
            ->assertSee('Salin Link Cek Simpanan');
    }

    // =================================================================
    // PIN TIDAK BOCOR
    // =================================================================

    /** Hash PIN tidak pernah ikut dirender ke halaman. */
    public function test_hash_pin_tidak_pernah_muncul_di_halaman(): void
    {
        // PIN-nya sengaja dipilih yang BUKAN potongan dari nomor HP
        // fixture (081234567890). PIN "123456" membuat assertion di
        // bawah gagal karena nomor HP-nya, bukan karena PIN-nya bocor.

        $this->anggota->setPin('740285');

        $this->detail($this->timKoperasi)
            ->assertOk()
            ->assertDontSee($this->anggota->fresh()->pin_hash)
            ->assertDontSee('740285');
    }

    /**
     * PIN tidak pernah lewat URL.
     *
     * Alamat halaman tersimpan di riwayat browser, tercatat di log web
     * server, dan ikut terkirim sebagai referer. PIN yang pernah muncul di
     * sana tidak bisa ditarik kembali.
     */
    public function test_pin_tidak_pernah_muncul_di_url(): void
    {
        $this->anggota->setPin('740285');

        $respons = $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggota->nomor_anggota,
            'pin' => '740285',
        ]);

        $tujuan = $respons->headers->get('Location') ?? '';

        $this->assertStringNotContainsString('740285', $tujuan);
        $this->assertStringNotContainsString('pin', strtolower(parse_url($tujuan, PHP_URL_QUERY) ?? ''));
    }

    /** PIN tidak pernah ikut tercatat di Activity Log. */
    public function test_pin_tidak_masuk_activity_log(): void
    {
        $this->anggota->setPin('740285');

        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggota->nomor_anggota,
            'pin' => '740285',
        ]);

        // Percobaan yang gagal pun tidak boleh meninggalkan PIN-nya.
        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggota->nomor_anggota,
            'pin' => '318604',
        ]);

        foreach (ActivityLog::all() as $baris) {
            $this->assertStringNotContainsString('740285', (string) $baris->description);
            $this->assertStringNotContainsString('318604', (string) $baris->description);
        }
    }

    // =================================================================
    // OTORISASI TIDAK BERUBAH
    // =================================================================

    /**
     * Menambahkan tombol ini tidak membuka pintu baru bagi siapa pun.
     */
    public function test_pendamping_dan_pimpinan_tetap_tidak_bisa_membuka_detail_anggota(): void
    {
        $pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);

        $akunPendamping = User::create([
            'name' => 'Rosa',
            'email' => 'rosa@link.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_PENDAMPING,
            'is_active' => true,
            'pendamping_id' => $pendamping->id,
        ]);

        $pimpinan = User::create([
            'name' => 'Pimpinan',
            'email' => 'pimpinan@link.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_PIMPINAN,
            'is_active' => true,
        ]);

        foreach ([$akunPendamping, $pimpinan] as $user) {
            $this->detail($user)->assertForbidden();
        }
    }

    /** Tamu tidak bisa melihat halaman detail anggota, tapi boleh membuka link publiknya. */
    public function test_tamu_ditolak_di_detail_tetapi_boleh_membuka_cek_simpanan(): void
    {
        $this->get(route('koperasi.anggota.show', $this->anggota))
            ->assertRedirect(route('login'));

        $this->get(route('anggota-akses.login'))->assertOk();
    }
}
