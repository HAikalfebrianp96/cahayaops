@extends('layouts.app')

@section('title', 'Manajemen User')

@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">Manajemen User</h2>
            <p class="text-sm text-text-secondary mt-0.5">Kelola akun internal yang memiliki akses ke CahayaOS.</p>
        </div>
        <a href="{{ route('manajemen-user.create') }}"
           class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
            + Tambah User
        </a>
    </div>

    {{-- PASSWORD SEMENTARA — tampil SEKALI setelah reset, lalu hilang.
         Tidak tersimpan di mana pun selain sebagai hash. --}}
    @if (session('password_baru'))
        <div class="bg-gold-light border border-status-warning/40 rounded-2xl p-5">
            <p class="text-sm font-semibold text-text-main mb-1">
                Password sementara untuk {{ session('password_baru_untuk') }}
            </p>
            <p class="text-lg font-mono font-bold text-cahaya-primary select-all">{{ session('password_baru') }}</p>
            <p class="text-xs text-text-secondary mt-2">
                Catat sekarang — password ini <strong>tidak bisa ditampilkan lagi</strong> setelah halaman ini ditinggalkan.
                Sampaikan ke pemilik akun dan minta segera diganti lewat menu Ganti Password.
            </p>
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-50 border border-status-danger/40 rounded-2xl px-4 py-3">
            @foreach ($errors->all() as $pesan)
                <p class="text-sm text-status-danger">{{ $pesan }}</p>
            @endforeach
        </div>
    @endif

    {{-- RINGKASAN
         Satu kartu untuk setiap peran yang dikenal sistem, dibangkitkan dari
         daftar peran itu sendiri. Jangan kembali menulis kartunya satu per
         satu: begitulah dulu Tim Koperasi hilang dari ringkasan. --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Total User</p>
            <p class="text-2xl font-bold text-text-main tabular-nums">{{ $ringkasan['total'] }}</p>
            <p class="text-[11px] text-text-secondary mt-1">
                {{ $ringkasan['aktif'] }} aktif &middot; {{ $ringkasan['nonaktif'] }} nonaktif
            </p>
        </div>
        @foreach ($ringkasan['peran'] as $kodePeran => $dataPeran)
            {{-- data-kartu-peran menandai kartunya secara tepat. Label peran
                 juga muncul di dropdown filter dan di badge tiap baris, jadi
                 tanpa penanda ini tidak ada cara menguji bahwa KARTUNYA benar
                 -benar dirender. --}}
            <div data-kartu-peran="{{ $kodePeran }}"
                 class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
                <p class="text-xs font-medium text-text-secondary mb-2">{{ $dataPeran['label'] }}</p>
                <p class="text-2xl font-bold text-text-main tabular-nums">{{ $dataPeran['jumlah'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- DAFTAR + FILTER --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <input type="text" name="cari" value="{{ $filters['cari'] ?? '' }}"
                   placeholder="Cari nama atau email..."
                   class="flex-1 min-w-[200px] rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">

            <select name="role" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="">Semua Role</option>
                @foreach ($roleOptions as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(($filters['role'] ?? '') === $nilai)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="">Semua Status</option>
                <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
            </select>

            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Filter</button>
            <a href="{{ route('manajemen-user.index') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
        </form>

        @if ($users->isEmpty())
            <div class="text-center py-10">
                <p class="text-sm font-medium text-text-main mb-1">Tidak Ada User</p>
                <p class="text-xs text-text-secondary">Tidak ada akun yang cocok dengan filter ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-3 py-2.5 font-medium">Nama</th>
                            <th class="px-3 py-2.5 font-medium">Email</th>
                            <th class="px-3 py-2.5 font-medium">Role</th>
                            <th class="px-3 py-2.5 font-medium">Status</th>
                            <th class="px-3 py-2.5 font-medium">Terakhir Login</th>
                            <th class="px-3 py-2.5 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-3 py-3 font-medium text-text-main">
                                    {{ $u->name }}
                                    @if ($u->id === auth()->id())
                                        <span class="ml-1 text-[10px] text-text-secondary">(Anda)</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-text-secondary">{{ $u->email }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                        {{ match ($u->role) {
                                            \App\Models\User::ROLE_ADMIN => 'bg-primary-light text-cahaya-primary',
                                            \App\Models\User::ROLE_PIMPINAN => 'bg-gold-light text-cahaya-accent',
                                            \App\Models\User::ROLE_TIM_KOPERASI => 'bg-surface-bg text-koperasi-primary',
                                            default => 'bg-surface-bg text-text-secondary',
                                        } }}">
                                        {{ \App\Models\User::ROLE_OPTIONS[$u->role] ?? $u->role }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                        {{ $u->isActive() ? 'bg-primary-light text-status-success' : 'bg-red-50 text-status-danger' }}">
                                        {{ $u->isActive() ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-text-secondary whitespace-nowrap">
                                    {{ $u->last_login_at ? $u->last_login_at->format('d-m-Y H:i') : 'Belum pernah' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <a href="{{ route('manajemen-user.edit', $u) }}" class="text-status-info hover:underline text-xs font-medium">Edit</a>
                                    <span class="text-border-soft mx-1">|</span>

                                    {{-- SIKLUS AKUN — satu-satunya jalan mencabut dan
                                         mengembalikan akses. Tidak ada tombol hapus:
                                         akun tidak pernah dibuang, hanya dinonaktifkan.

                                         Kalimat konfirmasinya menyebut apa yang TETAP ada
                                         (data, histori, kemungkinan dipulihkan), supaya Admin
                                         tidak ragu bahwa ini tindakan yang bisa dibatalkan. --}}
                                    <form method="POST" action="{{ route('manajemen-user.status', $u) }}" class="inline"
                                          onsubmit="return confirm({{ $u->isActive()
                                              ? Js::from('Nonaktifkan akun ini? Akun tidak dapat login lagi, tetapi data dan histori tetap tersimpan dan akun dapat dipulihkan oleh Admin.')
                                              : Js::from('Aktifkan kembali akun ini agar dapat digunakan?') }});">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium {{ $u->isActive() ? 'text-status-danger' : 'text-status-success' }} hover:underline">
                                            {{ $u->isActive() ? 'Nonaktifkan' : 'Aktifkan Kembali' }}
                                        </button>
                                    </form>

                                    {{-- Reset Password hanya ditawarkan untuk akun AKTIF.
                                         Pada akun nonaktif, password sementara yang dibuat
                                         tetap tidak bisa dipakai masuk — Admin akan menyerahkan
                                         sandi yang gagal dan mengira sistemnya rusak. Urutannya
                                         yang benar: Aktifkan Kembali dulu, baru reset kalau perlu.
                                         Backendnya TIDAK dipersempit; ini murni penuntun. --}}
                                    @if ($u->isActive())
                                        <span class="text-border-soft mx-1">|</span>
                                        <form method="POST" action="{{ route('manajemen-user.reset-password', $u) }}" class="inline"
                                              onsubmit="return confirm({{ Js::from('Buat password sementara baru untuk akun ini? Password lama langsung tidak berlaku.') }});">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-text-secondary hover:text-text-main hover:underline">Reset Password</button>
                                        </form>
                                    @endif

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
