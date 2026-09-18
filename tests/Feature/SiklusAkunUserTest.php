<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SIKLUS AKUN: AKTIF -> NONAKTIF -> AKTIF LAGI. TIDAK ADA UJUNG YANG HILANG.
 *
 * ==========================================================================
 * KEBIJAKAN YANG BERLAKU
 * ==========================================================================
 *
 *      AKTIF
 *        |  Nonaktifkan
 *        v
 *      NONAKTIF ......... tidak bisa login, data & histori tetap utuh
 *        |  Aktifkan Kembali
 *        v
 *      AKTIF
 *
 * Istilahnya sengaja BERBEDA dari data bisnis, yang memakai Arsipkan /
 * Pulihkan. Akun soal AKSES LOGIN; arsip soal DATA. Satu kata untuk dua hal
 * itu membuat Admin mengira Manajemen User adalah bagian dari Arsip Data.
 *
 * Akun TIDAK PERNAH dihapus. Berkas ini menggantikan HapusAkunUserTest, yang
 * dulu justru mengunci kebalikannya: bahwa akun dihapus permanen dan tidak
 * ada jalur pemulihan.
 *
 * ==========================================================================
 * KENAPA PENGHAPUSAN DILEPAS
 * ==========================================================================
 *
 * Seluruh foreign key ke `users` ber-ON DELETE SET NULL. Membuang barisnya
 * tidak menghapus data bisnis maupun catatan aktivitas — tapi membuat kolom
 * penunjuknya NULL. Jejak "siapa mengerjakan ini" lalu hanya tersisa sebagai
 * teks di kolom snapshot, tidak lagi sebagai relasi yang bisa ditelusuri, dan
 * itu tidak bisa dibatalkan.
 *
 * Menonaktifkan mencapai satu-satunya tujuan yang benar-benar dibutuhkan —
 * mencabut akses — tanpa kehilangan apa pun.
 *
 * ==========================================================================
 * YANG DIJAGA DI SINI
 * ==========================================================================
 *
 * Bukan hanya "tombolnya bekerja", melainkan bahwa menonaktifkan satu akun
 * tidak menarik apa pun lain ikut serta: peserta binaannya, anggota koperasi,
 * catatan pendampingan, dan seluruh Activity Log harus tetap pada tempatnya.
 * Dua pengaman lama tetap berlaku, karena keduanya bisa mengunci seluruh
 * sistem: tidak boleh menonaktifkan diri sendiri, dan tidak boleh
 * menonaktifkan Admin aktif terakhir.
 */
class SiklusAkunUserTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'password123';

    private function buat(string $role, string $email, ?int $pendampingId = null, bool $aktif = true): User
    {
        return User::create([
            'name' => 'Pengguna ' . substr(md5($email), 0, 6),
            'email' => $email,
            'password' => self::SANDI,
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => $aktif,
        ]);
    }

    /** Admin kedua supaya penolakan "Admin aktif terakhir" tidak ikut memicu. */
    private function adminPelaku(string $email = 'admin@siklus.test'): User
    {
        $this->buat('admin', 'admin.cadangan@siklus.test');

        return $this->buat('admin', $email);
    }

    private function ubahStatus(User $pelaku, User $sasaran)
    {
        return $this->actingAs($pelaku)->post(route('manajemen-user.status', $sasaran));
    }

    // =====================================================================
    // TIDAK ADA JALUR PENGHAPUSAN
    // =====================================================================

    /**
     * Diuji di tingkat RUTE, bukan tombol.
     *
     * Tombol bisa disembunyikan sementara request-nya tetap diterima — dan
     * kebijakan ini sudah dua kali berbalik arah, jadi yang dikunci harus
     * yang paling sulit dilewati.
     */
    public function test_tidak_ada_rute_penghapusan_akun(): void
    {
        $this->assertFalse(Route::has('manajemen-user.destroy'));
    }

    public function test_halaman_tidak_lagi_menawarkan_hapus_permanen(): void
    {
        $admin = $this->adminPelaku();

        $this->actingAs($admin)
            ->get(route('manajemen-user.index'))
            ->assertOk()
            ->assertDontSee('Hapus Permanen')
            ->assertSee('Nonaktifkan');
    }

    /**
     * Kalimat konfirmasinya menjanjikan hal yang benar.
     *
     * Ini bukan soal rapi-rapi kata: Admin memutuskan menekan tombol atau
     * tidak berdasarkan kalimat ini. Konfirmasi yang berbunyi "tidak dapat
     * dipulihkan" pada tindakan yang sebenarnya bisa dibatalkan akan membuat
     * Admin ragu menonaktifkan akun yang seharusnya dinonaktifkan.
     */
    public function test_konfirmasi_menjanjikan_data_tetap_tersimpan_dan_dapat_dipulihkan(): void
    {
        $admin = $this->adminPelaku();

        $halaman = $this->actingAs($admin)->get(route('manajemen-user.index'))->assertOk();

        $halaman->assertSee('tidak dapat login lagi', false);
        $halaman->assertSee('data dan histori tetap tersimpan', false);
        $halaman->assertSee('dapat dipulihkan oleh Admin', false);

        $halaman->assertDontSee('TIDAK DAPAT DIPULIHKAN', false);
    }

    /**
     * Baris akun NONAKTIF menawarkan "Aktifkan Kembali", bukan Reset Password.
     *
     * Password sementara untuk akun yang tidak bisa login tetap tidak bisa
     * dipakai masuk — Admin akan menyerahkan sandi yang gagal dan mengira
     * sistemnya rusak. Urutannya: aktifkan dulu, baru reset kalau perlu.
     *
     * Kata "Pulihkan" SENGAJA tidak dipakai di sini. Itu istilah untuk data
     * bisnis di Arsip Data. Akun dan data punya siklus yang berbeda, dan
     * memakai satu kata untuk keduanya membuat Admin mengira Manajemen User
     * adalah bagian dari Arsip Data.
     */
    public function test_baris_akun_nonaktif_menawarkan_aktifkan_kembali(): void
    {
        $admin = $this->adminPelaku();
        $this->buat('pendamping', 'rosa@siklus.test', aktif: false);

        // Disaring ke akun nonaktif saja supaya tombol milik baris Admin yang
        // aktif tidak ikut terbaca dan membuat assertion ini lulus palsu.
        $halaman = $this->actingAs($admin)
            ->get(route('manajemen-user.index', ['status' => 'nonaktif']))
            ->assertOk();

        $halaman->assertSee('Aktifkan Kembali');
        $halaman->assertSee('Aktifkan kembali akun ini agar dapat digunakan?', false);
        $halaman->assertDontSee('Reset Password');

        // Istilah data bisnis tidak boleh bocor ke halaman akun.
        $halaman->assertDontSee('Pulihkan');
        $halaman->assertDontSee('Arsipkan');
    }

    /** Kontrol pembanding: baris akun AKTIF memang menawarkan Reset Password. */
    public function test_baris_akun_aktif_menawarkan_reset_password(): void
    {
        $admin = $this->adminPelaku();

        $this->actingAs($admin)
            ->get(route('manajemen-user.index', ['status' => 'aktif']))
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertDontSee('Aktifkan Kembali');
    }

    // =====================================================================
    // 1-5. NONAKTIFKAN, TETAP ADA, AKTIFKAN KEMBALI, BISA LOGIN LAGI
    // =====================================================================

    public function test_admin_dapat_menonaktifkan_user_lain(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');

        $this->ubahStatus($admin, $sasaran)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('manajemen-user.index'))
            ->assertSessionHas('success', 'Akun berhasil dinonaktifkan.');

        $this->assertFalse($sasaran->fresh()->isActive());
    }

    public function test_user_nonaktif_tidak_dapat_login(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');

        $this->ubahStatus($admin, $sasaran);
        Auth::logout();

        $this->post(route('login'), [
            'email' => $sasaran->email,
            'password' => self::SANDI,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** Barisnya tetap ada, DAN tidak diam-diam ikut di-soft-delete. */
    public function test_user_nonaktif_tetap_ada_di_database(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pimpinan', 'pimpinan@siklus.test');

        $this->ubahStatus($admin, $sasaran);

        $this->assertDatabaseHas('users', [
            'id' => $sasaran->id,
            'email' => 'pimpinan@siklus.test',
            'deleted_at' => null,
        ]);

        $segar = $sasaran->fresh();

        // Nama, email, dan role TIDAK berubah — menonaktifkan hanya menyentuh
        // satu kolom.
        $this->assertSame($sasaran->name, $segar->name);
        $this->assertSame($sasaran->email, $segar->email);
        $this->assertSame($sasaran->role, $segar->role);
    }

    public function test_user_nonaktif_dapat_diaktifkan_kembali(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test', aktif: false);

        $this->ubahStatus($admin, $sasaran)
            ->assertSessionHas('success', 'Akun berhasil diaktifkan kembali.');

        $this->assertTrue($sasaran->fresh()->isActive());
    }

    /**
     * Setelah dipulihkan, sandi LAMANYA masih berlaku.
     *
     * Kalau menonaktifkan diam-diam merusak hash-nya, akunnya akan terlihat
     * pulih tapi tetap tidak bisa dipakai — dan Admin akan menyimpulkan
     * pemulihannya gagal.
     */
    public function test_setelah_diaktifkan_kembali_user_dapat_login(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');

        $this->ubahStatus($admin, $sasaran);
        $this->ubahStatus($admin, $sasaran->fresh());
        Auth::logout();

        $this->post(route('login'), [
            'email' => $sasaran->email,
            'password' => self::SANDI,
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($sasaran->fresh());
    }

    // =====================================================================
    // 6. HISTORI TETAP ADA
    // =====================================================================

    public function test_catatan_aktivitas_lama_tetap_ada_dan_masih_menyebut_pelakunya(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');

        ActivityLog::record(
            action: 'login',
            modelType: 'Auth',
            modelId: $sasaran->id,
            description: 'Login berhasil',
            userId: $sasaran->id,
        );

        $this->ubahStatus($admin, $sasaran);

        $jejak = ActivityLog::where('action', 'login')->latest('id')->first();

        $this->assertNotNull($jejak);
        $this->assertSame(
            $sasaran->id,
            $jejak->user_id,
            'Relasi ke pelakunya harus tetap utuh — inilah yang hilang kalau akunnya dihapus.'
        );
    }

    public function test_penonaktifan_tercatat_lengkap_di_activity_log(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('tim_koperasi', 'koperasi@siklus.test');

        $this->ubahStatus($admin, $sasaran);

        $jejak = ActivityLog::where('action', 'account_deactivated')->latest('id')->first();

        $this->assertNotNull($jejak);

        // Snapshot sasarannya: nama, email, role.
        $this->assertStringContainsString($sasaran->name, $jejak->description);
        $this->assertStringContainsString($sasaran->email, $jejak->description);
        $this->assertStringContainsString('Tim Koperasi', $jejak->description);

        // Pelakunya dan waktunya.
        $this->assertSame($admin->id, $jejak->user_id);
        $this->assertSame($admin->name, $jejak->actor_nama);
        $this->assertSame($admin->email, $jejak->actor_email);
        $this->assertNotNull($jejak->created_at);
    }

    public function test_pengaktifan_kembali_tercatat_di_activity_log(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pimpinan', 'pimpinan@siklus.test', aktif: false);

        $this->ubahStatus($admin, $sasaran);

        $jejak = ActivityLog::where('action', 'account_restored')->latest('id')->first();

        $this->assertNotNull($jejak, 'Pengaktifan kembali harus meninggalkan jejak.');
        $this->assertStringContainsString('diaktifkan kembali', $jejak->description);
        $this->assertStringContainsString($sasaran->email, $jejak->description);
        $this->assertSame($admin->id, $jejak->user_id);
    }

    /** Menonaktifkan tidak boleh menyentuh satu baris pun Activity Log lama. */
    public function test_activity_log_lama_tidak_berkurang(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');

        foreach (range(1, 5) as $i) {
            ActivityLog::record(
                action: 'login',
                modelType: 'Auth',
                modelId: $sasaran->id,
                description: 'Login ke-' . $i,
                userId: $sasaran->id,
            );
        }

        $sebelum = ActivityLog::count();

        $this->ubahStatus($admin, $sasaran);

        $this->assertGreaterThan(
            $sebelum,
            ActivityLog::count(),
            'Log hanya boleh bertambah — tidak pernah berkurang.'
        );
        $this->assertSame(5, ActivityLog::where('action', 'login')->count());
    }

    // =====================================================================
    // 7-8. DATA BISNIS TIDAK IKUT TERBAWA
    // =====================================================================

    public function test_data_peserta_tidak_ikut_terhapus_saat_pendamping_dinonaktifkan(): void
    {
        $admin = $this->adminPelaku();

        $pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);
        $user = $this->buat('pendamping', 'rosa@siklus.test', $pendamping->id);

        $peserta = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendamping->id,
        ]);

        $catatan = PendampinganRecord::create([
            'participant_id' => $peserta->id,
            'pendamping_id' => $pendamping->id,
            'tanggal' => now()->subMonth()->toDateString(),
            'jenis_kegiatan' => 'Kunjungan',
            'materi' => 'Pembukuan sederhana',
        ]);

        $this->ubahStatus($admin, $user);

        $this->assertDatabaseHas('participants', ['id' => $peserta->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('pendampingan_records', ['id' => $catatan->id, 'deleted_at' => null]);

        // Tautan peserta ke pendampingnya juga tetap — status kepegawaian di
        // tabel `pendampings` adalah urusan yang berbeda dari akses login.
        $this->assertSame($pendamping->id, $peserta->fresh()->pendamping_id);
        $this->assertDatabaseHas('pendampings', ['id' => $pendamping->id, 'status' => 'Aktif']);
    }

    public function test_data_koperasi_tidak_ikut_terhapus_saat_tim_koperasi_dinonaktifkan(): void
    {
        $admin = $this->adminPelaku();
        $user = $this->buat('tim_koperasi', 'koperasi@siklus.test');

        $anggota = KoperasiMember::create([
            'nama' => 'Bu Tuti',
            'nomor_hp' => '081299990000',
            'tanggal_bergabung' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
        ]);

        $this->ubahStatus($admin, $user);

        $this->assertDatabaseHas('koperasi_members', ['id' => $anggota->id, 'deleted_at' => null]);
        $this->assertSame(1, KoperasiMember::count());
    }

    // =====================================================================
    // 9-10. PENGAMAN YANG TIDAK BOLEH HILANG
    // =====================================================================

    public function test_admin_tidak_dapat_menonaktifkan_dirinya_sendiri(): void
    {
        $admin = $this->adminPelaku();

        $this->ubahStatus($admin, $admin)->assertSessionHasErrors('status');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_admin_aktif_terakhir_tidak_dapat_dinonaktifkan(): void
    {
        // Sengaja TANPA admin cadangan: inilah satu-satunya Admin aktif.
        $satunya = $this->buat('admin', 'satunya@siklus.test');
        $lain = $this->buat('pimpinan', 'pimpinan@siklus.test');

        $this->ubahStatus($lain, $satunya)->assertForbidden();

        // Dan Admin itu sendiri pun tidak bisa — lewat jalur "diri sendiri".
        $this->ubahStatus($satunya, $satunya)->assertSessionHasErrors('status');

        $this->assertTrue($satunya->fresh()->isActive());
    }

    /**
     * Admin yang sedang NONAKTIF tidak bisa menonaktifkan siapa pun.
     *
     * Penjagaannya ternyata berlapis, dan lapisan terluarnya yang bekerja
     * lebih dulu: middleware menolak akun nonaktif sebelum controller sempat
     * dipanggil sama sekali. Jadi yang diuji di sini bukan pesan penolakan
     * dari controller — request-nya memang tidak pernah sampai ke sana.
     *
     * Yang dikunci: akun nonaktif tidak bisa bertindak, dan sasarannya tetap
     * aktif.
     */
    public function test_admin_nonaktif_tidak_bisa_menonaktifkan_siapa_pun(): void
    {
        $satunya = $this->buat('admin', 'satunya@siklus.test');
        $adminNonaktif = $this->buat('admin', 'tidur@siklus.test', aktif: false);

        $this->ubahStatus($adminNonaktif, $satunya)
            ->assertSessionHasErrors();

        $this->assertStringContainsString(
            'dinonaktifkan',
            collect(session('errors')->all())->implode(' '),
            'Penolakannya harus menyebut sebabnya: akun pelakunya sendiri nonaktif.'
        );

        $this->assertTrue(
            $satunya->fresh()->isActive(),
            'Admin aktif terakhir tetap aktif.'
        );
    }

    // =====================================================================
    // 11-12. RESET PASSWORD & OTORISASI
    // =====================================================================

    public function test_reset_password_tetap_bekerja(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');
        $hashLama = $sasaran->password;

        $this->actingAs($admin)
            ->post(route('manajemen-user.reset-password', $sasaran))
            ->assertSessionHasNoErrors();

        $this->assertNotSame($hashLama, $sasaran->fresh()->password);

        // Sandi lamanya langsung tidak berlaku.
        Auth::logout();
        $this->post(route('login'), [
            'email' => $sasaran->email,
            'password' => self::SANDI,
        ])->assertSessionHasErrors('email');
    }

    /** Password akun LAIN tidak ikut berubah. */
    public function test_reset_password_tidak_menyentuh_akun_lain(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');
        $penonton = $this->buat('pimpinan', 'pimpinan@siklus.test');
        $hashPenonton = $penonton->password;

        $this->actingAs($admin)->post(route('manajemen-user.reset-password', $sasaran));

        $this->assertSame($hashPenonton, $penonton->fresh()->password);
    }

    /** Menonaktifkan TIDAK menyentuh password siapa pun. */
    public function test_menonaktifkan_tidak_mengubah_password(): void
    {
        $admin = $this->adminPelaku();
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');
        $hashLama = $sasaran->password;

        $this->ubahStatus($admin, $sasaran);

        $this->assertSame($hashLama, $sasaran->fresh()->password);
    }

    #[DataProvider('peranBukanAdmin')]
    public function test_selain_admin_ditolak_403(string $role): void
    {
        $this->buat('admin', 'admin@siklus.test');
        $pelaku = $this->buat($role, $role . '@siklus.test');
        $sasaran = $this->buat('pendamping', 'sasaran@siklus.test');

        $this->ubahStatus($pelaku, $sasaran)->assertForbidden();

        $this->assertTrue($sasaran->fresh()->isActive());
    }

    public static function peranBukanAdmin(): array
    {
        return [
            'pimpinan' => ['pimpinan'],
            'pendamping' => ['pendamping'],
            'tim koperasi' => ['tim_koperasi'],
        ];
    }

    public function test_tamu_tidak_dapat_mengubah_status_akun(): void
    {
        $sasaran = $this->buat('pendamping', 'rosa@siklus.test');

        $this->post(route('manajemen-user.status', $sasaran))
            ->assertRedirect(route('login'));

        $this->assertTrue($sasaran->fresh()->isActive());
    }
}
