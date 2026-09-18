<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Services\FollowUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * REMINDER CENTER — "Follow Up Saya".
 *
 * Menjawab pertanyaan pertama pendamping setiap pagi: siapa yang harus saya
 * kunjungi hari ini?
 *
 * TIDAK ADA TABEL FOLLOW UP BARU. Seluruh isinya diturunkan dari catatan
 * pendampingan yang memang sudah ditulis pendamping sendiri
 * (lihat [FollowUpService]) — jadi tidak ada data kedua yang harus dijaga
 * tetap sinkron, dan tidak ada yang perlu diinput dua kali.
 *
 * OTORISASI:
 *   Pendamping -> HANYA binaannya sendiri, di-scope di level query.
 *   Admin      -> seluruh pendamping, dengan filter per pendamping.
 *   Lainnya    -> ditolak. Pimpinan read-only tidak menindaklanjuti apa pun,
 *                 dan Tim Koperasi bukan urusannya.
 */
class FollowUpController extends Controller
{
    public function __construct(private FollowUpService $followUp)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $bolehLihat = $user->isAdmin()
            || ($user->isPendamping() && $user->pendamping_id);

        abort_unless($bolehLihat, 403);

        // ==========================================================
        // SCOPE — inti keamanan halaman ini.
        //
        // Untuk Pendamping, query berangkat dari pendamping_id-nya sendiri
        // dan filter ?pendamping_id dari URL DIABAIKAN sepenuhnya. Kalau
        // filter itu dihormati, mengetik id orang lain akan menampilkan
        // seluruh binaan pendamping lain.
        // ==========================================================
        // legalitasRecords ikut dimuat: kartu tugas sekarang menyebut
        // dokumen legalitas yang belum selesai, dan tanpa eager load itu
        // berarti satu query tambahan untuk SETIAP peserta yang tampil.
        $query = Participant::with(['pendamping', 'koperasiMember', 'legalitasRecords']);

        if ($user->isAdmin()) {
            if ($request->filled('pendamping_id')) {
                $query->where('pendamping_id', $request->integer('pendamping_id'));
            }
        } else {
            $query->where('pendamping_id', $user->pendamping_id);
        }

        // Binaan yang sudah Alumni/Nonaktif tidak perlu ditindaklanjuti lagi.
        $peserta = $query->orderBy('nama_peserta')->get()->filter->masihBerjalan();

        $kelompok = $this->followUp->kelompokkan($peserta);

        return view('follow-up.index', [
            'kelompok' => $kelompok,
            'ambang' => $this->followUp->ambang(),
            'isAdmin' => $user->isAdmin(),
            'pendampings' => $user->isAdmin() ? Pendamping::orderBy('nama')->get() : collect(),
            'filters' => $request->only(['pendamping_id']),
            'jumlah' => [
                'hari_ini' => $kelompok['hari_ini']->count(),
                'terlambat' => $kelompok['terlambat']->count(),
                'akan_datang' => $kelompok['akan_datang']->count(),
                'belum_pernah' => $kelompok['belum_pernah']->count(),
                'perlu_perhatian' => $kelompok['perlu_perhatian']->count(),
                'normal' => $kelompok['normal']->count(),
            ],
            // Dipakai judul halaman: Pendamping melihat "Tugas Saya",
            // Admin tetap melihat "Follow Up" karena ia memantau pekerjaan
            // orang lain, bukan mengerjakannya sendiri.
            'untukPendamping' => ! $user->isAdmin(),
        ]);
    }
}
