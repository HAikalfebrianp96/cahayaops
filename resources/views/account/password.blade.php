@extends('layouts.app')

@section('title', 'Ganti Password')

@section('content')
<div class="max-w-md">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Ganti Password Saya</h2>

    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6">
        <p class="text-sm text-gray-500 mb-5">
            Masuk sebagai <span class="font-medium text-gray-700">{{ auth()->user()->name }}</span>
            ({{ auth()->user()->email }})
        </p>

        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password Saat Ini</label>
                <input type="password" name="current_password" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('current_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                <input type="password" name="password" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-400 mt-1">Minimal 8 karakter.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru</label>
                <input type="password" name="password_confirmation" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
            </div>

            <button type="submit"
                    class="w-full bg-cahaya-primary text-white font-medium py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
                Simpan Password Baru
            </button>
        </form>
    </div>
</div>
@endsection
