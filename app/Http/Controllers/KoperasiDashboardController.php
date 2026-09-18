<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/**
 * ALIAS Dashboard Koperasi Cahaya.
 *
 * Dashboard Koperasi TIDAK lagi jadi halaman tersendiri — sekarang jadi
 * salah satu panel di Dashboard gabungan (route 'dashboard', switcher
 * ?modul=koperasi). SELURUH logic perhitungannya DIPINDAHKAN ke
 * DashboardController::koperasiData() apa adanya, tidak ada yang dihapus.
 *
 * Route 'koperasi.dashboard' sengaja DIPERTAHANKAN sebagai redirect supaya
 * link/bookmark lama ke /koperasi/dashboard tidak jadi 404.
 */
class KoperasiDashboardController extends Controller
{
    public function index()
    {
        // Guard dipertahankan persis seperti sebelumnya — Koperasi khusus Admin.
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return redirect()->route('dashboard', ['modul' => 'koperasi']);
    }
}
