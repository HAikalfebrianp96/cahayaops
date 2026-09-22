<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\Pendamping;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PEMBATALAN BERJEJAK untuk transaksi keuangan koperasi.
 *
 * Penjualan dan arus Dana Sosial/Hibah adalah UANG: laporan laba rugi, buku
 * dana, dan invoice yang sudah terbit akan ikut berubah tanpa jejak kalau
 * barisnya hilang begitu saja.
 *
 * Dana Sosial/Hibah karena itu SENGAJA tidak punya jalur hapus sama sekali.
 *
 * Penjualan punya hapus permanen, TAPI hanya setelah dibatalkan. Urutan itu
 * yang menjadi pengamannya: selama barisnya masih aktif ia sedang dihitung
 * di laba rugi, dan pembatalan mewajibkan alasan yang tercatat. Perilaku
 * hapusnya diuji di HapusPenjualanDanKategoriTest; berkas ini menjaga
 * batas antara keduanya.
 *
 * Yang tersedia adalah pembatalan: barisnya TETAP ADA, statusnya jadi
 * "Dibatalkan", berhenti dihitung ke laporan, dan alasan + pelakunya
 * tersimpan. Polanya sama persis dengan koreksi simpanan yang sudah ada.
 */
class PembatalanTransaksiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendamping;
    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@batal.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@batal.test');

        $profil = Pendamping::create(['nama' => 'PD', 'nomor_hp' => '0812', 'email' => 'pd@batal.test', 'status' => 'Aktif']);
        $this->pendamping = $this->user('pendamping', 'pd.akun@batal.test', $profil->id);
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@batal.test');
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function transaksi(): SalesTransaction
    {
        $unit = BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-BATAL', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        return SalesTransaction::create([
            'unit_id' => $unit->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 500000, 'diskon' => 0, 'ongkir' => 0, 'total' => 500000,
            'status_transaksi' => 'Aktif', 'status_pembayaran' => 'Lunas',
        ]);
    }

    // =====================================================================
    // PENJUALAN
    // =====================================================================

    public function test_transaksi_dibatalkan_tanpa_menghilangkan_barisnya(): void
    {
        $transaksi = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $transaksi), [
                'alasan_pembatalan' => 'Pesanan dibatalkan pembeli',
            ])
            ->assertRedirect(route('koperasi.penjualan.show', $transaksi->id));

        $segar = $transaksi->fresh();

        // Barisnya TIDAK dihapus.
        $this->assertDatabaseHas('sales_transactions', ['id' => $transaksi->id]);
        $this->assertSame(SalesTransaction::STATUS_DIBATALKAN, $segar->status_transaksi);
        $this->assertSame('Pesanan dibatalkan pembeli', $segar->alasan_pembatalan);
        $this->assertNotNull($segar->dibatalkan_at);
        $this->assertSame($this->timKoperasi->id, $segar->dibatalkan_oleh);
    }

    public function test_pembatalan_transaksi_wajib_menyertakan_alasan(): void
    {
        $transaksi = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $transaksi), [])
            ->assertSessionHasErrors('alasan_pembatalan');

        $this->assertSame('Aktif', $transaksi->fresh()->status_transaksi);
    }

    public function test_transaksi_yang_sudah_dibatalkan_tidak_bisa_dibatalkan_lagi(): void
    {
        $transaksi = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $transaksi), ['alasan_pembatalan' => 'Keliru']);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $transaksi->fresh()), ['alasan_pembatalan' => 'Lagi'])
            ->assertForbidden();
    }

    /**
     * Transaksi yang dibatalkan berhenti dihitung di laporan.
     *
     * Seluruh laporan memang SUDAH mengecualikan status 'Dibatalkan' sejak
     * awal; yang selama ini tidak ada hanyalah cara menyetelnya.
     */
    public function test_transaksi_dibatalkan_berhenti_dihitung_di_laporan(): void
    {
        $transaksi = $this->transaksi();

        // Diuji lewat SARINGAN yang dipakai seluruh laporan
        // (whereNotIn status_transaksi ['Draft','Dibatalkan']), bukan lewat
        // teks di satu halaman — saringan itulah yang menentukan angka di
        // laba rugi, laporan penjualan, laporan unit usaha, dan dashboard
        // sekaligus.
        $terhitung = fn () => SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])->count();

        $this->assertSame(1, $terhitung());

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $transaksi), ['alasan_pembatalan' => 'Salah input']);

        $this->assertSame(0, $terhitung(), 'Transaksi yang dibatalkan tidak boleh ikut dihitung laporan.');
        // Tapi barisnya tetap ada.
        $this->assertSame(1, SalesTransaction::count());

        // Seluruh halaman laporan tetap terbuka tanpa error.
        $this->actingAs($this->admin)->get(route('koperasi.laporan.penjualan'))->assertOk();
        $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('koperasi.laporan.laba-rugi'))->assertOk();
    }

    public function test_pembatalan_transaksi_tercatat_di_activity_log(): void
    {
        $transaksi = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $transaksi), ['alasan_pembatalan' => 'Barang batal dikirim']);

        $log = ActivityLog::where('action', 'sales_transaction_cancelled')
            ->where('model_id', $transaksi->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Barang batal dikirim', $log->description);
    }

    public function test_transaksi_tertutup_untuk_role_tanpa_hak(): void
    {
        $transaksi = $this->transaksi();

        foreach ([$this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)
                ->post(route('koperasi.penjualan.batalkan', $transaksi), ['alasan_pembatalan' => 'x'])
                ->assertForbidden();
        }

        $this->assertSame('Aktif', $transaksi->fresh()->status_transaksi);
    }

    /**
     * Jalur hapus penjualan KINI ADA — tetapi tetap di belakang Batalkan.
     *
     * Test ini dulu berbunyi assertFalse(Route::has(...)) dengan alasan
     * "transaksi penjualan tidak boleh punya jalur hapus". Kebijakan itu
     * diubah: Hapus Permanen sekarang tersedia.
     *
     * Yang TIDAK berubah adalah alasan di balik aturan lamanya, dan itulah
     * yang dijaga di sini: transaksi yang masih aktif tetap tidak bisa
     * dihapus. Batalkan mewajibkan ALASAN, dan alasan itu tercatat di
     * Activity Log — tanpa langkah itu, satu-satunya keterangan tentang
     * uang yang hilang dari laba rugi adalah kata "dihapus".
     */
    public function test_hapus_penjualan_hanya_setelah_dibatalkan(): void
    {
        $this->assertTrue(Route::has('koperasi.penjualan.destroy'));

        $transaksi = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.penjualan.destroy', $transaksi))
            ->assertSessionHasErrors('hapus');

        $this->assertDatabaseHas('sales_transactions', ['id' => $transaksi->id]);
        $this->assertSame('Aktif', $transaksi->fresh()->status_transaksi);
    }

    // =====================================================================
    // DANA SOSIAL & HIBAH
    // =====================================================================

    public function test_catatan_dana_bisa_diperbaiki(): void
    {
        $dana = DanaSukarelaMasuk::create([
            'tanggal' => '2026-09-01', 'nominal' => 100000, 'sumber' => 'Donatur A',
        ]);

        // Payloadnya lengkap, persis seperti yang dikirim form perbaikan:
        // 'sumber' ikut karena ia memang bagian dari catatannya, dan sejak
        // ada halaman editnya kolom itu ikut bisa dibetulkan.
        $this->actingAs($this->admin)
            ->put(route('koperasi.dana-sukarela.perbarui', ['jenis' => 'masuk', 'id' => $dana->id]), [
                'tanggal' => '2026-09-02', 'nominal' => 150000,
                'sumber' => 'Donatur A', 'keterangan' => 'Nominal diperbaiki',
            ])
            ->assertSessionHasNoErrors();

        $segar = $dana->fresh();

        $this->assertEqualsWithDelta(150000, (float) $segar->nominal, 0.01);
        $this->assertSame('2026-09-02', $segar->tanggal->format('Y-m-d'));
        $this->assertSame('Donatur A', $segar->sumber);

        // Perbaikannya berjejak, lengkap dengan nilai sebelum -> sesudah.
        $log = ActivityLog::where('action', 'dana_sukarela_updated')->where('model_id', $dana->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('100.000', $log->description);
        $this->assertStringContainsString('150.000', $log->description);
    }

    public function test_catatan_dana_dibatalkan_berhenti_dihitung_ke_saldo(): void
    {
        DanaSukarelaMasuk::create(['tanggal' => '2026-09-01', 'nominal' => 500000, 'sumber' => 'Donatur A']);
        $keliru = DanaSukarelaMasuk::create(['tanggal' => '2026-09-01', 'nominal' => 200000, 'sumber' => 'Salah input']);

        $this->assertEqualsWithDelta(700000, (float) DanaSukarelaMasuk::berlaku()->sum('nominal'), 0.01);

        $this->actingAs($this->admin)
            ->post(route('koperasi.dana-sukarela.batalkan', ['jenis' => 'masuk', 'id' => $keliru->id]), [
                'alasan_pembatalan' => 'Dicatat dua kali',
            ])
            ->assertRedirect(route('koperasi.keuangan.index'));

        // Barisnya TETAP ADA...
        $this->assertDatabaseHas('dana_sukarela_masuk', ['id' => $keliru->id]);
        $this->assertTrue($keliru->fresh()->dibatalkan());
        $this->assertSame('Dicatat dua kali', $keliru->fresh()->alasan_pembatalan);
        $this->assertSame($this->admin->id, $keliru->fresh()->dibatalkan_oleh);

        // ...tapi berhenti dihitung ke saldo.
        $this->assertEqualsWithDelta(500000, (float) DanaSukarelaMasuk::berlaku()->sum('nominal'), 0.01);
    }

    public function test_pembatalan_dana_wajib_menyertakan_alasan(): void
    {
        $dana = DanaHibahMasuk::create(['tanggal' => '2026-09-01', 'nominal' => 300000, 'sumber' => 'Hibah A']);

        $this->actingAs($this->admin)
            ->post(route('koperasi.dana-hibah.batalkan', ['jenis' => 'masuk', 'id' => $dana->id]), [])
            ->assertSessionHasErrors('alasan_pembatalan');

        $this->assertFalse($dana->fresh()->dibatalkan());
    }

    public function test_catatan_dana_yang_dibatalkan_tidak_bisa_diubah_lagi(): void
    {
        $dana = DanaSukarelaKeluar::create([
            'tanggal' => '2026-09-01', 'nominal' => 100000, 'keperluan' => 'Santunan',
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.dana-sukarela.batalkan', ['jenis' => 'keluar', 'id' => $dana->id]), [
                'alasan_pembatalan' => 'Salah catat',
            ]);

        $this->actingAs($this->admin)
            ->put(route('koperasi.dana-sukarela.perbarui', ['jenis' => 'keluar', 'id' => $dana->id]), [
                'tanggal' => '2026-09-05', 'nominal' => 999999,
            ])
            ->assertForbidden();

        $this->assertEqualsWithDelta(100000, (float) $dana->fresh()->nominal, 0.01);
    }

    /** Jenis di URL dibatasi daftar putih — bukan menyusun nama kelas dari input. */
    public function test_jenis_dana_karangan_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post('/koperasi/keuangan/dana-sukarela/User/1/batalkan', ['alasan_pembatalan' => 'x'])
            ->assertNotFound();
    }

    public function test_dana_tetap_admin_only(): void
    {
        $dana = DanaSukarelaMasuk::create([
            'tanggal' => '2026-09-01', 'nominal' => 100000, 'sumber' => 'Donatur',
        ]);

        foreach ([$this->timKoperasi, $this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)
                ->post(route('koperasi.dana-sukarela.batalkan', ['jenis' => 'masuk', 'id' => $dana->id]), [
                    'alasan_pembatalan' => 'x',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('koperasi.dana-sukarela.perbarui', ['jenis' => 'masuk', 'id' => $dana->id]), [
                    'tanggal' => '2026-09-01', 'nominal' => 1,
                ])
                ->assertForbidden();
        }

        $this->assertFalse($dana->fresh()->dibatalkan());
        $this->assertEqualsWithDelta(100000, (float) $dana->fresh()->nominal, 0.01);
    }

    /**
     * JALUR HAPUS ADA, TAPI HANYA SETELAH PEMBATALAN.
     *
     * Test ini dulu berbunyi "tidak ada route hapus dana". Aturannya berubah:
     * baris percobaan yang sudah dibatalkan boleh dibuang. Yang dijaga di
     * sini adalah syaratnya — baris yang MASIH BERLAKU tetap ditolak, untuk
     * keempat arus dana.
     *
     * Dengan begitu alasan pembatalan selalu sudah tercatat di Activity Log
     * sebelum barisnya lenyap, dan pihak yang menitipkan dananya tetap bisa
     * diberi keterangan.
     */
    public function test_dana_yang_masih_berlaku_tetap_tidak_bisa_dihapus(): void
    {
        $dana = \App\Models\DanaSukarelaMasuk::create([
            'tanggal' => '2026-09-01', 'nominal' => 100000,
            'status' => 'Tercatat', 'sumber' => 'Celengan',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('koperasi.dana-sukarela.destroy', ['jenis' => 'masuk', 'id' => $dana->id]))
            ->assertSessionHasErrors('hapus');

        $this->assertSame(1, \App\Models\DanaSukarelaMasuk::where('id', $dana->id)->count());
    }
}
