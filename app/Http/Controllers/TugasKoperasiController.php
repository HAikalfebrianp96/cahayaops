<?php

namespace App\Http\Controllers;

use App\Services\TugasKoperasiService;
use Illuminate\Support\Facades\Auth;

/**
 * TUGAS KOPERASI — halaman kerja harian Tim Koperasi.
 *
 * Pasangan dari "Tugas Saya" milik Pendamping, dan sengaja dibuat setipis
 * mungkin: seluruh perhitungannya ada di TugasKoperasiService, yang juga
 * dipakai ringkasan di Dashboard. Controller ini hanya mengurus siapa yang
 * boleh membuka.
 *
 * OTORISASI memakai bolehKelolaKoperasi() — helper yang SAMA dengan seluruh
 * modul Koperasi lainnya, jadi tidak ada aturan izin kedua yang bisa
 * berbeda. Admin ikut masuk lewat helper itu; Pendamping dan Pimpinan tidak.
 *
 * Pimpinan sengaja TIDAK diberi akses: halaman ini daftar pekerjaan yang
 * harus dikerjakan, bukan laporan. Yang dibutuhkan Pimpinan sudah tersedia
 * di Dashboard dan Laporan Koperasi.
 */
class TugasKoperasiController extends Controller
{
    public function __construct(private readonly TugasKoperasiService $tugas)
    {
    }

    public function index()
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.tugas.index', $this->tugas->semua());
    }
}
