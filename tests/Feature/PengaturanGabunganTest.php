<?php

namespace Tests\Feature;

use App\Models\KoperasiSetting;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Halaman Pengaturan gabungan — SATU halaman, tiga tab.
 *
 * Yang digabung HANYA tampilannya. Dua sistem datanya tetap terpisah:
 * SystemSetting (Cahaya Preneur) dan KoperasiSetting (Koperasi), masing-masing
 * dengan endpoint simpan, validasi, dan otorisasinya sendiri.
 */
class PengaturanGabunganTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin-pengaturan@example.com'],
            ['name' => 'Admin', 'password' => 'password123', 'role' => 'admin']
        );
    }

    private function pendamping(): User
    {
        return User::firstOrCreate(
            ['email' => 'pendamping-pengaturan@example.com'],
            ['name' => 'Pendamping', 'password' => 'password123', 'role' => 'pendamping']
        );
    }

    public function test_route_terdaftar_dan_tidak_hilang(): void
    {
        // Halaman gabungan.
        $this->assertTrue(Route::has('pengaturan.index'));
        // Endpoint simpan MASING-MASING sistem tetap ada dan terpisah.
        $this->assertTrue(Route::has('pengaturan.update'));
        $this->assertTrue(Route::has('pengaturan.logo'));
        $this->assertTrue(Route::has('koperasi.pengaturan.update'));
        // Route lama Koperasi dipertahankan sebagai alias.
        $this->assertTrue(Route::has('koperasi.pengaturan.index'));
    }

    public function test_tab_semua_menampilkan_kedua_kelompok(): void
    {
        $response = $this->actingAs($this->admin())->get(route('pengaturan.index'));

        $response->assertOk();
        $response->assertSee('Kelola identitas dan konfigurasi CahayaOS.');

        // Kedua form hadir, masing-masing menuju endpoint aslinya.
        $response->assertSee('action="' . route('pengaturan.update') . '"', false);
        $response->assertSee('action="' . route('koperasi.pengaturan.update') . '"', false);

        // Field khas kedua sistem.
        $response->assertSee('Nama Sistem');
        $response->assertSee('Identitas Koperasi');
        $response->assertSee('Prefix Nomor Invoice');
    }

    public function test_tab_preneur_hanya_menampilkan_cahaya_preneur(): void
    {
        $response = $this->actingAs($this->admin())->get(route('pengaturan.index', ['tab' => 'preneur']));

        $response->assertOk();
        $response->assertSee('action="' . route('pengaturan.update') . '"', false);
        $response->assertSee('Nama Sistem');
        // Form Koperasi TIDAK ikut ter-render.
        $response->assertDontSee('action="' . route('koperasi.pengaturan.update') . '"', false);
        $response->assertDontSee('Prefix Nomor Invoice');
    }

    public function test_tab_koperasi_hanya_menampilkan_koperasi(): void
    {
        $response = $this->actingAs($this->admin())->get(route('pengaturan.index', ['tab' => 'koperasi']));

        $response->assertOk();
        $response->assertSee('action="' . route('koperasi.pengaturan.update') . '"', false);
        $response->assertSee('Prefix Nomor Invoice');
        // Form Cahaya Preneur TIDAK ikut ter-render.
        $response->assertDontSee('action="' . route('pengaturan.update') . '"', false);
        $response->assertDontSee('Nama Sistem');
    }

    /** Refresh dengan ?tab= sembarang TIDAK boleh error — jatuh ke 'semua'. */
    public function test_tab_tidak_dikenal_jatuh_ke_semua(): void
    {
        foreach (['', 'ngawur', 'PRENEUR', '123'] as $nilai) {
            $response = $this->actingAs($this->admin())->get(route('pengaturan.index', ['tab' => $nilai]));

            $response->assertOk();
            $response->assertSee('Nama Sistem');
            $response->assertSee('Identitas Koperasi');
        }
    }

    public function test_route_lama_koperasi_redirect_bukan_404(): void
    {
        $response = $this->actingAs($this->admin())->get(route('koperasi.pengaturan.index'));

        $response->assertRedirect(route('pengaturan.index', ['tab' => 'koperasi']));
    }

    public function test_otorisasi_admin_tetap_dijaga(): void
    {
        $this->get(route('pengaturan.index'))->assertRedirect(route('login'));

        $pendamping = $this->pendamping();
        $this->actingAs($pendamping)->get(route('pengaturan.index'))->assertForbidden();
        $this->actingAs($pendamping)->get(route('koperasi.pengaturan.index'))->assertForbidden();
    }

    /**
     * Simpan Cahaya Preneur menulis ke SystemSetting SAJA — KoperasiSetting
     * tidak boleh ikut berubah.
     */
    public function test_simpan_cahaya_preneur_tidak_menyentuh_data_koperasi(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $kopSebelum = KoperasiSetting::current()->fresh()->only(['nama', 'prefix_invoice']);

        $response = $this->actingAs($this->admin())->post(route('pengaturan.update'), [
            'nama_sistem' => 'CahayaOS Uji',
            'nama_lembaga' => 'YBM PLN Uji',
            'nama_program' => 'Program Uji',
        ]);

        $response->assertRedirect(route('pengaturan.index', ['tab' => 'preneur']));

        $this->assertSame('CahayaOS Uji', SystemSetting::current()->nama_sistem);
        $this->assertSame($kopSebelum, KoperasiSetting::current()->fresh()->only(['nama', 'prefix_invoice']));
    }

    /**
     * Simpan Koperasi menulis ke KoperasiSetting SAJA — SystemSetting
     * tidak boleh ikut berubah.
     */
    public function test_simpan_koperasi_tidak_menyentuh_data_cahaya_preneur(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $preneurSebelum = SystemSetting::current()->fresh()->only(['nama_sistem', 'nama_program']);

        $response = $this->actingAs($this->admin())->post(route('koperasi.pengaturan.update'), [
            'nama' => 'Koperasi Uji',
            'subtitle' => 'Subtitle Uji',
            'prefix_invoice' => 'UJI',
        ]);

        $response->assertRedirect(route('pengaturan.index', ['tab' => 'koperasi']));

        $this->assertSame('Koperasi Uji', KoperasiSetting::current()->nama);
        $this->assertSame($preneurSebelum, SystemSetting::current()->fresh()->only(['nama_sistem', 'nama_program']));
    }

    /** Validasi masing-masing sistem tetap berjalan sendiri-sendiri. */
    public function test_validasi_masing_masing_tetap_terpisah(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->admin();

        // Cahaya Preneur: nama_sistem wajib.
        $this->actingAs($admin)->post(route('pengaturan.update'), ['nama_lembaga' => 'X', 'nama_program' => 'Y'])
            ->assertSessionHasErrors('nama_sistem');

        // Koperasi: prefix_invoice wajib.
        $this->actingAs($admin)->post(route('koperasi.pengaturan.update'), ['nama' => 'X', 'subtitle' => 'Y'])
            ->assertSessionHasErrors('prefix_invoice');
    }

    /** Sidebar hanya boleh punya SATU menu berlabel persis "Pengaturan". */
    public function test_sidebar_tidak_punya_dua_menu_pengaturan(): void
    {
        $html = $this->actingAs($this->admin())->get(route('pengaturan.index'))->getContent();
        $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

        $this->assertSame(1, preg_match_all('/>\s*Pengaturan\s*</', $nav), 'Menu "Pengaturan" harus tepat satu');
        // Menu monitoring dulu berlabel 'Pengaturan Sistem' — terlalu mirip
        // dengan 'Pengaturan' dan membingungkan. Sekarang jadi label ini.
        $this->assertStringContainsString('Monitoring &amp; Keamanan', $nav);
        $this->assertStringNotContainsString('Pengaturan Sistem', $nav);
        // Menu Pengaturan menyala saat halaman ini dibuka.
        $this->assertSame(1, substr_count($nav, 'bg-primary-light text-cahaya-primary'));
    }
}
