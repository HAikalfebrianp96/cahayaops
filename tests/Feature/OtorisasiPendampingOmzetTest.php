<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Temuan M-1 & M-2 dari audit Tahap 5 — dua lubang otorisasi yang SUDAH ADA
 * sebelum rencana role baru, jadi diperbaiki lebih dulu dan terpisah.
 *
 * M-1  PendampingController: delapan method publiknya, hanya resetPassword()
 *      yang dijaga. Tujuh sisanya — termasuk store()/update() yang bisa
 *      MEMBUAT AKUN LOGIN dan destroy() yang menghapus data — terbuka untuk
 *      siapa pun yang sudah login.
 *
 * M-2  OmzetMonitoringController::index() tidak punya penjagaan sama sekali;
 *      hanya bercabang "kalau Pendamping, saring". Role di luar itu tidak
 *      masuk cabang manapun sehingga melihat SELURUH omzet (fail-open).
 *
 * Test ini menguji dua hal sekaligus: akses yang sah TIDAK berubah, dan
 * akses yang tidak sah benar-benar ditolak di backend (bukan sekadar
 * menunya disembunyikan).
 */
class OtorisasiPendampingOmzetTest extends TestCase
{
    use RefreshDatabase;

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

    private function omzet(Participant $peserta, int $nilai): OmzetReport
    {
        return OmzetReport::create([
            'participant_id' => $peserta->id,
            'periode_bulan' => 1,
            'periode_tahun' => 2026,
            'omzet' => $nilai,
        ]);
    }

    // =====================================================================
    // M-1 — Modul Pendamping hanya untuk Admin
    // =====================================================================

    public function test_admin_tetap_bisa_membuka_seluruh_halaman_pendamping(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@uji.test');
        $profil = $this->pendamping();

        $this->actingAs($admin)->get(route('pendampings.index'))->assertOk();
        $this->actingAs($admin)->get(route('pendampings.create'))->assertOk();
        $this->actingAs($admin)->get(route('pendampings.show', $profil))->assertOk();
        $this->actingAs($admin)->get(route('pendampings.edit', $profil))->assertOk();
    }

    public function test_admin_tetap_bisa_menambah_dan_mengubah_data_pendamping(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin2@uji.test');

        $this->actingAs($admin)->post(route('pendampings.store'), [
            'nama' => 'Pendamping Baru',
            'nomor_hp' => '081999',
            'email' => 'baru@uji.test',
            'status' => 'Aktif',
        ])->assertRedirect(route('pendampings.index'));

        $this->assertDatabaseHas('pendampings', ['nama' => 'Pendamping Baru']);

        $profil = Pendamping::where('nama', 'Pendamping Baru')->first();

        $this->actingAs($admin)->put(route('pendampings.update', $profil), [
            'nama' => 'Pendamping Diubah',
            'nomor_hp' => '081999',
            'email' => 'baru@uji.test',
            'status' => 'Aktif',
        ])->assertRedirect(route('pendampings.index'));

        $this->assertDatabaseHas('pendampings', ['id' => $profil->id, 'nama' => 'Pendamping Diubah']);
    }

    public static function roleBukanAdmin(): array
    {
        return [
            'pimpinan' => [User::ROLE_PIMPINAN, false],
            'pendamping' => [User::ROLE_PENDAMPING, true],
        ];
    }

    /** Seluruh permukaan modul ditutup — bukan cuma halaman daftarnya. */
    #[DataProvider('roleBukanAdmin')]
    public function test_selain_admin_ditolak_di_semua_halaman_pendamping(string $role, bool $pakaiTautan): void
    {
        $profil = $this->pendamping();
        $user = $this->user($role, 'nonadmin@uji.test', $pakaiTautan ? $profil->id : null);

        $this->actingAs($user)->get(route('pendampings.index'))->assertForbidden();
        $this->actingAs($user)->get(route('pendampings.create'))->assertForbidden();
        $this->actingAs($user)->get(route('pendampings.show', $profil))->assertForbidden();
        $this->actingAs($user)->get(route('pendampings.edit', $profil))->assertForbidden();
    }

    #[DataProvider('roleBukanAdmin')]
    public function test_selain_admin_tidak_bisa_menulis_data_pendamping(string $role, bool $pakaiTautan): void
    {
        $profil = $this->pendamping();
        $user = $this->user($role, 'nonadmin2@uji.test', $pakaiTautan ? $profil->id : null);
        $namaAsli = $profil->nama;

        $this->actingAs($user)->post(route('pendampings.store'), [
            'nama' => 'Sisipan',
            'nomor_hp' => '081',
            'email' => 'sisipan@uji.test',
            'status' => 'Aktif',
        ])->assertForbidden();

        $this->actingAs($user)->put(route('pendampings.update', $profil), [
            'nama' => 'Dibajak',
            'nomor_hp' => '081',
            'email' => $profil->email,
            'status' => 'Aktif',
        ])->assertForbidden();

        $this->actingAs($user)->delete(route('pendampings.destroy', $profil))->assertForbidden();
        $this->actingAs($user)->put(route('pendampings.reset-password', $profil))->assertForbidden();

        // Tidak satu pun percobaan di atas menyentuh data.
        $this->assertDatabaseMissing('pendampings', ['nama' => 'Sisipan']);
        $this->assertDatabaseHas('pendampings', ['id' => $profil->id, 'nama' => $namaAsli]);
    }

    /**
     * Bagian paling berbahaya dari M-1: store() dan update() dengan opsi
     * "buat_akun" MEMBUAT AKUN LOGIN BARU. Sebelum diperbaiki, siapa pun
     * yang sudah login bisa menerbitkan akun untuk dirinya sendiri.
     */
    public function test_selain_admin_tidak_bisa_menerbitkan_akun_login_baru(): void
    {
        $profil = $this->pendamping();
        $pimpinan = $this->user(User::ROLE_PIMPINAN, 'pimpinan@uji.test');
        $jumlahAwal = User::count();

        $this->actingAs($pimpinan)->post(route('pendampings.store'), [
            'nama' => 'Akun Selundupan',
            'nomor_hp' => '081',
            'email' => 'selundupan@uji.test',
            'status' => 'Aktif',
            'buat_akun' => 1,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertForbidden();

        $this->actingAs($pimpinan)->put(route('pendampings.update', $profil), [
            'nama' => $profil->nama,
            'nomor_hp' => $profil->nomor_hp,
            'email' => $profil->email,
            'status' => 'Aktif',
            'buat_akun' => 1,
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'selundupan@uji.test']);
        $this->assertSame($jumlahAwal, User::count(), 'Tidak boleh ada akun baru yang terbit.');
    }

    /** Pendamping tidak boleh menghapus data dirinya sendiri lewat modul ini. */
    public function test_pendamping_tidak_bisa_menghapus_profil_pendamping(): void
    {
        $profil = $this->pendamping();
        $user = $this->user(User::ROLE_PENDAMPING, 'diri@uji.test', $profil->id);

        $this->actingAs($user)->delete(route('pendampings.destroy', $profil))->assertForbidden();

        $this->assertDatabaseHas('pendampings', ['id' => $profil->id]);
        $this->assertSame($profil->id, $user->fresh()->pendamping_id, 'Tautan akun tidak boleh ikut lepas.');
    }

    // =====================================================================
    // M-2 — Monitoring Omzet tidak boleh fail-open
    // =====================================================================

    public function test_admin_dan_pimpinan_tetap_melihat_seluruh_omzet(): void
    {
        $p1 = $this->pendamping('Pendamping Satu');
        $p2 = $this->pendamping('Pendamping Dua');
        $this->omzet($this->peserta('Peserta A', $p1->id), 1000);
        $this->omzet($this->peserta('Peserta B', $p2->id), 2000);

        foreach ([User::ROLE_ADMIN, User::ROLE_PIMPINAN] as $role) {
            $this->actingAs($this->user($role, $role . '@omzet.test'))
                ->get(route('omzet.index'))
                ->assertOk()
                ->assertSee('Peserta A')
                ->assertSee('Peserta B');
        }
    }

    public function test_pendamping_hanya_melihat_omzet_binaannya_sendiri(): void
    {
        $p1 = $this->pendamping('Pendamping Satu');
        $p2 = $this->pendamping('Pendamping Dua');
        $this->omzet($this->peserta('Peserta Milik Saya', $p1->id), 1000);
        $this->omzet($this->peserta('Peserta Orang Lain', $p2->id), 2000);

        $this->actingAs($this->user(User::ROLE_PENDAMPING, 'satu@omzet.test', $p1->id))
            ->get(route('omzet.index'))
            ->assertOk()
            ->assertSee('Peserta Milik Saya')
            ->assertDontSee('Peserta Orang Lain');
    }

    public function test_pendamping_tanpa_tautan_tidak_melihat_omzet_siapa_pun(): void
    {
        $p1 = $this->pendamping('Pendamping Satu');
        $this->omzet($this->peserta('Peserta A', $p1->id), 1000);
        $this->omzet($this->peserta('Peserta Yatim', null), 2000);

        $this->actingAs($this->user(User::ROLE_PENDAMPING, 'kosong@omzet.test'))
            ->get(route('omzet.index'))
            ->assertOk()
            ->assertDontSee('Peserta A')
            ->assertDontSee('Peserta Yatim');
    }

    /**
     * Inti M-2. Role yang tidak dikenal dulu lolos begitu saja karena tidak
     * masuk cabang `if ($user->isPendamping())`, lalu melihat SEMUA omzet.
     * Sekarang halamannya menolak.
     *
     * Ini memakai nilai role karangan HANYA untuk mensimulasikan role yang
     * belum terdaftar — tidak ada role baru yang ditambahkan ke aplikasi.
     */
    public function test_role_tak_dikenal_ditolak_bukan_malah_melihat_semua_omzet(): void
    {
        $p1 = $this->pendamping('Pendamping Satu');
        $this->omzet($this->peserta('Peserta Rahasia', $p1->id), 5000);

        $asing = $this->user('role_belum_terdaftar', 'asing@omzet.test');

        $respons = $this->actingAs($asing)->get(route('omzet.index'));

        $respons->assertForbidden();
        $respons->assertDontSee('Peserta Rahasia');
    }

    /** Role tak dikenal juga tidak boleh menembus modul Pendamping. */
    public function test_role_tak_dikenal_ditolak_di_modul_pendamping(): void
    {
        $profil = $this->pendamping();
        $asing = $this->user('role_belum_terdaftar', 'asing2@uji.test');

        $this->actingAs($asing)->get(route('pendampings.index'))->assertForbidden();
        $this->actingAs($asing)->get(route('pendampings.show', $profil))->assertForbidden();
        $this->actingAs($asing)->delete(route('pendampings.destroy', $profil))->assertForbidden();
    }

    // =====================================================================
    // Yang SENGAJA tidak ikut berubah
    // =====================================================================

    /**
     * Perbaikan ini hanya menutup index(); detail/edit omzet sudah dijaga
     * canViewParticipant()/canAccessParticipant() sejak awal dan tidak
     * disentuh. Diuji di sini supaya kalau nanti ikut tergeser, ketahuan.
     */
    public function test_aturan_detail_omzet_tidak_ikut_berubah(): void
    {
        $p1 = $this->pendamping('Pendamping Satu');
        $p2 = $this->pendamping('Pendamping Dua');
        $laporan = $this->omzet($this->peserta('Peserta A', $p1->id), 1000);

        $pemilik = $this->user(User::ROLE_PENDAMPING, 'pemilik@omzet.test', $p1->id);
        $bukanPemilik = $this->user(User::ROLE_PENDAMPING, 'bukan@omzet.test', $p2->id);
        $pimpinan = $this->user(User::ROLE_PIMPINAN, 'pimpinan@omzet.test');

        // Pemilik binaan: boleh lihat DAN ubah.
        $this->actingAs($pemilik)->get(route('omzet.show', $laporan))->assertOk();
        $this->actingAs($pemilik)->get(route('omzet.edit', $laporan))->assertOk();

        // Pimpinan: boleh lihat, TIDAK boleh ubah (tetap read-only).
        $this->actingAs($pimpinan)->get(route('omzet.show', $laporan))->assertOk();
        $this->actingAs($pimpinan)->get(route('omzet.edit', $laporan))->assertForbidden();

        // Pendamping lain: tidak boleh apa-apa.
        $this->actingAs($bukanPemilik)->get(route('omzet.show', $laporan))->assertForbidden();
        $this->actingAs($bukanPemilik)->get(route('omzet.edit', $laporan))->assertForbidden();
    }
}
