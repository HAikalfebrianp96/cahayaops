<?php

namespace App\Http\Controllers;

class PlaceholderController extends Controller
{
    public function cahayaPreneur()
    {
        return view('placeholder', [
            'judul' => 'Cahaya Preneur',
            'keterangan' => 'Modul pendataan peserta, pendamping, dan monitoring omzet akan dibangun pada Tahap 2.',
        ]);
    }

    public function laporan()
    {
        return view('placeholder', [
            'judul' => 'Laporan',
            'keterangan' => 'Modul laporan akan dibangun setelah data transaksi dan omzet tersedia pada tahap berikutnya.',
        ]);
    }

    public function pengaturan()
    {
        return view('placeholder', [
            'judul' => 'Pengaturan',
            'keterangan' => 'Modul pengaturan sistem akan dibangun pada tahap berikutnya.',
        ]);
    }
}
