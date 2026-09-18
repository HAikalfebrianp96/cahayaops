<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\UserAccountGuard;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 4 — sesi milik akun yang dinonaktifkan harus langsung berhenti berlaku.
 *
 * Tahap 2 sudah menutup pintu LOGIN BARU. Yang diuji di sini adalah celah
 * lanjutannya: pengguna yang SUDAH login lalu akunnya dinonaktifkan Admin —
 * request berikutnya tidak boleh lagi dilayani.
 */
class SesiAkunNonaktifTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN = 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.';

    private function buat(string $role, string $email, bool $aktif = true): User
    {
        return User::create([
            'name' => 'User ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => $aktif,
        ]);
    }

    // ==========================================================
    // 1 & 7. AKUN AKTIF — PERILAKU NORMAL
    // ==========================================================

    public function test_user_aktif_dapat_mengakses_halaman_protected(): void
    {
        $admin = $this->buat(User::ROLE_ADMIN, 'aktif@uji.test');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('manajemen-user.index'))->assertOk();
    }

    public function test_user_aktif_tetap_dapat_memakai_sesi_normal_berkali_kali(): void
    {
        $pendamping = $this->buat(User::ROLE_PENDAMPING, 'normal@uji.test');

        // Beberapa request berturut-turut dalam sesi yang sama harus tetap jalan
        // — middleware baru tidak boleh mengganggu pengguna yang sah.
        foreach ([1, 2, 3] as $ke) {
            $this->actingAs($pendamping)->get(route('dashboard'))->assertOk();
            $this->assertAuthenticatedAs($pendamping);
        }
    }

    // ==========================================================
    // 2. LOGIN BARU (fondasi Tahap 2, dipastikan tidak rusak)
    // ==========================================================

    public function test_user_nonaktif_tidak_dapat_login(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->buat(User::ROLE_ADMIN, 'nologin@uji.test', aktif: false);

        $this->post('/login', ['email' => 'nologin@uji.test', 'password' => 'password123']);

        $this->assertGuest();
    }

    // ==========================================================
    // 3-6. INTI TAHAP 4 — SESI YANG SUDAH BERJALAN
    // ==========================================================

    /**
     * Skenario nyatanya: pengguna sedang bekerja, Admin menonaktifkan akunnya,
     * lalu pengguna itu mengklik halaman berikutnya.
     */
    public function test_user_yang_sudah_login_lalu_dinonaktifkan_tidak_bisa_lanjut(): void
    {
        $korban = $this->buat(User::ROLE_PENDAMPING, 'korban@uji.test');

        // Sedang bekerja dengan sesi yang sah.
        $this->actingAs($korban)->get(route('dashboard'))->assertOk();

        // Admin menonaktifkan akunnya (mengubah data, bukan sesinya).
        $korban->is_active = false;
        $korban->save();

        // Request BERIKUTNYA dengan sesi yang sama harus ditolak.
        $response = $this->actingAs($korban)->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_sesi_user_nonaktif_di_invalidate(): void
    {
        $korban = $this->buat(User::ROLE_ADMIN, 'invalidate@uji.test');

        $this->actingAs($korban)->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($korban);

        $korban->is_active = false;
        $korban->save();

        $this->get(route('dashboard'));

        // Sesi benar-benar mati, bukan sekadar dialihkan.
        $this->assertGuest();
    }

    public function test_user_diarahkan_ke_halaman_login(): void
    {
        $korban = $this->buat(User::ROLE_PIMPINAN, 'redirect@uji.test');
        $this->actingAs($korban)->get(route('dashboard'));

        $korban->is_active = false;
        $korban->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_pesan_akun_dinonaktifkan_tampil(): void
    {
        $korban = $this->buat(User::ROLE_PENDAMPING, 'pesan@uji.test');
        $this->actingAs($korban)->get(route('dashboard'));

        $korban->is_active = false;
        $korban->save();

        $response = $this->actingAs($korban)->get(route('dashboard'));
        $response->assertSessionHasErrors(['akun' => self::PESAN]);

        // Pesannya benar-benar TERLIHAT di halaman login yang dituju.
        // Sesinya dibangun ulang dengan actingAs() karena request di atas
        // sudah menghanguskan sesi sebelumnya — tanpa itu request berikutnya
        // datang sebagai tamu dan cuma kena redirect 'auth' biasa tanpa pesan.
        $this->actingAs($korban)
            ->followingRedirects()
            ->get(route('dashboard'))
            ->assertSee(self::PESAN);
    }

    /** Seluruh halaman internal ikut terlindungi, bukan cuma dashboard. */
    public function test_semua_halaman_internal_ikut_tertutup(): void
    {
        $korban = $this->buat(User::ROLE_ADMIN, 'semua@uji.test');
        $this->actingAs($korban)->get(route('dashboard'))->assertOk();

        $korban->is_active = false;
        $korban->save();

        foreach ([
            route('dashboard'),
            route('participants.index'),
            route('manajemen-user.index'),
            route('backup-database.index'),
            route('pengaturan.index'),
        ] as $url) {
            $this->actingAs($korban)->get($url)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    /** Penghentian sesi tercatat sebagai aktivitas keamanan, tanpa password. */
    public function test_penghentian_sesi_tercatat_di_activity_log(): void
    {
        $korban = $this->buat(User::ROLE_PENDAMPING, 'log@uji.test');
        $this->actingAs($korban)->get(route('dashboard'));

        $korban->is_active = false;
        $korban->save();

        $this->get(route('dashboard'));

        $log = ActivityLog::where('action', 'session_blocked')->where('model_id', $korban->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('password123', $log->description);
        $this->assertSame(0, ActivityLog::where('description', 'like', '%password123%')->count());
    }

    // ==========================================================
    // 8. TAMU & HALAMAN PUBLIK
    // ==========================================================

    public function test_guest_tetap_dapat_membuka_halaman_login(): void
    {
        $this->get(route('login'))->assertOk();
        $this->assertGuest();
    }

    /** Halaman publik non-login tidak boleh ikut terkena middleware ini. */
    public function test_halaman_publik_tidak_terganggu(): void
    {
        $this->get(route('anggota-akses.login'))->assertOk();
        $this->get('/')->assertRedirect(route('login'));
    }

    /** Tidak boleh terjadi pantulan tanpa henti antara halaman internal dan login. */
    public function test_tidak_ada_infinite_redirect(): void
    {
        $korban = $this->buat(User::ROLE_ADMIN, 'loop@uji.test');
        $this->actingAs($korban)->get(route('dashboard'));

        $korban->is_active = false;
        $korban->save();

        // followingRedirects() akan gagal/berputar kalau ada loop —
        // di sini harus berhenti di halaman login dengan status 200.
        $this->followingRedirects()->get(route('dashboard'))->assertOk();
        $this->get(route('login'))->assertOk();
    }

    // ==========================================================
    // 9. PENGAMAN ADMIN TERAKHIR (Tahap 2) TIDAK BERUBAH
    // ==========================================================

    public function test_pengaman_admin_terakhir_tetap_berlaku(): void
    {
        $guard = new UserAccountGuard();
        $admin = $this->buat(User::ROLE_ADMIN, 'terakhir@uji.test');

        $this->assertSame(1, $guard->jumlahAdminAktif());
        $this->assertTrue($guard->adminAktifTerakhir($admin));
        $this->assertFalse($guard->bolehDinonaktifkan($admin));
        $this->assertFalse($guard->bolehUbahRole($admin, User::ROLE_PIMPINAN));
    }

    /** Diaktifkan kembali -> sesi baru boleh jalan lagi seperti biasa. */
    public function test_akun_yang_diaktifkan_kembali_bisa_dipakai_lagi(): void
    {
        $korban = $this->buat(User::ROLE_PENDAMPING, 'balik@uji.test', aktif: false);

        $this->actingAs($korban)->get(route('dashboard'))->assertRedirect(route('login'));

        $korban->is_active = true;
        $korban->save();

        $this->actingAs($korban)->get(route('dashboard'))->assertOk();
    }
}
