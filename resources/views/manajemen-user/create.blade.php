@extends('layouts.app')

@section('title', 'Tambah User')

@section('content')
<div class="space-y-6">

    <div>
        <h2 class="text-[20px] font-bold text-text-main">Tambah User</h2>
        <p class="text-sm text-text-secondary mt-0.5">Buat akun internal baru untuk mengakses CahayaOS.</p>
    </div>

    <form method="POST" action="{{ route('manajemen-user.store') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 space-y-6">
        @csrf

        <div>
            <h3 class="text-sm font-semibold text-text-main mb-4">Identitas Akun</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nama</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('name') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    <p class="text-xs text-text-secondary mt-1">Dipakai untuk login. Harus belum terdaftar.</p>
                    @error('email') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Role</label>
                    <select name="role" required
                            class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        @foreach ($roleOptions as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('role') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Status</label>
                    <select name="is_active" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        <option value="1" @selected(old('is_active', '1') === '1')>Aktif</option>
                        <option value="0" @selected(old('is_active') === '0')>Nonaktif</option>
                    </select>
                    <p class="text-xs text-text-secondary mt-1">Akun nonaktif tidak bisa login sampai diaktifkan.</p>
                </div>
            </div>
        </div>

        <div class="border-t border-border-soft pt-6">
            <h3 class="text-sm font-semibold text-text-main mb-1">Password Awal</h3>
            <p class="text-xs text-text-secondary mb-4">
                Minimal 8 karakter. Sampaikan ke pemilik akun dan minta segera diganti lewat menu Ganti Password.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Password</label>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                    @error('password') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Konfirmasi Password</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                </div>
            </div>
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan Akun
            </button>
            <a href="{{ route('manajemen-user.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
