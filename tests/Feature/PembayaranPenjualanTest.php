<?php

namespace Tests\Feature;

use App\Models\AkunKas;
use App\Models\BusinessUnit;
use App\Models\PembayaranPenjualan;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * KAS & BANK — TAHAP 2: RIWAYAT PEMBAYARAN PENJUALAN.
 *
 * Satu janji yang dijaga di seluruh berkas ini: dp_nominal SELALU sama
 * dengan jumlah pembayaran yang berlaku. Invoice, laba rugi, laporan, dan
 * SHU masih membaca dp_nominal — begitu keduanya berbeda, angka di layar
 * dan uang di laci mulai bercerita dua hal yang berlainan.
 */
class PembayaranPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;
    private BusinessUnit $unit;
    private ProductService $beras;
    private AkunKas $kas;
    private AkunKas $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'koperasi@bayar.test');
        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'UNIT-BYR', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
        $this->beras = ProductService::create([
            'unit_id' => $this->unit->id, 'nama' => 'Beras 5kg', 'jenis' => 'Produk', 'satuan' => 'Pcs',
            'harga_modal' => 80000, 'harga_jual' => 100000, 'status' => 'Aktif',
        ]);
        $this->kas = AkunKas::where('nama', 'Kas Tunai')->firstOrFail();
        $this->bank = AkunKas::where('nama', 'Rekening Bank')->firstOrFail();
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

    /** Transaksi satu item senilai Rp100.000 lewat form sungguhan. */
    private function payload(array $ubah = []): array
    {
        return array_merge([
            'unit_id' => $this->unit->id,
            'tanggal' => '2026-09-01',
            'status_transaksi' => 'Aktif',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
            'metode_pengiriman' => 'Ambil di Tempat',
            'customer_nama' => 'Pelanggan Toko',
            'items_master' => [[
                'product_id' => $this->beras->id, 'qty' => 1, 'harga_jual' => 100000, 'harga_modal' => 80000,
            ]],
        ], $ubah);
    }

    private function simpan(array $ubah = []): SalesTransaction
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.store'), $this->payload($ubah))
            ->assertSessionHasNoErrors();

        return SalesTransaction::latest('id')->firstOrFail();
    }

    private function terima(SalesTransaction $trx, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->timKoperasi)->post(route('koperasi.penjualan.pembayaran.store', $trx), array_merge([
            'tanggal' => '2026-09-10',
            'akun_kas_id' => $this->kas->id,
            'metode' => 'Cash',
        ], $data));
    }

    private function assertRingkasanSamaDenganRiwayat(SalesTransaction $trx): void
    {
        $trx->refresh();
        $this->assertEquals(
            (float) $trx->pembayaran()->berlaku()->sum('nominal'),
            (float) $trx->dp_nominal,
            'dp_nominal harus selalu sama dengan jumlah pembayaran yang berlaku'
        );
    }

    // =================================================================
    // FORM TRANSAKSI
    // =================================================================

    public function test_transaksi_lunas_cash_mencatat_satu_pembayaran_ke_kas(): void
    {
        $trx = $this->simpan();

        $this->assertCount(1, $trx->pembayaran);
        $p = $trx->pembayaran->first();
        $this->assertEquals(100000, (float) $p->nominal);
        $this->assertSame($this->kas->id, $p->akun_kas_id);
        $this->assertSame('2026-09-01', $p->tanggal->toDateString());
        $this->assertSame('Lunas', $trx->status_pembayaran);
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_kelebihan_bayar_dicatat_sebagai_kembalian(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.store'), $this->payload(['nominal_dibayar' => 120000]))
            ->assertSessionHas('success', fn ($pesan) => str_contains($pesan, 'Kelebihan Rp20.000 dicatat sebagai kembalian'));

        $trx = SalesTransaction::latest('id')->firstOrFail();
        $this->assertEquals(100000, (float) $trx->dp_nominal);
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_dp_transfer_masuk_ke_akun_bank(): void
    {
        $trx = $this->simpan([
            'status_pembayaran' => 'DP', 'metode_pembayaran' => 'Transfer', 'nominal_dibayar' => 30000,
        ]);

        $this->assertSame($this->bank->id, $trx->pembayaran->first()->akun_kas_id);
        $this->assertSame('DP', $trx->status_pembayaran);
        $this->assertEquals(70000, $trx->sisaPembayaran());
    }

    public function test_akun_pilihan_petugas_mengalahkan_akun_bawaan(): void
    {
        $trx = $this->simpan(['metode_pembayaran' => 'Transfer', 'akun_kas_id' => $this->kas->id]);

        $this->assertSame($this->kas->id, $trx->pembayaran->first()->akun_kas_id);
    }

    public function test_belum_dibayar_tidak_membuat_pembayaran(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'Belum Dibayar']);

        $this->assertCount(0, $trx->pembayaran);
        $this->assertEquals(0, (float) $trx->dp_nominal);
    }

    public function test_menaikkan_nominal_lewat_edit_mencatat_tambahan_bertanggal_hari_ini(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'DP', 'nominal_dibayar' => 30000]);

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(['status_pembayaran' => 'DP', 'nominal_dibayar' => 50000]))
            ->assertSessionHasNoErrors();

        $berlaku = $trx->pembayaran()->berlaku()->get();
        $this->assertCount(2, $berlaku);
        $this->assertEquals(20000, (float) $berlaku->last()->nominal);
        $this->assertSame(now()->toDateString(), $berlaku->last()->tanggal->toDateString());
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_edit_tanpa_mengubah_nominal_tidak_menambah_pembayaran(): void
    {
        $trx = $this->simpan();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(['catatan' => 'hanya ganti catatan']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $trx->pembayaran()->count());
    }

    public function test_menurunkan_nominal_membatalkan_dan_mengganti_dengan_tanggal_asli(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'DP', 'nominal_dibayar' => 30000]);
        $this->terima($trx, ['nominal' => 20000, 'tanggal' => '2026-09-05'])->assertSessionHasNoErrors();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(['status_pembayaran' => 'DP', 'nominal_dibayar' => 20000]))
            ->assertSessionHasNoErrors();

        $berlaku = $trx->pembayaran()->berlaku()->get();
        $this->assertCount(1, $berlaku);
        $this->assertEquals(20000, (float) $berlaku->first()->nominal);
        // Pengganti memakai tanggal pembayaran pertama (2026-09-01), bukan hari ini.
        $this->assertSame('2026-09-01', $berlaku->first()->tanggal->toDateString());
        // Tidak ada yang dihapus: dua baris lama tetap ada sebagai jejak.
        $this->assertSame(2, $trx->pembayaran()->where('status', 'Dibatalkan')->count());
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_total_turun_di_bawah_yang_dibayar_dibatasi_setinggi_total(): void
    {
        $trx = $this->simpan();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload([
                'items_master' => [[
                    'product_id' => $this->beras->id, 'qty' => 1, 'harga_jual' => 90000, 'harga_modal' => 80000,
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $trx->refresh();
        $this->assertEquals(90000, (float) $trx->dp_nominal);
        $this->assertSame('Lunas', $trx->status_pembayaran);
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    // =================================================================
    // TERIMA & BATALKAN PEMBAYARAN
    // =================================================================

    public function test_pelunasan_punya_tanggal_dan_akunnya_sendiri(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'DP', 'nominal_dibayar' => 30000]);

        $this->terima($trx, ['nominal' => 70000, 'akun_kas_id' => $this->bank->id, 'metode' => 'Transfer'])
            ->assertRedirect(route('koperasi.penjualan.show', $trx));

        $trx->refresh();
        $this->assertSame('Lunas', $trx->status_pembayaran);
        $pelunasan = $trx->pembayaran->last();
        $this->assertSame('2026-09-10', $pelunasan->tanggal->toDateString());
        $this->assertSame($this->bank->id, $pelunasan->akun_kas_id);
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_pembayaran_melebihi_sisa_ditolak(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'DP', 'nominal_dibayar' => 30000]);

        $this->terima($trx, ['nominal' => 70001])->assertSessionHasErrors('nominal', null, 'pembayaran');
        $this->assertSame(1, $trx->pembayaran()->count());
    }

    public function test_tanggal_pembayaran_di_masa_depan_ditolak(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'Belum Dibayar']);

        $this->terima($trx, ['nominal' => 1000, 'tanggal' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('tanggal', null, 'pembayaran');
    }

    public function test_batalkan_pembayaran_wajib_alasan_dan_barisnya_tetap_ada(): void
    {
        $trx = $this->simpan();
        $p = $trx->pembayaran->first();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.pembayaran.batalkan', $p), [])
            ->assertSessionHasErrors('alasan_pembatalan', null, 'batalPembayaran');

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.pembayaran.batalkan', $p), ['alasan_pembatalan' => 'Salah input'])
            ->assertRedirect(route('koperasi.penjualan.show', $trx));

        $trx->refresh();
        $this->assertSame('Belum Dibayar', $trx->status_pembayaran);
        $this->assertSame('Dibatalkan', $p->fresh()->status);
        $this->assertSame($this->timKoperasi->id, $p->fresh()->dibatalkan_oleh);
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_pimpinan_tidak_bisa_mencatat_pembayaran(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'Belum Dibayar']);

        $this->actingAs($this->user(User::ROLE_PIMPINAN, 'pimpinan@bayar.test'))
            ->post(route('koperasi.penjualan.pembayaran.store', $trx), [
                'tanggal' => '2026-09-10', 'nominal' => 1000, 'akun_kas_id' => $this->kas->id, 'metode' => 'Cash',
            ])->assertForbidden();
    }

    public function test_halaman_detail_menampilkan_riwayat_dan_form_terima(): void
    {
        $trx = $this->simpan(['status_pembayaran' => 'DP', 'nominal_dibayar' => 30000]);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertSee('Riwayat Pembayaran')
            ->assertSee('Terima Pembayaran (sisa Rp70.000)')
            ->assertSee('Kas Tunai');
    }

    // =================================================================
    // TRANSAKSI DIBATALKAN & DATA LAMA
    // =================================================================

    public function test_pembayaran_transaksi_dibatalkan_berhenti_dihitung_dan_kembali_saat_dipulihkan(): void
    {
        $trx = $this->simpan();
        $this->assertEquals(100000, (float) PembayaranPenjualan::masukKas()->sum('nominal'));

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.batalkan', $trx), ['alasan_pembatalan' => 'Pembeli batal']);
        $this->assertEquals(0, (float) PembayaranPenjualan::masukKas()->sum('nominal'));

        $this->terima($trx, ['nominal' => 1000])->assertForbidden();

        $this->actingAs($this->timKoperasi)->post(route('koperasi.penjualan.pulihkan', $trx));
        $this->assertEquals(100000, (float) PembayaranPenjualan::masukKas()->sum('nominal'));
    }

    public function test_transaksi_lama_tanpa_riwayat_tidak_kehilangan_uangnya(): void
    {
        // Seperti transaksi lama/impor: dp_nominal ada, baris pembayaran belum.
        $trx = SalesTransaction::create([
            'unit_id' => $this->unit->id, 'tanggal' => '2026-08-01', 'status_transaksi' => 'Aktif',
            'subtotal' => 100000, 'total' => 100000, 'status_pembayaran' => 'DP',
            'dp_nominal' => 50000, 'metode_pembayaran' => 'Cash',
        ]);

        $this->terima($trx, ['nominal' => 20000])->assertSessionHasNoErrors();

        $trx->refresh();
        $this->assertEquals(70000, (float) $trx->dp_nominal);
        $this->assertSame('2026-08-01', $trx->pembayaran->first()->tanggal->toDateString());
        $this->assertRingkasanSamaDenganRiwayat($trx);
    }

    public function test_migrasi_data_lama_membatasi_lebih_bayar_dan_aman_diulang(): void
    {
        $lunas = SalesTransaction::create([
            'unit_id' => $this->unit->id, 'tanggal' => '2026-06-30', 'status_transaksi' => 'Selesai',
            'subtotal' => 19000, 'total' => 19000, 'status_pembayaran' => 'Lunas',
            'dp_nominal' => 20000, 'metode_pembayaran' => 'Cash',
        ]);
        $qris = SalesTransaction::create([
            'unit_id' => $this->unit->id, 'tanggal' => '2026-07-01', 'status_transaksi' => 'Aktif',
            'subtotal' => 50000, 'total' => 50000, 'status_pembayaran' => 'DP',
            'dp_nominal' => 10000, 'metode_pembayaran' => 'QRIS',
        ]);
        $belum = SalesTransaction::create([
            'unit_id' => $this->unit->id, 'tanggal' => '2026-07-02', 'status_transaksi' => 'Aktif',
            'subtotal' => 5000, 'total' => 5000, 'status_pembayaran' => 'Belum Dibayar',
            'dp_nominal' => 0, 'metode_pembayaran' => 'Cash',
        ]);

        $migration = require database_path('migrations/2026_09_23_200001_create_pembayaran_penjualan_table.php');
        $jalankan = (new \ReflectionMethod($migration, 'pindahkanPembayaranLama'))->getClosure($migration);
        $jalankan();
        $jalankan(); // kedua kali tidak boleh menggandakan

        $this->assertSame(1, $lunas->pembayaran()->count());
        $this->assertEquals(19000, (float) $lunas->pembayaran()->first()->nominal);
        $this->assertStringContainsString('dianggap kembalian', $lunas->pembayaran()->first()->catatan);
        $this->assertEquals(19000, (float) DB::table('sales_transactions')->where('id', $lunas->id)->value('dp_nominal'));

        $this->assertSame($this->bank->id, $qris->pembayaran()->first()->akun_kas_id);
        $this->assertSame('2026-07-01', $qris->pembayaran()->first()->tanggal->toDateString());
        $this->assertSame(0, $belum->pembayaran()->count());
    }

    public function test_akun_yang_pernah_menerima_pembayaran_tidak_bisa_dihapus(): void
    {
        $this->simpan();

        $this->actingAs($this->timKoperasi)
            ->from(route('koperasi.kas-bank.index'))
            ->delete(route('koperasi.kas-bank.destroy', $this->kas))
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'pembayaran penjualan'));

        $this->assertTrue(AkunKas::whereKey($this->kas->id)->exists());
    }
}
