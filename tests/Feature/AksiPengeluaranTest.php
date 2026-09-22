<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Pengeluaran;
use App\Models\PengeluaranCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AKSI PADA DAFTAR PENGELUARAN.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Daftar Pengeluaran sama sekali TIDAK punya kolom Aksi — barisnya hanya
 * bisa diklik untuk menuju detail. Edit, Batalkan, dan Pulihkan sudah ada
 * rutenya, tapi tidak ada satu pun jalan ke sana dari daftar.
 *
 * Hapus permanen belum ada sama sekali.
 *
 * =====================================================================
 * HAPUS PERMANEN HANYA SETELAH DIBATALKAN
 * =====================================================================
 *
 * Penjaganya menolak pengeluaran yang masih aktif, sama seperti transaksi
 * penjualan. Batalkan menuntut ALASAN yang tercatat di Activity Log —
 * tanpa langkah itu, satu-satunya keterangan tentang uang yang hilang dari
 * laporan adalah kata "dihapus".
 */
class AksiPengeluaranTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@aksi.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);
    }

    private function pengeluaran(string $status = 'Aktif'): Pengeluaran
    {
        $kategori = PengeluaranCategory::where('nama', 'Re-Stok Produk')->first();

        $p = Pengeluaran::create([
            'tanggal' => now()->toDateString(),
            'pengeluaran_category_id' => $kategori->id,
            'kategori' => $kategori->nama,
            'nominal' => 400_000,
            'metode_pembayaran' => 'Cash',
            'keterangan' => 'Beli stok beras',
        ]);

        if ($status !== 'Aktif') {
            $p->forceFill([
                'status' => Pengeluaran::STATUS_DIBATALKAN,
                'alasan_pembatalan' => 'Salah input',
                'dibatalkan_at' => now(),
                'dibatalkan_oleh' => $this->timKoperasi->id,
            ])->save();
        }

        return $p->fresh();
    }

    private function daftar()
    {
        // Daftar menyaring periode berjalan secara bawaan.
        return $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.pengeluaran.index', ['tahun' => now()->year, 'bulan' => now()->month]))
            ->assertOk();
    }

    // =================================================================
    // AKSI MENGIKUTI KEADAAN BARIS
    // =================================================================

    public function test_pengeluaran_aktif_menawarkan_detail_edit_batalkan(): void
    {
        $this->pengeluaran();

        $this->daftar()
            ->assertSee('Detail')
            ->assertSee('Edit')
            ->assertSee('Batalkan')
            ->assertDontSee('Pulihkan')
            ->assertDontSee('Hapus Permanen');
    }

    public function test_pengeluaran_dibatalkan_menawarkan_pulihkan_dan_hapus_permanen(): void
    {
        $this->pengeluaran('Dibatalkan');

        $this->daftar()
            ->assertSee('Detail')
            ->assertSee('Pulihkan')
            ->assertSee('Hapus Permanen')
            ->assertDontSee('Batalkan');
    }

    /** Kalimat konfirmasinya persis seperti yang ditetapkan. */
    public function test_konfirmasi_hapus_muncul_di_aplikasi(): void
    {
        $this->pengeluaran('Dibatalkan');

        $this->daftar()
            ->assertSee('Hapus Pengeluaran Permanen?', false)
            ->assertSee('Data yang dihapus tidak dapat dipulihkan', false);
    }

    // =================================================================
    // HAPUS PERMANEN
    // =================================================================

    public function test_pengeluaran_aktif_tidak_bisa_dihapus(): void
    {
        $p = $this->pengeluaran();

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.pengeluaran.destroy', $p))
            ->assertSessionHasErrors('hapus');

        $this->assertDatabaseHas('pengeluaran', ['id' => $p->id]);
        $this->assertStringContainsString('Batalkan dulu', session('errors')->first('hapus'));
    }

    public function test_pengeluaran_dibatalkan_bisa_dihapus_permanen(): void
    {
        $p = $this->pengeluaran('Dibatalkan');

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.pengeluaran.destroy', $p))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('koperasi.pengeluaran.index'));

        $this->assertDatabaseMissing('pengeluaran', ['id' => $p->id]);
    }

    /** Kategorinya TIDAK ikut terhapus. */
    public function test_kategori_tidak_ikut_terhapus(): void
    {
        $p = $this->pengeluaran('Dibatalkan');
        $kategoriId = $p->pengeluaran_category_id;

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.pengeluaran.destroy', $p));

        $this->assertDatabaseHas('pengeluaran_categories', ['id' => $kategoriId]);
    }

    public function test_penghapusan_tercatat_di_activity_log(): void
    {
        $p = $this->pengeluaran('Dibatalkan');

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.pengeluaran.destroy', $p));

        $jejak = ActivityLog::where('action', 'expense_permanently_deleted')->latest('id')->first();

        $this->assertNotNull($jejak);
        $this->assertStringContainsString('Re-Stok Produk', $jejak->description);
        $this->assertSame($this->timKoperasi->id, $jejak->user_id);
        $this->assertNotNull($jejak->created_at);
    }

    /** Tidak ada sandi yang ikut tercatat. */
    public function test_activity_log_tidak_memuat_kredensial(): void
    {
        $p = $this->pengeluaran('Dibatalkan');

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.pengeluaran.destroy', $p));

        foreach (ActivityLog::all() as $baris) {
            $this->assertStringNotContainsString('rahasia123', (string) $baris->description);
            $this->assertStringNotContainsString($this->timKoperasi->password, (string) $baris->description);
        }
    }

    // =================================================================
    // OTORISASI
    // =================================================================

    public function test_peran_lain_tidak_bisa_menghapus(): void
    {
        $p = $this->pengeluaran('Dibatalkan');

        foreach ([User::ROLE_PIMPINAN, User::ROLE_PENDAMPING] as $role) {
            $pelaku = User::create([
                'name' => 'Akun ' . $role,
                'email' => $role . '@aksi.test',
                'password' => bcrypt('rahasia123'),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($pelaku)
                ->delete(route('koperasi.pengeluaran.destroy', $p))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('pengeluaran', ['id' => $p->id]);
    }

    public function test_tamu_tidak_bisa_menghapus(): void
    {
        $p = $this->pengeluaran('Dibatalkan');

        $this->delete(route('koperasi.pengeluaran.destroy', $p))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('pengeluaran', ['id' => $p->id]);
    }

    // =================================================================
    // KATEGORI TETAP FLEKSIBEL
    // =================================================================

    public function test_kategori_restok_produk_tersedia_dan_terhitung(): void
    {
        $this->pengeluaran();

        $this->assertSame(400_000.0, (float) Pengeluaran::aktif()->sum('nominal'));

        $this->daftar()->assertSee('Re-Stok Produk');
    }

    public function test_kategori_muncul_di_filter(): void
    {
        $this->pengeluaran();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.pengeluaran.index', [
                'tahun' => now()->year, 'bulan' => now()->month, 'kategori' => 'Re-Stok Produk',
            ]))
            ->assertOk()
            ->assertSee('Re-Stok Produk')
            ->assertSee('400.000');
    }
}
