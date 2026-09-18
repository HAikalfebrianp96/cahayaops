<?php

if (! function_exists('rupiah')) {
    /**
     * Format nominal jadi Rupiah dengan LETAK TANDA MINUS YANG BENAR.
     *
     * Sebelumnya di view ditulis manual: Rp{{ number_format($n, 0, ',', '.') }}
     * — pola itu menghasilkan "Rp-885.000" untuk nilai negatif, padahal yang
     * benar "-Rp885.000" (minus milik angkanya, bukan bagian dari simbol mata
     * uang). Helper ini dipakai supaya penulisannya seragam di seluruh
     * aplikasi dan nilai negatif baru yang muncul nanti otomatis benar.
     *
     * Untuk nilai NOL dan POSITIF hasilnya PERSIS SAMA dengan penulisan lama,
     * jadi penggantian ini tidak mengubah tampilan angka manapun yang tidak
     * negatif.
     *
     *   rupiah(750000)   => "Rp750.000"
     *   rupiah(-885000)  => "-Rp885.000"
     *   rupiah(0)        => "Rp0"
     *   rupiah(null)     => "Rp0"
     *
     * Pembulatan dilakukan DULU sebelum tanda ditentukan, supaya nilai pecahan
     * kecil seperti -0,4 (yang membulat jadi 0) tidak tampil sebagai "-Rp0".
     */
    function rupiah(int|float|string|null $nominal): string
    {
        $angka = round((float) $nominal);
        $tanda = $angka < 0 ? '-' : '';

        return $tanda . 'Rp' . number_format(abs($angka), 0, ',', '.');
    }
}
