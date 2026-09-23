<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\TugasKoperasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TUGAS KOPERASI — alur kerja harian Tim Koperasi.
 *
 * ==========================================================================
 * YANG DIJAGA BERKAS INI
 * ==========================================================================
 *
 * Daftar tugas salah dengan dua cara, dan keduanya senyap:
 *
 *   1. MENAGIH pekerjaan yang sebenarnya tidak ada — misalnya menyuruh
 *      membuat invoice untuk transaksi yang sudah dibatalkan. Sekali ini
 *      terjadi, daftarnya berhenti dipercaya dan tidak dibuka lagi.
 *   2. TIDAK menagih pekerjaan yang benar-benar ada, sehingga Tim Koperasi
 *      merasa semuanya beres.
 *
 * Selain itu dijaga satu sifat yang lebih halus: tidak ada ambang waktu
 * karangan. Seluruh tugas di sini adalah "ada atau tidak ada", bukan "sudah
 * berapa lama" — karena CahayaOS memang belum punya kesepakatan tenggat
 * untuk hal-hal ini.
 */
class TugasKoperasiTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;
    private User $admin;
    private User $pendamping;
    private User $pimpinan;
    private BusinessUnit $unit;
    private ProductService $produk;
    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@tugas-kop.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'marchelina@tugas-kop.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@tugas-kop.test');

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'status' => 'Aktif']);
        $this->pendamping = $this->user('pendamping', 'rosa@tugas-kop.test', $profil->id);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);
        $this->produk = ProductService::create([
            'unit_id' => $this->unit->id, 'kode' => 'BRS-001', 'nama' => 'Beras 5kg',
            'jenis' => 'Produk', 'harga_modal' => 55_000, 'harga_jual' => 62_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email, 'email' => $email, 'password' => bcrypt('rahasia123'),
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function jual(string $statusBayar = 'Lunas'): SalesTransaction
    {
        $this->actingAs($this->admin)->post(route('koperasi.penjualan.store'), [
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'items_master' => [['product_id' => $this->produk->id, 'qty' => 2]],
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => $statusBayar,
            'metode_pembayaran' => 'Cash',
        ])->assertSessionHasNoErrors();

        return SalesTransaction::latest('id')->firstOrFail();
    }

    private function anggota(string $nama = 'Pak Budi'): KoperasiMember
    {
        return KoperasiMember::create([
            'nama' => $nama, 'nomor_hp' => '0814',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
    }

    private function tugas(): array
    {
        return app(TugasKoperasiService::class)->semua();
    }

    // ==================================================================
    // A. OTORISASI
    // ==================================================================

    public function test_tim_koperasi_dapat_membuka_tugas_koperasi(): void
    {
        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee('Tugas Koperasi');
    }

    public function test_admin_dapat_membuka_tugas_koperasi(): void
    {
        $this->actingAs($this->admin)->get(route('koperasi.tugas.index'))->assertOk();
    }

    public function test_pendamping_dan_pimpinan_ditolak(): void
    {
        // Pimpinan sengaja ditolak: ini daftar pekerjaan yang harus
        // dikerjakan, bukan laporan. Yang ia butuhkan ada di Dashboard.
        $this->actingAs($this->pendamping)->get(route('koperasi.tugas.index'))->assertForbidden();
        $this->actingAs($this->pimpinan)->get(route('koperasi.tugas.index'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('koperasi.tugas.index'))->assertRedirect(route('login'));
    }

    // ==================================================================
    // B. TIDAK MENAGIH PEKERJAAN YANG TIDAK ADA
    // ==================================================================

    public function test_transaksi_dibatalkan_tidak_menjadi_tugas(): void
    {
        // Kesalahan paling merusak kepercayaan: menyuruh membuat invoice
        // untuk transaksi yang sudah dibatalkan.
        $t = $this->jual('Belum Dibayar');

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.batalkan', $t), [
            'alasan_pembatalan' => 'pesanan batal',
        ])->assertRedirect();

        $tugas = $this->tugas();

        $this->assertSame(0, $tugas['segera']['transaksi_tanpa_invoice']->count());
        $this->assertSame(0, $tugas['segera']['transaksi_belum_lunas']->count());
        $this->assertSame(0, $tugas['ringkas']['segera']);
    }

    public function test_transaksi_lunas_dan_berinvoice_tidak_menjadi_tugas(): void
    {
        $t = $this->jual('Lunas');
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $t))->assertRedirect();

        $tugas = $this->tugas();

        $this->assertSame(0, $tugas['ringkas']['segera']);
        $this->assertSame(1, $tugas['ringkas']['beres']);
    }

    public function test_anggota_nonaktif_tidak_menjadi_tugas(): void
    {
        $a = $this->anggota();
        $a->update(['status' => 'Nonaktif']);

        $tugas = $this->tugas();

        $this->assertSame(0, $tugas['perhatian']['anggota_tanpa_pin']->count());
        $this->assertSame(0, $tugas['perhatian']['anggota_tanpa_simpanan']->count());
    }

    public function test_pengeluaran_dibatalkan_tidak_menjadi_tugas(): void
    {
        $p = Pengeluaran::create([
            'tanggal' => now()->toDateString(), 'business_unit_id' => $this->unit->id,
            'kategori' => 'Operasional', 'nominal' => 500_000,
            'metode_pembayaran' => 'Cash', 'status' => 'Aktif', 'created_by' => $this->admin->id,
        ]);

        $this->assertSame(1, $this->tugas()['perhatian']['pengeluaran_tanpa_bukti']->count());

        $this->actingAs($this->admin)->post(route('koperasi.pengeluaran.cancel', $p), [
            'alasan_pembatalan' => 'salah catat',
        ])->assertRedirect();

        $this->assertSame(0, $this->tugas()['perhatian']['pengeluaran_tanpa_bukti']->count());
    }

    // ==================================================================
    // C. MENAGIH PEKERJAAN YANG MEMANG ADA
    // ==================================================================

    public function test_transaksi_belum_lunas_tanpa_invoice_muncul_sebagai_perlu_segera(): void
    {
        $t = $this->jual('Belum Dibayar');

        $tugas = $this->tugas();
        $this->assertSame(1, $tugas['segera']['transaksi_tanpa_invoice']->count());
        // Tidak terhitung dua kali: kelompok "belum lunas" hanya berisi yang
        // sudah berinvoice.
        $this->assertSame(0, $tugas['segera']['transaksi_belum_lunas']->count());
        $this->assertSame(1, $tugas['ringkas']['segera']);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee($t->nomor_transaksi)
            ->assertSee('Belum ada invoice');
    }

    /**
     * Kasus nyata (23 Sep 2026): "Perlu Segera 150" — seluruhnya penjualan
     * tunai yang sudah LUNAS & diambil di tempat, hanya belum dibuatkan
     * invoice. Tidak ada yang menunggu; itu bukan tugas.
     */
    public function test_penjualan_lunas_tanpa_invoice_bukan_tugas(): void
    {
        $t = $this->jual('Lunas');

        $tugas = $this->tugas();
        $this->assertSame(0, $tugas['segera']['transaksi_tanpa_invoice']->count());
        $this->assertSame(0, $tugas['ringkas']['segera']);
        $this->assertSame(1, $tugas['ringkas']['beres']);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertDontSee($t->nomor_transaksi);
    }

    /**
     * Perlu Segera = jumlah transaksi belum lunas — sama dengan jumlah
     * "Transaksi belum lunas" di ringkasan Piutang untuk data yang sama —
     * dan berubah begitu pembayarannya dicatat.
     */
    public function test_perlu_segera_mengikuti_pembayaran_dan_sama_dengan_piutang(): void
    {
        $tanpaInvoice = $this->jual('Belum Dibayar');
        $berInvoice = $this->jual('DP');
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $berInvoice));
        $this->jual('Lunas');

        $ringkas = $this->tugas()['ringkas'];
        $this->assertSame(2, $ringkas['segera']);
        $this->assertSame(1, $ringkas['beres']);

        // Dashboard Tim Koperasi: angka Tugas = halaman Tugas, dan sama
        // dengan jumlah transaksi belum lunas di ringkasan Piutang.
        $respon = $this->actingAs($this->timKoperasi)->get(route('dashboard'))->assertOk();
        $this->assertSame($ringkas, $respon->viewData('ringkasTugas'));
        $this->assertSame(2, $respon->viewData('usaha')['stats']['transaksi_belum_lunas']);

        // Lunasi satu transaksi -> angkanya turun di kedua tempat.
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.pembayaran.store', $tanpaInvoice), [
                'tanggal' => now()->toDateString(),
                'nominal' => (int) $tanpaInvoice->fresh()->sisaPembayaran(),
                'akun_kas_id' => \App\Models\AkunKas::aktif()->value('id'),
                'metode' => 'Cash',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('Lunas', $tanpaInvoice->fresh()->status_pembayaran);

        $this->assertSame(1, $this->tugas()['ringkas']['segera']);
        $this->assertSame(2, $this->tugas()['ringkas']['beres']);
        $this->assertSame(1, $this->actingAs($this->timKoperasi)->get(route('dashboard'))
            ->viewData('usaha')['stats']['transaksi_belum_lunas']);
    }

    public function test_transaksi_belum_lunas_muncul_sebagai_perlu_segera(): void
    {
        $t = $this->jual('DP');
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $t));

        $this->assertSame(1, $this->tugas()['segera']['transaksi_belum_lunas']->count());

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee('Perbarui Pembayaran');
    }

    public function test_anggota_tanpa_pin_muncul_sebagai_perlu_dilengkapi(): void
    {
        // Tanpa PIN, anggota tidak bisa membuka Cek Simpanan sama sekali.
        $this->anggota('Pak Budi');

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee('Pak Budi')
            ->assertSee('Belum punya PIN');
    }

    public function test_anggota_yang_sudah_punya_pin_hilang_dari_daftar(): void
    {
        $a = $this->anggota();
        $a->setPin('123456');
        $a->save();

        $this->assertSame(0, $this->tugas()['perhatian']['anggota_tanpa_pin']->count());
    }

    public function test_pengeluaran_tanpa_bukti_muncul_sebagai_perlu_dilengkapi(): void
    {
        Pengeluaran::create([
            'tanggal' => now()->toDateString(), 'business_unit_id' => $this->unit->id,
            'kategori' => 'Transportasi', 'nominal' => 250_000,
            'metode_pembayaran' => 'Cash', 'status' => 'Aktif', 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee('Transportasi')
            ->assertSee('Belum ada berkas bukti');
    }

    public function test_calon_anggota_diambil_dari_peserta_cahaya_preneur(): void
    {
        // Satu-satunya jembatan antara dua modul CahayaOS.
        Participant::create([
            'nama_peserta' => 'Bu Ani', 'nomor_hp' => '0815',
            'nama_usaha' => 'Keripik', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
        ]);

        $this->assertSame(1, $this->tugas()['ringkas']['calon_anggota']);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.tugas.index'))
            ->assertOk()
            ->assertSee('Bu Ani');
    }

    // ==================================================================
    // D. TIDAK ADA AMBANG WAKTU KARANGAN
    // ==================================================================

    public function test_tugas_tidak_bergantung_pada_umur_data(): void
    {
        // Transaksi berumur setahun yang sudah lunas & berinvoice TIDAK
        // menjadi tugas. Kalau suatu saat ada yang menambahkan aturan
        // "lewat sekian hari", test ini yang menangkapnya — karena aturan
        // seperti itu memang belum pernah disepakati.
        $t = $this->jual('Lunas');
        $t->update(['tanggal' => now()->subYear()->toDateString()]);
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $t));

        $this->assertSame(0, $this->tugas()['ringkas']['segera']);
    }

    public function test_service_tidak_menyebut_satu_pun_ambang_hari(): void
    {
        // Penjaga tertulis: berkas service-nya sendiri tidak boleh memuat
        // perhitungan berbasis hari. Kalau kelak memang dibutuhkan, ia harus
        // datang dari Pengaturan seperti ambang follow-up — bukan ditanam
        // diam-diam di dalam kode.
        $isi = (string) file_get_contents(app_path('Services/TugasKoperasiService.php'));

        foreach (['subDays(', 'diffInDays(', 'addDays('] as $pola) {
            $this->assertStringNotContainsString($pola, $isi,
                'TugasKoperasiService tidak boleh memakai ambang waktu yang tidak disepakati.');
        }
    }

    // ==================================================================
    // E. DASHBOARD SEJALAN DENGAN HALAMANNYA
    // ==================================================================

    public function test_angka_dashboard_sama_dengan_halaman_tugas(): void
    {
        $this->jual('Belum Dibayar');
        $this->anggota('Pak Budi');

        $ringkas = $this->tugas()['ringkas'];

        $this->actingAs($this->timKoperasi)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tugas Koperasi')
            ->assertViewHas('ringkasTugas', fn ($r) => $r === $ringkas);
    }

    public function test_menu_tugas_koperasi_tampil_untuk_tim_koperasi(): void
    {
        // Diperiksa lewat TAUTANNYA, bukan potongan <nav>.
        //
        // Tim Koperasi memakai definisi menu yang BERBEDA dari Admin
        // ($menuFlat, bukan $menuKoperasi) — dan itulah yang membuat
        // penambahan menu ini sempat terlewat di salah satu tempat.
        // Memeriksa keberadaan route-nya membuat test tidak bergantung pada
        // susunan markup yang bisa berbeda antar role.
        $html = $this->actingAs($this->timKoperasi)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString(route('koperasi.tugas.index'), $html);
    }
}
