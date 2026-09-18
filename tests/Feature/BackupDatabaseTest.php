<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Test untuk halaman Backup Database (dipisah dari Pengaturan Sistem).
 *
 * CATATAN PENTING: environment testing pakai SQLite in-memory (lihat
 * phpunit.xml: DB_CONNECTION=sqlite, DB_DATABASE=:memory:), BUKAN MySQL.
 * Backup/Restore asli pakai sintaks khusus MySQL (SHOW TABLES, SHOW
 * CREATE TABLE, dump SQL lewat DB::unprepared) yang TIDAK kompatibel
 * dengan SQLite — jadi test di sini SENGAJA HANYA menguji jalur
 * VALIDASI/KEAMANAN restore (yang berhenti SEBELUM DB::unprepared
 * dipanggil), TIDAK PERNAH menjalankan restore sungguhan. Ini konsisten
 * dengan instruksi: jangan test restore destruktif.
 */
class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin Test',
            'email' => 'admin-backup@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);
    }

    private function makePendamping(): User
    {
        return User::create([
            'name' => 'Pendamping Test',
            'email' => 'pendamping-backup@example.com',
            'password' => 'password123',
            'role' => 'pendamping',
        ]);
    }

    public function test_route_backup_database_is_registered(): void
    {
        $this->assertTrue(Route::has('backup-database.index'));
        $this->assertTrue(Route::has('backup-database.store'));
        $this->assertTrue(Route::has('backup-database.download'));
        $this->assertTrue(Route::has('backup-database.destroy'));
        $this->assertTrue(Route::has('backup-database.restore.show'));
        $this->assertTrue(Route::has('backup-database.restore.store'));
    }

    public function test_guest_cannot_access_backup_database(): void
    {
        $response = $this->get(route('backup-database.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_is_forbidden_from_backup_database(): void
    {
        $pendamping = $this->makePendamping();

        $response = $this->actingAs($pendamping)->get(route('backup-database.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_open_backup_database(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('backup-database.index'));

        $response->assertOk();
        $response->assertSee('Daftar File Backup');
        $response->assertSee('Buat Backup Sekarang');
    }

    public function test_non_admin_cannot_access_restore_page(): void
    {
        $pendamping = $this->makePendamping();

        $response = $this->actingAs($pendamping)->get(route('backup-database.restore.show'));

        $response->assertForbidden();
    }

    public function test_admin_can_open_restore_page(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('backup-database.restore.show'));

        $response->assertOk();
        $response->assertSee('RESTORE DATABASE');
    }

    public function test_restore_upload_with_wrong_confirmation_text_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $admin = $this->makeAdmin();
        $file = UploadedFile::fake()->create('backup.sql', 10);

        $response = $this->actingAs($admin)->post(route('backup-database.restore.store'), [
            'file' => $file,
            'konfirmasi' => 'teks yang salah',
        ]);

        $response->assertSessionHasErrors('konfirmasi');
    }

    public function test_restore_upload_without_confirmation_field_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $admin = $this->makeAdmin();
        $file = UploadedFile::fake()->create('backup.sql', 10);

        $response = $this->actingAs($admin)->post(route('backup-database.restore.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('konfirmasi');
    }

    public function test_restore_rejects_non_sql_file_extension(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $admin = $this->makeAdmin();
        $file = UploadedFile::fake()->create('malicious.txt', 10);

        $response = $this->actingAs($admin)->post(route('backup-database.restore.store'), [
            'file' => $file,
            'konfirmasi' => 'RESTORE DATABASE',
        ]);

        $response->assertSessionHasErrors('file');
    }

    /**
     * Kolom "Dibuat" harus memakai timezone aplikasi (WIB), bukan UTC.
     *
     * Sejak Carbon 3, createFromTimestamp() default-nya UTC — beda dari
     * Carbon 2 yang mengikuti timezone default. Tanpa timezone eksplisit,
     * kolom ini tampil mundur 7 jam dan bertentangan dengan jam pada nama
     * file backup itu sendiri.
     */
    public function test_kolom_dibuat_memakai_timezone_aplikasi(): void
    {
        $admin = $this->makeAdmin();

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Nama file WAJIB cocok pola yang diterima listBackups().
        $nama = 'backup-2020-01-02_030405.sql';
        $path = $dir . '/' . $nama;
        $waktu = \Carbon\Carbon::parse('2020-01-02 03:04:05', config('app.timezone'));

        file_put_contents($path, '-- berkas uji');
        touch($path, $waktu->getTimestamp());

        try {
            $response = $this->actingAs($admin)->get(route('backup-database.index'));

            $response->assertOk();
            $response->assertSee($nama);
            // Jam yang tampil harus sama dengan jam pada nama file (WIB),
            // bukan mundur beberapa jam karena dibaca sebagai UTC.
            $response->assertSee('02-01-2020 03:04');
        } finally {
            @unlink($path);
        }
    }

    /**
     * Path traversal HARUS ditolak — nama file backup wajib cocok pola
     * "backup-YYYY-MM-DD_HHMMSS.sql" / "pre-restore-backup-...".
     */
    public function test_download_rejects_invalid_filename(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('backup-database.download', 'sembarang-file.sql'));

        $response->assertNotFound();
    }
}
