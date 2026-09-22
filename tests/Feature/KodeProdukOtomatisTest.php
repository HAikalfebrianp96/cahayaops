<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KODE PRODUK DIBUAT SISTEM.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Produk bisa lahir dari dua jalur, dan keduanya berbeda perlakuan:
 *
 *   Form Tambah Produk  -> petugas DIMINTA mengetik kodenya sendiri
 *   Jalur cepat di form penjualan -> sistem membuatkan "QP" + tanggal + acak
 *
 * Dua jalur, dua pola kode, untuk data yang sama. Dan kode yang diketik
 * tangan berarti dua barang berbeda bisa memakai kode yang sama di unit
 * yang berbeda — karena unique-nya hanya gabungan (unit_id, kode).
 *
 * Kode produk ikut tercetak di nota. Kode yang sama untuk dua barang
 * berbeda membuat nota lama berhenti bisa dipercaya.
 *
 * =====================================================================
 * YANG DIJAGA DI SINI
 * =====================================================================
 *
 * Bukan hanya "kodenya terisi", melainkan empat hal yang masing-masing
 * rusak tanpa menimbulkan galat: kodenya berurut, tidak pernah terpakai
 * ulang, tidak berubah saat diedit, dan tidak bisa disisipkan lewat
 * request buatan tangan.
 */
class KodeProdukOtomatisTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;
    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@kode.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-KD', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    /**
     * Menanam kode yang SUDAH ADA, meniru keadaan produksi.
     *
     * Database pengujian berangkat kosong, jadi tanpa ini setiap test hanya
     * membuktikan "kode pertama adalah 0001" — bukan hal yang diminta.
     * Yang perlu dibuktikan justru: generator MELANJUTKAN nomor yang sudah
     * dipakai petugas, bukan memulai deret sendiri.
     */
    private function tanamKodeExisting(): void
    {
        foreach (['0201', '0202', '0203'] as $i => $nomor) {
            ProductService::create([
                'unit_id' => $this->unit->id,
                'kode' => ProductService::AWALAN_KODE . $nomor,
                'nama' => 'Produk Lama ' . ($i + 1),
                'jenis' => 'Produk',
                'harga_modal' => 1_000,
                'harga_jual' => 2_000,
                'status' => 'Aktif',
            ]);
        }
    }

    private function buatProduk(array $ganti = []): ProductService
    {
        return ProductService::create(array_merge([
            'unit_id' => $this->unit->id,
            'nama' => 'Keripik Pisang',
            'jenis' => 'Produk',
            'satuan' => 'Pcs',
            'harga_modal' => 10_000,
            'harga_jual' => 15_000,
            'status' => 'Aktif',
        ], $ganti));
    }

    // =================================================================
    // OTOMATIS DAN BERURUT
    // =================================================================

    public function test_produk_baru_melanjutkan_nomor_yang_sudah_ada(): void
    {
        $this->tanamKodeExisting();

        $produk = $this->buatProduk();

        $this->assertNotNull($produk->kode);
        $this->assertSame(ProductService::AWALAN_KODE . '0204', $produk->kode);
    }

    /** Pada database yang benar-benar kosong, deretnya mulai dari satu. */
    public function test_tanpa_kode_sebelumnya_mulai_dari_satu(): void
    {
        $this->assertSame(ProductService::AWALAN_KODE . '0001', $this->buatProduk()->kode);
    }

    public function test_kode_berurut(): void
    {
        $this->tanamKodeExisting();

        $this->assertSame(ProductService::AWALAN_KODE . '0204', $this->buatProduk(['nama' => 'A'])->kode);
        $this->assertSame(ProductService::AWALAN_KODE . '0205', $this->buatProduk(['nama' => 'B'])->kode);
        $this->assertSame(ProductService::AWALAN_KODE . '0206', $this->buatProduk(['nama' => 'C'])->kode);
    }

    /**
     * Kode yang pernah sampai ke nota TIDAK bisa terpakai ulang — dan yang
     * menjaminnya bukan generatornya, melainkan penjaga penghapusan.
     *
     * Nomornya diambil dari kode tertinggi yang pernah ada, jadi mengarsipkan
     * produk tidak membebaskan nomornya (diuji terpisah di bawah). Untuk
     * penghapusan PERMANEN, jaminannya datang dari arah lain:
     * PenghapusanPermanenService menolak produk yang sudah dipakai satu baris
     * transaksi pun.
     *
     * Artinya produk yang kodenya pernah tercetak di nota tidak akan pernah
     * bisa dibuang — sehingga nomornya tidak pernah kembali beredar untuk
     * barang yang berbeda. Yang boleh dibuang hanya produk yang belum pernah
     * dipakai, dan kode semacam itu memang belum pernah sampai ke tangan
     * siapa pun.
     */
    public function test_produk_yang_pernah_dipakai_transaksi_tidak_bisa_dihapus(): void
    {
        $produk = $this->buatProduk();

        $transaksi = SalesTransaction::create([
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 15_000, 'diskon' => 0, 'ongkir' => 0, 'total' => 15_000,
            'status_transaksi' => 'Aktif', 'status_pembayaran' => 'Lunas',
        ]);

        SalesTransactionItem::create([
            'sales_transaction_id' => $transaksi->id,
            'product_service_id' => $produk->id,
            'nama_snapshot' => $produk->nama,
            'jenis_snapshot' => 'Produk',
            'qty' => 1,
            'harga_jual_snapshot' => 15_000,
            'harga_modal_snapshot' => 10_000,
            'subtotal' => 15_000,
        ]);

        $alasan = app(\App\Services\PenghapusanPermanenService::class)->alasanTertahan($produk);

        $this->assertNotNull($alasan, 'Produk yang sudah dipakai transaksi harus tertahan.');
        $this->assertStringContainsString('sudah dipakai', $alasan);

        $this->assertDatabaseHas('products_services', ['id' => $produk->id]);
    }

    /** Produk yang diarsipkan tetap memegang nomornya. */
    public function test_produk_terarsip_tetap_menahan_nomornya(): void
    {
        $this->tanamKodeExisting();

        $this->buatProduk(['nama' => 'A']);
        $this->buatProduk(['nama' => 'B'])->delete();

        $this->assertSame(ProductService::AWALAN_KODE . '0206', $this->buatProduk(['nama' => 'C'])->kode);
    }

    // =================================================================
    // UNIK — DIJAGA DATABASE, BUKAN HANYA PHP
    // =================================================================

    public function test_dua_produk_tidak_bisa_memakai_kode_yang_sama(): void
    {
        $this->tanamKodeExisting();
        $this->buatProduk(['nama' => 'A']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->buatProduk(['nama' => 'B', 'kode' => ProductService::AWALAN_KODE . '0204']);
    }

    /** Termasuk pada unit usaha yang BERBEDA. */
    public function test_kode_unik_lintas_unit_usaha(): void
    {
        $this->tanamKodeExisting();
        $this->buatProduk(['nama' => 'A']);

        $unitLain = BusinessUnit::create([
            'nama' => 'Unit Kedua', 'kode' => 'UNIT-KD2', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->buatProduk(['unit_id' => $unitLain->id, 'nama' => 'B', 'kode' => ProductService::AWALAN_KODE . '0204']);
    }

    // =================================================================
    // TIDAK BERUBAH, TIDAK BISA DISISIPKAN
    // =================================================================

    public function test_kode_tidak_berubah_saat_produk_diedit(): void
    {
        $produk = $this->buatProduk();
        $kodeAwal = $produk->kode;

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.produk.update', $produk), [
                'nama' => 'Keripik Pisang Manis',
                'jenis' => 'Produk',
                'satuan' => 'Pack',
                'harga_modal' => 12_000,
                'harga_jual' => 18_000,
                'status' => 'Aktif',
            ])
            ->assertSessionHasNoErrors();

        $segar = $produk->fresh();

        $this->assertSame($kodeAwal, $segar->kode);
        $this->assertSame('Keripik Pisang Manis', $segar->nama);
        $this->assertSame('18000.00', $segar->harga_jual);
    }

    /**
     * Kode tidak bisa disisipkan lewat request.
     *
     * Kalau kode masih dibaca dari input, satu permintaan buatan tangan bisa
     * menetapkan kode pilihan sendiri — termasuk kode yang sudah beredar di
     * nota untuk barang lain.
     */
    public function test_kode_dari_request_diabaikan(): void
    {
        $produk = $this->buatProduk();
        $kodeAwal = $produk->kode;

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.produk.update', $produk), [
                'kode' => 'KODE-PALSU',
                'nama' => 'Keripik Pisang',
                'jenis' => 'Produk',
                'harga_modal' => 10_000,
                'harga_jual' => 15_000,
                'status' => 'Aktif',
            ]);

        $this->assertSame($kodeAwal, $produk->fresh()->kode);
        $this->assertDatabaseMissing('products_services', ['kode' => 'KODE-PALSU']);
    }

    // =================================================================
    // TAMPIL DI FORM, DAFTAR, DAN PENJUALAN
    // =================================================================

    public function test_form_tambah_menyatakan_kode_dibuat_otomatis(): void
    {
        $this->tanamKodeExisting();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.produk.create', $this->unit))
            ->assertOk()
            ->assertSee('Dibuat otomatis oleh sistem')
            ->assertSee(ProductService::AWALAN_KODE . '0204');
    }

    /** Kode existing TIDAK diubah oleh generator. */
    public function test_kode_existing_tidak_berubah(): void
    {
        $this->tanamKodeExisting();

        $this->buatProduk(['nama' => 'Baru']);

        foreach (['0201', '0202', '0203'] as $nomor) {
            $this->assertDatabaseHas('products_services', ['kode' => ProductService::AWALAN_KODE . $nomor]);
        }
    }

    public function test_form_edit_menampilkan_kode_sebagai_keterangan(): void
    {
        $produk = $this->buatProduk();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.produk.edit', $produk))
            ->assertOk()
            ->assertSee($produk->kode)
            ->assertSee('Kode tidak berubah')
            ->assertDontSee('name="kode"', false);
    }

    public function test_kode_tampil_di_daftar_produk(): void
    {
        $produk = $this->buatProduk();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.unit-usaha.show', $this->unit))
            ->assertOk()
            ->assertSee($produk->kode)
            ->assertSee('Keripik Pisang');
    }

    /** Produk yang sudah dipakai transaksi tetap memegang kodenya. */
    public function test_kode_tetap_setelah_produk_dipakai_transaksi(): void
    {
        $produk = $this->buatProduk();
        $kodeAwal = $produk->kode;

        $transaksi = SalesTransaction::create([
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 15_000, 'diskon' => 0, 'ongkir' => 0, 'total' => 15_000,
            'status_transaksi' => 'Aktif', 'status_pembayaran' => 'Lunas',
        ]);

        SalesTransactionItem::create([
            'sales_transaction_id' => $transaksi->id,
            'product_service_id' => $produk->id,
            'nama_snapshot' => $produk->nama,
            'jenis_snapshot' => 'Produk',
            'qty' => 1,
            'harga_jual_snapshot' => 15_000,
            'harga_modal_snapshot' => 10_000,
            'subtotal' => 15_000,
        ]);

        $this->assertSame($kodeAwal, $produk->fresh()->kode);
    }

    // =================================================================
    // OTORISASI TIDAK BERUBAH
    // =================================================================

    public function test_peran_lain_tidak_bisa_membuat_produk(): void
    {
        foreach ([User::ROLE_PIMPINAN, User::ROLE_PENDAMPING] as $role) {
            $pelaku = User::create([
                'name' => 'Akun ' . $role,
                'email' => $role . '@kode.test',
                'password' => bcrypt('rahasia123'),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($pelaku)
                ->get(route('koperasi.produk.create', $this->unit))
                ->assertForbidden();
        }
    }
}
