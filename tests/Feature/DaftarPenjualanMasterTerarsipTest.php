<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DAFTAR PENJUALAN KETIKA UNIT ATAU PELANGGANNYA SUDAH DIARSIPKAN.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Daftar Penjualan memakai relasi biasa (businessUnit, customer) yang kena
 * global scope SoftDeletes, sehingga master yang terarsip tampil sebagai
 * "—". Halaman Detail memakai varian ...TermasukArsip dan menampilkan
 * namanya.
 *
 * Satu transaksi karena itu menunjukkan dua hal berbeda di dua halaman.
 *
 * Di koperasi ini akibatnya menyeluruh: KETIGA transaksinya menampilkan
 * kolom pelanggan kosong, karena ketiga pelanggannya terarsip. Untuk tahu
 * siapa yang membeli, petugas harus membuka detail satu per satu.
 *
 * =====================================================================
 * KENAPA KEPUTUSAN LAMA DIUBAH
 * =====================================================================
 *
 * Perilaku "—" itu DISENGAJA, dan ada komentarnya di SalesTransaction:
 * daftar dan laporan memang seharusnya berhenti menampilkan master yang
 * terarsip.
 *
 * Alasan itu berlaku selama Arsip Data masih ada: "—" berarti "datanya
 * ada, lihat di Arsip". Sejak halaman Arsip dilepas, tidak ada lagi tempat
 * itu — dan "—" berubah arti menjadi "tidak ada datanya".
 *
 * Yang dipilih bukan mengembalikan nama begitu saja, melainkan nama
 * BESERTA PENANDA: namanya menjawab "siapa yang membeli", penandanya
 * menjawab "kenapa dia tidak ada di daftar pelanggan".
 */
class DaftarPenjualanMasterTerarsipTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@daftar.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);
    }

    private function transaksi(): SalesTransaction
    {
        $unit = BusinessUnit::create([
            'nama' => 'Cahaya Sembako', 'kode' => 'UNIT-ARS', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        $pelanggan = Customer::create(['nama' => 'Yayan Hendriko']);

        return SalesTransaction::create([
            'unit_id' => $unit->id,
            'customer_id' => $pelanggan->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 250_000, 'diskon' => 0, 'ongkir' => 0, 'total' => 250_000,
            'status_transaksi' => 'Aktif', 'status_pembayaran' => 'Lunas',
        ]);
    }

    private function daftar()
    {
        return $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.index'))
            ->assertOk();
    }

    // =================================================================

    /** Selama master-nya aktif, tidak ada penanda apa pun. */
    public function test_master_aktif_tampil_tanpa_penanda(): void
    {
        $this->transaksi();

        $this->daftar()
            ->assertSee('Yayan Hendriko')
            ->assertSee('Cahaya Sembako')
            ->assertDontSee('(diarsipkan)');
    }

    public function test_pelanggan_terarsip_namanya_tetap_tampil(): void
    {
        $t = $this->transaksi();

        $t->customer->delete();

        $this->daftar()
            ->assertSee('Yayan Hendriko')
            ->assertSee('(diarsipkan)');
    }

    public function test_unit_terarsip_namanya_tetap_tampil(): void
    {
        $t = $this->transaksi();

        $t->businessUnit->delete();

        $this->daftar()
            ->assertSee('Cahaya Sembako')
            ->assertSee('(diarsipkan)');
    }

    /**
     * Daftar dan Detail harus menyebut nama yang SAMA.
     *
     * Inilah inti persoalannya: satu transaksi tidak boleh menunjukkan dua
     * hal berbeda tergantung halaman mana yang dibuka.
     */
    public function test_daftar_dan_detail_menyebut_pelanggan_yang_sama(): void
    {
        $t = $this->transaksi();
        $nama = $t->customer->nama;

        $t->customer->delete();

        $this->daftar()->assertSee($nama);

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.show', $t))
            ->assertOk()
            ->assertSee($nama);
    }

    /** Transaksi tanpa pelanggan tetap menampilkan tanda hubung, bukan kosong. */
    public function test_transaksi_tanpa_pelanggan_menampilkan_tanda_hubung(): void
    {
        $unit = BusinessUnit::create([
            'nama' => 'Unit Tanpa Pelanggan', 'kode' => 'UNIT-TP', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        SalesTransaction::create([
            'unit_id' => $unit->id,
            'customer_id' => null,
            'tanggal' => now()->toDateString(),
            'subtotal' => 100_000, 'diskon' => 0, 'ongkir' => 0, 'total' => 100_000,
            'status_transaksi' => 'Aktif', 'status_pembayaran' => 'Lunas',
        ]);

        $this->daftar()
            ->assertSee('—', false)
            ->assertDontSee('(diarsipkan)');
    }

    /**
     * Master terarsip TIDAK boleh kembali muncul sebagai pilihan aktif.
     *
     * Menampilkan namanya di baris riwayat adalah satu hal; menawarkannya
     * kembali sebagai unit atau pelanggan yang bisa dipilih adalah hal lain,
     * dan itu tetap tidak boleh terjadi.
     */
    public function test_master_terarsip_tidak_kembali_jadi_pilihan_aktif(): void
    {
        $t = $this->transaksi();

        $t->customer->delete();
        $t->businessUnit->delete();

        $this->assertSame(0, Customer::count());
        $this->assertSame(0, BusinessUnit::count());

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.pelanggan.index'))
            ->assertOk()
            ->assertDontSee('Yayan Hendriko');
    }
}
