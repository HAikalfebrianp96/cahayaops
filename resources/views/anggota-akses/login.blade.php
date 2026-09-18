<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-cahayaos.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>Cek Simpanan Anggota — Koperasi Cahaya</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-koperasi-primary flex items-center justify-center px-4">
    <div class="bg-white w-full max-w-sm rounded-xl shadow-lg p-8">
        <div class="text-center mb-6">
            <h1 class="text-lg font-bold text-koperasi-primary">Koperasi Cahaya</h1>
            <p class="text-xs text-gray-500">Koperasi Usaha Bersama</p>
            <p class="text-[11px] text-koperasi-accent font-medium mt-0.5">Dari Kita &bull; Untuk Kita &bull; Oleh Kita</p>
            <p class="text-sm text-gray-600 mt-4 font-medium">Cek Simpanan Anggota</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('anggota-akses.login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Anggota</label>
                <input type="text" name="nomor_anggota" value="{{ old('nomor_anggota') }}" required autofocus
                       placeholder="Contoh: KOP-2026-0001"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-koperasi-accent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PIN</label>
                <input type="password" name="pin" inputmode="numeric" maxlength="6" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-koperasi-accent">
            </div>
            <button type="submit"
                    class="w-full bg-koperasi-primary text-white font-medium py-2.5 rounded-md hover:opacity-90 transition">
                Lihat Simpanan Saya
            </button>
        </form>

        <p class="text-xs text-gray-400 text-center mt-6">
            Belum punya Nomor Anggota atau PIN? Hubungi pengurus Koperasi Cahaya.
        </p>
    </div>
</body>
</html>
