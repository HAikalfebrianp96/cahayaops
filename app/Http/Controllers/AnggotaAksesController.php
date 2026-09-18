<?php

namespace App\Http\Controllers;

use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\KoperasiMember;
use Illuminate\Http\Request;

class AnggotaAksesController extends Controller
{
    public function showLogin()
    {
        return view('anggota-akses.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'nomor_anggota' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ]);

        $member = KoperasiMember::where('nomor_anggota', $validated['nomor_anggota'])->first();

        if (! $member || ! $member->verifyPin($validated['pin'])) {
            return back()
                ->withErrors(['pin' => 'Nomor Anggota atau PIN salah.'])
                ->withInput($request->only('nomor_anggota'));
        }

        // Sesi TERBATAS khusus anggota ini — TIDAK terkait sama sekali dgn
        // Auth::guard sistem users (Admin/Pendamping/Pimpinan).
        $request->session()->regenerate();
        session(['anggota_koperasi_id' => $member->id]);

        return redirect()->route('anggota-akses.hasil');
    }

    public function hasil()
    {
        $memberId = session('anggota_koperasi_id');
        abort_unless($memberId, 403);

        $member = KoperasiMember::find($memberId);

        // Kalau anggota sudah dihapus Admin, sesi lama otomatis tidak berlaku lagi.
        if (! $member) {
            session()->forget('anggota_koperasi_id');
            abort(403);
        }

        $member->load('savings');

        // Rumusnya diambil dari model, BUKAN disusun ulang di sini — lihat
        // KoperasiMember::ringkasanSimpanan(). Halaman ini dan halaman Admin
        // menampilkan uang milik orang yang sama; keduanya harus mustahil
        // berbeda, bukan sekadar kebetulan sama.
        $ringkasan = $member->ringkasanSimpanan();

        // Dana Sosial ditampilkan sbg SALDO BERSAMA (transparansi), BUKAN saldo pribadi.
        $saldoDanaSosial = (float) DanaSukarelaMasuk::berlaku()->sum('nominal') - (float) DanaSukarelaKeluar::berlaku()->sum('nominal');

        // Grafik sederhana: akumulasi simpanan pribadi dari waktu ke waktu.
        // SELURUH jenis simpanan ikut, termasuk Sukarela — kalau tidak,
        // garis grafiknya berakhir di angka yang berbeda dari kartu Total
        // tepat di atasnya, dan anggota melihat dua jawaban untuk satu
        // pertanyaan yang sama.
        $riwayatUrutLama = $member->savings
            ->where('status', '!=', \App\Models\MemberSaving::STATUS_DIBATALKAN)
            ->sortBy('tanggal');

        $akumulasi = 0;
        $chartLabels = [];
        $chartData = [];
        foreach ($riwayatUrutLama as $s) {
            $akumulasi += (float) $s->nominal;
            $chartLabels[] = $s->tanggal->format('M Y');
            $chartData[] = $akumulasi;
        }

        return view('anggota-akses.hasil', [
            'member' => $member,
            'ringkasan' => $ringkasan,
            'saldoDanaSosial' => $saldoDanaSosial,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget('anggota_koperasi_id');
        $request->session()->regenerate();

        return redirect()->route('anggota-akses.login');
    }
}
