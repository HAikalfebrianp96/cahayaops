<?php

namespace Tests\Feature\Uat;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UAT — CEK SIMPANAN TANPA AKUN.
 *
 * Anggota koperasi tidak punya akun CahayaOS. Satu-satunya cara ia melihat
 * simpanannya adalah halaman publik ini: nomor anggota + PIN.
 *
 * ==========================================================================
 * KENAPA HALAMAN INI PERLU DIJAGA LEBIH KETAT
 * ==========================================================================
 *
 * Ini pintu tanpa login yang membuka DATA KEUANGAN PRIBADI. Kalau sesinya
 * bocor antar anggota, atau PIN bisa ditebak tanpa hambatan, yang terekspos
 * bukan sekadar angka — melainkan saldo tabungan orang yang mempercayakannya
 * ke koperasi.
 *
 * Empat hal yang dijaga:
 *   1. PIN TIDAK PERNAH TERSIMPAN SEBAGAI TEKS BIASA.
 *   2. Anggota A tidak bisa melihat data anggota B.
 *   3. Halaman hasil tidak bisa dibuka tanpa melewati PIN.
 *   4. Percobaan PIN dibatasi (rate limit) — tanpa itu, PIN enam digit bisa
 *      ditebak habis oleh skrip sederhana.
 */
class CekSimpananTanpaAkunTest extends TestCase
{
    use RefreshDatabase;

    private KoperasiMember $anggotaA;
    private KoperasiMember $anggotaB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@pin.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->anggotaA = $this->anggota('Bu Ani');
        $this->anggotaB = $this->anggota('Pak Budi');

        $this->anggotaA->setPin('111111');
        $this->anggotaB->setPin('222222');

        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);
        $this->simpan($this->anggotaA, 'Simpanan Wajib', 250_000);
        $this->simpan($this->anggotaA, 'Simpanan Sukarela', 500_000);

        $this->simpan($this->anggotaB, 'Simpanan Pokok', 100_000);
    }

    // ==================================================================
    // A. ALUR NORMAL
    // ==================================================================

    public function test_anggota_melihat_simpanannya_dengan_nomor_dan_pin_yang_benar(): void
    {
        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggotaA->nomor_anggota,
            'pin' => '111111',
        ])->assertRedirect(route('anggota-akses.hasil'));

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Bu Ani')
            ->assertSee($this->anggotaA->nomor_anggota)
            // Pokok 100.000 + Wajib 250.000 + Sukarela 500.000 = 850.000.
            //
            // Angka ini SEBELUMNYA 350.000, ketika Simpanan Sukarela belum
            // ikut dijumlahkan. Ketetapan koperasi kemudian menegaskan bahwa
            // Sukarela adalah bagian dari simpanan anggota, jadi rumusnya
            // berubah — dan test ini ikut berubah karena memang mengunci
            // aturan bisnisnya, bukan sekadar angkanya.
            // Rumus lengkapnya dijaga di TotalSimpananSukarelaTest.
            ->assertSee('850.000');
    }

    public function test_histori_transaksi_simpanan_ikut_ditampilkan(): void
    {
        $this->masuk($this->anggotaA, '111111');

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Simpanan Pokok')
            ->assertSee('Simpanan Wajib');
    }

    // ==================================================================
    // B. PENOLAKAN
    // ==================================================================

    public function test_pin_salah_ditolak(): void
    {
        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggotaA->nomor_anggota,
            'pin' => '999999',
        ])->assertSessionHasErrors('pin');

        $this->assertNull(session('anggota_koperasi_id'));
    }

    public function test_nomor_anggota_yang_tidak_ada_ditolak(): void
    {
        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => 'KOP-9999-9999',
            'pin' => '111111',
        ])->assertSessionHasErrors('pin');
    }

    public function test_pesan_penolakan_tidak_membocorkan_nomor_mana_yang_terdaftar(): void
    {
        // Pesan yang berbeda antara "nomor tidak ada" dan "PIN salah" akan
        // memberi tahu penyerang nomor mana yang sah — dan nomor anggota
        // berpola urut, jadi daftarnya mudah disusun.
        $nomorSalah = $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => 'KOP-9999-9999', 'pin' => '111111',
        ]);

        $pinSalah = $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggotaA->nomor_anggota, 'pin' => '999999',
        ]);

        $this->assertSame(
            $nomorSalah->getSession()->get('errors')->first('pin'),
            $pinSalah->getSession()->get('errors')->first('pin'),
        );
    }

    public function test_anggota_tanpa_pin_tidak_bisa_masuk(): void
    {
        $tanpaPin = $this->anggota('Belum Ber-PIN');

        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $tanpaPin->nomor_anggota,
            'pin' => '123456',
        ])->assertSessionHasErrors('pin');
    }

    public function test_halaman_hasil_tidak_bisa_dibuka_tanpa_melewati_pin(): void
    {
        $this->get(route('anggota-akses.hasil'))->assertForbidden();
    }

    // ==================================================================
    // C. ISOLASI ANTAR ANGGOTA
    // ==================================================================

    public function test_anggota_a_tidak_melihat_data_anggota_b(): void
    {
        $this->masuk($this->anggotaA, '111111');

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Bu Ani')
            ->assertDontSee('Pak Budi')
            ->assertDontSee($this->anggotaB->nomor_anggota);
    }

    public function test_keluar_lalu_masuk_sebagai_anggota_lain_mengganti_data_sepenuhnya(): void
    {
        $this->masuk($this->anggotaA, '111111');
        $this->post(route('anggota-akses.logout'));

        $this->masuk($this->anggotaB, '222222');

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('Pak Budi')
            ->assertDontSee('Bu Ani');
    }

    public function test_keluar_benar_benar_memutus_akses(): void
    {
        $this->masuk($this->anggotaA, '111111');
        $this->post(route('anggota-akses.logout'))->assertRedirect(route('anggota-akses.login'));

        $this->get(route('anggota-akses.hasil'))->assertForbidden();
    }

    public function test_sesi_anggota_tidak_memberi_akses_ke_sistem_internal(): void
    {
        // Sesi anggota SENGAJA terpisah dari Auth guard sistem. Kalau tidak,
        // anggota yang cek simpanan akan ikut membawa akses ke modul internal.
        $this->masuk($this->anggotaA, '111111');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('koperasi.anggota.index'))->assertRedirect(route('login'));
    }

    public function test_anggota_yang_dihapus_admin_kehilangan_sesinya(): void
    {
        $this->masuk($this->anggotaA, '111111');
        $this->anggotaA->delete();

        $this->get(route('anggota-akses.hasil'))->assertForbidden();
    }

    // ==================================================================
    // D. KEAMANAN PIN
    // ==================================================================

    public function test_pin_tidak_pernah_tersimpan_sebagai_teks_biasa(): void
    {
        $baris = \DB::table('koperasi_members')->where('id', $this->anggotaA->id)->first();

        $this->assertNotSame('111111', $baris->pin_hash);
        $this->assertStringNotContainsString('111111', (string) $baris->pin_hash);
        $this->assertTrue(Hash::check('111111', $baris->pin_hash));
    }

    public function test_pin_tidak_ikut_terbawa_saat_data_anggota_diserialkan(): void
    {
        // $hidden pada model menjaga pin_hash tidak ikut bocor lewat JSON —
        // mis. kalau suatu saat ada endpoint yang mengembalikan data anggota.
        $this->assertArrayNotHasKey('pin_hash', $this->anggotaA->fresh()->toArray());
    }

    public function test_pin_tidak_bisa_ditanam_lewat_mass_assignment(): void
    {
        $palsu = KoperasiMember::create([
            'nama' => 'Penyusup', 'nomor_hp' => '0800',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
            'pin_hash' => Hash::make('000000'),
        ]);

        $this->assertFalse($palsu->fresh()->verifyPin('000000'));
    }

    public function test_admin_membuat_pin_dan_pin_hanya_ditampilkan_sekali(): void
    {
        $anggota = $this->anggota('Baru');

        $respons = $this->actingAs($this->admin)
            ->post(route('koperasi.anggota.generate-pin', $anggota));

        $respons->assertRedirect();

        $pinBaru = session('pin_baru');

        $this->assertNotNull($pinBaru, 'PIN baru harus disampaikan sekali ke Admin untuk diteruskan ke anggota.');
        $this->assertTrue($anggota->fresh()->verifyPin($pinBaru));

        // Setelah halaman berikutnya dibuka, PIN plaintext tidak ada lagi
        // di mana pun — termasuk di database.
        $this->assertNotSame($pinBaru, $anggota->fresh()->pin_hash);
    }

    public function test_percobaan_pin_dibatasi_rate_limit(): void
    {
        // PIN enam digit hanya punya sejuta kemungkinan. Tanpa pembatasan,
        // skrip sederhana bisa menebaknya habis dalam hitungan jam.
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('anggota-akses.login.submit'), [
                'nomor_anggota' => $this->anggotaA->nomor_anggota,
                'pin' => '000000',
            ]);
        }

        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggotaA->nomor_anggota,
            'pin' => '000000',
        ])->assertStatus(429);
    }

    // ==================================================================

    // =====================================================================
    // PANJANG HALAMAN — riwayat yang tidak pernah menyusut
    // =====================================================================

    public function test_riwayat_panjang_dipendekkan_tanpa_menghilangkan_satu_pun_entri(): void
    {
        // Anggota yang menyetor Simpanan Wajib tiap bulan menambah dua belas
        // baris setiap tahun. Halaman ini dibuka dari ponsel untuk satu
        // pertanyaan — "berapa saldo saya?" — jadi jawabannya tidak boleh
        // makin jauh dari layar seiring lamanya ia jadi anggota.
        for ($i = 1; $i <= 15; $i++) {
            $this->anggotaA->savings()->create([
                'jenis_simpanan' => 'Simpanan Wajib',
                'tanggal' => now()->subMonths($i)->toDateString(),
                'nominal' => 50_000 + $i,
                'status' => 'Tercatat',
                'keterangan' => 'Setoran ke-' . $i,
            ]);
        }

        $this->masuk($this->anggotaA, '111111');
        $respons = $this->get(route('anggota-akses.hasil'))->assertOk();

        // Sisanya ditawarkan lewat pembuka, bukan dibuang.
        $respons->assertSee('riwayat lama');

        // Seluruh entri TETAP dirender — yang berubah hanya sebagian
        // tertutup. Entri tertua sekalipun harus tetap ada di halaman.
        $respons->assertSee('Setoran ke-1');
        $respons->assertSee('Setoran ke-15');
    }

    public function test_riwayat_pendek_tidak_memunculkan_pembuka_riwayat_lama(): void
    {
        // Jangan menawarkan "lihat riwayat lama" kalau memang tidak ada yang
        // disembunyikan — anggota baru tidak perlu tombol yang tidak berguna.
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);

        $this->masuk($this->anggotaA, '111111');

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertDontSee('riwayat lama');
    }

    private function anggota(string $nama): KoperasiMember
    {
        return KoperasiMember::create([
            'nama' => $nama,
            'nomor_hp' => '0812' . random_int(10000000, 99999999),
            'tanggal_bergabung' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
        ])->fresh();
    }

    private function simpan(KoperasiMember $m, string $jenis, int $nominal): void
    {
        MemberSaving::create([
            'koperasi_member_id' => $m->id,
            'jenis_simpanan' => $jenis,
            'tanggal' => now()->subMonths(3)->toDateString(),
            'nominal' => $nominal,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    private function masuk(KoperasiMember $m, string $pin): void
    {
        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $m->nomor_anggota,
            'pin' => $pin,
        ])->assertRedirect(route('anggota-akses.hasil'));
    }
}
