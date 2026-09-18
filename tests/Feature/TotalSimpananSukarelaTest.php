<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TOTAL SIMPANAN = POKOK + WAJIB + SUKARELA.
 *
 * ==========================================================================
 * KETETAPAN KOPERASI, DAN KENAPA IA PERLU DIJAGA TEST
 * ==========================================================================
 *
 * Sebelum ini rumusnya ditulis dua kali. Halaman Admin menjumlahkan ketiga
 * jenis; halaman Cek Simpanan milik anggota hanya menjumlahkan Pokok dan
 * Wajib. Akibatnya satu anggota bisa melihat total yang LEBIH KECIL daripada
 * yang dilihat petugas untuk orang yang sama — tanpa satu pun salah ketik,
 * tanpa satu pun pesan error, dan dengan kedua angka yang sama-sama masuk
 * akal bila dilihat sendiri-sendiri.
 *
 * Perbaikannya bukan menyamakan kedua rumus itu, melainkan meniadakan salah
 * satunya: kini keduanya memanggil KoperasiMember::ringkasanSimpanan().
 * Test di bawah menjaga dua hal sekaligus — rumusnya benar, DAN kedua layar
 * tidak bisa lagi menyimpang satu sama lain.
 */
class TotalSimpananSukarelaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private KoperasiMember $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@simp.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '081200000001',
            'tanggal_bergabung' => now()->subYear()->toDateString(), 'status' => 'Aktif',
        ])->fresh();

        $this->simpan('Simpanan Pokok', 100_000);
        $this->simpan('Simpanan Wajib', 250_000);
        $this->simpan('Simpanan Sukarela', 400_000);
    }

    // ==================================================================
    // A. RUMUS
    // ==================================================================

    public function test_total_menjumlahkan_ketiga_jenis_simpanan(): void
    {
        $r = $this->anggota->ringkasanSimpanan();

        $this->assertSame(100_000.0, $r['pokok']);
        $this->assertSame(250_000.0, $r['wajib']);
        $this->assertSame(400_000.0, $r['sukarela']);
        $this->assertSame(750_000.0, $r['total']);
    }

    public function test_total_sama_dengan_penjumlahan_komponennya(): void
    {
        // Dijaga sebagai hubungan, bukan sebagai angka tetap — supaya tetap
        // berlaku berapa pun nominal yang dicatat kemudian.
        $r = $this->anggota->ringkasanSimpanan();

        $this->assertSame($r['pokok'] + $r['wajib'] + $r['sukarela'], $r['total']);
    }

    public function test_simpanan_sukarela_benar_benar_mengubah_total(): void
    {
        $sebelum = $this->anggota->ringkasanSimpanan()['total'];

        $this->simpan('Simpanan Sukarela', 50_000);

        $this->assertSame($sebelum + 50_000, $this->anggota->fresh()->ringkasanSimpanan()['total']);
    }

    public function test_simpanan_yang_dibatalkan_tidak_ikut_dihitung(): void
    {
        // Pembatalan adalah jalur koreksi resmi modul ini. Entri yang
        // dibatalkan tetap ada sebagai jejak, tapi berhenti menambah saldo.
        $batal = $this->simpan('Simpanan Sukarela', 900_000);
        $batal->update(['status' => MemberSaving::STATUS_DIBATALKAN]);

        $this->assertSame(750_000.0, $this->anggota->fresh()->ringkasanSimpanan()['total']);
    }

    public function test_anggota_tanpa_simpanan_menghasilkan_nol_bukan_error(): void
    {
        $baru = KoperasiMember::create([
            'nama' => 'Anggota Baru', 'nomor_hp' => '081200000002',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);

        $r = $baru->ringkasanSimpanan();

        $this->assertSame(0.0, $r['total']);
        $this->assertSame(0.0, $r['sukarela']);
    }

    // ==================================================================
    // B. HALAMAN CEK SIMPANAN (anggota, tanpa login)
    // ==================================================================

    public function test_halaman_cek_simpanan_menampilkan_empat_kartu(): void
    {
        $this->masuk();

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Simpanan Pokok')
            ->assertSee('Simpanan Wajib')
            ->assertSee('Simpanan Sukarela')
            ->assertSee('Total Simpanan Saya')
            // Nominal masing-masing jenis, dan totalnya.
            ->assertSee('100.000')
            ->assertSee('250.000')
            ->assertSee('400.000')
            ->assertSee('750.000');
    }

    public function test_halaman_cek_simpanan_menyebutkan_rumus_totalnya(): void
    {
        // Anggota berhak tahu angka Total itu dari mana, tanpa harus
        // menjumlahkan sendiri ketiga kartunya.
        $this->masuk();

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Pokok + Wajib + Sukarela');
    }

    public function test_riwayat_transaksi_tetap_tampil_lengkap(): void
    {
        $this->masuk();

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Riwayat Simpanan')
            ->assertSee('Simpanan Pokok')
            ->assertSee('Simpanan Wajib')
            ->assertSee('Simpanan Sukarela');
    }

    // ==================================================================
    // C. KEDUA LAYAR TIDAK BOLEH BERBEDA
    // ==================================================================

    public function test_total_di_halaman_admin_sama_dengan_halaman_anggota(): void
    {
        // Inti dari perbaikan ini. Keduanya menampilkan uang milik orang yang
        // sama; kalau angkanya bisa berbeda, salah satunya pasti salah dan
        // tidak ada cara mengetahui yang mana.
        $halamanAdmin = $this->actingAs($this->admin)
            ->get(route('koperasi.anggota.show', $this->anggota));
        $halamanAdmin->assertOk()->assertSee('750.000');

        $this->post(route('anggota-akses.logout'));
        $this->masuk();

        $this->get(route('anggota-akses.hasil'))->assertOk()->assertSee('750.000');
    }

    public function test_kedua_halaman_memakai_satu_rumus_yang_sama(): void
    {
        // Dijaga di tingkat kode, bukan hanya tampilan: kalau suatu saat salah
        // satu controller menyusun ulang rumusnya sendiri, test ini tetap
        // lulus tapi test di atas akan gagal — jadi keduanya saling menutup.
        $publik = file_get_contents(app_path('Http/Controllers/AnggotaAksesController.php'));
        $admin = file_get_contents(app_path('Http/Controllers/KoperasiMemberController.php'));

        $this->assertStringContainsString('ringkasanSimpanan()', $publik);
        $this->assertStringContainsString('ringkasanSimpanan()', $admin);
    }

    // ==================================================================
    // D. DATA LAMA TIDAK TERSENTUH
    // ==================================================================

    public function test_perubahan_tampilan_tidak_mengubah_data_simpanan(): void
    {
        $sebelum = MemberSaving::orderBy('id')->get(['id', 'jenis_simpanan', 'nominal', 'status'])->toArray();

        $this->masuk();
        $this->get(route('anggota-akses.hasil'))->assertOk();
        $this->actingAs($this->admin)->get(route('koperasi.anggota.show', $this->anggota))->assertOk();

        $this->assertSame(
            $sebelum,
            MemberSaving::orderBy('id')->get(['id', 'jenis_simpanan', 'nominal', 'status'])->toArray(),
            'Membuka halaman tidak boleh menyentuh data simpanan.'
        );
    }

    // ==================================================================

    private function simpan(string $jenis, int $nominal): MemberSaving
    {
        return MemberSaving::create([
            'koperasi_member_id' => $this->anggota->id,
            'jenis_simpanan' => $jenis,
            'tanggal' => now()->subMonths(2)->toDateString(),
            'nominal' => $nominal,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    private function masuk(): void
    {
        $this->anggota->setPin('123456');

        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggota->nomor_anggota,
            'pin' => '123456',
        ])->assertRedirect(route('anggota-akses.hasil'));
    }
}
