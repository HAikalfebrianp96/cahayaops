<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MASTER DIARSIPKAN — HISTORI TETAP TERBACA, OPERASIONAL BARU TETAP BERSIH.
 *
 * ==========================================================================
 * DUA SIFAT YANG HARUS BERLAKU BERSAMAAN
 * ==========================================================================
 *
 * Mengarsipkan master itu tindakan yang wajar: unit usaha tutup, produk tidak
 * dijual lagi, peserta lulus program. Yang TIDAK boleh terjadi ada dua, dan
 * keduanya berlawanan arah:
 *
 *   1. Histori ikut hilang. Transaksi tahun lalu harus tetap menyebut
 *      "Cahaya Sembako", bukan "—", dan halamannya harus tetap terbuka.
 *
 *   2. Master terarsip bocor ke operasional baru. Produk yang sudah tidak
 *      dijual tidak boleh muncul di daftar pilihan transaksi hari ini.
 *
 * Memperbaiki yang pertama dengan withTrashed() di mana-mana akan langsung
 * merusak yang kedua. Karena itu keduanya diuji berdampingan di sini.
 *
 * SmokeHalamanMasterTerarsipTest menjawab "apakah halamannya terbuka".
 * Berkas ini menjawab "apakah ISINYA benar".
 */
class MasterTerarsipHistoriAmanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendampingUser;
    private User $pimpinan;
    private Pendamping $pendamping;
    private BusinessUnit $unit;
    private ProductService $produk;
    private Customer $pelanggan;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->pimpinan = User::create([
            'name' => 'Pimpinan', 'email' => 'pimpinan@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'pimpinan', 'is_active' => true,
        ]);

        $this->pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);
        $this->pendampingUser = User::create([
            'name' => 'Rosa Linda', 'email' => 'rosa@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'pendamping', 'is_active' => true,
            'pendamping_id' => $this->pendamping->id,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Bu Ani', 'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->pendamping->id,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Sembako', 'kode' => 'CS', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);
        $this->produk = ProductService::create([
            'unit_id' => $this->unit->id, 'kode' => 'BRS-001', 'nama' => 'Beras Lima Kilo',
            'jenis' => 'Produk', 'harga_modal' => 55_000, 'harga_jual' => 62_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    private function jual(): SalesTransaction
    {
        $this->actingAs($this->admin)->post(route('koperasi.penjualan.store'), [
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'items_master' => [['product_id' => $this->produk->id, 'qty' => 2]],
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ])->assertSessionHasNoErrors();

        return SalesTransaction::with('items')->latest('id')->firstOrFail();
    }

    private function catatanPendampingan()
    {
        return $this->peserta->pendampinganRecords()->create([
            'tanggal' => now()->subMonth()->toDateString(),
            'jenis_kegiatan' => 'Kunjungan',
            'materi' => 'Pembukuan sederhana',
            'pendamping_id' => $this->pendamping->id,
        ]);
    }

    // ==================================================================
    // A & B — DETAIL: master aktif, lalu diarsipkan
    // ==================================================================

    public function test_detail_transaksi_menyebut_nama_unit_walau_unitnya_diarsipkan(): void
    {
        $trx = $this->jual();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()->assertSee('Cahaya Sembako');

        $this->unit->delete();

        // Bukan cuma "tidak error" — namanya HARUS tetap muncul. Laporan yang
        // menampilkan "—" sama tidak bergunanya dengan halaman yang mati.
        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()->assertSee('Cahaya Sembako');
    }

    public function test_detail_transaksi_menyebut_nama_produk_walau_produknya_diarsipkan(): void
    {
        $trx = $this->jual();
        $this->produk->delete();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()->assertSee('Beras Lima Kilo');
    }

    public function test_detail_transaksi_menyebut_pelanggan_walau_pelanggannya_diarsipkan(): void
    {
        $trx = $this->jual();
        $this->pelanggan->delete();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()->assertSee('Warung Bu Sri');
    }

    public function test_catatan_pendampingan_menyebut_peserta_walau_pesertanya_diarsipkan(): void
    {
        $catatan = $this->catatanPendampingan();
        $this->peserta->delete();

        $this->actingAs($this->admin)->get(route('pendampingan.index'))
            ->assertOk()->assertSee('Bu Ani');

        $this->actingAs($this->admin)->get(route('pendampingan.edit', $catatan))
            ->assertOk()->assertSee('Bu Ani')->assertSee('Peserta diarsipkan');
    }

    // ==================================================================
    // C — EDIT tetap terbuka
    // ==================================================================

    public function test_seluruh_halaman_koreksi_riwayat_peserta_tetap_terbuka(): void
    {
        $catatan = $this->catatanPendampingan();
        $pelatihan = $this->peserta->trainingRecords()->create([
            'nama_pelatihan' => 'Digital Marketing', 'tanggal' => now()->subMonth()->toDateString(),
            'penyelenggara' => 'YBM PLN', 'status' => 'Selesai',
        ]);
        $legalitas = $this->peserta->legalitasRecords()->create([
            'jenis_dokumen' => 'NIB', 'nomor_dokumen' => '123', 'status' => 'Sudah Ada',
        ]);

        $this->peserta->delete();

        foreach ([
            route('pendampingan.edit', $catatan),
            route('training.edit', $pelatihan),
            route('legalitas.edit', $legalitas),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_koreksi_simpanan_terbuka_walau_keanggotaannya_diarsipkan(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Pak Budi', 'nomor_hp' => '0814',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
        $simpanan = $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Sukarela', 'tanggal' => now()->toDateString(),
            'nominal' => 100_000, 'status' => 'Tercatat',
        ]);

        $anggota->delete();

        $this->actingAs($this->admin)->get(route('koperasi.savings.edit', $simpanan))
            ->assertOk()->assertSee('Pak Budi');
    }

    // ==================================================================
    // D — MASTER TERARSIP TIDAK BOCOR KE OPERASIONAL BARU
    // ==================================================================

    public function test_produk_terarsip_tidak_muncul_di_form_transaksi_baru(): void
    {
        $this->produk->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.create', ['unit' => $this->unit->id]))
            ->assertOk()
            ->assertDontSee('Beras Lima Kilo');
    }

    public function test_unit_terarsip_tidak_muncul_saat_memilih_unit(): void
    {
        $this->unit->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.create'))
            ->assertOk()
            ->assertDontSee('Cahaya Sembako');
    }

    public function test_pelanggan_terarsip_tidak_muncul_sebagai_pilihan(): void
    {
        $lain = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);
        $this->pelanggan->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.create', ['unit' => $lain->id]))
            ->assertOk()
            ->assertDontSee('Warung Bu Sri');
    }

    public function test_peserta_terarsip_tidak_muncul_di_daftar_aktif(): void
    {
        $this->peserta->delete();

        $this->actingAs($this->admin)->get(route('participants.index'))
            ->assertOk()->assertDontSee('Keripik Bawang');
    }

    public function test_transaksi_baru_ke_unit_terarsip_ditolak(): void
    {
        $this->unit->delete();

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.store'), [
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'items_master' => [['product_id' => $this->produk->id, 'qty' => 1]],
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ])->assertNotFound();

        $this->assertSame(0, SalesTransaction::count());
    }

    // ==================================================================
    // E — PEMULIHAN mengembalikan keadaan
    // ==================================================================

    public function test_memulihkan_master_mengembalikannya_ke_operasional(): void
    {
        $this->produk->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.create', ['unit' => $this->unit->id]))
            ->assertOk()->assertDontSee('Beras Lima Kilo');

        $this->actingAs($this->admin)->post(route('koperasi.produk.restore', $this->produk->id))
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.create', ['unit' => $this->unit->id]))
            ->assertOk()->assertSee('Beras Lima Kilo');
    }

    // ==================================================================
    // F & G & H — LAPORAN, PDF, EXCEL
    // ==================================================================

    public function test_laporan_penjualan_tetap_menyebut_unit_terarsip(): void
    {
        $this->jual();
        $this->unit->delete();

        $this->actingAs($this->admin)->get(route('koperasi.laporan.penjualan'))
            ->assertOk()->assertSee('Cahaya Sembako');
    }

    public function test_pdf_dan_excel_tetap_terbentuk_saat_master_terarsip(): void
    {
        $this->jual();
        $this->unit->delete();
        $this->produk->delete();
        $this->pelanggan->delete();

        foreach ([
            route('koperasi.laporan.penjualan.pdf'),
            route('koperasi.laporan.penjualan.excel'),
            route('koperasi.laporan.laba-rugi.pdf'),
            route('koperasi.laporan.ringkasan.pdf'),
        ] as $url) {
            $respons = $this->actingAs($this->admin)->get($url);
            $this->assertLessThan(400, $respons->getStatusCode(), 'Gagal: ' . $url);
        }
    }

    public function test_invoice_lama_tetap_menyebut_unit_dan_produknya(): void
    {
        $trx = $this->jual();
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $trx))->assertRedirect();

        $this->unit->delete();
        $this->produk->delete();

        $this->actingAs($this->admin)->get(route('koperasi.invoice.show', $trx))
            ->assertOk()
            ->assertSee('Cahaya Sembako')
            ->assertSee('Beras Lima Kilo');
    }

    public function test_laporan_peserta_tetap_menyebut_nama_peserta_yang_diarsipkan(): void
    {
        // Laporan membaca tabel catatannya langsung, jadi catatan milik
        // peserta yang sudah LULUS program tetap ikut terhitung — dan memang
        // seharusnya begitu. Yang tidak boleh adalah barisnya tercetak dengan
        // "-" di kolom Peserta: laporan untuk pemberi dana yang memuat baris
        // tanpa nama tidak bisa dipertanggungjawabkan.
        $this->peserta->omzetReports()->create([
            'periode_bulan' => (int) now()->format('m'),
            'periode_tahun' => (int) now()->format('Y'),
            'omzet' => 5_000_000,
        ]);
        $this->catatanPendampingan();
        $this->peserta->trainingRecords()->create([
            'nama_pelatihan' => 'Digital Marketing', 'tanggal' => now()->subMonth()->toDateString(),
            'penyelenggara' => 'YBM PLN', 'status' => 'Selesai',
        ]);

        $this->peserta->delete();

        foreach ([
            route('laporan.omzet'),
            route('laporan.pendampingan'),
            route('laporan.pelatihan'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee('Bu Ani');
        }
    }

    public function test_ekspor_csv_tetap_memuat_nama_peserta_yang_diarsipkan(): void
    {
        $this->peserta->omzetReports()->create([
            'periode_bulan' => (int) now()->format('m'),
            'periode_tahun' => (int) now()->format('Y'),
            'omzet' => 5_000_000,
        ]);

        $this->peserta->delete();

        $respons = $this->actingAs($this->admin)->get(route('laporan.omzet.export'));
        $respons->assertOk();

        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Bu Ani', $isi);
        $this->assertStringNotContainsString(',-,-,', $isi);
    }

    // ==================================================================
    // I — DASHBOARD tidak menghitung yang terarsip
    // ==================================================================

    public function test_dashboard_berhenti_menghitung_master_terarsip(): void
    {
        $this->jual();

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->peserta->delete();
        $this->unit->delete();

        // Angkanya boleh berubah; yang tidak boleh adalah halamannya mati atau
        // data terarsip ikut dihitung sebagai data aktif.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, Participant::count());
        $this->assertSame(1, Participant::withTrashed()->count());
        $this->assertSame(0, BusinessUnit::count());
    }

    public function test_dashboard_pendamping_tetap_hidup_saat_profilnya_diarsipkan(): void
    {
        // Alur Arsipkan Pendamping melepas tautan akunnya lebih dulu, tapi
        // pengaman itu ada di controller lain. Halaman pertama yang dilihat
        // pengguna tidak boleh bergantung pada pengaman yang jauh darinya.
        $this->pendamping->delete();

        $this->actingAs($this->pendampingUser)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->pendampingUser)->get(route('binaan.index'))->assertForbidden();
    }

    // ==================================================================
    // J — OTORISASI tidak ikut longgar
    // ==================================================================

    public function test_otorisasi_tidak_berubah_karena_master_diarsipkan(): void
    {
        $catatan = $this->catatanPendampingan();

        $pendampingLain = Pendamping::create([
            'nama' => 'Siti', 'nomor_hp' => '0815', 'status' => 'Aktif',
        ]);
        $userLain = User::create([
            'name' => 'Siti', 'email' => 'siti@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'pendamping', 'is_active' => true,
            'pendamping_id' => $pendampingLain->id,
        ]);

        $this->peserta->delete();

        // Pendamping pemiliknya tetap boleh membuka catatannya sendiri…
        $this->actingAs($this->pendampingUser)->get(route('pendampingan.edit', $catatan))->assertOk();

        // …tapi Pendamping lain TETAP ditolak. Mengarsipkan peserta tidak
        // boleh membuka catatannya untuk orang yang sebelumnya tidak berhak.
        $this->actingAs($userLain)->get(route('pendampingan.edit', $catatan))->assertForbidden();

        // Pimpinan tetap read-only.
        $this->actingAs($this->pimpinan)->get(route('pendampingan.edit', $catatan))->assertForbidden();
    }

    public function test_pendamping_tidak_bisa_membuka_hpp_binaan_orang_lain_walau_terarsip(): void
    {
        $pesertaLain = Participant::create([
            'nama_peserta' => 'Pak Joko', 'nomor_hp' => '0816',
            'nama_usaha' => 'Bengkel', 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
        ]);
        $hpp = $pesertaLain->hppCalculations()->create([
            'nama_produk' => 'Servis', 'satuan_produksi' => 'unit', 'jumlah_produksi' => 1,
            'total_biaya_produksi' => 1000, 'hpp_per_unit' => 1000, 'harga_jual' => 2000,
            'laba_per_unit' => 1000, 'margin_persen' => 50, 'estimasi_laba_batch' => 1000,
        ]);

        $pesertaLain->delete();

        $this->actingAs($this->pendampingUser)->get(route('hpp.show', $hpp))->assertForbidden();
        $this->actingAs($this->admin)->get(route('hpp.show', $hpp))->assertOk();
    }
}
