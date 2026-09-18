<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu "Binaan Saya" harus mencerminkan akses yang sebenarnya.
 *
 * Temuan audit: akun ber-role `pendamping` bisa punya `pendamping_id` NULL
 * (mis. dibuat lewat Manajemen User, atau tautannya dilepas). Sidebar dulu
 * tetap menampilkan menu itu, padahal BinaanSayaController menolaknya dengan
 * 403 — menu buntu.
 *
 * Yang diperbaiki HANYA visibility menu. Otorisasi di controller sengaja
 * TIDAK dilonggarkan dan tetap diuji di sini sebagai pengaman terakhir.
 */
class MenuBinaanSayaTest extends TestCase
{
    use RefreshDatabase;

    private const MENU = 'Binaan Saya';

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

    // ==========================================================
    // TEST 1 — Pendamping dengan pendamping_id valid
    // ==========================================================

    public function test_pendamping_dengan_data_binaan_melihat_menu_dan_bisa_membukanya(): void
    {
        $profil = $this->profilPendamping();
        $user = $this->buat(User::ROLE_PENDAMPING, 'punya@uji.test', $profil->id);

        // Menu tampil.
        $this->assertStringContainsString(self::MENU, $this->nav($user));

        // Dan halamannya memang bisa dibuka — menu tidak buntu.
        $this->actingAs($user)->get(route('binaan.index'))->assertOk();
    }

    // ==========================================================
    // TEST 2 — Pendamping dengan pendamping_id NULL
    // ==========================================================

    public function test_pendamping_tanpa_data_binaan_tidak_melihat_menu(): void
    {
        $user = $this->buat(User::ROLE_PENDAMPING, 'kosong@uji.test');
        $this->assertNull($user->pendamping_id);

        $nav = $this->nav($user);

        // Menu TIDAK tampil...
        $this->assertStringNotContainsString(self::MENU, $nav);
        // ...tapi menu lain miliknya tetap ada (bukan sidebar kosong).
        $this->assertStringContainsString('Dashboard', $nav);

        // CATATAN: baris ini dulu berbunyi assertStringContainsString('Laporan').
        // Saat itu menu "Laporan" memang masih tampil untuk Pendamping tanpa
        // tautan — dan itu ternyata cacat yang sama: halamannya menolak dengan
        // 403 (lihat ReportController::index()). Sesudah diperbaiki, menu itu
        // ikut disembunyikan, jadi assertion lamanya justru menegaskan perilaku
        // yang salah. Diperketat, bukan dilonggarkan. Rinciannya di
        // [MenuLaporanTest].
        $this->assertStringNotContainsString(route('laporan') . '"', $nav);
    }

    /** Otorisasi backend TIDAK dilonggarkan — tetap menolak. */
    public function test_route_binaan_tetap_ditolak_untuk_pendamping_tanpa_data(): void
    {
        $user = $this->buat(User::ROLE_PENDAMPING, 'kosong2@uji.test');

        $this->actingAs($user)->get(route('binaan.index'))->assertForbidden();
    }

    // ==========================================================
    // TEST 3 & 4 — Admin dan Pimpinan
    // ==========================================================

    public function test_admin_tidak_melihat_menu_binaan_saya(): void
    {
        $admin = $this->buat(User::ROLE_ADMIN, 'admin@uji.test');

        $this->assertStringNotContainsString(self::MENU, $this->nav($admin));
    }

    public function test_pimpinan_tidak_melihat_menu_binaan_saya(): void
    {
        $pimpinan = $this->buat(User::ROLE_PIMPINAN, 'pimpinan@uji.test');

        $this->assertStringNotContainsString(self::MENU, $this->nav($pimpinan));
    }

    // ==========================================================
    // Menu mengikuti perubahan data, bukan sekadar role
    // ==========================================================

    /**
     * Saat tautan Pendamping dilepas (mis. role diubah lalu dikembalikan,
     * atau data Pendamping dihapus), menunya ikut hilang tanpa perlu
     * menyentuh apa pun di controller.
     */
    public function test_menu_hilang_begitu_tautan_dilepas(): void
    {
        $profil = $this->profilPendamping('Pendamping Lepas');
        $user = $this->buat(User::ROLE_PENDAMPING, 'lepas@uji.test', $profil->id);

        $this->assertStringContainsString(self::MENU, $this->nav($user));

        // Tautannya dilepas — TANPA mengubah role atau menghapus akun.
        $user->pendamping_id = null;
        $user->save();

        $this->assertStringNotContainsString(self::MENU, $this->nav($user->fresh()));
        $this->actingAs($user->fresh())->get(route('binaan.index'))->assertForbidden();
    }
}
