<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccountGuard;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fondasi status akun User (Manajemen User — Tahap 2).
 *
 * Menguji tiga hal: akun nonaktif benar-benar tidak bisa login, login berhasil
 * mencatat waktu & IP, dan penjagaan "Admin aktif terakhir" bekerja.
 *
 * Tahap 2 SENGAJA belum punya UI/endpoint untuk menonaktifkan akun atau
 * mengubah role, jadi penjagaan Admin terakhir diuji langsung lewat
 * UserAccountGuard — bukan lewat endpoint yang dibuat khusus untuk test.
 */
class StatusAkunUserTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, bool $aktif = true): User
    {
        return User::create([
            'name' => 'User ' . $role,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => $aktif,
        ]);
    }

    /** Akun baru harus aktif tanpa perlu diisi — default kolomnya true. */
    public function test_akun_baru_default_aktif(): void
    {
        $user = User::create([
            'name' => 'Tanpa Status',
            'email' => 'tanpa-status@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->assertTrue($user->fresh()->isActive());
    }

    // ==========================================================
    // 1-3. LOGIN
    // ==========================================================

    public function test_user_aktif_dapat_login(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->user('admin', 'aktif@example.com');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_nonaktif_tidak_dapat_login(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->user('admin', 'nonaktif@example.com', aktif: false);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertRedirect();

        // Password TIDAK berubah dan akun TIDAK terhapus — hanya aksesnya dicabut.
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $user->fresh()->password));
    }

    public function test_user_nonaktif_mendapat_pesan_yang_jelas(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->user('pendamping', 'nonaktif2@example.com', aktif: false);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi administrator.',
        ]);

        // Pesannya HARUS beda dari pesan kredensial salah, supaya pemilik akun
        // tidak mengira dirinya lupa password.
        $pesan = session('errors')->first('email');
        $this->assertStringNotContainsString('salah', $pesan);

        // Penolakan tercatat sebagai aktivitas keamanan.
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login_blocked',
            'user_id' => $user->id,
        ]);
    }

    // ==========================================================
    // 4-5. LAST LOGIN
    // ==========================================================

    public function test_login_berhasil_mencatat_last_login_at(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->user('pimpinan', 'lastlogin@example.com');

        $this->assertNull($user->last_login_at, 'Sebelum login harus masih kosong');

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue($user->fresh()->last_login_at->isToday());
    }

    public function test_login_berhasil_mencatat_last_login_ip(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $user = $this->user('admin', 'lastip@example.com');

        $this->assertNull($user->last_login_ip);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $this->assertNotNull($user->fresh()->last_login_ip);
    }

    /** Login yang GAGAL tidak boleh meninggalkan jejak last login. */
    public function test_login_gagal_tidak_mencatat_last_login(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $nonaktif = $this->user('admin', 'gagal1@example.com', aktif: false);
        $this->post('/login', ['email' => $nonaktif->email, 'password' => 'password123']);
        $this->assertNull($nonaktif->fresh()->last_login_at, 'Akun nonaktif tidak boleh tercatat login');

        $aktif = $this->user('admin', 'gagal2@example.com');
        $this->post('/login', ['email' => $aktif->email, 'password' => 'SALAH-total']);
        $this->assertNull($aktif->fresh()->last_login_at, 'Password salah tidak boleh tercatat login');
    }

    // ==========================================================
    // 6-7. PENJAGAAN ADMIN AKTIF TERAKHIR
    // ==========================================================

    public function test_admin_aktif_terakhir_tidak_boleh_dinonaktifkan(): void
    {
        $guard = new UserAccountGuard();
        $admin = $this->user('admin', 'satu-satunya@example.com');

        // Ada Pimpinan & Pendamping, tapi keduanya BUKAN Admin.
        $this->user('pimpinan', 'pimpinan@example.com');
        $this->user('pendamping', 'pendamping@example.com');

        $this->assertSame(1, $guard->jumlahAdminAktif());
        $this->assertTrue($guard->adminAktifTerakhir($admin));
        $this->assertFalse($guard->bolehDinonaktifkan($admin));
        $this->assertNotNull($guard->alasanTidakBoleh($admin));
    }

    public function test_admin_aktif_terakhir_tidak_boleh_diturunkan_rolenya(): void
    {
        $guard = new UserAccountGuard();
        $admin = $this->user('admin', 'satu-satunya2@example.com');

        $this->assertFalse($guard->bolehUbahRole($admin, 'pimpinan'));
        $this->assertFalse($guard->bolehUbahRole($admin, 'pendamping'));
        $this->assertNotNull($guard->alasanTidakBoleh($admin, 'pimpinan'));

        // Tetap Admin = tidak ada yang hilang, jadi boleh.
        $this->assertTrue($guard->bolehUbahRole($admin, 'admin'));
    }

    /** Begitu ada Admin aktif kedua, penjagaannya melonggar dengan sendirinya. */
    public function test_penjagaan_longgar_kalau_ada_admin_aktif_lain(): void
    {
        $guard = new UserAccountGuard();
        $adminA = $this->user('admin', 'admin-a@example.com');
        $this->user('admin', 'admin-b@example.com');

        $this->assertSame(2, $guard->jumlahAdminAktif());
        $this->assertFalse($guard->adminAktifTerakhir($adminA));
        $this->assertTrue($guard->bolehDinonaktifkan($adminA));
        $this->assertTrue($guard->bolehUbahRole($adminA, 'pimpinan'));
        $this->assertNull($guard->alasanTidakBoleh($adminA));
    }

    /** Admin yang sudah nonaktif tidak dihitung sebagai penjaga terakhir. */
    public function test_admin_nonaktif_tidak_dihitung(): void
    {
        $guard = new UserAccountGuard();
        $aktif = $this->user('admin', 'admin-aktif@example.com');
        $nonaktif = $this->user('admin', 'admin-nonaktif@example.com', aktif: false);

        $this->assertSame(1, $guard->jumlahAdminAktif());
        $this->assertTrue($guard->adminAktifTerakhir($aktif));
        $this->assertFalse($guard->adminAktifTerakhir($nonaktif));
        // Yang sudah nonaktif memang tidak memegang akses apa pun.
        $this->assertTrue($guard->bolehDinonaktifkan($nonaktif));
    }

    /** Non-Admin tidak pernah kena penjagaan ini. */
    public function test_non_admin_tidak_kena_penjagaan(): void
    {
        $guard = new UserAccountGuard();
        $this->user('admin', 'admin-satu@example.com');
        $pimpinan = $this->user('pimpinan', 'pimpinan2@example.com');

        $this->assertFalse($guard->adminAktifTerakhir($pimpinan));
        $this->assertTrue($guard->bolehDinonaktifkan($pimpinan));
        $this->assertTrue($guard->bolehUbahRole($pimpinan, 'pendamping'));
    }
}
