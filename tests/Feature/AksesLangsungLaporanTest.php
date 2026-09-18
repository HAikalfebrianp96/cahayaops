<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Temuan M-3 — halaman /laporan (ReportController::index) dulu tanpa
 * penjagaan sama sekali.
 *
 * Isinya memang "hanya" daftar tautan, tapi menu BUKAN batas keamanan:
 * Pimpinan dan akun Pendamping yang belum tertaut tidak pernah melihat
 * menunya, namun tetap bisa membuka halamannya dengan mengetik URL.
 *
 * Aturan yang dipakai bukan aturan karangan baru — persis sama dengan
 * gerbang scopeParticipant() yang sudah lama menjaga ke-18 sub-laporan:
 * Admin, atau Pendamping yang benar-benar tertaut ke data Pendamping.
 *
 * Karena scopeParticipant() SENGAJA tidak diubah, kelas ini juga mengunci
 * kesamaan keduanya: kalau salah satu bergeser sendiri di kemudian hari,
 * test di bagian "halaman utama sejalan dengan sub-laporan" akan merah.
 */
class AksesLangsungLaporanTest extends TestCase
{
    use RefreshDatabase;

    /** Seluruh sub-laporan yang dijaga scopeParticipant(). */
    private const SUB_LAPORAN = [
        'laporan.peserta',
        'laporan.omzet',
        'laporan.pendampingan',
        'laporan.pelatihan',
        'laporan.legalitas',
    ];

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'pendamping_id' => $pendampingId,
        ]);
    }

    private function pendamping(string $nama = 'Pendamping Uji'): Pendamping
    {
        return Pendamping::create([
            'nama' => $nama,
            'nomor_hp' => '081200000000',
            'email' => strtolower(str_replace(' ', '.', $nama)) . '@uji.test',
            'status' => 'Aktif',
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0816',
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Makanan & Minuman',
            'tanggal_mulai_program' => now()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ]);
    }

    // =====================================================================
    // 1. Yang memang berhak — TIDAK BOLEH ikut terkunci
    // =====================================================================

    public function test_admin_bisa_membuka_halaman_laporan(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'admin@lap.test'))
            ->get(route('laporan'))
            ->assertOk()
            ->assertSee('Laporan');
    }

    public function test_pendamping_yang_tertaut_bisa_membuka_halaman_laporan(): void
    {
        $profil = $this->pendamping();

        $this->actingAs($this->user(User::ROLE_PENDAMPING, 'tertaut@lap.test', $profil->id))
            ->get(route('laporan'))
            ->assertOk();
    }

    /** Akses sah tidak boleh berubah sampai ke isi sub-laporannya. */
    public function test_akses_sah_ke_sub_laporan_tidak_berubah(): void
    {
        $profil = $this->pendamping();
        $this->peserta('Peserta Binaan', $profil->id);

        $admin = $this->user(User::ROLE_ADMIN, 'admin2@lap.test');
        $pendamping = $this->user(User::ROLE_PENDAMPING, 'tertaut2@lap.test', $profil->id);

        foreach (self::SUB_LAPORAN as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertOk();
            $this->actingAs($pendamping)->get(route($rute))->assertOk();
        }

        // Rekap Program tetap khusus Admin — aturan lama, tidak disentuh.
        $this->actingAs($admin)->get(route('laporan.rekap'))->assertOk();
        $this->actingAs($pendamping)->get(route('laporan.rekap'))->assertForbidden();
    }

    // =====================================================================
    // 2. M-3 — direct URL untuk yang TIDAK berhak
    // =====================================================================

    public static function tidakBerhak(): array
    {
        return [
            // [role, punya pendamping_id]
            'pimpinan' => [User::ROLE_PIMPINAN, false],
            'pendamping tanpa tautan' => [User::ROLE_PENDAMPING, false],
            'role tak dikenal' => ['role_belum_terdaftar', false],
        ];
    }

    /**
     * Inti M-3: mengetik URL langsung tidak lagi menembus authorization.
     */
    #[DataProvider('tidakBerhak')]
    public function test_direct_url_laporan_ditolak_untuk_yang_tidak_berhak(string $role, bool $tertaut): void
    {
        $profil = $this->pendamping();
        $user = $this->user($role, 'tidakberhak@lap.test', $tertaut ? $profil->id : null);

        $respons = $this->actingAs($user)->get('/laporan');

        $respons->assertForbidden();
    }

    /**
     * Bukan cuma status code — isi halamannya benar-benar tidak bocor.
     * Sebelum diperbaiki, seluruh daftar tautan laporan ikut terkirim.
     */
    public function test_halaman_laporan_tidak_membocorkan_isinya_ke_pimpinan(): void
    {
        $pimpinan = $this->user(User::ROLE_PIMPINAN, 'pimpinan@lap.test');

        $respons = $this->actingAs($pimpinan)->get('/laporan');

        $respons->assertForbidden();
        $respons->assertDontSee(route('laporan.peserta'));
        $respons->assertDontSee(route('laporan.omzet'));
        $respons->assertDontSee(route('laporan.download-semua'));
    }

    /** Tamu tetap diarahkan ke login, bukan 403 — perilaku lama. */
    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/laporan')->assertRedirect(route('login'));
    }

    // =====================================================================
    // 3. Halaman utama sejalan dengan sub-laporan
    // =====================================================================

    /**
     * Pengunci konsistensi. index() dan scopeParticipant() adalah dua tempat
     * berbeda yang HARUS memutuskan hal yang sama. Kalau salah satunya
     * bergeser, test ini merah — tanpa perlu menggabungkan kodenya.
     *
     * @param string $role
     */
    #[DataProvider('semuaRole')]
    public function test_keputusan_halaman_utama_sama_dengan_sub_laporan(string $role, bool $tertaut): void
    {
        $profil = $this->pendamping();
        $user = $this->user($role, 'konsisten@lap.test', $tertaut ? $profil->id : null);

        $statusIndex = $this->actingAs($user)->get(route('laporan'))->getStatusCode();

        foreach (self::SUB_LAPORAN as $rute) {
            $statusSub = $this->actingAs($user)->get(route($rute))->getStatusCode();

            $this->assertSame(
                $statusIndex,
                $statusSub,
                "Role [$role] mendapat $statusIndex di /laporan tapi $statusSub di $rute — dua aturan sudah tidak sejalan.",
            );
        }
    }

    public static function semuaRole(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN, false],
            'pimpinan' => [User::ROLE_PIMPINAN, false],
            'pendamping tertaut' => [User::ROLE_PENDAMPING, true],
            'pendamping tanpa tautan' => [User::ROLE_PENDAMPING, false],
            'role tak dikenal' => ['role_belum_terdaftar', false],
        ];
    }

    // =====================================================================
    // 4. Tidak ada efek samping ke data
    // =====================================================================

    /** Halaman laporan hanya membaca — percobaan akses tidak menyentuh data. */
    public function test_percobaan_akses_tidak_mengubah_data(): void
    {
        $profil = $this->pendamping();
        $this->peserta('Peserta A', $profil->id);

        $jumlah = [
            'users' => User::count(),
            'pendampings' => Pendamping::count(),
            'participants' => Participant::count(),
        ];

        $pimpinan = $this->user(User::ROLE_PIMPINAN, 'efek@lap.test');
        $this->actingAs($pimpinan)->get('/laporan')->assertForbidden();

        $admin = $this->user(User::ROLE_ADMIN, 'efek2@lap.test');
        $this->actingAs($admin)->get('/laporan')->assertOk();

        // +2 user yang sengaja dibuat test ini, selebihnya harus persis sama.
        $this->assertSame($jumlah['users'] + 2, User::count());
        $this->assertSame($jumlah['pendampings'], Pendamping::count());
        $this->assertSame($jumlah['participants'], Participant::count());
    }

    // =====================================================================
    // 5. Otorisasi lain tidak ikut dilonggarkan
    // =====================================================================

    /**
     * Perbaikan M-3 hanya menambah penjagaan di satu tempat. Modul lain yang
     * sudah dijaga sebelumnya dicek ulang di sini supaya kalau perbaikan ini
     * tanpa sengaja menggeser sesuatu, ketahuan.
     */
    public function test_otorisasi_modul_lain_tetap_seperti_semula(): void
    {
        $profil = $this->pendamping();
        $pimpinan = $this->user(User::ROLE_PIMPINAN, 'lain@lap.test');
        $pendamping = $this->user(User::ROLE_PENDAMPING, 'lain2@lap.test', $profil->id);

        // Modul Pendamping tetap khusus Admin (perbaikan M-1).
        $this->actingAs($pimpinan)->get(route('pendampings.index'))->assertForbidden();

        // Monitoring Omzet tetap boleh untuk Pimpinan & Pendamping (M-2).
        $this->actingAs($pimpinan)->get(route('omzet.index'))->assertOk();
        $this->actingAs($pendamping)->get(route('omzet.index'))->assertOk();

        // Binaan Saya tidak dilonggarkan.
        $this->actingAs($pendamping)->get(route('binaan.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('binaan.index'))->assertForbidden();
    }
}
