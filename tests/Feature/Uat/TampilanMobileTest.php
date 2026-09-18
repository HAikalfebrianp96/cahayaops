<?php

namespace Tests\Feature\Uat;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT — HALAMAN BERTABEL TIDAK MELUBERKAN LAYAR PONSEL.
 *
 * ==========================================================================
 * MASALAH NYATA YANG DIPERBAIKI DI TAHAP UAT INI
 * ==========================================================================
 *
 * Enam halaman memuat tabel 4-7 kolom berisi nominal rupiah tanpa pembungkus
 * geser mendatar. Di layar 375px, yang melebar bukan hanya tabelnya melainkan
 * SELURUH HALAMAN: judul, tombol, dan menu ikut bergeser, dan kolom paling
 * kanan — yang justru memuat nominal dan tombol aksi — terpotong di tepi
 * layar tanpa cara melihatnya.
 *
 * Yang paling merugikan di antaranya adalah halaman Cek Simpanan: satu-satunya
 * halaman yang dibuka ANGGOTA, hampir selalu dari ponsel, dan satu-satunya
 * cara ia melihat saldonya sendiri.
 *
 * Perbaikannya memakai pola yang SUDAH dipakai halaman lain CahayaOS
 * (overflow-x-auto + min-width), bukan pola baru. Test ini menguncinya supaya
 * tidak diam-diam hilang saat halamannya disunting lagi.
 */
class TampilanMobileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Participant $peserta;
    private KoperasiMember $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@mob.test', 'status' => 'Aktif']);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@mob.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Bu Ani', 'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '0800',
            'tanggal_bergabung' => now()->subYear()->toDateString(), 'status' => 'Aktif',
        ])->fresh();

        MemberSaving::create([
            'koperasi_member_id' => $this->anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => now()->subMonths(3)->toDateString(),
            'nominal' => 100_000, 'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    // ==================================================================
    // A. HALAMAN YANG DIPERBAIKI
    // ==================================================================

    public function test_halaman_cek_simpanan_anggota_bisa_digeser_mendatar(): void
    {
        // Halaman paling penting dari sisi ini: dibuka anggota, dari ponsel,
        // dan memuat nominal yang justru ada di kolom paling kanan.
        $this->anggota->setPin('123456');

        $this->post(route('anggota-akses.login.submit'), [
            'nomor_anggota' => $this->anggota->nomor_anggota, 'pin' => '123456',
        ]);

        $this->get(route('anggota-akses.hasil'))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);
    }

    public function test_riwayat_simpanan_di_detail_anggota_bisa_digeser(): void
    {
        $this->actingAs($this->admin)
            ->get(route('koperasi.anggota.show', $this->anggota))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);
    }

    public function test_detail_penjualan_dan_invoice_bisa_digeser(): void
    {
        $transaksi = $this->buatTransaksi();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.show', $transaksi))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);

        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $transaksi));

        $this->actingAs($this->admin)
            ->get(route('koperasi.invoice.show', $transaksi))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);
    }

    public function test_daftar_pengeluaran_dan_kategori_bisa_digeser(): void
    {
        Pengeluaran::create([
            'tanggal' => now()->toDateString(), 'kategori' => 'Operasional',
            'keterangan' => 'Listrik', 'nominal' => 250_000, 'status' => 'Aktif',
        ]);

        $this->actingAs($this->admin)
            ->get(route('koperasi.pengeluaran.index'))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);

        $this->actingAs($this->admin)
            ->get(route('koperasi.kategori-pengeluaran.index'))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);
    }

    public function test_detail_hpp_peserta_bisa_digeser(): void
    {
        $hpp = HppCalculation::create([
            'participant_id' => $this->peserta->id,
            'nama_produk' => 'Keripik 250gr', 'satuan_produksi' => 'bungkus',
            'jumlah_produksi' => 100, 'total_biaya_produksi' => 800_000,
            'hpp_per_unit' => 8_000, 'harga_jual' => 12_000,
            'laba_per_unit' => 4_000, 'margin_persen' => 33.33,
            'estimasi_laba_batch' => 400_000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('hpp.show', $hpp))
            ->assertOk()
            ->assertSee('overflow-x-auto', false);
    }

    // ==================================================================
    // B. PENJAGA MENYELURUH
    // ==================================================================

    public function test_tidak_ada_halaman_bertabel_yang_kehilangan_pembungkus_gesernya(): void
    {
        // Penjaga agar perbaikan ini tidak hilang satu per satu di kemudian
        // hari. Diperiksa langsung ke berkas Blade-nya, bukan lewat request,
        // supaya halaman yang datanya kebetulan kosong tetap ikut terperiksa.
        //
        // Template PDF dikecualikan: keluarannya kertas A4, bukan layar,
        // dan pembungkus geser tidak berarti apa-apa di sana.
        $tanpaPembungkus = [];

        foreach ($this->berkasBlade() as $berkas) {
            if (str_contains($berkas, DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR)
                || str_ends_with($berkas, DIRECTORY_SEPARATOR . 'pdf.blade.php')) {
                continue;
            }

            $isi = file_get_contents($berkas);

            if (! str_contains($isi, '<table')) {
                continue;
            }

            // Kolom dihitung PER TABEL, bukan per berkas. Satu halaman bisa
            // memuat beberapa tabel sempit yang jumlah totalnya besar padahal
            // masing-masing muat di layar — menjumlahkannya akan menandai
            // halaman yang sebenarnya tidak bermasalah, dan sebaliknya
            // melewatkan halaman bertabel lebar tunggal.
            if ($this->kolomTerlebar($isi) < 4) {
                continue;
            }

            if (! str_contains($isi, 'overflow-x-auto') && ! str_contains($isi, 'overflow-auto')) {
                $tanpaPembungkus[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $berkas);
            }
        }

        $this->assertSame(
            [],
            $tanpaPembungkus,
            "Halaman bertabel lebar berikut akan meluberkan layar ponsel:\n" . implode("\n", $tanpaPembungkus)
        );
    }

    public function test_seluruh_halaman_memakai_meta_viewport(): void
    {
        // Tanpa meta ini, peramban ponsel merender halaman selebar desktop
        // lalu mengecilkannya — seluruh teks jadi tidak terbaca, dan tidak
        // satu pun perbaikan tata letak di atas akan terasa.
        foreach ([
            route('login'),
            route('anggota-akses.login'),
            route('omzet.public.show', $this->peserta->fresh()->report_token),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('name="viewport"', false);
        }

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('name="viewport"', false);
    }

    // ==================================================================

    /** Jumlah kolom tabel terlebar dalam satu berkas Blade. */
    private function kolomTerlebar(string $isi): int
    {
        $terlebar = 0;
        $posisi = 0;

        while (($mulai = strpos($isi, '<table', $posisi)) !== false) {
            $tutup = strpos($isi, '</table>', $mulai);
            $akhir = $tutup === false ? strlen($isi) : $tutup;

            $terlebar = max($terlebar, substr_count(substr($isi, $mulai, $akhir - $mulai), '<th'));
            $posisi = $akhir + 1;
        }

        return $terlebar;
    }

    /** @return list<string> */
    private function berkasBlade(): array
    {
        $berkas = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $f) {
            if (str_ends_with($f->getFilename(), '.blade.php')) {
                $berkas[] = $f->getPathname();
            }
        }

        return $berkas;
    }

    private function buatTransaksi(): SalesTransaction
    {
        $unit = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif']);

        $produk = ProductService::create([
            'unit_id' => $unit->id, 'kode' => 'P-1', 'nama' => 'Beras', 'jenis' => 'Produk',
            'harga_modal' => 50_000, 'harga_jual' => 60_000, 'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $pelanggan = Customer::create(['nama' => 'Warung Sri', 'nomor_hp' => '0899']);

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.store'), [
            'unit_id' => $unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $pelanggan->id,
            'items_master' => [['product_id' => $produk->id, 'qty' => 2]],
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ])->assertSessionHasNoErrors();

        return SalesTransaction::latest('id')->firstOrFail();
    }
}
