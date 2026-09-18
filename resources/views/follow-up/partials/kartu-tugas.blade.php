{{--
    SATU KARTU TUGAS — dipakai seluruh bagian di halaman Tugas Saya.

    Dibuat sebagai partial supaya lima bagian di halaman itu tidak menyalin
    susunan yang sama lima kali; memperbaiki satu tombol cukup di sini.

    Yang dijawab kartu ini, berurutan:
      siapa -> kapan terakhir disentuh -> kenapa muncul di daftar -> apa
      yang harus dilakukan sekarang.

    Aksi utamanya SATU dan menonjol: Catat Pendampingan. Dua aksi lain
    sengaja dibuat lebih kecil — kalau ketiganya sama besar, pendamping harus
    memilih dulu sebelum bekerja.

    Variabel: $baris (dari FollowUpService::kelompokkan), $isAdmin.
--}}
@php
    $p = $baris['peserta'];
    $r = $baris['ringkas'];
    $wa = $p->tautanWhatsapp();
@endphp

<div class="border border-border-soft rounded-xl p-4">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <a href="{{ route('participants.show', $p) }}"
               class="text-[15px] font-semibold text-text-main hover:underline">
                {{ $p->nama_peserta }}
            </a>
            <p class="text-sm text-text-secondary">{{ $p->nama_usaha }}</p>

            {{-- Nama pendamping hanya berguna bagi Admin yang memantau banyak
                 orang. Bagi pendamping sendiri, seluruh isi halaman ini memang
                 binaannya — menampilkannya justru menambah bacaan. --}}
            @if ($isAdmin)
                <p class="text-[11px] text-text-secondary mt-0.5">
                    Pendamping: {{ $p->pendamping->nama ?? '—' }}
                </p>
            @endif
        </div>

        <span class="flex-none text-[11px] font-medium px-2 py-0.5 rounded-full {{ $r['warna'] }}">
            {{ $r['status'] }}
        </span>
    </div>

    {{-- KAPAN TERAKHIR DISENTUH --}}
    <div class="mt-3 text-sm">
        @if ($r['terakhir'])
            <p class="text-text-main">
                Terakhir didampingi:
                <span class="font-medium">{{ $r['terakhir']->translatedFormat('d F Y') }}</span>
            </p>
            <p class="text-xs text-text-secondary">
                {{ $r['hari'] }} hari lalu &bull; {{ $r['jenis'] }}
            </p>
        @else
            <p class="text-text-main italic">Belum pernah didampingi.</p>
        @endif

        @if ($r['follow_up'])
            <p class="text-xs text-text-secondary mt-0.5">
                Jadwal tindak lanjut: {{ $r['follow_up']->translatedFormat('d F Y') }}
            </p>
        @endif
    </div>

    {{-- KENAPA MUNCUL DI SINI

         Dua jenis alasan ditampilkan bersama:

           1. soal JADWAL   — dari FollowUpService, sudah lama ada di sini;
           2. soal KEADAAN USAHA — omzet, legalitas, kelengkapan data.

         Yang kedua dulu hanya bisa dibaca di halaman Prioritas Usaha, jadi
         pendamping yang bekerja dari halaman ini tidak pernah tahu kalau
         omzet peserta itu belum masuk. Dua daftar untuk satu pertanyaan
         berarti salah satunya pasti jarang dibuka.

         Keduanya dibaca dari service yang sama, bukan ditulis ulang di
         tampilan, supaya tidak pernah berbeda dari yang dipakai menghitung. --}}
    <ul class="mt-2 space-y-0.5">
        <li class="text-xs text-text-secondary">&bull; {{ $r['alasan'] }}</li>

        @foreach ($baris['keadaan_usaha'] ?? [] as $keadaan)
            <li class="text-xs text-text-secondary">&bull; {{ $keadaan['alasan'] }}</li>
        @endforeach
    </ul>

    {{-- AKSI

         Tombolnya mengikuti keadaan peserta ini, bukan daftar tetap. Kalau
         omzet bulan ini belum masuk, tombol "Update Omzet" muncul di sini —
         sebelumnya alasan itu tertulis di kartu tapi tidak ada jalan
         mengerjakannya, dan pendamping harus mencari sendiri lewat mana.

         Daftarnya datang dari FollowUpService::tindakan(), sumber yang sama
         dengan daftar Prioritas. Tidak ada form baru yang dibuat di sini —
         satu tempat mencatat, satu perilaku. --}}
    @php
        $aksiUtama = collect($baris['tindakan'])->firstWhere('utama', true);
        $aksiLain = collect($baris['tindakan'])->where('utama', false);
    @endphp

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @if ($aksiUtama)
            <a href="{{ route($aksiUtama['rute'], $p) }}{{ $aksiUtama['fragmen'] }}"
               class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                + {{ $aksiUtama['label'] }}
            </a>
        @endif

        @foreach ($aksiLain as $aksi)
            <a href="{{ route($aksi['rute'], $p) }}{{ $aksi['fragmen'] }}"
               class="inline-flex items-center justify-center rounded-lg border border-border-soft px-3.5 py-2.5 text-xs font-medium text-text-main hover:bg-surface-bg transition">
                {{ $aksi['label'] }}
            </a>
        @endforeach

        <a href="{{ route('participants.show', $p) }}"
           class="inline-flex items-center justify-center rounded-lg border border-border-soft px-3.5 py-2.5 text-xs font-medium text-text-main hover:bg-surface-bg transition">
            Lihat Peserta
        </a>

        @if ($wa)
            <a href="{{ $wa }}" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center rounded-lg border border-border-soft px-3.5 py-2.5 text-xs font-medium text-text-main hover:bg-surface-bg transition">
                Hubungi
            </a>
        @else
            {{-- Disebutkan apa adanya. Tautan WhatsApp ke nomor yang tidak sah
                 baru ketahuan rusak saat pendamping menekannya di depan
                 pesertanya. --}}
            <span class="text-[11px] text-text-secondary">Nomor WhatsApp belum tersedia.</span>
        @endif
    </div>
</div>
