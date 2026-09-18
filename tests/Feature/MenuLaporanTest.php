<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visibility menu "Laporan" di sidebar.
 *
 * Temuan lanjutan dari audit M-3: akun ber-role `pendamping` bisa punya
 * `pendamping_id` NULL (dibuat lewat Manajemen User, atau tautannya dilepas).
 * Sidebar tetap menampilkan menu "Laporan" untuk akun seperti itu, padahal
 * ReportController::index() menolaknya dengan 403 — menu buntu, persis
 * masalah yang dulu terjadi pada menu "Binaan Saya".
 *
 * Yang diperbaiki HANYA visibility menu. Otorisasi backend sengaja TIDAK
 * dilonggarkan dan tetap diuji di sini sebagai pengaman terakhir.
 *
 * Berkas ini terpisah dari [MenuBinaanSayaTest] karena menguji menu yang
 * berbeda, meski sekarang keduanya kebetulan memakai syarat yang sama.
 */
class MenuLaporanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Menu diperiksa lewat URL persisnya, BUKAN lewat kata "Laporan".
     * Sidebar Admin juga memuat menu Koperasi berlabel "Laporan"
     * (/koperasi/laporan), jadi mencocokkan teks saja akan menyesatkan.
     */
    private function tautanLaporan(): string
    {
        return 'href="' . route('laporan') . '"';
    }

    private function nav(User $user): string
    {
        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();
        $awal = strpos($html, '<nav');

        return substr($html, $awal, strpos($html, '</nav>') - $awal);
    }

    private function buat(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'pendamping_id' => $pendampingId,
        ]);
    }

    private function profilPendamping(string $nama = 'Pendamping Uji'): Pendamping
    {
        return Pendamping::create([
            'nama' => $nama,
            'nomor_hp' => '081200000000',
            'email' => strtolower(str_replace(' ', '.', $nama)) . '@uji.test',
            'status' => 'Aktif',
        ]);
    }

    // =====================================================================
    // Yang MELIHAT menu
    // =====================================================================

    public function test_admin_melihat_menu_laporan(): void
    {
        $admin = $this->buat(User::ROLE_ADMIN, 'admin@menu.test');

        $this->assertStringContainsString($this->tautanLaporan(), $this->nav($admin));
    }

    public function test_pendamping_dengan_tautan_melihat_menu_laporan(): void
    {
        $profil = $this->profilPendamping();
        $user = $this->buat(User::ROLE_PENDAMPING, 'tertaut@menu.test', $profil->id);

        $this->assertStringContainsString($this->tautanLaporan(), $this->nav($user));
    }

    // =====================================================================
    // Yang TIDAK melihat menu
    // =====================================================================

    public function test_pendamping_tanpa_tautan_tidak_melihat_menu_laporan(): void
    {
        $user = $this->buat(User::ROLE_PENDAMPING, 'kosong@menu.test');
        $this->assertNull($user->pendamping_id);

        $nav = $this->nav($user);

        // Menu Laporan hilang...
        $this->assertStringNotContainsString($this->tautanLaporan(), $nav);
        // ...tapi sidebarnya tidak jadi kosong — Dashboard tetap ada.
        $this->assertStringContainsString('Dashboard', $nav);
    }

    public function test_role_tak_dikenal_tidak_melihat_menu_laporan(): void
    {
        $user = $this->buat('role_belum_terdaftar', 'asing@menu.test');

        $this->assertStringNotContainsString($this->tautanLaporan(), $this->nav($user));
    }

    // =====================================================================
    // Menu mengikuti data, bukan sekadar role
    // =====================================================================

    public function test_menu_laporan_hilang_begitu_tautan_dilepas(): void
    {
        $profil = $this->profilPendamping('Pendamping Lepas');
        $user = $this->buat(User::ROLE_PENDAMPING, 'lepas@menu.test', $profil->id);

        $this->assertStringContainsString($this->tautanLaporan(), $this->nav($user));

        // Tautannya dilepas — TANPA mengubah role atau menghapus akun.
        $user->pendamping_id = null;
        $user->save();

        $this->assertStringNotContainsString($this->tautanLaporan(), $this->nav($user->fresh()));
    }

    // =====================================================================
    // Menu dan backend harus sepakat
    // =====================================================================

    /**
     * Pengunci inti: menu hanya boleh tampil kalau halamannya memang bisa
     * dibuka. Kalau salah satu sisi bergeser sendiri nanti — menu muncul
     * padahal 403, atau menu hilang padahal boleh — test ini merah.
     */
    public function test_menu_tampil_persis_ketika_halamannya_boleh_dibuka(): void
    {
        $profil = $this->profilPendamping('Pendamping Cek');

        $kasus = [
            'admin' => $this->buat(User::ROLE_ADMIN, 'cek.admin@menu.test'),
            'pendamping tertaut' => $this->buat(User::ROLE_PENDAMPING, 'cek.tertaut@menu.test', $profil->id),
            'pendamping tanpa tautan' => $this->buat(User::ROLE_PENDAMPING, 'cek.kosong@menu.test'),
            'role tak dikenal' => $this->buat('role_belum_terdaftar', 'cek.asing@menu.test'),
        ];

        foreach ($kasus as $label => $user) {
            $menuTampil = str_contains($this->nav($user), $this->tautanLaporan());
            $halamanTerbuka = $this->actingAs($user)->get(route('laporan'))->getStatusCode() === 200;

            $this->assertSame(
                $halamanTerbuka,
                $menuTampil,
                "[$label] menu tampil=" . var_export($menuTampil, true)
                    . ' tapi halaman terbuka=' . var_export($halamanTerbuka, true)
                    . ' — sidebar dan otorisasi sudah tidak sejalan.',
            );
        }
    }

    /**
     * Menyembunyikan menu TIDAK menggantikan otorisasi. Backend tetap
     * menolak walau menunya sudah tidak ada.
     */
    public function test_backend_tetap_menolak_walau_menunya_sudah_disembunyikan(): void
    {
        $user = $this->buat(User::ROLE_PENDAMPING, 'buntu@menu.test');

        $this->assertStringNotContainsString($this->tautanLaporan(), $this->nav($user));
        $this->actingAs($user)->get(route('laporan'))->assertForbidden();
        $this->actingAs($user)->get(route('laporan.peserta'))->assertForbidden();
    }

    /**
     * Menu "Binaan Saya" tidak boleh ikut terpengaruh — dua menu berbeda
     * yang kebetulan bersyarat sama.
     */
    public function test_menu_binaan_saya_tidak_ikut_berubah(): void
    {
        $profil = $this->profilPendamping('Pendamping Binaan');
        $tertaut = $this->buat(User::ROLE_PENDAMPING, 'binaan.ada@menu.test', $profil->id);
        $kosong = $this->buat(User::ROLE_PENDAMPING, 'binaan.kosong@menu.test');

        $this->assertStringContainsString('Binaan Saya', $this->nav($tertaut));
        $this->assertStringNotContainsString('Binaan Saya', $this->nav($kosong));
    }
}
