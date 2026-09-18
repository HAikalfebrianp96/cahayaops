<?php

namespace Tests\Feature\Uat;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\FinancialProxy;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SETIAP HALAMAN DIBUKA LAGI — SETELAH SELURUH DATA MASTERNYA DIARSIPKAN.
 *
 * ==========================================================================
 * KENAPA TEST INI ADA
 * ==========================================================================
 *
 * Sebuah bug nyata ditemukan di lapangan: Cahaya Sembako diarsipkan lewat
 * menu Unit Usaha, dan berhari-hari kemudian halaman Koreksi seluruh
 * transaksi lamanya mati dengan TypeError. Pengarsipannya sendiri berhasil
 * tanpa satu pun peringatan.
 *
 * Bentuk bugnya bisa diulang di mana saja:
 *
 *     data historis masih menunjuk master  ->  master diarsipkan
 *     ->  relasi biasa berhenti menemukannya  ->  halaman histori mati.
 *
 * Mengarsipkan master adalah tindakan yang MEMANG didukung sistem ini —
 * ada tombolnya, ada halaman Arsipnya, ada pemulihannya. Jadi keadaan ini
 * bukan kejadian langka, melainkan akibat wajar dari pemakaian normal.
 *
 * SmokeSeluruhHalamanTest membuka setiap halaman dengan data yang serba
 * aktif. Berkas ini membuka halaman YANG SAMA, dengan data yang sama, tapi
 * setelah SETIAP master diarsipkan sekaligus — keadaan terburuk yang masuk
 * akal. Yang dicari cuma satu: halaman yang membalas 500.
 *
 * Datanya sengaja TIDAK dibuat ulang di sini; keduanya memakai
 * [MenyiapkanDataSeluruhModul] supaya modul baru yang ditambahkan ke smoke
 * test ikut terlindungi di sini tanpa ada yang perlu ingat menyalinnya.
 */
class SmokeHalamanMasterTerarsipTest extends TestCase
{
    use MenyiapkanDataSeluruhModul;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanPengguna();
        $this->siapkanDataContoh();
    }

    /**
     * Arsipkan seluruh master yang boleh diarsipkan.
     *
     * Catatan penting: yang diarsipkan hanya MASTER/REFERENSI, bukan catatan
     * historisnya. Peserta, pendamping, unit usaha, produk, pelanggan,
     * anggota, kategori, dan proxy adalah hal-hal yang memang berhenti
     * dipakai suatu hari — sementara omzet, penjualan, dan simpanan yang
     * menunjuk ke sana tetap harus terbaca.
     */
    private function arsipkanSeluruhMaster(): array
    {
        $diarsipkan = [];

        foreach ([
            'peserta' => Participant::class,
            'pendamping' => Pendamping::class,
            'unit usaha' => BusinessUnit::class,
            'produk' => ProductService::class,
            'pelanggan' => Customer::class,
            'anggota koperasi' => KoperasiMember::class,
            'kategori pengeluaran' => PengeluaranCategory::class,
            'financial proxy' => FinancialProxy::class,
        ] as $label => $kelas) {
            $jumlah = $kelas::query()->get()->each->delete()->count();

            if ($jumlah > 0) {
                $diarsipkan[$label] = $jumlah;
            }
        }

        return $diarsipkan;
    }

    public static function daftarRole(): array
    {
        return [
            'Admin' => ['admin'],
            'Pimpinan' => ['pimpinan'],
            'Pendamping' => ['pendamping'],
            'Tim Koperasi' => [User::ROLE_TIM_KOPERASI],
        ];
    }

    /**
     * @dataProvider daftarRole
     */
    public function test_tidak_ada_halaman_yang_meledak_setelah_master_diarsipkan(string $role): void
    {
        $diarsipkan = $this->arsipkanSeluruhMaster();

        $this->assertNotEmpty($diarsipkan, 'Tidak ada master yang berhasil diarsipkan — test ini jadi hampa.');

        $gagal = [];

        foreach ($this->routeGet() as $nama => $url) {
            $respons = $this->actingAs($this->aktor[$role])->get($url);

            if ($respons->getStatusCode() >= 500) {
                $gagal[] = $nama . ' (' . $url . ') -> ' . $respons->getStatusCode()
                    . ' :: ' . $this->ringkasKesalahan($respons);
            }
        }

        $this->assertSame([], $gagal,
            "Halaman berikut mati setelah master diarsipkan, untuk role {$role}:\n" . implode("\n", $gagal));
    }

    public function test_halaman_publik_tetap_hidup_setelah_master_diarsipkan(): void
    {
        // Tiga pintu tanpa login. Peserta dan anggota di lapangan tidak punya
        // cara lain berinteraksi dengan sistem, dan mereka tidak akan pernah
        // tahu bahwa penyebabnya adalah pengarsipan di kantor.
        $token = $this->contoh['peserta']->report_token;

        $this->arsipkanSeluruhMaster();

        $this->get(route('login'))->assertOk();
        $this->get(route('anggota-akses.login'))->assertOk();

        // Peserta yang diarsipkan memang tidak boleh lagi melapor omzet —
        // yang penting jawabannya sopan (404), bukan halaman rusak.
        $respons = $this->get(route('omzet.public.show', $token));
        $this->assertLessThan(500, $respons->getStatusCode());
    }

    public function test_pemulihan_master_mengembalikan_keadaan_semula(): void
    {
        $this->arsipkanSeluruhMaster();

        foreach ([Participant::class, Pendamping::class, BusinessUnit::class, ProductService::class,
                  Customer::class, KoperasiMember::class, PengeluaranCategory::class] as $kelas) {
            $kelas::onlyTrashed()->get()->each->restore();
        }

        $gagal = [];

        foreach ($this->routeGet() as $nama => $url) {
            $respons = $this->actingAs($this->aktor['admin'])->get($url);

            if ($respons->getStatusCode() >= 500) {
                $gagal[] = $nama . ' -> ' . $respons->getStatusCode();
            }
        }

        $this->assertSame([], $gagal, "Halaman berikut rusak setelah master dipulihkan:\n" . implode("\n", $gagal));
        $this->assertSame(0, Participant::onlyTrashed()->count());
    }
}
