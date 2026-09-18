{{--
    ALUR PENYUSUNAN LAPORAN DAMPAK — sebelas langkah berurutan.

    ==========================================================================
    KENAPA URUTANNYA DITAMPILKAN, BUKAN CUMA KARTU-KARTUNYA
    ==========================================================================

    Menyusun laporan dampak punya urutan yang tidak bisa dibalik: outcome tidak
    berarti tanpa proxy, proxy tidak berarti tanpa perubahan terukur, dan
    perubahan tidak ada tanpa baseline. Halaman yang menampilkan kartu-kartu
    sejajar membiarkan Admin mengisi dari mana saja, lalu menemukan urutannya
    salah setelah pekerjaan menumpuk.

    Nomor di sini SAH sebagai perangkat: isinya memang sebuah urutan kerja,
    bukan daftar yang kebetulan dinomori.

    Statusnya DITURUNKAN dari data, bukan disimpan — langkah yang datanya
    dihapus otomatis kembali menjadi "belum".
--}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
    @php
        $selesai = collect($langkah)->where('selesai', true)->count();
        $total = count($langkah);
    @endphp

    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
        <h3 class="text-sm font-semibold text-text-main">Alur Penyusunan</h3>
        {{-- Frasanya ditulis sebagai SATU teks utuh, bukan angka yang
             dibungkus span di tengah kalimat. Teks yang terpecah tag tidak
             terbaca sebagai satu kalimat oleh pembaca layar, dan tidak bisa
             dicari sebagai satu frasa. --}}
        <p class="text-xs font-medium text-text-main">{{ $selesai }} dari {{ $total }} langkah selesai</p>
    </div>

    <div class="h-1.5 rounded-full bg-surface-bg overflow-hidden mb-4">
        <div class="h-full bg-cahaya-primary rounded-full transition-all"
             style="width: {{ $total > 0 ? round($selesai / $total * 100) : 0 }}%"></div>
    </div>

    <ol class="space-y-1">
        @foreach ($langkah as $i => $l)
            <li class="flex items-start gap-3 py-2 {{ ! $loop->last ? 'border-b border-border-soft' : '' }}">
                {{-- Penanda keadaan: angka untuk yang belum, centang untuk
                     yang selesai. Bentuknya berbeda, bukan hanya warnanya —
                     supaya terbaca juga tanpa membedakan warna. --}}
                <span class="flex-none mt-0.5 h-5 w-5 rounded-full flex items-center justify-center text-[10px] font-semibold
                             {{ $l['selesai']
                                ? 'bg-status-success/15 text-status-success'
                                : 'bg-surface-bg text-text-secondary' }}">
                    {{ $l['selesai'] ? '✓' : $i + 1 }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-medium text-text-main">{{ $l['judul'] }}</p>
                    <p class="text-[11px] text-text-secondary mt-0.5">{{ $l['ket'] }}</p>
                </div>

                @if ($l['tautan'] && $l['aksi'] && $bisaKelola)
                    <a href="{{ $l['tautan'] }}"
                       class="flex-none text-[11px] font-medium text-cahaya-secondary hover:underline mt-0.5">
                        {{ $l['aksi'] }} &rarr;
                    </a>
                @endif
            </li>
        @endforeach
    </ol>
</div>
