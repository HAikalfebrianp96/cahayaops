<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class KoperasiPlaceholderController extends Controller
{
    private const LABEL = [
        'pengeluaran' => 'Pengeluaran',
        'laporan' => 'Laporan',
    ];

    public function show(string $modul)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        abort_unless(array_key_exists($modul, self::LABEL), 404);

        return view('koperasi.placeholder', [
            'judul' => self::LABEL[$modul],
        ]);
    }
}
