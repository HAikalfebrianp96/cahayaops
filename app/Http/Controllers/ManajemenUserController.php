<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\UserAccountGuard;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * MANAJEMEN USER — akun internal yang punya akses login ke CahayaOS
 * (Admin, Pimpinan, Pendamping).
 *
 * BUKAN untuk Anggota Koperasi — anggota memakai mekanisme Anggota + PIN
 * yang terpisah dan tidak punya baris di tabel `users`.
 *
 * TIDAK ADA fitur hapus akun. Alasannya bukan sekadar kebijakan: 7 tabel
 * merujuk `users` dengan ON DELETE SET NULL (activity_logs.user_id dan
 * created_by di 6 tabel transaksi), jadi menghapus user akan mengosongkan
 * jejak pelaku di seluruh riwayat. Yang tersedia hanya nonaktifkan.
 *
 * Guard Admin memakai pola yang sama dengan seluruh controller lain:
 * abort_unless(Auth::user()->isAdmin(), 403) — tidak ada middleware/role
 * system baru.
 */
class ManajemenUserController extends Controller
{
    public function __construct(
        private UserAccountGuard $guard,
        private UserAccountService $akun,
    ) {
    }

    // ==========================================================
    // DAFTAR
    // ==========================================================

    public function index(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $filters = $request->only(['cari', 'role', 'status', 'tampilan']);

        // SATU daftar, memuat akun aktif maupun nonaktif — keduanya dibedakan
        // oleh lencana status di tiap baris, dan bisa disaring lewat filter
        // Status. Tidak ada daftar kedua "akun terhapus": akun memang tidak
        // pernah dihapus, hanya dinonaktifkan.
        //
        // Global scope SoftDeletes tetap terpasang di model sebagai pengaman
        // terakhir — kalau suatu saat ada kode yang memanggil delete() pada
        // User, akunnya langsung berhenti bisa login alih-alih diam-diam
        // tetap hidup.
        $users = User::query()
            ->when($request->filled('cari'), function ($q) use ($request) {
                $kata = '%' . $request->string('cari') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $kata)->orWhere('email', 'like', $kata));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            // input() (bukan string()) — string() mengembalikan objek Stringable,
            // sehingga perbandingan === dengan string biasa SELALU false dan
            // filter status akan salah menyaring.
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'aktif'))
            // Urut Admin -> Pimpinan -> Pendamping. Memakai CASE (bukan FIELD()
            // yang khusus MySQL) supaya tetap jalan di driver lain, termasuk
            // SQLite yang dipakai environment test.
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'pimpinan' THEN 2 ELSE 3 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('manajemen-user.index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => User::ROLE_OPTIONS,
            'ringkasan' => $this->ringkasan(),
        ]);
    }

    /** Angka ringkas untuk kartu di atas tabel — hitungan sederhana, tanpa rumus baru. */
    /**
     * Ringkasan jumlah user per peran.
     *
     * Daftar perannya diambil dari User::ROLE_OPTIONS, BUKAN ditulis satu
     * per satu di sini. Versi sebelumnya menyebut tiga peran secara manual,
     * dan ketika peran Tim Koperasi ditambahkan ke sistem, kartunya tidak
     * ikut muncul — Admin melihat "3 kartu untuk 4 peran" dan wajar mengira
     * akunnya belum ada. Dengan bersumber pada ROLE_OPTIONS, peran apa pun
     * yang ditambahkan nanti otomatis punya kartunya sendiri.
     *
     * Peran yang belum punya satu user pun tetap ditampilkan sebagai 0,
     * supaya terlihat bedanya antara "belum ada akunnya" dan "perannya tidak
     * dikenal sistem".
     */
    private function ringkasan(): array
    {
        // Satu query dikelompokkan per peran, bukan satu query per peran.
        $perPeran = User::query()
            ->selectRaw('role, count(*) as jumlah')
            ->groupBy('role')
            ->pluck('jumlah', 'role');

        $peran = [];

        foreach (User::ROLE_OPTIONS as $kode => $label) {
            $peran[$kode] = [
                'label' => $label,
                'jumlah' => (int) ($perPeran[$kode] ?? 0),
            ];
        }

        return [
            'total' => User::count(),
            'peran' => $peran,
            'aktif' => User::where('is_active', true)->count(),
            'nonaktif' => User::where('is_active', false)->count(),
        ];
    }

    // ==========================================================
    // TAMBAH
    // ==========================================================

    public function create()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return view('manajemen-user.create', [
            'roleOptions' => User::ROLE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(User::ROLE_OPTIONS))],
            // Standar yang sama dengan AccountController & PendampingController.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            // Di-hash otomatis lewat cast 'hashed' di model User.
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Memakai action yang SUDAH ada di project ('account_created', dipakai
        // juga oleh PendampingController) — bukan istilah baru.
        ActivityLog::record(
            action: 'account_created',
            modelType: 'User',
            modelId: $user->id,
            description: 'Akun dibuat lewat Manajemen User: ' . $user->name
                . ' (' . $user->email . ') sebagai ' . User::ROLE_OPTIONS[$user->role],
        );

        return redirect()
            ->route('manajemen-user.index')
            ->with('success', 'Akun ' . $user->name . ' berhasil dibuat.');
    }

    // ==========================================================
    // EDIT
    // ==========================================================

    public function edit(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return view('manajemen-user.edit', [
            'user' => $user,
            'roleOptions' => User::ROLE_OPTIONS,
            'adminAktifTerakhir' => $this->guard->adminAktifTerakhir($user),
            'diriSendiri' => $user->id === Auth::id(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLE_OPTIONS))],
        ]);

        $roleLama = $user->role;
        $roleBaru = $validated['role'];
        $roleBerubah = $roleLama !== $roleBaru;

        if ($roleBerubah) {
            // Diri sendiri: menurunkan role sendiri SELALU membuat pengguna ini
            // kehilangan aksesnya sendiri ke Manajemen User — dicegah, apa pun
            // jumlah Admin lain yang ada.
            if ($user->id === Auth::id() && $roleLama === User::ROLE_ADMIN) {
                return back()
                    ->withInput()
                    ->withErrors(['role' => 'Anda tidak dapat mengubah role akun Anda sendiri. Minta Admin lain yang melakukannya.']);
            }

            // Pengaman Admin aktif terakhir — logic dari Tahap 2, tidak diduplikasi.
            if (! $this->guard->bolehUbahRole($user, $roleBaru)) {
                return back()
                    ->withInput()
                    ->withErrors(['role' => 'Perubahan tidak dapat dilakukan karena ini adalah Admin aktif terakhir.']);
            }
        }

        DB::transaction(function () use ($user, $validated, $roleLama, $roleBaru, $roleBerubah) {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->role = $roleBaru;

            // Pendamping -> Admin/Pimpinan: putuskan tautan ke data Pendamping.
            // Kalau dibiarkan terisi, akun non-Pendamping tetap memegang
            // pendamping_id dan datanya jadi rancu. Status di tabel
            // `pendampings` SENGAJA TIDAK diubah — itu status kepegawaian,
            // urusan yang berbeda dari akses login.
            $pendampingIdLama = $user->pendamping_id;
            if ($roleBerubah && $roleLama === User::ROLE_PENDAMPING && $pendampingIdLama !== null) {
                $user->pendamping_id = null;
            }

            $user->save();

            if ($roleBerubah) {
                $keterangan = 'Role diubah: ' . $user->name . ' dari '
                    . (User::ROLE_OPTIONS[$roleLama] ?? $roleLama) . ' menjadi '
                    . (User::ROLE_OPTIONS[$roleBaru] ?? $roleBaru);

                if ($pendampingIdLama !== null && $user->pendamping_id === null) {
                    $keterangan .= '. Tautan ke data Pendamping dilepas.';
                }

                ActivityLog::record(
                    action: 'role_changed',
                    modelType: 'User',
                    modelId: $user->id,
                    description: $keterangan,
                );
            } else {
                ActivityLog::record(
                    action: 'account_updated',
                    modelType: 'User',
                    modelId: $user->id,
                    description: 'Data akun diperbarui: ' . $user->name . ' (' . $user->email . ')',
                );
            }
        });

        return redirect()
            ->route('manajemen-user.index')
            ->with('success', 'Akun ' . $user->name . ' berhasil diperbarui.');
    }

    // ==========================================================
    // AKTIFKAN / NONAKTIFKAN
    // ==========================================================

    public function status(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        // Mengaktifkan kembali selalu aman — tidak pernah bisa membuat sistem
        // kehilangan Admin. Yang perlu dijaga hanya arah menonaktifkan.
        if ($user->isActive()) {
            if ($user->id === Auth::id()) {
                return back()->withErrors([
                    'status' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ]);
            }

            if (! $this->guard->bolehDinonaktifkan($user)) {
                return back()->withErrors([
                    'status' => 'Perubahan tidak dapat dilakukan karena ini adalah Admin aktif terakhir.',
                ]);
            }
        }

        $menjadiAktif = ! $user->isActive();

        // Password, role, pendamping_id, dan seluruh riwayat TIDAK disentuh —
        // hanya kolom is_active yang berubah.
        $user->is_active = $menjadiAktif;
        $user->save();

        // Snapshot identitasnya ikut dituliskan — nama, email, DAN role.
        // Pelakunya sendiri (actor_nama/actor_email), waktunya, serta IP-nya
        // sudah dibekukan oleh ActivityLog::record.
        //
        // Nama aksinya 'account_restored', bukan 'account_activated': itulah
        // nama yang sudah dipakai jejak pemulihan akun di sistem ini sejak
        // dulu, jadi riwayat lama dan baru tetap terbaca sebagai satu hal
        // yang sama. Kode aksi adalah IDENTITAS baris log — mengubahnya
        // memutus riwayat, sementara mengubah teksnya tidak.
        //
        // Yang dibaca Admin di tombol dan pesan memang "Aktifkan Kembali",
        // sengaja dibedakan dari "Pulihkan" milik data bisnis: yang satu
        // soal AKSES LOGIN, yang lain soal DATA.
        $role = User::ROLE_OPTIONS[$user->role] ?? $user->role;

        ActivityLog::record(
            action: $menjadiAktif ? 'account_restored' : 'account_deactivated',
            modelType: 'User',
            modelId: $user->id,
            description: ($menjadiAktif ? 'Akun diaktifkan kembali: ' : 'Akun dinonaktifkan: ')
                . $user->name . ' (' . $user->email . ') sebagai ' . $role
                . '. Data bisnis dan catatan aktivitasnya tetap tersimpan.',
        );

        return redirect()
            ->route('manajemen-user.index')
            ->with('success', $menjadiAktif
                ? 'Akun berhasil diaktifkan kembali.'
                : 'Akun berhasil dinonaktifkan.');
    }

    // ==========================================================
    // SIKLUS AKUN
    // ==========================================================
    //
    // Akun TIDAK dihapus. Satu-satunya cara mencabut akses adalah
    // menonaktifkannya lewat status() di atas, dan satu-satunya cara
    // mengembalikannya adalah menonaktifkan balik lewat metode yang sama.
    //
    // Dulu ada destroy() yang memanggil forceDelete(). Itu dilepas, bukan
    // dilemahkan: akun yang barisnya benar-benar dibuang membuat user_id di
    // seluruh Activity Log dan data bisnisnya menjadi NULL (foreign key-nya
    // ON DELETE SET NULL), sehingga jejak "siapa mengerjakan ini" hanya
    // tersisa sebagai teks di kolom snapshot — tidak bisa lagi ditelusuri
    // sebagai relasi.
    //
    // Akun salah ketik pun tidak butuh penghapusan: email, nama, dan role
    // semuanya bisa diperbaiki lewat update().

    // ==========================================================
    // RESET PASSWORD
    // ==========================================================

    /**
     * Password sementara dibuat oleh UserAccountService — logic yang SAMA
     * dengan reset password di halaman Pendamping, bukan salinan kedua.
     */
    public function resetPassword(User $user)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $passwordBaru = $this->akun->resetPassword($user, 'Manajemen User');

        // Ditampilkan SEKALI lewat flash session supaya Admin bisa
        // meneruskannya ke pemilik akun. Tidak disimpan di mana pun selain
        // sebagai hash, dan tidak pernah masuk Activity Log.
        return redirect()
            ->route('manajemen-user.index')
            ->with('password_baru', $passwordBaru)
            ->with('password_baru_untuk', $user->name)
            ->with('success', 'Password sementara untuk ' . $user->name . ' berhasil dibuat.');
    }
}
