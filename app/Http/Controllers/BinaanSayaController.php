<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BINAAN SAYA — daftar peserta milik Pendamping yang sedang login.
 *
 * Ini adalah "Data Peserta"-nya Pendamping. Daftar global di
 * ParticipantController::index() TETAP Admin-only; Pendamping tidak pernah
 * melihat binaan Pendamping lain karena query di sini berangkat dari
 * $user->pendamping->participants() — ter-scope di level database, bukan
 * disaring belakangan di tampilan.
 */
class BinaanSayaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->isPendamping() && $user->pendamping_id, 403);

        // Akun bisa saja masih menyimpan pendamping_id sementara profilnya
        // sudah diarsipkan. Alur Arsipkan Pendamping memang melepas tautannya
        // lebih dulu, tapi pengamannya ada di controller LAIN — jadi halaman
        // ini tidak boleh bergantung pada itu. Tanpa profil, tidak ada binaan
        // yang bisa ditampilkan, dan itu jawaban yang benar.
        abort_unless($user->pendamping !== null, 403, 'Akun Anda belum tertaut ke data Pendamping manapun.');

        $query = $user->pendamping->participants()->with('koperasiMember');

        if ($request->filled('q')) {
            $keyword = $request->string('q');
            $query->where(function ($sub) use ($keyword) {
                $sub->where('nama_peserta', 'like', "%{$keyword}%")
                    ->orWhere('nama_usaha', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $participants = $query->orderBy('nama_peserta')->get();

        return view('binaan.index', [
            'participants' => $participants,
            'pendamping' => $user->pendamping,
            'statusOptions' => Participant::STATUS_OPTIONS,
            'filters' => $request->only(['q', 'status']),
        ]);
    }
}
