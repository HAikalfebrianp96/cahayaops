@extends('layouts.app')

@section('title', 'Arsip Data')

@section('content')
{{-- ============================================================
     ARSIP DATA — satu pintu untuk seluruh data yang di-Soft Delete.

     Tab yang tampil di sini SUDAH disaring controller sesuai kewenangan;
     Blade tidak menebak-nebak role sendiri. Tombol Pulihkan menembak route
     modul masing-masing, jadi policy modulnya yang tetap jadi pengaman
     terakhir — halaman ini tidak punya jalur restore sendiri.
     ============================================================ --}}
<div class="space-y-5">

    <div>
        <h2 class="text-[20px] font-bold text-text-main">Arsip Data</h2>
        <p class="text-sm text-text-secondary mt-0.5">
            {{-- Kalimat lama menjanjikan bahwa data yang dihapus "tidak pernah
                 hilang". Janji itu sudah tidak benar sejak Hapus Permanen ada,
                 dan janji yang salah di halaman arsip adalah jenis kekeliruan
                 yang baru ketahuan setelah ada yang mencarinya. --}}
            Arsip Data berisi data yang tidak aktif tetapi masih diperlukan untuk histori.
            Data yang dihapus permanen tidak dapat dipulihkan.
        </p>
    </div>

    {{-- TAB MODUL --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-2 overflow-x-auto">
        <div class="flex gap-1 min-w-max">
            @foreach ($tabTersedia as $kunci => $judul)
                @php $aktif = $kunci === $tabAktif; @endphp
                <a href="{{ route('arsip.index', ['tab' => $kunci]) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-[13px] font-medium whitespace-nowrap transition
                          {{ $aktif ? 'bg-cahaya-primary text-white' : 'text-text-secondary hover:bg-surface-bg' }}">
                    {{ $judul }}
                    @if (($jumlahPerTab[$kunci] ?? 0) > 0)
                        <span class="text-[11px] px-1.5 py-0.5 rounded-full
                                     {{ $aktif ? 'bg-white/20 text-white' : 'bg-gold-light text-cahaya-accent' }}">
                            {{ $jumlahPerTab[$kunci] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- PENCARIAN --}}
    <form method="GET" action="{{ route('arsip.index') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="tab" value="{{ $tabAktif }}">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari di arsip {{ strtolower($judulTab) }}..."
               class="flex-1 rounded-xl border border-border-soft px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        <div class="flex gap-2">
            <button type="submit"
                    class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Cari
            </button>
            <a href="{{ route('arsip.index', ['tab' => $tabAktif]) }}"
               class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
        </div>
    </form>

    {{-- ISI ARSIP --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($baris->isEmpty())
            <div class="text-center py-14 px-6">
                <p class="text-sm font-medium text-text-main mb-1">Arsip {{ $judulTab }} Kosong</p>
                <p class="text-xs text-text-secondary">
                    Tidak ada data {{ strtolower($judulTab) }}{{ str_ends_with($tabAktif, '-batal') ? '' : ' yang diarsipkan' }}.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-5 py-3 font-medium">Data</th>
                            {{-- Tab pembatalan memakai kata yang berbeda dari tab arsip:
                                 catatan uang tidak "dihapus", ia dibatalkan. --}}
                            <th class="px-5 py-3 font-medium">{{ str_ends_with($tabAktif, '-batal') ? 'Dibatalkan' : 'Diarsipkan' }}</th>
                            <th class="px-5 py-3 font-medium">Oleh</th>
                            <th class="px-5 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $item)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-text-main">{{ $item['judul'] }}</p>
                                    @if ($item['keterangan'])
                                        <p class="text-xs text-text-secondary mt-0.5">{{ $item['keterangan'] }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-text-secondary whitespace-nowrap">
                                    {{ $item['dihapus']?->translatedFormat('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-text-secondary">{{ $item['penghapus'] }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if ($item['rute'])
                                            <form method="POST" action="{{ route($item['rute'], $item['id']) }}"
                                                  onsubmit="return confirm('Pulihkan data ini?\n\nData akan kembali muncul di daftar aktif beserta seluruh relasinya.');">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center rounded-lg px-3.5 py-1.5 text-xs font-medium bg-primary-light text-status-success hover:opacity-80 transition">
                                                    Pulihkan
                                                </button>
                                            </form>
                                        @else
                                            {{-- Entri simpanan yang sudah punya pengganti berlaku.
                                                 Memulihkannya akan menghitung uang yang sama dua kali
                                                 ke saldo anggota. --}}
                                            <span class="text-[11px] text-text-secondary"
                                                  title="Batalkan dulu entri penggantinya kalau entri ini memang perlu dipulihkan.">
                                                Sudah diganti entri lain
                                            </span>
                                        @endif

                                        @if ($bolehHapusPermanen)
                                            @if ($item['tertahan'])
                                                {{-- Data yang punya tanggungan histori TIDAK diberi
                                                     tombol mati.

                                                     Yang ditampilkan ALASANNYA, bukan label buntu
                                                     "Tidak bisa dihapus permanen". Dua sebabnya:

                                                     1. Alasan dari alasanTertahan() selalu berisi
                                                        langkah yang harus dilakukan ("Arsipkan dulu",
                                                        "Batalkan dulu"). Menyembunyikannya di tooltip
                                                        membuang justru bagian yang berguna — dan di
                                                        layar sentuh tooltip tidak pernah muncul.

                                                     2. Sebagian besar penahanan ini BISA dilepas.
                                                        Menulis "Tidak bisa" seolah-olah permanen. --}}
                                                <span class="text-[11px] text-text-secondary max-w-xs inline-block leading-snug">
                                                    {{ $item['tertahan'] }}
                                                </span>
                                            @else
                                                <button type="button"
                                                        onclick="bukaHapusPermanen({{ $item['id'] }})"
                                                        class="inline-flex items-center rounded-lg px-3.5 py-1.5 text-xs font-medium bg-red-50 text-status-danger hover:bg-red-100 transition">
                                                    Hapus Permanen
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            @if ($bolehHapusPermanen && ! $item['tertahan'])
                                {{-- ============================================================
                                     KONFIRMASI HAPUS PERMANEN

                                     Kalimatnya harus DIKETIK ULANG, bukan sekadar diklik.
                                     Pola yang sama dipakai Restore Database — dua tindakan
                                     yang sama-sama tidak bisa dibatalkan sebaiknya terasa
                                     sama beratnya.

                                     Tidak ada satu pun kata "dapat dipulihkan" di sini.
                                     ============================================================ --}}
                                <tr id="hapus-permanen-{{ $item['id'] }}" class="hidden">
                                    <td colspan="4" class="px-5 py-4">
                                        <form method="POST" action="{{ route('arsip.hapus-permanen', ['modul' => $tabAktif, 'id' => $item['id']]) }}"
                                              class="bg-red-50/60 border border-red-200 rounded-xl p-4 space-y-3 max-w-2xl">
                                            @csrf
                                            @method('DELETE')

                                            <p class="text-[13px] font-semibold text-status-danger">
                                                Hapus permanen "{{ $item['judul'] }}"?
                                            </p>
                                            <p class="text-xs text-text-main">
                                                Data yang dihapus permanen TIDAK dapat dipulihkan. Pastikan ini memang
                                                data uji coba atau data yang benar-benar tidak diperlukan lagi.
                                            </p>

                                            <div>
                                                <label class="block text-xs font-medium text-text-main mb-1">
                                                    Ketik <span class="font-mono font-semibold">{{ $kalimatKonfirmasi }}</span> untuk melanjutkan
                                                </label>
                                                <input type="text" name="konfirmasi" required autocomplete="off"
                                                       class="w-full max-w-sm rounded-lg border border-border-soft px-3 py-2 text-sm">
                                            </div>

                                            <div class="flex gap-2">
                                                <button type="submit"
                                                        class="bg-status-danger text-white text-xs font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                                                    Hapus Permanen
                                                </button>
                                                <button type="button" onclick="bukaHapusPermanen({{ $item['id'] }})"
                                                        class="text-xs text-text-secondary px-4 py-2 hover:text-text-main">
                                                    Batal
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>{{ $baris->links() }}</div>
</div>
@if ($errors->any())
    {{-- Galat hapus permanen muncul setelah redirect back, jadi barisnya
         sudah tertutup lagi. Ditampilkan di atas tabel supaya tidak hilang. --}}
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mt-4">
        @foreach ($errors->all() as $pesan)
            <p class="text-xs text-status-danger">{{ $pesan }}</p>
        @endforeach
    </div>
@endif

<script>
    function bukaHapusPermanen(id) {
        const baris = document.getElementById('hapus-permanen-' + id);
        if (baris) {
            baris.classList.toggle('hidden');
        }
    }
</script>

@endsection
