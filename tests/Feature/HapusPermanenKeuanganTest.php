<?php

namespace Tests\Feature;

use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SETIAP DATA YANG DIINPUT HARUS BISA DILIHAT, DIEDIT, DAN DIHAPUS PERMANEN.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Simpanan dan keempat arus dana punya jalur mencatat dan jalur membatalkan,
 * tapi tidak punya jalur MEMBUANG. Akibatnya baris percobaan menumpuk
 * selamanya di riwayat anggota dan di halaman Keuangan, mengaburkan angka
 * yang sebenarnya — padahal ia tidak mewakili uang mana pun.
 *
 * Arus dana bahkan tidak punya jalur memperbaiki yang bisa dicapai: rute
 * perbarui() sudah lama ada, tapi tidak satu halaman pun memanggilnya.
 *
 * =====================================================================
 * YANG DIJAGA TETAP DIJAGA
 * =====================================================================
 *
 * Hapus permanen TIDAK menggantikan pembatalan, ia menyusulnya:
 *
 *   1. catatan uang harus DIBATALKAN dulu beserta alasannya, dan alasan itu
 *      sudah tercatat di Activity Log sebelum barisnya lenyap;
 *   2. entri simpanan yang sudah jadi mata rantai koreksi entri lain tetap
 *      ditolak — menghapusnya memutus rantai itu.
 *
 * Jadi membuang selalu keputusan KEDUA, diambil pada waktu berbeda dari
 * keputusan pertama.
 *
 * Satu hal yang TIDAK pernah jadi alasan menghapus simpanan: status
 * anggotanya.
 */
class HapusPermanenKeuanganTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private KoperasiMember $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@hapus.test',
            'password' => bcrypt('rahasia123'), 'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-01', 'status' => 'Aktif',
        ]);
    }

    private function simpanan(string $status = MemberSaving::STATUS_TERCATAT): MemberSaving
    {
        return MemberSaving::create([
            'koperasi_member_id' => $this->anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 50_000,
            'status' => $status,
        ]);
    }

    // =================================================================
    // SIMPANAN
    // =================================================================

    /** Entri yang masih berlaku ditolak, DAN alasannya disampaikan. */
    public function test_simpanan_berlaku_tidak_bisa_langsung_dihapus(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->admin)
            ->delete(route('koperasi.savings.destroy', $simpanan))
            ->assertSessionHasErrors('hapus');

        $this->assertDatabaseHas('member_savings', ['id' => $simpanan->id]);
    }

    public function test_simpanan_dibatalkan_bisa_dihapus_permanen(): void
    {
        $simpanan = $this->simpanan(MemberSaving::STATUS_DIBATALKAN);

        $this->actingAs($this->admin)
            ->delete(route('koperasi.savings.destroy', $simpanan))
            ->assertRedirect(route('koperasi.anggota.show', $this->anggota->id));

        $this->assertDatabaseMissing('member_savings', ['id' => $simpanan->id]);
    }

    /**
     * Saldo anggota tidak disimpan di kolom mana pun — ia dijumlahkan dari
     * entri-entrinya. Inilah yang membuat menghapus entri cukup untuk
     * membetulkan saldo, tanpa ada angka kedua yang perlu disesuaikan.
     */
    public function test_saldo_anggota_ikut_turun_setelah_entri_dihapus(): void
    {
        $tetap = $this->simpanan();
        $dibuang = $this->simpanan(MemberSaving::STATUS_DIBATALKAN);

        $this->assertSame(100_000.0, (float) MemberSaving::sum('nominal'));

        $this->actingAs($this->admin)->delete(route('koperasi.savings.destroy', $dibuang));

        $this->assertSame(50_000.0, (float) MemberSaving::sum('nominal'));
        $this->assertDatabaseHas('member_savings', ['id' => $tetap->id]);
    }

    /** Mata rantai koreksi tidak boleh diputus. */
    public function test_entri_yang_sudah_punya_pengganti_tidak_bisa_dihapus(): void
    {
        $asal = $this->simpanan(MemberSaving::STATUS_DIBATALKAN);

        MemberSaving::create([
            'koperasi_member_id' => $this->anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 75_000,
            'status' => MemberSaving::STATUS_TERCATAT,
            'dikoreksi_dari_id' => $asal->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('koperasi.savings.destroy', $asal))
            ->assertSessionHasErrors('hapus');

        $this->assertDatabaseHas('member_savings', ['id' => $asal->id]);
    }

    /**
     * Status anggota BUKAN alasan menghapus, dan bukan pula alasan menahan.
     *
     * Menonaktifkan anggota tidak mengembalikan uangnya; simpanannya tetap
     * utuh. Yang menentukan boleh-tidaknya menghapus hanya keadaan entrinya
     * sendiri.
     */
    public function test_status_anggota_tidak_mempengaruhi_simpanannya(): void
    {
        $simpanan = $this->simpanan();

        $this->anggota->update(['status' => 'Tidak Aktif']);

        $this->assertDatabaseHas('member_savings', [
            'id' => $simpanan->id,
            'nominal' => 50_000,
        ]);

        // Masih berlaku -> tetap ditolak, sama seperti ketika anggotanya aktif.
        $this->actingAs($this->admin)
            ->delete(route('koperasi.savings.destroy', $simpanan))
            ->assertSessionHasErrors('hapus');
    }

    public function test_penghapusan_simpanan_tercatat_di_activity_log(): void
    {
        $simpanan = $this->simpanan(MemberSaving::STATUS_DIBATALKAN);

        $this->actingAs($this->admin)->delete(route('koperasi.savings.destroy', $simpanan));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'saving_permanently_deleted',
            'model_type' => 'MemberSaving',
            'model_id' => $simpanan->id,
        ]);
    }

    /**
     * MENGUBAH NOMINAL HARUS MENGUBAH RINGKASANNYA JUGA.
     *
     * Kegagalan yang dijaga di sini berbentuk khas: detail transaksinya
     * berubah, tapi angka di halaman Keuangan tidak ikut — dan dua halaman
     * yang menyebut uang yang sama jadi berbeda pendapat. Itu hanya mungkin
     * terjadi kalau saldo pernah disimpan di kolomnya sendiri.
     */
    public function test_mengubah_nominal_ikut_mengubah_ringkasan_keuangan(): void
    {
        $simpanan = $this->simpanan();

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame(50_000.0, $halaman->viewData('totalSimpananPokok'));

        $this->actingAs($this->admin)
            ->put(route('koperasi.savings.update', $simpanan), [
                'jenis_simpanan' => 'Simpanan Pokok',
                'tanggal' => '2026-02-01',
                'nominal' => 150_000,
            ])
            ->assertSessionHasNoErrors();

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame(150_000.0, $halaman->viewData('totalSimpananPokok'));
    }

    /** Menghapus entri ikut menurunkan angka di halaman Keuangan. */
    public function test_menghapus_entri_ikut_menurunkan_ringkasan_keuangan(): void
    {
        $this->simpanan();
        $dibuang = $this->simpanan(MemberSaving::STATUS_DIBATALKAN);

        // Entri dibatalkan memang sudah tidak dihitung, jadi yang tampil 50.000.
        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame(50_000.0, $halaman->viewData('totalSimpananPokok'));

        $this->actingAs($this->admin)->delete(route('koperasi.savings.destroy', $dibuang));

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame(50_000.0, $halaman->viewData('totalSimpananPokok'));
        $this->assertSame(1, MemberSaving::count());
    }

    /**
     * Anggota tanpa transaksi melihat Rp0 di halaman Cek Simpanan — bukan
     * saldo lama yang tertinggal.
     */
    public function test_cek_simpanan_menunjukkan_nol_tanpa_transaksi(): void
    {
        $halaman = $this->withSession(['anggota_koperasi_id' => $this->anggota->id])
            ->get(route('anggota-akses.hasil'))
            ->assertOk();

        // Diperiksa per angka, bukan lewat assertSee('Rp0') — "Rp0" bisa
        // muncul dari baris mana pun di halaman dan assertion itu akan lulus
        // walau totalnya sebenarnya salah.
        $ringkasan = $halaman->viewData('ringkasan');

        foreach (['pokok', 'wajib', 'sukarela', 'total'] as $angka) {
            $this->assertSame(0.0, (float) $ringkasan[$angka], "Angka {$angka} tidak nol.");
        }
    }

    /** Pembanding: begitu ada transaksinya, halaman itu ikut menunjukkannya. */
    public function test_cek_simpanan_mengikuti_transaksi_yang_ada(): void
    {
        $this->simpanan();

        $ringkasan = $this->withSession(['anggota_koperasi_id' => $this->anggota->id])
            ->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->viewData('ringkasan');

        $this->assertSame(50_000.0, (float) $ringkasan['pokok']);
        $this->assertSame(50_000.0, (float) $ringkasan['total']);
    }

    // =================================================================
    // ARUS DANA — keempat jenisnya
    // =================================================================

    /** @return array<string, array{0: class-string, 1: string, 2: string, 3: array<string, mixed>}> */
    public static function arusDana(): array
    {
        return [
            'sukarela masuk' => [DanaSukarelaMasuk::class, 'dana-sukarela', 'masuk', ['sumber' => 'Celengan']],
            'sukarela keluar' => [DanaSukarelaKeluar::class, 'dana-sukarela', 'keluar', ['keperluan' => 'Santunan']],
            'hibah masuk' => [DanaHibahMasuk::class, 'dana-hibah', 'masuk', ['sumber' => 'YBM PLN']],
            'hibah keluar' => [DanaHibahKeluar::class, 'dana-hibah', 'keluar', ['keperluan' => 'Modal usaha']],
        ];
    }

    private function dana(string $kelas, array $tambahan, string $status = 'Tercatat')
    {
        return $kelas::create(array_merge([
            'tanggal' => '2026-02-01',
            'nominal' => 100_000,
            'status' => $status,
        ], $tambahan));
    }

    #[DataProvider('arusDana')]
    public function test_dana_berlaku_tidak_bisa_langsung_dihapus(string $kelas, string $slug, string $jenis, array $tambahan): void
    {
        $baris = $this->dana($kelas, $tambahan);

        $this->actingAs($this->admin)
            ->delete(route('koperasi.' . $slug . '.destroy', ['jenis' => $jenis, 'id' => $baris->id]))
            ->assertSessionHasErrors('hapus');

        $this->assertSame(1, $kelas::where('id', $baris->id)->count());
    }

    #[DataProvider('arusDana')]
    public function test_dana_dibatalkan_bisa_dihapus_permanen(string $kelas, string $slug, string $jenis, array $tambahan): void
    {
        $baris = $this->dana($kelas, $tambahan, 'Dibatalkan');

        $this->actingAs($this->admin)
            ->delete(route('koperasi.' . $slug . '.destroy', ['jenis' => $jenis, 'id' => $baris->id]))
            ->assertRedirect(route('koperasi.keuangan.index'));

        $this->assertSame(0, $kelas::where('id', $baris->id)->count());
    }

    /** Halaman perbaikan arus dana benar-benar bisa dibuka dan menyimpan. */
    #[DataProvider('arusDana')]
    public function test_dana_bisa_diperbaiki_lewat_halamannya(string $kelas, string $slug, string $jenis, array $tambahan): void
    {
        $baris = $this->dana($kelas, $tambahan);

        $this->actingAs($this->admin)
            ->get(route('koperasi.' . $slug . '.edit', ['jenis' => $jenis, 'id' => $baris->id]))
            ->assertOk()
            ->assertSee('Perbaiki Catatan Dana');

        $this->actingAs($this->admin)
            ->put(
                route('koperasi.' . $slug . '.perbarui', ['jenis' => $jenis, 'id' => $baris->id]),
                array_merge(['tanggal' => '2026-03-05', 'nominal' => 250_000], $tambahan)
            )
            ->assertRedirect(route('koperasi.keuangan.index'));

        $this->assertSame(250_000.0, (float) $kelas::find($baris->id)->nominal);
    }

    /**
     * Kolom keterangan (sumber / keperluan) ikut bisa diperbaiki.
     *
     * Tanpa ini, salah ketik nama penyumbang hanya bisa dibereskan dengan
     * membatalkan seluruh catatannya — pembatalan untuk sesuatu yang bukan
     * kesalahan angka.
     */
    public function test_sumber_dana_ikut_bisa_diperbaiki(): void
    {
        $baris = $this->dana(DanaSukarelaMasuk::class, ['sumber' => 'Salah Ketik']);

        $this->actingAs($this->admin)->put(
            route('koperasi.dana-sukarela.perbarui', ['jenis' => 'masuk', 'id' => $baris->id]),
            ['tanggal' => '2026-02-01', 'nominal' => 100_000, 'sumber' => 'Celengan Bersama']
        );

        $this->assertSame('Celengan Bersama', DanaSukarelaMasuk::find($baris->id)->sumber);
    }

    /** Bukan Admin tidak bisa membuang dana titipan pihak ketiga. */
    public function test_selain_admin_tidak_bisa_menghapus_dana(): void
    {
        $timKoperasi = User::create([
            'name' => 'Tim', 'email' => 'tim@hapus.test',
            'password' => bcrypt('rahasia123'), 'role' => User::ROLE_TIM_KOPERASI, 'is_active' => true,
        ]);

        $baris = $this->dana(DanaSukarelaMasuk::class, ['sumber' => 'Celengan'], 'Dibatalkan');

        $this->actingAs($timKoperasi)
            ->delete(route('koperasi.dana-sukarela.destroy', ['jenis' => 'masuk', 'id' => $baris->id]))
            ->assertForbidden();

        $this->assertSame(1, DanaSukarelaMasuk::where('id', $baris->id)->count());
    }

    public function test_penghapusan_dana_tercatat_di_activity_log(): void
    {
        $baris = $this->dana(DanaSukarelaMasuk::class, ['sumber' => 'Celengan'], 'Dibatalkan');

        $this->actingAs($this->admin)
            ->delete(route('koperasi.dana-sukarela.destroy', ['jenis' => 'masuk', 'id' => $baris->id]));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'dana_sukarela_permanently_deleted',
            'model_type' => 'DanaSukarelaMasuk',
            'model_id' => $baris->id,
        ]);
    }

    /**
     * Saldo dana dijumlahkan dari baris masuk & keluar, bukan disimpan.
     * Baris yang dibatalkan memang sudah tidak ikut dihitung; menghapusnya
     * karena itu TIDAK boleh menggeser saldo sedikit pun.
     */
    public function test_saldo_dana_tidak_bergeser_saat_baris_batal_dibuang(): void
    {
        $this->dana(DanaSukarelaMasuk::class, ['sumber' => 'Celengan']);
        $dibuang = $this->dana(DanaSukarelaMasuk::class, ['sumber' => 'Salah Catat'], 'Dibatalkan');

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame(100_000.0, $halaman->viewData('saldo'));

        $this->actingAs($this->admin)
            ->delete(route('koperasi.dana-sukarela.destroy', ['jenis' => 'masuk', 'id' => $dibuang->id]));

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame(100_000.0, $halaman->viewData('saldo'));
        $this->assertSame(1, DanaSukarelaMasuk::count());
    }

    /**
     * Baris yang dibatalkan HARUS terlihat di riwayat.
     *
     * Kalau ia disembunyikan, alasan pembatalannya tidak bisa dibaca siapa
     * pun dan barisnya tidak bisa ditindaklanjuti lagi — termasuk dihapus,
     * karena tombolnya justru hanya muncul untuk baris yang sudah dibatalkan.
     */
    public function test_baris_dibatalkan_tetap_tampil_di_riwayat(): void
    {
        $this->dana(DanaSukarelaMasuk::class, ['sumber' => 'Salah Catat'], 'Dibatalkan');

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();

        $this->assertCount(1, $halaman->viewData('riwayatMasuk'));
        $halaman->assertSee('Salah Catat');
        $halaman->assertSee('Hapus Permanen');
    }
}
