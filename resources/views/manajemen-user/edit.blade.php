@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="space-y-6">

    <div>
        <h2 class="text-[20px] font-bold text-text-main">Edit User</h2>
        <p class="text-sm text-text-secondary mt-0.5">{{ $user->name }} &middot; {{ $user->email }}</p>
    </div>

    @if ($adminAktifTerakhir)
        <div class="bg-gold-light border border-status-warning/40 rounded-2xl px-4 py-3">
            <p class="text-sm text-text-main">
                Akun ini adalah <strong>Admin aktif terakhir</strong>. Role-nya tidak dapat diturunkan
                dan akunnya tidak dapat dinonaktifkan sampai ada Admin aktif lain.
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

    <form method="POST" action="{{ route('manajemen-user.update', $user) }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <h3 class="text-sm font-semibold text-text-main mb-4">Identitas Akun</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('name') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('email') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Role</label>
                    <select name="role" id="role-select" required
                            class="w-full md:w-1/2 rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        @foreach ($roleOptions as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('role', $user->role) === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror

                    @if ($diriSendiri)
                        <p class="text-xs text-text-secondary mt-2">
                            Ini akun Anda sendiri — role-nya tidak dapat Anda ubah. Minta Admin lain yang melakukannya.
                        </p>
                    @endif

                    {{-- Peringatan pemutusan tautan Pendamping. Ditampilkan hanya
                         kalau akun ini memang Pendamping yang punya tautan data. --}}
                    @if ($user->role === \App\Models\User::ROLE_PENDAMPING && $user->pendamping_id)
                        <div id="peringatan-pendamping" class="hidden mt-3 bg-gold-light border border-status-warning/40 rounded-xl px-4 py-3">
                            <p class="text-sm text-text-main">
                                Perubahan role akan menghapus hubungan user dengan data Pendamping.
                                Akun ini tidak lagi terhubung sebagai Pendamping.
                            </p>
                            <p class="text-xs text-text-secondary mt-1">
                                Data Pendamping beserta peserta binaannya tetap tersimpan — yang dilepas hanya tautan akun ini.
                                Status kepegawaian di halaman Pendamping tidak ikut berubah.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="border-t border-border-soft pt-6">
            <h3 class="text-sm font-semibold text-text-main mb-1">Status &amp; Password</h3>
            <p class="text-xs text-text-secondary mb-3">
                Status akun dan reset password diubah lewat tombol tersendiri di halaman daftar —
                keduanya tindakan terpisah supaya tidak ikut tersimpan tanpa sengaja saat mengedit data.
            </p>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                    {{ $user->isActive() ? 'bg-primary-light text-status-success' : 'bg-red-50 text-status-danger' }}">
                    {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                </span>
                <span class="text-text-secondary">
                    Terakhir login:
                    {{ $user->last_login_at ? $user->last_login_at->format('d-m-Y H:i') : 'Belum pernah' }}
                </span>
            </div>
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan Perubahan
            </button>
            <a href="{{ route('manajemen-user.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>

@if ($user->role === \App\Models\User::ROLE_PENDAMPING && $user->pendamping_id)
<script>
    // Peringatan muncul begitu role digeser dari Pendamping ke role lain,
    // dan hilang lagi kalau dikembalikan.
    (function () {
        const pilih = document.getElementById('role-select');
        const kotak = document.getElementById('peringatan-pendamping');
        if (!pilih || !kotak) return;

        const perbarui = () => kotak.classList.toggle('hidden', pilih.value === @js(\App\Models\User::ROLE_PENDAMPING));
        pilih.addEventListener('change', perbarui);
        perbarui();
    })();
</script>
@endif
@endsection
