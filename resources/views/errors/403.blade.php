<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tidak Punya Akses — CahayaOS</title>
    @vite(['resources/css/app.css'])
</head>

{{--
    HALAMAN "TIDAK PUNYA AKSES".

    =====================================================================
    KEADAAN YANG DITEMUKAN
    =====================================================================

    Sebelum berkas ini ada, CahayaOS memakai halaman bawaan Laravel:

        403 | THIS ACTION IS UNAUTHORIZED.

    Berbahasa Inggris, di aplikasi yang seluruh isinya berbahasa Indonesia,
    tanpa satu kata pun tentang APA yang terjadi dan APA yang harus
    dilakukan — bahkan tanpa jalan kembali. Bagi pengurus koperasi yang
    bukan orang teknis, kalimat itu terbaca sebagai "sistemnya rusak",
    padahal sistemnya justru sedang bekerja persis sebagaimana mestinya:
    ia sedang menolak sesuatu yang memang tidak boleh.

    Yang diperbaiki di sini HANYA cara penolakan itu disampaikan. Aturan
    siapa boleh apa tidak disentuh sama sekali — penjaganya tetap Policy
    dan Gate di backend.
--}}

<body class="bg-surface-bg text-text-main">
    <div class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg bg-white border border-border-soft rounded-2xl shadow-sm p-8">

            <div class="w-12 h-12 rounded-xl bg-status-danger/10 flex items-center justify-center mb-5">
                <svg class="w-6 h-6 text-status-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z" />
                </svg>
            </div>

            <h1 class="text-xl font-bold text-text-main mb-2">Halaman ini bukan untuk akun Anda</h1>

            <p class="text-sm text-text-secondary leading-relaxed mb-4">
                Bukan kesalahan sistem dan bukan data yang hilang. Akun Anda memang tidak diberi
                kewenangan untuk membuka halaman ini, dan CahayaOS menahannya sebelum apa pun
                terjadi pada datanya.
            </p>

            @php
                // Pesan bawaan Laravel ("This action is unauthorized.") tidak
                // ditampilkan — ia berbahasa Inggris dan tidak menerangkan apa
                // pun. Yang ditampilkan hanya pesan yang memang SENGAJA ditulis
                // pemanggil abort(), misalnya alasan penolakan yang menyebut
                // data apa yang menahannya.
                $pesanKhusus = trim((string) ($exception?->getMessage() ?? ''));
                $bawaan = ['This action is unauthorized.', 'Forbidden', 'User does not have the right roles.'];
                $tampilkanPesan = $pesanKhusus !== '' && ! in_array($pesanKhusus, $bawaan, true);
            @endphp

            @if ($tampilkanPesan)
                <div class="bg-gold-light border border-status-warning/30 rounded-xl px-4 py-3 mb-4">
                    <p class="text-xs text-text-main">{{ $pesanKhusus }}</p>
                </div>
            @endif

            @auth
                <div class="border-t border-border-soft pt-4 mb-5">
                    <p class="text-xs text-text-secondary">
                        Anda masuk sebagai
                        <span class="font-medium text-text-main">{{ auth()->user()->name }}</span>
                        &mdash; {{ \App\Models\User::ROLE_OPTIONS[auth()->user()->role] ?? auth()->user()->role }}.
                    </p>
                    <p class="text-xs text-text-secondary mt-1">
                        Kalau Anda memang seharusnya bisa membuka halaman ini, mintalah Admin
                        menyesuaikan hak akses akun Anda. Jangan memakai akun orang lain.
                    </p>
                </div>

                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                    Kembali ke Dashboard
                </a>
            @else
                <div class="border-t border-border-soft pt-4 mb-5">
                    <p class="text-xs text-text-secondary">
                        Sesi Anda mungkin sudah berakhir. Masuk kembali, lalu coba lagi.
                    </p>
                </div>

                <a href="{{ route('login') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                    Masuk
                </a>
            @endauth
        </div>
    </div>
</body>
</html>
