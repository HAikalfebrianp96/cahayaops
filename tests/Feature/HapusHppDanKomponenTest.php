<?php

namespace Tests\Feature;

use App\Models\HppCalculation;
use App\Models\HppComponent;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MENGHAPUS PERHITUNGAN HPP BESERTA RINCIAN BAHANNYA.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Sejak konsep Arsip dilepas, tombol Hapus Permanen di halaman HPP
 * benar-benar membuang barisnya. `hpp_components` ber-ON DELETE CASCADE,
 * jadi seluruh rincian bahannya ikut hilang.
 *
 * Di produksi, tiga belas perhitungan HPP masing-masing memuat 4 sampai
 * 14 baris bahan — daftar yang disusun Pendamping bersama pesertanya,
 * satu per satu. Kalimat konfirmasinya hanya berbunyi "Data yang dihapus
 * tidak dapat dipulihkan", tanpa menyebut sama sekali bahwa rincian itu
 * ikut musnah.
 *
 * Orang yang melihat SATU BARIS di tabel tidak punya cara menduga bahwa
 * empat belas baris lain hilang bersamanya.
 *
 * =====================================================================
 * YANG DIUBAH
 * =====================================================================
 *
 * Bukan pengamannya — komponen memang bagian dari perhitungannya sendiri,
 * bukan data berdiri sendiri, jadi ikut terhapus adalah perilaku yang
 * benar. Yang diperbaiki keterangannya: konfirmasi kini menyebut jumlah
 * baris yang ikut hilang, diambil dari data sungguhan.
 */
class HapusHppDanKomponenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@hpp.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendamping->id,
        ]);
    }

    private function hpp(int $jumlahKomponen = 3): HppCalculation
    {
        $hpp = HppCalculation::create([
            'participant_id' => $this->peserta->id,
            'nama_produk' => 'Keripik Bawang',
            'satuan_produksi' => 'pack',
            'jumlah_produksi' => 100,
            'total_biaya_produksi' => 500_000,
            'hpp_per_unit' => 5_000,
            'harga_jual' => 8_000,
            'laba_per_unit' => 3_000,
            'margin_persen' => 37.5,
            'estimasi_laba_batch' => 300_000,
        ]);

        // range(1, 0) di PHP menghasilkan [1, 0] — menurun, bukan kosong.
        // Tanpa penjaga ini, permintaan "nol komponen" justru membuat dua.
        for ($i = 1; $i <= $jumlahKomponen; $i++) {
            HppComponent::create([
                'hpp_calculation_id' => $hpp->id,
                'kategori' => 'Bahan Baku',
                'nama_biaya' => 'Bahan ke-' . $i,
                'qty' => 1,
                'satuan' => 'kg',
                'harga_satuan' => 10_000,
                'total' => 10_000,
            ]);
        }

        return $hpp->fresh();
    }

    // =================================================================
    // KONFIRMASI MENYEBUT APA YANG IKUT HILANG
    // =================================================================

    public function test_konfirmasi_menyebut_jumlah_rincian_bahan(): void
    {
        $this->hpp(jumlahKomponen: 7);

        $this->actingAs($this->admin)
            ->get(route('hpp.index', $this->peserta))
            ->assertOk()
            ->assertSee('beserta 7 baris rincian bahannya', false);
    }

    /**
     * Angkanya mengikuti data, bukan ditulis tetap.
     *
     * Kalimat yang menyebut jumlah keliru lebih buruk daripada kalimat yang
     * tidak menyebut apa-apa: pembacanya jadi yakin pada angka yang salah.
     */
    public function test_jumlah_pada_konfirmasi_mengikuti_data(): void
    {
        $this->hpp(jumlahKomponen: 12);

        $this->actingAs($this->admin)
            ->get(route('hpp.index', $this->peserta))
            ->assertOk()
            ->assertSee('beserta 12 baris rincian bahannya', false)
            ->assertDontSee('beserta 7 baris rincian bahannya', false);
    }

    /** Perhitungan tanpa komponen tetap menyebut angkanya apa adanya. */
    public function test_perhitungan_tanpa_komponen_menyebut_nol(): void
    {
        $this->hpp(jumlahKomponen: 0);

        $this->actingAs($this->admin)
            ->get(route('hpp.index', $this->peserta))
            ->assertOk()
            ->assertSee('beserta 0 baris rincian bahannya', false);
    }

    // =================================================================
    // PENGHAPUSAN TIDAK MENYISAKAN YATIM
    // =================================================================

    public function test_komponen_ikut_terhapus_tanpa_menyisakan_yatim(): void
    {
        $hpp = $this->hpp(jumlahKomponen: 5);

        $this->assertSame(5, HppComponent::where('hpp_calculation_id', $hpp->id)->count());

        $this->actingAs($this->admin)
            ->delete(route('hpp.destroy', $hpp))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('hpp_calculations', ['id' => $hpp->id]);
        $this->assertSame(0, HppComponent::where('hpp_calculation_id', $hpp->id)->count());

        // Tidak ada komponen yang menunjuk perhitungan yang sudah tiada.
        $this->assertSame(
            0,
            HppComponent::whereNotIn('hpp_calculation_id', HppCalculation::pluck('id'))->count()
        );
    }

    /** Perhitungan LAIN milik peserta yang sama tidak ikut terbawa. */
    public function test_perhitungan_lain_tidak_ikut_terhapus(): void
    {
        $dihapus = $this->hpp(jumlahKomponen: 3);
        $bertahan = $this->hpp(jumlahKomponen: 4);

        $this->actingAs($this->admin)->delete(route('hpp.destroy', $dihapus));

        $this->assertDatabaseHas('hpp_calculations', ['id' => $bertahan->id]);
        $this->assertSame(4, HppComponent::where('hpp_calculation_id', $bertahan->id)->count());
    }

    /** Pesertanya sendiri tidak ikut terhapus. */
    public function test_peserta_tidak_ikut_terhapus(): void
    {
        $hpp = $this->hpp();

        $this->actingAs($this->admin)->delete(route('hpp.destroy', $hpp));

        $this->assertDatabaseHas('participants', ['id' => $this->peserta->id]);
    }

    // =================================================================
    // OTORISASI TIDAK BERUBAH
    // =================================================================

    public function test_peran_tanpa_hak_tidak_bisa_menghapus(): void
    {
        $hpp = $this->hpp();

        foreach ([User::ROLE_PIMPINAN, User::ROLE_TIM_KOPERASI] as $role) {
            $pelaku = User::create([
                'name' => 'Akun ' . $role,
                'email' => $role . '@hpp.test',
                'password' => bcrypt('rahasia123'),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($pelaku)
                ->delete(route('hpp.destroy', $hpp))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('hpp_calculations', ['id' => $hpp->id]);
    }
}
