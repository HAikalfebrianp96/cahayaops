<?php

namespace Tests\Feature;

use App\Models\AkunKas;
use App\Models\BusinessUnit;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\KoperasiMember;
use App\Models\PembayaranPenjualan;
use App\Models\Pengeluaran;
use App\Models\RekonsiliasiKas;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\KasBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KAS & BANK — TAHAP 5: SALDO, BUKU MUTASI, PEMISAHAN UANG, HITUNG FISIK.
 *
 * Skenarionya kecil tapi memuat setiap jenis catatan uang, termasuk yang
 * TIDAK boleh dihitung: catatan sebelum saldo awal, pengeluaran yang
 * dibatalkan, dan pembayaran dari transaksi yang dibatalkan.
 */
class KasBankLaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;
    private AkunKas $kas;
    private AkunKas $bank;
    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi', 'email' => 'tim@laporan.test', 'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI, 'is_active' => true,
        ]);
        $this->unit = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'UNIT-KAS', 'tipe' => 'Barang', 'status' => 'Aktif']);

        $this->kas = AkunKas::where('nama', 'Kas Tunai')->firstOrFail();
        $this->kas->update(['saldo_awal' => 1000000, 'tanggal_saldo_awal' => '2026-06-24']);
        $this->bank = AkunKas::where('nama', 'Rekening Bank')->firstOrFail();
        $this->bank->update(['saldo_awal' => 0, 'tanggal_saldo_awal' => '2026-06-24']);
    }

    private function penjualan(string $tanggal, float $nominal, string $status = 'Selesai'): SalesTransaction
    {
        $trx = SalesTransaction::create([
            'unit_id' => $this->unit->id, 'tanggal' => $tanggal, 'status_transaksi' => $status,
            'subtotal' => $nominal, 'total' => $nominal, 'status_pembayaran' => 'Lunas',
            'dp_nominal' => $nominal, 'metode_pembayaran' => 'Cash',
        ]);
        $p = new PembayaranPenjualan(['tanggal' => $tanggal, 'nominal' => $nominal, 'akun_kas_id' => $this->kas->id, 'metode' => 'Cash']);
        $p->sales_transaction_id = $trx->id;
        $p->save();

        return $trx;
    }

    private function pengeluaran(string $tanggal, float $nominal, string $status = 'Aktif', ?int $akun = null): Pengeluaran
    {
        return Pengeluaran::create([
            'tanggal' => $tanggal, 'kategori' => 'Re-Stok Produk', 'nominal' => $nominal,
            'metode_pembayaran' => 'Cash', 'status' => $status, 'akun_kas_id' => $akun ?? $this->kas->id,
            'business_unit_id' => $this->unit->id,
        ]);
    }

    /** Skenario lengkap. Saldo Kas yang benar: 1.000.000 + 300.000 + 50.000 + 20.000 - 120.000 - 5.000 = 1.245.000 */
    private function skenario(): void
    {
        $this->penjualan('2026-06-20', 999000);             // sebelum saldo awal — sudah termasuk
        $this->penjualan('2026-06-24', 100000);             // tepat di tanggal saldo awal — dihitung
        $this->penjualan('2026-07-10', 200000);
        $this->penjualan('2026-07-11', 77000, 'Dibatalkan'); // transaksi batal — tidak dihitung

        $this->pengeluaran('2026-07-01', 120000);
        $this->pengeluaran('2026-07-02', 55000, 'Dibatalkan');

        $anggota = KoperasiMember::create(['nama' => 'Siti', 'nomor_hp' => '0812', 'tanggal_bergabung' => '2026-07-01', 'status' => 'Aktif']);
        $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-07-05', 'nominal' => 50000, 'akun_kas_id' => $this->kas->id,
        ]);

        DanaSukarelaMasuk::create(['tanggal' => '2026-07-06', 'nominal' => 20000, 'sumber' => 'Celengan', 'akun_kas_id' => $this->kas->id]);
        DanaSukarelaKeluar::create(['tanggal' => '2026-07-07', 'nominal' => 5000, 'keperluan' => 'Konsumsi', 'akun_kas_id' => $this->kas->id]);
        DanaHibahMasuk::create(['tanggal' => '2026-07-08', 'nominal' => 500000, 'sumber' => 'YBM PLN', 'akun_kas_id' => $this->bank->id]);
    }

    private function layanan(): KasBankService
    {
        return app(KasBankService::class);
    }

    // =================================================================
    // SALDO & PEMISAHAN UANG
    // =================================================================

    public function test_saldo_hanya_menghitung_catatan_berlaku_sejak_saldo_awal(): void
    {
        $this->skenario();

        $this->assertEquals(1245000, $this->layanan()->saldo($this->kas));
        $this->assertEquals(500000, $this->layanan()->saldo($this->bank));
    }

    public function test_saldo_pada_tanggal_tertentu(): void
    {
        $this->skenario();

        $this->assertEquals(1000000, $this->layanan()->saldo($this->kas, '2026-06-23')); // sebelum saldo awal
        $this->assertEquals(1100000, $this->layanan()->saldo($this->kas, '2026-06-30'));
        $this->assertEquals(980000, $this->layanan()->saldo($this->kas, '2026-07-01'));
    }

    public function test_uang_usaha_dipisah_dari_dana_anggota_dan_titipan(): void
    {
        $this->skenario();

        $r = $this->layanan()->ringkasan();

        $this->assertEquals(1745000, $r['uang_tersedia']);
        $this->assertEquals(50000, $r['titipan']['simpanan']);
        $this->assertEquals(15000, $r['titipan']['dana_sosial']);
        $this->assertEquals(500000, $r['titipan']['dana_hibah']);
        $this->assertEquals(565000, $r['titipan']['total']);
        $this->assertEquals(1180000, $r['uang_usaha']);
        $this->assertTrue($r['bisa_dipercaya']);
    }

    public function test_pembatalan_dan_pemulihan_langsung_terlihat_di_saldo(): void
    {
        $trx = $this->penjualan('2026-07-10', 200000);
        $this->assertEquals(1200000, $this->layanan()->saldo($this->kas));

        $trx->update(['status_transaksi' => SalesTransaction::STATUS_DIBATALKAN]);
        $this->assertEquals(1000000, $this->layanan()->saldo($this->kas));

        $trx->update(['status_transaksi' => 'Aktif']);
        $this->assertEquals(1200000, $this->layanan()->saldo($this->kas));
    }

    public function test_akun_tanpa_saldo_awal_dan_catatan_tanpa_akun_ditandai(): void
    {
        $this->bank->update(['tanggal_saldo_awal' => null]);
        $this->pengeluaran('2026-07-01', 10000)->forceFill(['akun_kas_id' => null])->save();

        $r = $this->layanan()->ringkasan();

        $this->assertSame(1, $r['belum_ditetapkan']);
        $this->assertSame(1, $r['tanpa_akun']['jumlah']);
        $this->assertEquals(-10000, $r['tanpa_akun']['bersih']);
        $this->assertEquals(990000, $r['uang_tersedia']);
        $this->assertFalse($r['bisa_dipercaya']);
    }

    /** Angka audit: saldo awal 0, penjualan 8.659.500, re-stok 3.322.000. */
    public function test_rekonsiliasi_angka_audit(): void
    {
        $this->kas->update(['saldo_awal' => 0]);
        $this->penjualan('2026-06-24', 8659500);
        $this->pengeluaran('2026-07-15', 1005000);
        $this->pengeluaran('2026-08-21', 2317000);

        $this->assertEquals(5337500, $this->layanan()->saldo($this->kas));
    }

    // =================================================================
    // BUKU MUTASI
    // =================================================================

    public function test_buku_mutasi_dengan_saldo_berjalan(): void
    {
        $this->skenario();

        $m = $this->layanan()->bukuMutasi($this->kas, '2026-07-01', '2026-07-31');

        $this->assertEquals(1100000, $m['saldo_pembuka']);
        $this->assertSame(
            ['2026-07-01', '2026-07-05', '2026-07-06', '2026-07-07', '2026-07-10'],
            $m['baris']->pluck('tanggal')->all()
        );
        $this->assertEquals([980000, 1030000, 1050000, 1045000, 1245000], $m['baris']->pluck('saldo')->all());
        $this->assertEquals(270000, $m['total_masuk']);
        $this->assertEquals(125000, $m['total_keluar']);
        $this->assertEquals(1245000, $m['saldo_penutup']);
    }

    public function test_periode_sebelum_saldo_awal_dipotong(): void
    {
        $this->skenario();

        $m = $this->layanan()->bukuMutasi($this->kas, '2026-06-01', '2026-06-30');

        $this->assertSame('2026-06-24', $m['dari_efektif']);
        $this->assertEquals(1000000, $m['saldo_pembuka']);
        $this->assertCount(1, $m['baris']); // penjualan 20 Juni tidak muncul
    }

    public function test_halaman_kas_bank_dan_buku_mutasi(): void
    {
        $this->skenario();

        $this->actingAs($this->timKoperasi)->get(route('koperasi.kas-bank.index'))
            ->assertOk()
            ->assertSee('Uang Tersedia')
            ->assertSee('Rp1.745.000')
            ->assertSee('Rp1.180.000');

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.kas-bank.show', ['akun' => $this->kas, 'dari' => '2026-07-01', 'sampai' => '2026-07-31']))
            ->assertOk()
            ->assertSee('Simpanan Pokok — Siti')
            ->assertSee('Rp1.245.000');
    }

    public function test_unduh_excel_buku_mutasi(): void
    {
        $this->skenario();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.kas-bank.excel', ['akun' => $this->kas, 'dari' => '2026-07-01', 'sampai' => '2026-07-31']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_pimpinan_tidak_bisa_membuka_buku_mutasi(): void
    {
        $pimpinan = User::create([
            'name' => 'Pimpinan', 'email' => 'p@laporan.test', 'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_PIMPINAN, 'is_active' => true,
        ]);

        $this->actingAs($pimpinan)->get(route('koperasi.kas-bank.show', $this->kas))->assertForbidden();
    }

    // =================================================================
    // HITUNG FISIK
    // =================================================================

    public function test_hitung_fisik_cocok_dan_saldo_sistem_dibekukan(): void
    {
        $this->skenario();

        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.rekonsiliasi', $this->kas), [
            'tanggal' => '2026-07-31', 'saldo_fisik' => 1245000,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $rk = RekonsiliasiKas::firstOrFail();
        $this->assertTrue($rk->cocok());
        $this->assertEquals(1245000, (float) $rk->saldo_sistem);

        // Catatan baru sesudahnya tidak mengubah angka yang sudah dibekukan.
        $this->pengeluaran('2026-07-20', 45000);
        $this->assertEquals(1245000, (float) $rk->fresh()->saldo_sistem);
    }

    public function test_hitung_fisik_dengan_selisih_wajib_catatan(): void
    {
        $this->skenario();

        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.rekonsiliasi', $this->kas), [
            'tanggal' => '2026-07-31', 'saldo_fisik' => 1200000,
        ])->assertSessionHasErrors('catatan', null, 'rekonsiliasi');
        $this->assertSame(0, RekonsiliasiKas::count());

        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.rekonsiliasi', $this->kas), [
            'tanggal' => '2026-07-31', 'saldo_fisik' => 1200000, 'catatan' => 'Diduga ATK belum dicatat',
        ])->assertSessionHas('warning');

        $this->assertEquals(-45000, (float) RekonsiliasiKas::firstOrFail()->selisih);
    }

    public function test_akun_dengan_hitung_fisik_tidak_bisa_dihapus(): void
    {
        $akun = AkunKas::create(['nama' => 'Kas Kecil', 'jenis' => 'Kas', 'saldo_awal' => 0, 'status' => 'Aktif']);
        $rk = new RekonsiliasiKas(['tanggal' => '2026-07-01', 'saldo_sistem' => 0, 'saldo_fisik' => 0, 'selisih' => 0]);
        $rk->akun_kas_id = $akun->id;
        $rk->save();

        $this->actingAs($this->timKoperasi)
            ->from(route('koperasi.kas-bank.index'))
            ->delete(route('koperasi.kas-bank.destroy', $akun))
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, 'hitung fisik'));
    }

    // =================================================================
    // DASHBOARD & TUGAS
    // =================================================================

    public function test_dashboard_tim_koperasi_menampilkan_posisi_uang(): void
    {
        $this->skenario();

        $this->actingAs($this->timKoperasi)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Posisi Uang Hari Ini')
            ->assertSee('Rp1.745.000');
    }

    public function test_tugas_koperasi_mengingatkan_saldo_awal_kosong(): void
    {
        $this->bank->update(['tanggal_saldo_awal' => null]);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee('Isi Saldo Awal')
            ->assertSee('Rekening Bank');
    }
}
