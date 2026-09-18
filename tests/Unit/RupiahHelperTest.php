<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Helper rupiah() — inti perbaikannya: LETAK TANDA MINUS.
 *
 * Penulisan lama "Rp{{ number_format(...) }}" menghasilkan "Rp-885.000".
 * Yang benar "-Rp885.000" — minus milik angkanya, bukan bagian simbol
 * mata uang.
 */
class RupiahHelperTest extends TestCase
{
    public function test_nilai_positif_sama_persis_dengan_penulisan_lama(): void
    {
        // Ini yang menjamin 263 penggantian di view TIDAK mengubah tampilan
        // angka non-negatif manapun.
        foreach ([0, 1, 999, 1000, 750000, 1234567, 999999999] as $n) {
            $lama = 'Rp' . number_format($n, 0, ',', '.');
            $this->assertSame($lama, rupiah($n), "Nilai {$n} berubah tampilannya");
        }
    }

    public function test_nilai_negatif_minus_di_depan_rp(): void
    {
        $this->assertSame('-Rp885.000', rupiah(-885000));
        $this->assertSame('-Rp1', rupiah(-1));
        $this->assertSame('-Rp1.234.567', rupiah(-1234567));

        // Yang lama salah — pastikan kita benar-benar tidak menghasilkan itu lagi.
        $this->assertStringNotContainsString('Rp-', rupiah(-885000));
    }

    public function test_nol_dan_null_tidak_bertanda(): void
    {
        $this->assertSame('Rp0', rupiah(0));
        $this->assertSame('Rp0', rupiah(null));
        $this->assertSame('Rp0', rupiah(0.0));
    }

    public function test_pecahan_kecil_negatif_tidak_jadi_minus_nol(): void
    {
        // -0,4 membulat jadi 0 — TIDAK boleh tampil "-Rp0".
        $this->assertSame('Rp0', rupiah(-0.4));
        // -0,6 membulat jadi -1 — memang harus bertanda.
        $this->assertSame('-Rp1', rupiah(-0.6));
    }

    public function test_menerima_string_numerik_dari_kolom_decimal(): void
    {
        // Kolom decimal:2 di Eloquent dikembalikan sebagai string.
        $this->assertSame('Rp750.000', rupiah('750000.00'));
        $this->assertSame('-Rp885.000', rupiah('-885000.00'));
    }

    public function test_pembulatan_ke_rupiah_terdekat(): void
    {
        $this->assertSame('Rp1.235', rupiah(1234.6));
        $this->assertSame('Rp1.234', rupiah(1234.4));
    }
}
