<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Test untuk fitur Pengaturan Sistem — sekarang HANYA System Health,
 * System Information, dan Activity Log.
 *
 * Backup & Restore sudah PINDAH ke halaman sendiri; test-nya ikut pindah
 * ke tests/Feature/BackupDatabaseTest.php.
 */
class PengaturanSistemTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin-test@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
    }

    private function makePendamping(): User
    {
        return User::create([
            'name' => 'Pendamping Test',
            'email' => 'pendamping-test@example.com',
            'password' => 'password123',
            'role' => 'pendamping',
        ]);
    }

    public function test_guest_cannot_access_pengaturan_sistem(): void
    {
        $response = $this->get(route('pengaturan-sistem.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_is_forbidden_from_pengaturan_sistem(): void
    {
        $pendamping = $this->makePendamping();

        $response = $this->actingAs($pendamping)->get(route('pengaturan-sistem.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_open_pengaturan_sistem(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('pengaturan-sistem.index'));

        $response->assertOk();

        // Judul & subtitle halaman — namanya diganti dari 'Pengaturan Sistem'
        // karena halaman ini bukan halaman konfigurasi.
        $response->assertSee('Monitoring &amp; Keamanan', false);
        $response->assertSee('Pantau kesehatan sistem dan riwayat aktivitas aplikasi.');
        $response->assertDontSee('Pengaturan Sistem');

        // Isi fiturnya TIDAK berubah sama sekali.
        $response->assertSee('System Health');
        $response->assertSee('System Information');
        $response->assertSee('Log Aktivitas');
        // Backup Database SUDAH TIDAK di halaman ini — sudah jadi halaman sendiri.
        $response->assertDontSee('Buat Backup Sekarang');
    }

    public function test_route_pengaturan_sistem_is_registered(): void
    {
        $this->assertTrue(Route::has('pengaturan-sistem.index'));

        // Route backup/restore lama SUDAH TIDAK ADA — sudah pindah ke
        // grup 'backup-database.*' (lihat BackupDatabaseTest).
        $this->assertFalse(Route::has('pengaturan-sistem.backup.store'));
        $this->assertFalse(Route::has('pengaturan-sistem.backup.download'));
        $this->assertFalse(Route::has('pengaturan-sistem.backup.destroy'));
        $this->assertFalse(Route::has('pengaturan-sistem.restore.show'));
        $this->assertFalse(Route::has('pengaturan-sistem.restore.store'));
    }

    public function test_successful_login_is_recorded_in_activity_log(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = $this->makeAdmin();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login',
            'user_id' => $user->id,
        ]);
    }

    public function test_logout_is_recorded_in_activity_log(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = $this->makeAdmin();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_password_change_is_recorded_without_storing_password_value(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::create([
            'name' => 'User Password Test',
            'email' => 'password-test@example.com',
            'password' => 'lamaPassword123',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'lamaPassword123',
            'password' => 'baruPassword456',
            'password_confirmation' => 'baruPassword456',
        ]);

        $response->assertRedirect(route('account.password.edit'));

        $log = ActivityLog::where('action', 'password_change')->where('user_id', $user->id)->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('lamaPassword123', $log->description);
        $this->assertStringNotContainsString('baruPassword456', $log->description);
    }

    public function test_activity_log_filter_by_user_and_date_range(): void
    {
        $admin = $this->makeAdmin();
        $otherUser = $this->makePendamping();

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'created',
            'model_type' => 'Participant',
            'model_id' => 1,
            'description' => 'LOG-MILIK-ADMIN-TARGET',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        ActivityLog::create([
            'user_id' => $otherUser->id,
            'action' => 'created',
            'model_type' => 'Participant',
            'model_id' => 2,
            'description' => 'LOG-MILIK-USER-LAIN-TIDAK-BOLEH-MUNCUL',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($admin)->get(route('pengaturan-sistem.index', [
            'user_id' => $admin->id,
            'tanggal_mulai' => now()->subDays(3)->toDateString(),
            'tanggal_akhir' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('LOG-MILIK-ADMIN-TARGET');
        $response->assertDontSee('LOG-MILIK-USER-LAIN-TIDAK-BOLEH-MUNCUL');
    }
}
