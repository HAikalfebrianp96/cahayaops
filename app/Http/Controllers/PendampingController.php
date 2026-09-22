<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\ActivityLog;
use App\Models\Pendamping;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan data Pendamping — MODUL KHUSUS ADMIN.
 *
 * Menunya memang hanya muncul di sidebar Admin, dan resetPassword()
 * serta destroy() sejak awal sudah dijaga isAdmin(). Tetapi enam method
 * lainnya dulu sama sekali tanpa penjagaan, jadi Pimpinan atau Pendamping
 * yang mengetik URL-nya langsung tetap bisa membuka, menambah, dan
 * mengubah data Pendamping — termasuk store()/update() dengan opsi
 * "buat_akun" yang MEMBUAT AKUN LOGIN BARU.
 *
 * Penjagaan di bawah menyamakan seluruh method dengan rancangan yang
 * sudah berlaku di modul sejenis (ParticipantController). Tidak ada
 * perilaku Admin yang berubah.
 */
class PendampingController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $pendampings = Pendamping::withCount('participants')
            ->with(['user', 'penghapus'])
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        // Summary KPI — query sederhana dari data existing, tidak ada tabel/rumus baru.
        $summary = [
            'total_pendamping' => Pendamping::count(),
            'pendamping_aktif' => Pendamping::where('status', 'Aktif')->count(),
            'total_peserta_binaan' => \App\Models\Participant::whereNotNull('pendamping_id')->count(),
        ];

        return view('pendampings.index', [
            'pendampings' => $pendampings,
            'summary' => $summary,
            // Ambang beban dipakai menandai pendamping yang binaannya terlalu
            // banyak — bahan Admin untuk menimbang redistribusi peserta.
            'kpi' => app(\App\Services\KpiService::class),
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return view('pendampings.create', [
            'statusOptions' => Pendamping::STATUS_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $this->validated($request);
        $buatAkun = $request->boolean('buat_akun');

        if ($buatAkun) {
            DB::transaction(function () use ($validated, $request) {
                $pendamping = Pendamping::create([
                    'nama' => $validated['nama'],
                    'nomor_hp' => $validated['nomor_hp'],
                    'email' => $validated['email'],
                    'status' => $validated['status'],
                ]);

                $user = User::create([
                    'name' => $pendamping->nama,
                    'email' => $pendamping->email,
                    'password' => $request->input('password'),
                    'role' => 'pendamping',
                    'pendamping_id' => $pendamping->id,
                ]);

                // Activity Log — akun login baru dibuat (aktivitas keamanan,
                // terpisah dari log "Created Pendamping" yang sudah tercatat
                // otomatis lewat Observer untuk data profilnya).
                ActivityLog::record(
                    action: 'account_created',
                    modelType: 'User',
                    modelId: $user->id,
                    description: 'Akun login Pendamping dibuat: ' . $pendamping->nama . ' (' . $pendamping->email . ')',
                );
            });

            return redirect()
                ->route('pendampings.index')
                ->with('success', 'Pendamping baru & akun login berhasil dibuat.');
        }

        Pendamping::create([
            'nama' => $validated['nama'],
            'nomor_hp' => $validated['nomor_hp'],
            'email' => $validated['email'],
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('pendampings.index')
            ->with('success', 'Pendamping baru berhasil ditambahkan.');
    }

    public function show(Pendamping $pendamping)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $pendamping->load(['participants', 'user']);

        // Data ringan tambahan utk Detail Pendamping — SEMUA cuma membaca
        // data existing, TIDAK ADA tabel/rumus baru.
        $jumlahAktivitasPendampingan = \App\Models\PendampinganRecord::where('pendamping_id', $pendamping->id)->count();
        $omzetBinaan = \App\Models\OmzetReport::whereIn('participant_id', $pendamping->participants->pluck('id'))->sum('omzet');

        return view('pendampings.show', compact('pendamping', 'jumlahAktivitasPendampingan', 'omzetBinaan'));
    }

    public function edit(Pendamping $pendamping)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $pendamping->load('user');

        return view('pendampings.edit', [
            'pendamping' => $pendamping,
            'statusOptions' => Pendamping::STATUS_OPTIONS,
        ]);
    }

    public function update(Request $request, Pendamping $pendamping)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $sudahPunyaAkun = (bool) $pendamping->user;
        $validated = $this->validated($request, $pendamping);
        $buatAkun = $request->boolean('buat_akun');

        DB::transaction(function () use ($pendamping, $validated, $buatAkun, $sudahPunyaAkun, $request) {
            $pendamping->update([
                'nama' => $validated['nama'],
                'nomor_hp' => $validated['nomor_hp'],
                'email' => $validated['email'],
                'status' => $validated['status'],
            ]);

            if ($buatAkun && ! $sudahPunyaAkun) {
                $user = User::create([
                    'name' => $pendamping->nama,
                    'email' => $pendamping->email,
                    'password' => $request->input('password'),
                    'role' => 'pendamping',
                    'pendamping_id' => $pendamping->id,
                ]);

                ActivityLog::record(
                    action: 'account_created',
                    modelType: 'User',
                    modelId: $user->id,
                    description: 'Akun login Pendamping dibuat: ' . $pendamping->nama . ' (' . $pendamping->email . ')',
                );
            } elseif ($sudahPunyaAkun && $pendamping->wasChanged('email')) {
                $pendamping->user->update(['email' => $pendamping->email]);
            }
        });

        return redirect()
            ->route('pendampings.index')
            ->with('success', 'Data pendamping berhasil diperbarui.');
    }

    /**
     * Reset password akun login Pendamping (Admin saja).
     *
     * Sejak Tahap 3, pembuatan password sementara + pencatatan Activity Log
     * DIPINDAHKAN ke UserAccountService supaya modul Manajemen User dan
     * halaman ini memakai logic yang SAMA PERSIS — bukan dua salinan yang
     * bisa berbeda perilaku. Alur, guard, dan hasil akhirnya tidak berubah.
     */
    public function resetPassword(Pendamping $pendamping, UserAccountService $akun)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $user = $pendamping->user;

        // Validasi relasi: pastikan akun benar-benar milik pendamping ini,
        // bukan akun nyasar/tidak konsisten.
        if (! $user || $user->role !== 'pendamping' || $user->pendamping_id !== $pendamping->id) {
            return redirect()
                ->route('pendampings.edit', $pendamping)
                ->with('error', 'Akun login Pendamping tidak ditemukan atau belum terhubung.');
        }

        $passwordBaru = $akun->resetPassword($user, 'Pendamping');

        return redirect()
            ->route('pendampings.edit', $pendamping)
            ->with('password_baru', $passwordBaru)
            ->with('success', 'Password sementara berhasil dibuat.');
    }

    public function destroy(Pendamping $pendamping)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        // Peserta yang terkait TIDAK ikut terhapus — pendamping_id mereka
        // otomatis jadi kosong (lihat migration: nullOnDelete()).
        //
        // Akun login (User) TIDAK dihapus — cuma DILEPAS (pendamping_id
        // dikosongkan). Menghapus User berisiko kehilangan histori/audit
        // akun, padahal user dgn pendamping_id kosong otomatis tidak bisa
        // akses data peserta manapun lagi lewat canAccessParticipant().
        // Penjaga DULU, baru melepas akun. Dulu urutannya terbalik: akun
        // sudah dilepas sebelum penjaga menolak, sehingga penghapusan yang
        // DITOLAK tetap membuat Pendamping kehilangan akses ke binaannya.
        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($pendamping);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = DB::transaction(function () use ($pendamping, $penghapusan) {
            if ($pendamping->user) {
                $userId = $pendamping->user->id;
                $userName = $pendamping->user->name;

                $pendamping->user()->update(['pendamping_id' => null]);

                ActivityLog::record(
                    action: 'account_unlinked',
                    modelType: 'User',
                    modelId: $userId,
                    description: 'Akun login dilepas dari data Pendamping (dihapus): ' . $userName,
                );
            }

            return $penghapusan->hapusDenganCatatan($pendamping);
        });

        return redirect()
            ->route('pendampings.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    private function validated(Request $request, ?Pendamping $pendamping = null): array
    {
        $buatAkun = $request->boolean('buat_akun');
        $sudahPunyaAkun = $pendamping && $pendamping->user;

        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'nomor_hp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(Pendamping::STATUS_OPTIONS)],
        ];

        if ($buatAkun && ! $sudahPunyaAkun) {
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        } elseif ($sudahPunyaAkun && $pendamping->email !== $request->input('email')) {
            $rules['email'] = [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($pendamping->user->id),
            ];
        }

        return $request->validate($rules);
    }
}
