<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LABA RUGI HARUS BISA DITELUSURI BARIS PER BARIS.
 *
 * =====================================================================
 * SATU HUBUNGAN YANG PALING MUDAH "DIPERBAIKI" JADI SALAH
 * =====================================================================
 *
 * Laba bersih TOTAL bukan penjumlahan laba bersih tiap unit. Selisihnya
 * adalah pengeluaran operasional koperasi — biaya yang tidak menempel ke
 * unit usaha mana pun (listrik kantor, administrasi) dan karena itu tidak
 * dibebankan otomatis ke unit mana pun.
 *
 *   laba bersih total = jumlah laba bersih unit - pengeluaran operasional
 *
 * Itu keputusan akuntansi yang disengaja dan sudah tertulis di servicenya.
 * Tapi bagi orang yang membaca sepintas, total yang tidak sama dengan
 * jumlah barisnya terlihat seperti kekeliruan — dan "memperbaikinya" menjadi
 * penjumlahan biasa akan membuat seluruh pengeluaran operasional LENYAP dari
 * laporan laba rugi tanpa ada satu pun angka yang terlihat aneh.
 *
 * Test ini menjaga hubungan itu apa adanya.
 */
class LabaRugiRekonsiliasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BusinessUnit $unitA;
    private BusinessUnit $unitB;
    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@labarugi.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->unitA = BusinessUnit::create([
            'nama' => 'Unit A', 'kode' => 'UA', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);

        $this->unitB = BusinessUnit::create([
            'nama' => 'Unit B', 'kode' => 'UB', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);

        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function jual(BusinessUnit $unit, int $qty, int $hargaJual, int $hargaModal): void
    {
        $produk = ProductService::create([
            'unit_id' => $unit->id,
            'kode' => 'P-' . $unit->id . '-' . $qty . $hargaJual,
            'nama' => 'Produk ' . $unit->kode,
            'jenis' => 'Produk',
            'harga_modal' => $hargaModal,
            'harga_jual' => $hargaJual,
            'status' => 'Aktif',
            'stock_tracking' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $unit->id,
                'tanggal' => now()->toDateString(),
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'items_master' => [['product_id' => $produk->id, 'qty' => $qty]],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();
    }

    private function keluar(?BusinessUnit $unit, int $nominal): void
    {
        Pengeluaran::create([
            'tanggal' => now()->toDateString(),
            'business_unit_id' => $unit?->id,
            'kategori' => 'Operasional',
            'nominal' => $nominal,
            'status' => Pengeluaran::STATUS_AKTIF,
        ]);
    }

    private function hitung(): array
    {
        return app(LabaRugiService::class)->hitung((string) now()->year, (string) now()->month);
    }

    // =================================================================
    // RANTAI PERHITUNGAN
    // =================================================================

    public function test_laba_kotor_adalah_penjualan_dikurangi_hpp(): void
    {
        $this->jual($this->unitA, 3, 20000, 12000);

        $unit = $this->hitung()['per_unit'][$this->unitA->id];

        $this->assertSame(60000.0, (float) $unit['penjualan']);
        $this->assertSame(36000.0, (float) $unit['hpp']);
        $this->assertSame(
            (float) $unit['penjualan'] - (float) $unit['hpp'],
            (float) $unit['laba_kotor']
        );
    }

    public function test_laba_bersih_unit_adalah_kontribusi_dikurangi_pengeluarannya(): void
    {
        $this->jual($this->unitA, 3, 20000, 12000);
        $this->keluar($this->unitA, 5000);

        $unit = $this->hitung()['per_unit'][$this->unitA->id];

        $this->assertSame(5000.0, (float) $unit['pengeluaran']);
        $this->assertSame(
            (float) $unit['kontribusi_usaha'] - (float) $unit['pengeluaran'],
            (float) $unit['laba_bersih']
        );
    }

    public function test_total_penjualan_adalah_jumlah_seluruh_unit(): void
    {
        $this->jual($this->unitA, 3, 20000, 12000);
        $this->jual($this->unitB, 2, 35000, 20000);

        $hasil = $this->hitung();

        $jumlahUnit = array_sum(array_map(
            fn ($u) => (float) $u['penjualan'],
            $hasil['per_unit']
        ));

        $this->assertSame($jumlahUnit, (float) $hasil['total']['penjualan']);
        $this->assertSame(130000.0, (float) $hasil['total']['penjualan']);
    }

    // =================================================================
    // PENGELUARAN OPERASIONAL — INTI TEST INI
    // =================================================================

    /**
     * Biaya tanpa unit tidak boleh lenyap, dan tidak boleh pula dibebankan
     * diam-diam ke salah satu unit.
     */
    public function test_pengeluaran_operasional_tidak_dibebankan_ke_unit_mana_pun(): void
    {
        $this->jual($this->unitA, 3, 20000, 12000);
        $this->jual($this->unitB, 2, 35000, 20000);
        $this->keluar(null, 40000);

        $hasil = $this->hitung();

        foreach ($hasil['per_unit'] as $u) {
            $this->assertSame(
                0.0,
                (float) $u['pengeluaran'],
                'Pengeluaran operasional bocor ke unit ' . $u['nama'] . '.'
            );
        }

        $this->assertSame(40000.0, (float) $hasil['total']['pengeluaran_operasional']);
        $this->assertSame(40000.0, (float) $hasil['total']['pengeluaran_total']);
    }

    /**
     * Hubungan yang harus tetap begitu — lihat penjelasan di kepala berkas.
     */
    public function test_laba_bersih_total_adalah_jumlah_unit_dikurangi_operasional(): void
    {
        $this->jual($this->unitA, 3, 20000, 12000);
        $this->jual($this->unitB, 2, 35000, 20000);
        $this->keluar($this->unitA, 5000);
        $this->keluar($this->unitB, 7000);
        $this->keluar(null, 40000);

        $hasil = $this->hitung();

        $jumlahLabaUnit = array_sum(array_map(
            fn ($u) => (float) $u['laba_bersih'],
            $hasil['per_unit']
        ));

        $this->assertSame(
            $jumlahLabaUnit - 40000.0,
            (float) $hasil['total']['laba_bersih'],
            'Laba bersih total harus jumlah laba unit DIKURANGI pengeluaran operasional.'
        );

        // Dan pengeluaran operasional memang ikut terhitung — bukan nol.
        $this->assertNotSame($jumlahLabaUnit, (float) $hasil['total']['laba_bersih']);
    }

    // =================================================================
    // KEADAAN KOSONG
    // =================================================================

    /**
     * Margin tanpa penjualan tidak terdefinisi — bukan 0%.
     *
     * Pola yang sama dengan rata-rata omzet: nol persen terbaca sebagai
     * "usahanya tidak untung", padahal yang benar "belum ada penjualan".
     */
    public function test_margin_tanpa_penjualan_bernilai_null_bukan_nol(): void
    {
        $hasil = $this->hitung();

        $this->assertNull($hasil['total']['margin']);

        foreach ($hasil['per_unit'] as $u) {
            $this->assertNull($u['margin'], 'Margin unit ' . $u['nama'] . ' seharusnya null.');
        }

        $this->assertFalse($hasil['ada_transaksi']);
    }

    /**
     * Transaksi Draft dan Dibatalkan tidak boleh masuk laba rugi.
     */
    public function test_transaksi_dibatalkan_tidak_ikut_dihitung(): void
    {
        $this->jual($this->unitA, 3, 20000, 12000);

        $sebelum = (float) $this->hitung()['total']['penjualan'];

        SalesTransaction::latest('id')->firstOrFail()
            ->update(['status_transaksi' => SalesTransaction::STATUS_DIBATALKAN]);

        $this->assertSame(60000.0, $sebelum);
        $this->assertSame(0.0, (float) $this->hitung()['total']['penjualan']);
    }
}
