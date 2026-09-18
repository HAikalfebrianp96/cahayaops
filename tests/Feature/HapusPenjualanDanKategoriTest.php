<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\Pengeluaran;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HAPUS PERMANEN PENJUALAN, DAN KATEGORI PENGELUARAN YANG DINAMIS.
 *
 * =====================================================================
 * PENJUALAN — KENAPA HARUS DIBATALKAN DULU
 * =====================================================================
 *
 * Penjaganya menolak menghapus transaksi yang masih aktif. Itu bukan
 * langkah birokratis: Batalkan MEWAJIBKAN alasan, dan alasan itu tercatat
 * di Activity Log.
 *
 * Kalau transaksi aktif boleh langsung dihapus, satu-satunya keterangan
 * yang tersisa tentang uang yang hilang dari laba rugi adalah kata
 * "dihapus" — tanpa siapa pun tahu kenapa, termasuk orang yang menghapus
 * itu sendiri enam bulan kemudian.
 *
 * =====================================================================
 * ITEM DAN INVOICE IKUT — DAN ITU HARUS UTUH
 * =====================================================================
 *
 * sales_transaction_items dan invoices ber-ON DELETE CASCADE. Keduanya
 * bagian dari transaksi yang sama, bukan data berdiri sendiri. Yang dijaga
 * di sini: tidak boleh tersisa separuh — baris induk hilang sementara
 * item-itemnya tertinggal sebagai yatim yang tidak lagi bisa dijumlahkan
 * ke transaksi mana pun.
 */
class HapusPenjualanDanKategoriTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'koperasi@hapus.test');
        $this->admin = $this->user(User::ROLE_ADMIN, 'admin@hapus.test');
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => 'Akun ' . $role,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function unit(): BusinessUnit
    {
        return BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-HP', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    /** Transaksi lengkap: punya item dan invoice, seperti transaksi sungguhan. */
    private function transaksiLengkap(string $status = 'Aktif'): SalesTransaction
    {
        $unit = $this->unit();

        $produk = ProductService::create([
            'unit_id' => $unit->id,
            'kode' => 'PRD-UJI-1',
            'nama' => 'Beras 5 kg',
            'jenis' => 'Barang',
            'harga_modal' => 50_000,
            'harga_jual' => 65_000,
            'status' => 'Aktif',
        ]);

        $transaksi = SalesTransaction::create([
            'unit_id' => $unit->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 130_000, 'diskon' => 0, 'ongkir' => 0, 'total' => 130_000,
            'status_transaksi' => $status,
            'status_pembayaran' => 'Lunas',
        ]);

        SalesTransactionItem::create([
            'sales_transaction_id' => $transaksi->id,
            'product_service_id' => $produk->id,
            'nama_snapshot' => 'Beras 5 kg',
            'jenis_snapshot' => 'Barang',
            'qty' => 2,
            'harga_jual_snapshot' => 65_000,
            'harga_modal_snapshot' => 50_000,
            'subtotal' => 130_000,
        ]);

        Invoice::create([
            'sales_transaction_id' => $transaksi->id,
            'nomor_invoice' => 'INV-UJI-' . $transaksi->id,
            'tanggal_invoice' => now()->toDateString(),
        ]);

        return $transaksi->fresh();
    }

    private function batalkan(SalesTransaction $t): SalesTransaction
    {
        $t->forceFill([
            'status_transaksi' => SalesTransaction::STATUS_DIBATALKAN,
            'alasan_pembatalan' => 'Salah input jumlah',
            'dibatalkan_at' => now(),
            'dibatalkan_oleh' => $this->timKoperasi->id,
        ])->save();

        return $t->fresh();
    }

    // =================================================================
    // PENJUALAN — HAPUS PERMANEN
    // =================================================================

    /**
     * Transaksi AKTIF ditolak, dengan alasan yang bisa dibaca petugas.
     */
    public function test_transaksi_aktif_tidak_bisa_dihapus_permanen(): void
    {
        $transaksi = $this->transaksiLengkap();

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.penjualan.destroy', $transaksi))
            ->assertSessionHasErrors('hapus');

        $this->assertDatabaseHas('sales_transactions', ['id' => $transaksi->id]);

        $pesan = session('errors')->first('hapus');

        $this->assertStringContainsString('Batalkan dulu', $pesan);
    }

    public function test_transaksi_dibatalkan_bisa_dihapus_permanen(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.penjualan.destroy', $transaksi))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('koperasi.penjualan.index'));

        $this->assertDatabaseMissing('sales_transactions', ['id' => $transaksi->id]);
    }

    /**
     * Tidak boleh ada yatim.
     *
     * Item dan invoice yang tertinggal tanpa transaksinya tidak akan pernah
     * muncul di laporan mana pun — tapi tetap ikut terhitung kalau suatu
     * saat ada yang menjumlahkan tabel itu langsung.
     */
    public function test_item_dan_invoice_ikut_terhapus_tanpa_menyisakan_yatim(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        $this->assertSame(1, SalesTransactionItem::where('sales_transaction_id', $transaksi->id)->count());
        $this->assertSame(1, Invoice::where('sales_transaction_id', $transaksi->id)->count());

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.penjualan.destroy', $transaksi));

        $this->assertSame(0, SalesTransactionItem::where('sales_transaction_id', $transaksi->id)->count());
        $this->assertSame(0, Invoice::where('sales_transaction_id', $transaksi->id)->count());

        // Dan tidak ada item/invoice yang menunjuk transaksi yang sudah tiada.
        $this->assertSame(0, SalesTransactionItem::whereNotIn(
            'sales_transaction_id',
            SalesTransaction::pluck('id')
        )->count());
    }

    /** Produk yang pernah dijual TIDAK ikut terhapus. */
    public function test_produk_tidak_ikut_terhapus(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.penjualan.destroy', $transaksi));

        $this->assertSame(1, ProductService::count());
        $this->assertSame(1, BusinessUnit::count());
    }

    public function test_penghapusan_tercatat_di_activity_log(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());
        $nomor = $transaksi->nomor_transaksi;

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.penjualan.destroy', $transaksi));

        $jejak = ActivityLog::where('action', 'sale_permanently_deleted')->latest('id')->first();

        $this->assertNotNull($jejak);
        $this->assertStringContainsString($nomor, $jejak->description);
        $this->assertSame($this->timKoperasi->id, $jejak->user_id);
        $this->assertNotNull($jejak->created_at);
    }

    /** Tidak ada sandi yang ikut tercatat. */
    public function test_activity_log_tidak_memuat_kredensial(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.penjualan.destroy', $transaksi));

        foreach (ActivityLog::all() as $baris) {
            $this->assertStringNotContainsString('rahasia123', (string) $baris->description);
            $this->assertStringNotContainsString($this->timKoperasi->password, (string) $baris->description);
        }
    }

    // =================================================================
    // PENJUALAN — OTORISASI
    // =================================================================

    public function test_admin_juga_boleh_menghapus(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        $this->actingAs($this->admin)
            ->delete(route('koperasi.penjualan.destroy', $transaksi))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sales_transactions', ['id' => $transaksi->id]);
    }

    public function test_peran_lain_tidak_bisa_menghapus(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        foreach ([User::ROLE_PIMPINAN, User::ROLE_PENDAMPING] as $role) {
            $pelaku = $this->user($role, $role . '@hapus.test');

            $this->actingAs($pelaku)
                ->delete(route('koperasi.penjualan.destroy', $transaksi))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('sales_transactions', ['id' => $transaksi->id]);
    }

    public function test_tamu_tidak_bisa_menghapus(): void
    {
        $transaksi = $this->batalkan($this->transaksiLengkap());

        $this->delete(route('koperasi.penjualan.destroy', $transaksi))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('sales_transactions', ['id' => $transaksi->id]);
    }

    /** Tombolnya hanya muncul pada baris yang memang boleh dihapus. */
    public function test_tombol_hapus_permanen_hanya_pada_transaksi_dibatalkan(): void
    {
        $this->transaksiLengkap();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.index'))
            ->assertOk()
            ->assertSee('Edit')
            ->assertDontSee('Hapus Permanen');

        $this->batalkan(SalesTransaction::first());

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.index'))
            ->assertOk()
            ->assertSee('Hapus Permanen')
            ->assertSee('Pulihkan');
    }

    // =================================================================
    // PENGELUARAN — KATEGORI DINAMIS
    // =================================================================

    public function test_kategori_restok_produk_tersedia(): void
    {
        $this->assertDatabaseHas('pengeluaran_categories', [
            'nama' => 'Re-Stok Produk',
            'status' => 'Aktif',
        ]);
    }

    /** Keenam kategori yang diminta ada semua. */
    public function test_kategori_bawaan_lengkap(): void
    {
        foreach (['Gaji', 'Listrik', 'BBM', 'Kemasan', 'Re-Stok Produk', 'Lainnya'] as $nama) {
            $this->assertDatabaseHas('pengeluaran_categories', ['nama' => $nama]);
        }
    }

    public function test_kategori_muncul_di_form_pengeluaran(): void
    {
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.pengeluaran.create'))
            ->assertOk()
            ->assertSee('Re-Stok Produk')
            ->assertSee('+ Tambah Kategori');
    }

    /** Tautan tambah kategori tidak membuka pintu bagi peran lain. */
    public function test_tautan_tambah_kategori_hanya_untuk_pengelola_koperasi(): void
    {
        $pendamping = $this->user(User::ROLE_PENDAMPING, 'rosa@hapus.test');

        $this->actingAs($pendamping)
            ->get(route('koperasi.kategori-pengeluaran.index'))
            ->assertForbidden();
    }

    public function test_kategori_baru_bisa_dibuat_dan_dipakai(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.kategori-pengeluaran.store'), ['nama' => 'Internet'])
            ->assertSessionHasNoErrors();

        $kategori = PengeluaranCategory::where('nama', 'Internet')->first();

        $this->assertNotNull($kategori);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.pengeluaran.store'), [
                'tanggal' => now()->toDateString(),
                'pengeluaran_category_id' => $kategori->id,
                'nominal' => 250_000,
                'metode_pembayaran' => 'Cash',
                'keterangan' => 'Langganan bulanan',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengeluaran', [
            'pengeluaran_category_id' => $kategori->id,
            'nominal' => 250_000,
        ]);
    }

    /**
     * Kategori baru ikut terbaca laporan, bukan hanya muncul di dropdown.
     *
     * Kategori yang tampil di form tapi tidak terhitung di total pengeluaran
     * adalah kesalahan yang paling sulit terlihat: datanya masuk, angkanya
     * tidak.
     */
    public function test_pengeluaran_kategori_baru_ikut_terhitung(): void
    {
        $restok = PengeluaranCategory::where('nama', 'Re-Stok Produk')->first();

        $this->actingAs($this->timKoperasi)->post(route('koperasi.pengeluaran.store'), [
            'tanggal' => now()->toDateString(),
            'pengeluaran_category_id' => $restok->id,
            'nominal' => 400_000,
            'metode_pembayaran' => 'Cash',
            'keterangan' => 'Beli stok beras',
        ])->assertSessionHasNoErrors();

        $this->assertSame(400_000.0, (float) Pengeluaran::aktif()->sum('nominal'));

        $halaman = $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.pengeluaran.index'))
            ->assertOk();

        // Daftar pengeluaran menampilkan Tanggal/Unit/Kategori/Nominal/
        // Metode/Status — keterangannya ada di halaman detail, bukan di sini.
        $halaman->assertSee('Re-Stok Produk');
        $halaman->assertSee('400.000');

        // Dan kategorinya bisa dipakai menyaring, bukan cuma tampil.
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.pengeluaran.index', ['kategori' => 'Re-Stok Produk']))
            ->assertOk()
            ->assertSee('Re-Stok Produk')
            ->assertSee('400.000');
    }

    /** Kategori yang sudah dipakai tidak boleh hilang begitu saja. */
    public function test_kategori_yang_sudah_dipakai_tidak_bisa_dihapus(): void
    {
        $restok = PengeluaranCategory::where('nama', 'Re-Stok Produk')->first();

        Pengeluaran::create([
            'tanggal' => now()->toDateString(),
            'pengeluaran_category_id' => $restok->id,
            'kategori' => $restok->nama,
            'nominal' => 100_000,
            'keterangan' => 'Stok',
        ]);

        // Penolakannya disampaikan sebagai PENJELASAN (flash 'error'), bukan
        // sebagai galat validasi — ini keputusan yang disengaja, bukan
        // kegagalan sistem.
        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.kategori-pengeluaran.destroy', $restok))
            ->assertSessionHas('error');

        $this->assertStringContainsString('masih dipakai', session('error'));

        $this->assertDatabaseHas('pengeluaran_categories', ['id' => $restok->id]);
    }
}
