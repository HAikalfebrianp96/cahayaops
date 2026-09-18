{{--
    TUGAS SAYA — tampilan khusus ROLE PENDAMPING.

    ==========================================================================
    KENAPA BERBEDA DARI TAMPILAN ADMIN
    ==========================================================================

    Admin MEMANTAU pekerjaan banyak orang, jadi tabel lima kolom yang padat
    memang bentuk yang tepat baginya — dan tampilan itu tidak diubah.

    Pendamping MENGERJAKAN, sering sambil berdiri di depan warung binaannya
    dengan satu tangan memegang ponsel. Yang ia butuhkan bukan tabel yang bisa
    disaring, melainkan urutan: siapa dulu, lalu apa yang harus dilakukan.

    Karena itu halaman ini disusun sebagai antrean kerja, dari yang paling
    mendesak ke yang paling tenang, dan ditutup dengan satu baris yang
    menyatakan sisanya memang sudah beres.

    Datanya SAMA PERSIS dengan tampilan Admin — keduanya memakai
    FollowUpService::kelompokkan(). Yang berbeda hanya susunannya.
--}}

{{-- ====================================================================
     RINGKASAN — menjawab "berapa banyak?" sebelum "siapa saja?"
     ==================================================================== --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    @php
        $perluSegera = $jumlah['terlambat'] + $jumlah['belum_pernah'];
    @endphp

    @foreach ([
        ['label' => 'Perlu Segera', 'nilai' => $perluSegera, 'tanda' => '🔴',
         'warna' => $perluSegera > 0 ? 'text-status-danger' : 'text-text-main'],
        ['label' => 'Perlu Ditindaklanjuti', 'nilai' => $jumlah['perlu_perhatian'], 'tanda' => '🟠',
         'warna' => $jumlah['perlu_perhatian'] > 0 ? 'text-status-warning' : 'text-text-main'],
        ['label' => 'Jadwal Hari Ini', 'nilai' => $jumlah['hari_ini'], 'tanda' => '📅',
         'warna' => $jumlah['hari_ini'] > 0 ? 'text-status-info' : 'text-text-main'],
        ['label' => 'Kondisi Normal', 'nilai' => $jumlah['normal'], 'tanda' => '🟢',
         'warna' => 'text-status-success'],
    ] as $kartu)
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1 leading-tight">
                <span aria-hidden="true">{{ $kartu['tanda'] }}</span> {{ $kartu['label'] }}
            </p>
            <p class="text-2xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
        </div>
    @endforeach
</div>

{{-- ====================================================================
     🔴 PERLU SEGERA

     Dua keranjang digabung di sini — yang jadwalnya sudah lewat DAN yang
     belum pernah didampingi sama sekali. Keduanya sama mendesaknya bagi
     pendamping, dan memisahkannya hanya memaksa ia membaca dua daftar
     untuk menjawab satu pertanyaan yang sama.

     Bedanya tetap terbaca: kalimat "Alasan" di tiap kartu menyebutkannya.
     ==================================================================== --}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
    <h3 class="text-[15px] font-semibold text-text-main">
        <span aria-hidden="true">🔴</span> Perlu Segera
        <span class="text-text-secondary font-normal">({{ $perluSegera }})</span>
    </h3>
    <p class="text-xs text-text-secondary mt-0.5 mb-4">
        Sudah melewati jadwal tindak lanjut, melewati {{ $ambang['merah'] }} hari tanpa pendampingan,
        atau belum pernah didampingi sama sekali.
    </p>

    @if ($perluSegera === 0)
        <p class="text-sm text-text-secondary italic">
            Tidak ada yang mendesak. Kerja bagus.
        </p>
    @else
        <div class="space-y-3">
            @foreach ($kelompok['terlambat'] as $baris)
                @include('follow-up.partials.kartu-tugas', ['baris' => $baris, 'isAdmin' => $isAdmin])
            @endforeach
            @foreach ($kelompok['belum_pernah'] as $baris)
                @include('follow-up.partials.kartu-tugas', ['baris' => $baris, 'isAdmin' => $isAdmin])
            @endforeach
        </div>
    @endif
</div>

{{-- ====================================================================
     🟠 PERLU DITINDAKLANJUTI

     Peserta yang sudah melewati ambang kuning tapi belum punya jadwal.
     Sebelum ada keranjang ini, mereka tidak muncul di halaman mana pun dan
     baru terlihat setelah cukup lama terbengkalai untuk menjadi merah.
     ==================================================================== --}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
    <h3 class="text-[15px] font-semibold text-text-main">
        <span aria-hidden="true">🟠</span> Perlu Ditindaklanjuti
        <span class="text-text-secondary font-normal">({{ $jumlah['perlu_perhatian'] }})</span>
    </h3>
    <p class="text-xs text-text-secondary mt-0.5 mb-4">
        Sudah lebih dari {{ $ambang['kuning'] }} hari tanpa pendampingan, belum sampai terlambat.
    </p>

    @if ($kelompok['perlu_perhatian']->isEmpty())
        <p class="text-sm text-text-secondary italic">Tidak ada.</p>
    @else
        <div class="space-y-3">
            @foreach ($kelompok['perlu_perhatian'] as $baris)
                @include('follow-up.partials.kartu-tugas', ['baris' => $baris, 'isAdmin' => $isAdmin])
            @endforeach
        </div>
    @endif
</div>

{{-- ====================================================================
     📅 JADWAL HARI INI
     ==================================================================== --}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
    <h3 class="text-[15px] font-semibold text-text-main">
        <span aria-hidden="true">📅</span> Jadwal Hari Ini
        <span class="text-text-secondary font-normal">({{ $jumlah['hari_ini'] }})</span>
    </h3>
    <p class="text-xs text-text-secondary mt-0.5 mb-4">
        Tindak lanjut yang Anda jadwalkan sendiri untuk hari ini.
    </p>

    @if ($kelompok['hari_ini']->isEmpty())
        <p class="text-sm text-text-secondary italic">Tidak ada jadwal untuk hari ini.</p>
    @else
        <div class="space-y-3">
            @foreach ($kelompok['hari_ini'] as $baris)
                @include('follow-up.partials.kartu-tugas', ['baris' => $baris, 'isAdmin' => $isAdmin])
            @endforeach
        </div>
    @endif
</div>

{{-- ====================================================================
     AKAN DATANG — tertutup.

     Ini bukan pekerjaan hari ini. Cukup diketahui keberadaannya; dibuka
     kalau pendamping ingin merencanakan ke depan.
     ==================================================================== --}}
@if ($kelompok['akan_datang']->isNotEmpty())
    <details class="group bg-white border border-border-soft rounded-2xl shadow-sm">
        <summary class="flex items-center justify-between gap-3 p-6 cursor-pointer list-none">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Sudah Dijadwalkan</h3>
                <p class="text-xs text-text-secondary mt-0.5">
                    {{ $jumlah['akan_datang'] }} tindak lanjut untuk hari-hari berikutnya.
                </p>
            </div>
            <span class="flex-none text-xs font-medium text-status-info whitespace-nowrap">
                <span class="group-open:hidden">Lihat &rarr;</span>
                <span class="hidden group-open:inline">Tutup</span>
            </span>
        </summary>

        <div class="px-6 pb-6 space-y-3">
            @foreach ($kelompok['akan_datang'] as $baris)
                @include('follow-up.partials.kartu-tugas', ['baris' => $baris, 'isAdmin' => $isAdmin])
            @endforeach
        </div>
    </details>
@endif

{{-- ====================================================================
     🟢 NORMAL — hanya dihitung.

     Menampilkan kelima belas kartu di sini akan mengubur empat yang
     benar-benar perlu dikerjakan. Yang penting diketahui pendamping cuma
     satu: sisanya memang sudah beres, bukan terlewat dari daftar.
     ==================================================================== --}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-[15px] font-semibold text-text-main">
                <span aria-hidden="true">🟢</span> Kondisi Normal
            </h3>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $jumlah['normal'] }} binaan tidak memerlukan tindakan saat ini.
            </p>
        </div>

        <a href="{{ route('binaan.index') }}"
           class="inline-flex items-center justify-center rounded-lg border border-border-soft px-4 py-2.5 text-sm font-medium text-text-main hover:bg-surface-bg transition">
            Lihat Peserta Saya
        </a>
    </div>
</div>
