<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Modul Manajemen User (Tahap 3) — CRUD akun internal.
 *
 * Melengkapi StatusAkunUserTest (Tahap 2) yang menguji fondasinya; di sini
 * yang diuji adalah endpoint & aturannya. Test Tahap 2 tidak diubah.
 */
class ManajemenUserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function buat(string $role, string $email, bool $aktif = true, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => $aktif,
            'pendamping_id' => $pendampingId,
        ]);
    }

    /** Admin utama + satu Admin cadangan, supaya penjagaan "Admin terakhir" tidak ikut campur. */
    private function adminDenganCadangan(): User
    {
        $admin = $this->buat(User::ROLE_ADMIN, 'admin@uji.test');
        $this->buat(User::ROLE_ADMIN, 'admin-cadangan@uji.test');

        return $admin;
    }

    // ==========================================================
    // 1-2. AKSES
    // ==========================================================

    public function test_admin_dapat_membuka_manajemen_user(): void
    {
        $response = $this->actingAs($this->adminDenganCadangan())->get(route('manajemen-user.index'));

        $response->assertOk();
        $response->assertSee('Manajemen User');
        $response->assertSee('Kelola akun internal yang memiliki akses ke CahayaOS.');
    }

    public function test_non_admin_tidak_dapat_membuka_manajemen_user(): void
    {
        $this->get(route('manajemen-user.index'))->assertRedirect(route('login'));

        foreach ([User::ROLE_PIMPINAN, User::ROLE_PENDAMPING] as $role) {
            $this->actingAs($this->buat($role, $role . '@uji.test'))
                ->get(route('manajemen-user.index'))
                ->assertForbidden();
        }
    }

    // ==========================================================
    // 3-5. TAMBAH USER
    // ==========================================================

    public function test_admin_dapat_membuat_user(): void
    {
        $response = $this->actingAs($this->adminDenganCadangan())->post(route('manajemen-user.store'), [
            'name' => 'Budi Pimpinan',
            'email' => 'budi@uji.test',
            'role' => User::ROLE_PIMPINAN,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('manajemen-user.index'));

        $baru = User::where('email', 'budi@uji.test')->first();
        $this->assertNotNull($baru);
        $this->assertSame(User::ROLE_PIMPINAN, $baru->role);
        $this->assertTrue($baru->isActive());

        $this->assertDatabaseHas('activity_logs', ['action' => 'account_created', 'model_id' => $baru->id]);
    }

    public function test_email_duplicate_ditolak(): void
    {
        $admin = $this->adminDenganCadangan();
        $this->buat(User::ROLE_PENDAMPING, 'sudahada@uji.test');

        $this->actingAs($admin)->post(route('manajemen-user.store'), [
            'name' => 'Kembar',
            'email' => 'sudahada@uji.test',
            'role' => User::ROLE_PENDAMPING,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'sudahada@uji.test')->count());
    }

    public function test_password_di_hash_dan_tidak_pernah_plaintext(): void
    {
        $this->actingAs($this->adminDenganCadangan())->post(route('manajemen-user.store'), [
            'name' => 'Aman',
            'email' => 'aman@uji.test',
            'role' => User::ROLE_PENDAMPING,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ]);

        $baru = User::where('email', 'aman@uji.test')->first();

        $this->assertNotSame('rahasia12345', $baru->password, 'Password tidak boleh disimpan apa adanya');
        $this->assertTrue(Hash::check('rahasia12345', $baru->password));
        // Password TIDAK boleh bocor ke Activity Log.
        $this->assertSame(0, ActivityLog::where('description', 'like', '%rahasia12345%')->count());
    }

    public function test_konfirmasi_password_wajib_cocok(): void
    {
        $this->actingAs($this->adminDenganCadangan())->post(route('manajemen-user.store'), [
            'name' => 'Beda',
            'email' => 'beda@uji.test',
            'role' => User::ROLE_PENDAMPING,
            'password' => 'rahasia12345',
            'password_confirmation' => 'SALAH12345',
        ])->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', 'beda@uji.test')->first());
    }

    public function test_role_karangan_ditolak(): void
    {
        $this->actingAs($this->adminDenganCadangan())->post(route('manajemen-user.store'), [
            'name' => 'Palsu',
            'email' => 'palsu@uji.test',
            'role' => 'superadmin',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertSessionHasErrors('role');
    }

    // ==========================================================
    // 6-8. EDIT & ROLE
    // ==========================================================

    public function test_admin_dapat_mengedit_user(): void
    {
        $admin = $this->adminDenganCadangan();
        $target = $this->buat(User::ROLE_PIMPINAN, 'lama@uji.test');

        $this->actingAs($admin)->put(route('manajemen-user.update', $target), [
            'name' => 'Nama Baru',
            'email' => 'baru@uji.test',
            'role' => User::ROLE_PIMPINAN,
        ])->assertRedirect(route('manajemen-user.index'));

        $target->refresh();
        $this->assertSame('Nama Baru', $target->name);
        $this->assertSame('baru@uji.test', $target->email);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account_updated', 'model_id' => $target->id]);
    }

    public function test_role_dapat_berubah(): void
    {
        $admin = $this->adminDenganCadangan();
        $target = $this->buat(User::ROLE_PIMPINAN, 'naik@uji.test');

        $this->actingAs($admin)->put(route('manajemen-user.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertSame(User::ROLE_ADMIN, $target->fresh()->role);

        $log = ActivityLog::where('action', 'role_changed')->where('model_id', $target->id)->first();
        $this->assertNotNull($log);
        // Role lama & baru ikut tercatat.
        $this->assertStringContainsString('Pimpinan', $log->description);
        $this->assertStringContainsString('Admin', $log->description);
    }

    public function test_pendamping_jadi_admin_mengosongkan_pendamping_id(): void
    {
        $admin = $this->adminDenganCadangan();

        $pendamping = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '0812', 'email' => 'p@uji.test', 'status' => 'Aktif',
        ]);
        $target = $this->buat(User::ROLE_PENDAMPING, 'pdg@uji.test', pendampingId: $pendamping->id);
        $this->assertSame($pendamping->id, $target->pendamping_id);

        $this->actingAs($admin)->put(route('manajemen-user.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => User::ROLE_ADMIN,
        ]);

        $target->refresh();
        $this->assertSame(User::ROLE_ADMIN, $target->role);
        $this->assertNull($target->pendamping_id, 'Tautan ke data Pendamping harus dilepas');

        // Data Pendamping-nya SENDIRI tidak boleh terhapus / berubah status.
        $pendamping->refresh();
        $this->assertSame('Aktif', $pendamping->status, 'Status kepegawaian Pendamping tidak boleh ikut berubah');
        $this->assertDatabaseHas('pendampings', ['id' => $pendamping->id]);
    }

    public function test_pendamping_jadi_pimpinan_juga_mengosongkan_pendamping_id(): void
    {
        $admin = $this->adminDenganCadangan();
        $pendamping = Pendamping::create([
            'nama' => 'Pendamping Dua', 'nomor_hp' => '0813', 'email' => 'p2@uji.test', 'status' => 'Aktif',
        ]);
        $target = $this->buat(User::ROLE_PENDAMPING, 'pdg2@uji.test', pendampingId: $pendamping->id);

        $this->actingAs($admin)->put(route('manajemen-user.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'role' => User::ROLE_PIMPINAN,
        ]);

        $this->assertNull($target->fresh()->pendamping_id);
    }

    // ==========================================================
    // 9-11. NONAKTIF / AKTIF
    // ==========================================================

    public function test_user_dapat_dinonaktifkan(): void
    {
        $admin = $this->adminDenganCadangan();
        $target = $this->buat(User::ROLE_PENDAMPING, 'matikan@uji.test');

        $this->actingAs($admin)->post(route('manajemen-user.status', $target))
            ->assertRedirect(route('manajemen-user.index'));

        $this->assertFalse($target->fresh()->isActive());
        $this->assertDatabaseHas('activity_logs', ['action' => 'account_deactivated', 'model_id' => $target->id]);
        // Akun TIDAK dihapus.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_user_nonaktif_tidak_dapat_login(): void
    {
        $admin = $this->adminDenganCadangan();
        $target = $this->buat(User::ROLE_PENDAMPING, 'nologin@uji.test');

        $this->actingAs($admin)->post(route('manajemen-user.status', $target));
        auth()->logout();

        $this->post('/login', ['email' => 'nologin@uji.test', 'password' => 'password123']);

        $this->assertGuest();
    }

    public function test_user_dapat_diaktifkan_kembali_tanpa_mengubah_apapun(): void
    {
        $admin = $this->adminDenganCadangan();
        $pendamping = Pendamping::create([
            'nama' => 'P3', 'nomor_hp' => '0814', 'email' => 'p3@uji.test', 'status' => 'Aktif',
        ]);
        $target = $this->buat(User::ROLE_PENDAMPING, 'hidupkan@uji.test', aktif: false, pendampingId: $pendamping->id);

        $passwordSebelum = $target->password;

        $this->actingAs($admin)->post(route('manajemen-user.status', $target));

        $target->refresh();
        $this->assertTrue($target->isActive());
        // Password, role, dan pendamping_id TIDAK berubah.
        $this->assertSame($passwordSebelum, $target->password);
        $this->assertSame(User::ROLE_PENDAMPING, $target->role);
        $this->assertSame($pendamping->id, $target->pendamping_id);
        $this->assertTrue(Hash::check('password123', $target->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account_restored', 'model_id' => $target->id]);
    }

    // ==========================================================
    // 12-13. ADMIN AKTIF TERAKHIR
    // ==========================================================

    public function test_admin_aktif_terakhir_tidak_dapat_dinonaktifkan(): void
    {
        $admin = $this->buat(User::ROLE_ADMIN, 'satu2nya@uji.test');
        $lain = $this->buat(User::ROLE_ADMIN, 'admin2@uji.test');

        // Nonaktifkan Admin kedua dulu -> tersisa satu Admin aktif.
        $this->actingAs($admin)->post(route('manajemen-user.status', $lain));
        $this->assertFalse($lain->fresh()->isActive());

        // Admin yang login mencoba menonaktifkan dirinya sendiri -> ditolak
        // oleh self-protection, dan tetap aktif.
        $this->actingAs($admin)->post(route('manajemen-user.status', $admin))
            ->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isActive());

        // Dari Admin lain pun tetap ditolak — penjagaan Admin aktif terakhir.
        $penguji = $this->buat(User::ROLE_ADMIN, 'penguji@uji.test', aktif: false);
        $penguji->is_active = true;
        $penguji->save();
        $this->actingAs($penguji)->post(route('manajemen-user.status', $penguji))
            ->assertSessionHasErrors('status');
    }

    /**
     * Admin aktif terakhir tidak boleh diturunkan role-nya — berlapis.
     *
     * Sejak Tahap 4, cabang ini TIDAK BISA LAGI dicapai lewat HTTP, dan itu
     * memang tujuannya:
     *   - Pelaku harus Admin AKTIF (kalau nonaktif, middleware
     *     EnsureUserIsActive langsung memutus sesinya). Dulu celah inilah
     *     yang dipakai test ini untuk mencapai cabang tersebut.
     *   - Kalau pelaku Admin aktif dan target orang lain, otomatis ada >= 2
     *     Admin aktif, sehingga target bukan "Admin aktif terakhir".
     *   - Kalau pelaku menurunkan dirinya sendiri, self-protection yang
     *     menangkap lebih dulu.
     *
     * Jadi yang diuji di sini: kedua lapis penjagaannya benar-benar berdiri.
     */
    public function test_admin_aktif_terakhir_tidak_dapat_diturunkan_rolenya(): void
    {
        $terakhir = $this->buat(User::ROLE_ADMIN, 'terakhir@uji.test');
        $this->buat(User::ROLE_ADMIN, 'pelaku-nonaktif@uji.test', aktif: false);

        // Hanya satu Admin yang terhitung aktif.
        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count());

        // LAPIS 1 — backstop di service (Tahap 2): tetap menolak.
        $guard = new \App\Services\UserAccountGuard();
        $this->assertTrue($guard->adminAktifTerakhir($terakhir));
        $this->assertFalse($guard->bolehUbahRole($terakhir, User::ROLE_PIMPINAN));
        $this->assertTrue($guard->bolehUbahRole($terakhir, User::ROLE_ADMIN), 'Tetap Admin harus boleh');

        // LAPIS 2 — lewat HTTP, satu-satunya pelaku yang mungkin adalah
        // dirinya sendiri, dan itu ditolak self-protection.
        $this->actingAs($terakhir)->put(route('manajemen-user.update', $terakhir), [
            'name' => $terakhir->name,
            'email' => $terakhir->email,
            'role' => User::ROLE_PIMPINAN,
        ])->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $terakhir->fresh()->role, 'Role Admin terakhir tidak boleh turun');

        // Celah lama sudah tertutup: Admin nonaktif TIDAK bisa lagi memakai
        // sesinya untuk menurunkan role siapa pun.
        $pelakuNonaktif = User::where('email', 'pelaku-nonaktif@uji.test')->first();
        $this->actingAs($pelakuNonaktif)->put(route('manajemen-user.update', $terakhir), [
            'name' => $terakhir->name,
            'email' => $terakhir->email,
            'role' => User::ROLE_PIMPINAN,
        ])->assertRedirect(route('login'));

        $this->assertSame(User::ROLE_ADMIN, $terakhir->fresh()->role);
    }

    // ==========================================================
    // 14-15. RESET PASSWORD
    // ==========================================================

    public function test_admin_dapat_reset_password_user(): void
    {
        $admin = $this->adminDenganCadangan();
        $target = $this->buat(User::ROLE_PENDAMPING, 'reset@uji.test');
        $passwordLama = $target->password;

        $response = $this->actingAs($admin)->post(route('manajemen-user.reset-password', $target));

        $response->assertRedirect(route('manajemen-user.index'));
        $response->assertSessionHas('password_baru');

        $target->refresh();
        $this->assertNotSame($passwordLama, $target->password, 'Password lama harus langsung tidak berlaku');
        $this->assertFalse(Hash::check('password123', $target->password));

        // Password baru yang ditampilkan sekali itu memang password yang tersimpan.
        $this->assertTrue(Hash::check(session('password_baru'), $target->password));
    }

    public function test_reset_password_tercatat_tanpa_memuat_password(): void
    {
        $admin = $this->adminDenganCadangan();
        $target = $this->buat(User::ROLE_PENDAMPING, 'resetlog@uji.test');

        $this->actingAs($admin)->post(route('manajemen-user.reset-password', $target));
        $passwordBaru = session('password_baru');

        $log = ActivityLog::where('action', 'password_reset')->where('model_id', $target->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString($passwordBaru, $log->description);
        $this->assertStringNotContainsString('password123', $log->description);

        // Tidak ada satu pun baris log yang memuat password itu.
        $this->assertSame(0, ActivityLog::where('description', 'like', '%' . $passwordBaru . '%')->count());
    }

    // ==========================================================
    // 16-17. SELF-PROTECTION & KEAMANAN DATA
    // ==========================================================

    public function test_user_tidak_dapat_menonaktifkan_dirinya_sendiri(): void
    {
        $admin = $this->adminDenganCadangan(); // ada Admin cadangan, jadi bukan Admin terakhir

        $this->actingAs($admin)->post(route('manajemen-user.status', $admin))
            ->assertSessionHasErrors('status');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_admin_tidak_dapat_mengubah_role_dirinya_sendiri(): void
    {
        $admin = $this->adminDenganCadangan();

        $this->actingAs($admin)->put(route('manajemen-user.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => User::ROLE_PIMPINAN,
        ])->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    /** Mengelola satu akun tidak boleh menyentuh akun lain atau datanya. */
    public function test_user_existing_tetap_aman(): void
    {
        $admin = $this->adminDenganCadangan();

        $pendamping = Pendamping::create([
            'nama' => 'Tak Tersentuh', 'nomor_hp' => '0815', 'email' => 'aman-p@uji.test', 'status' => 'Aktif',
        ]);
        $lain = $this->buat(User::ROLE_PENDAMPING, 'takdisentuh@uji.test', pendampingId: $pendamping->id);
        Participant::create([
            'nama_peserta' => 'Peserta Uji',
            'nomor_hp' => '0816',
            'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Makanan & Minuman',
            'tanggal_mulai_program' => now()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendamping->id,
        ]);

        $sebelum = $lain->only(['name', 'email', 'role', 'is_active', 'pendamping_id', 'password']);
        $jumlahUser = User::count();

        // Lakukan berbagai aksi pada TARGET LAIN.
        $target = $this->buat(User::ROLE_PIMPINAN, 'target@uji.test');
        $this->actingAs($admin)->put(route('manajemen-user.update', $target), [
            'name' => 'Diubah', 'email' => 'diubah@uji.test', 'role' => User::ROLE_PENDAMPING,
        ]);
        $this->actingAs($admin)->post(route('manajemen-user.status', $target));
        $this->actingAs($admin)->post(route('manajemen-user.reset-password', $target));

        // Akun lain sama sekali tidak berubah, dan tidak ada yang terhapus.
        $this->assertSame($sebelum, $lain->fresh()->only(['name', 'email', 'role', 'is_active', 'pendamping_id', 'password']));
        $this->assertSame($jumlahUser + 1, User::count(), 'Tidak boleh ada akun yang hilang');
        $this->assertDatabaseHas('participants', ['nama_peserta' => 'Peserta Uji', 'pendamping_id' => $pendamping->id]);
    }

    // ==========================================================
    // FILTER
    // ==========================================================

    public function test_filter_dapat_dikombinasikan(): void
    {
        $admin = $this->adminDenganCadangan();
        $this->buat(User::ROLE_PENDAMPING, 'andi.aktif@uji.test');
        $this->buat(User::ROLE_PENDAMPING, 'andi.mati@uji.test', aktif: false);
        $this->buat(User::ROLE_PIMPINAN, 'andi.pimpinan@uji.test');

        $html = $this->actingAs($admin)->get(route('manajemen-user.index', [
            'cari' => 'andi', 'role' => User::ROLE_PENDAMPING, 'status' => 'aktif',
        ]))->getContent();
        $main = substr($html, strpos($html, '<main'));

        $this->assertStringContainsString('andi.aktif@uji.test', $main);
        $this->assertStringNotContainsString('andi.mati@uji.test', $main);
        $this->assertStringNotContainsString('andi.pimpinan@uji.test', $main);
    }
}
