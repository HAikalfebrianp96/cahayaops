<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unduh PDF Laporan Unit Usaha.
 *
 * Laporan ini selalu tentang SATU unit. Tanpa ?unit_id= terpilih, view-nya
 * dulu membaca $unit->nama pada nilai null dan menghasilkan HTTP 500 —
 * ditemukan saat menyapu seluruh route per role. Halaman HTML-nya sudah
 * menampilkan pesan "pilih unit dulu"; sekarang jalur PDF-nya disamakan.
 */
class LaporanUnitUsahaPdfTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $role): User
    {
        return User::create([
            'name' => 'User ' . $role,
            'email' => $role . '@pdf.test',
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function unit(): BusinessUnit
    {
        return BusinessUnit::create([
            'nama' => 'Unit Uji PDF',
            'kode' => 'UUP-01',
            'tipe' => 'Barang',
            'status' => 'Aktif',
        ]);
    }

    /** Tanpa unit terpilih: ditolak dengan pesan, BUKAN 500. */
    public function test_tanpa_unit_terpilih_tidak_error_dan_memberi_pesan(): void
    {
        foreach (['admin', 'pimpinan', 'tim_koperasi'] as $role) {
            $respons = $this->actingAs($this->buat($role))
                ->get(route('koperasi.laporan.unit-usaha.pdf'));

            $this->assertNotSame(500, $respons->getStatusCode(), "Role $role masih mendapat 500.");
            $respons->assertSessionHasErrors('unit');
        }
    }

    /** Dengan unit terpilih: PDF benar-benar terunduh. */
    public function test_dengan_unit_terpilih_pdf_terunduh(): void
    {
        $unit = $this->unit();

        $respons = $this->actingAs($this->buat('admin'))
            ->get(route('koperasi.laporan.unit-usaha.pdf', ['unit_id' => $unit->id]));

        $respons->assertOk();
        $this->assertSame('application/pdf', $respons->headers->get('content-type'));
        $this->assertStringContainsString('unit-uji-pdf', $respons->headers->get('content-disposition'));
    }

    /** Otorisasi laporan tidak ikut berubah oleh perbaikan ini. */
    public function test_pendamping_tetap_ditolak(): void
    {
        $this->actingAs($this->buat('pendamping'))
            ->get(route('koperasi.laporan.unit-usaha.pdf'))
            ->assertForbidden();
    }

    /** Halaman HTML-nya tetap bisa dibuka tanpa unit terpilih. */
    public function test_halaman_html_tetap_normal_tanpa_unit(): void
    {
        $this->actingAs($this->buat('tim_koperasi'))
            ->get(route('koperasi.laporan.unit-usaha'))
            ->assertOk();
    }
}
