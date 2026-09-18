@extends('layouts.app')

@section('title', 'Laporan Perkembangan Usaha')

@section('content')
@php
    /** Tampilkan selisih dengan tanda, atau strip kalau belum bisa dibandingkan. */
    $selisih = function (?array $p, bool $rupiah = true) {
        if ($p === null) {
            return ['teks' => '—', 'kelas' => 'text-text-secondary'];
        }

        $angka = $rupiah
            ? rupiah($p['selisih'])
            : number_format($p['selisih'], 0, ',', '.');

        return [
            'teks' => ($p['selisih'] > 0 ? '+' : '') . $angka,
            'kelas' => $p['membaik'] === true
                ? 'text-status-success'
                : ($p['membaik'] === false ? 'text-status-danger' : 'text-text-main'),
        ];
    };
@endphp

<div class="space-y-4">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Perkembangan Usaha</h2>
            <p class="text-xs text-text-secondary mt-0.5">Perubahan kondisi usaha peserta, baseline &rarr; endline.</p>
        </div>
        <a href="{{ route('laporan.perkembangan.export', request()->query()) }}"
           class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
            Export Excel
        </a>
    </div>

    {{--
        CAKUPAN — ditaruh paling atas, sebelum angka apa pun.

        Laporan ini satu-satunya yang isinya SELISIH, dan selisih paling mudah
        disalahbaca: total kenaikan omzet dari 3 peserta terlihat persis sama
        meyakinkannya dengan dari 300. Jadi pembaginya dinyatakan lebih dulu.
    --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <p class="text-sm font-semibold text-text-main mb-1">Cakupan Pengukuran</p>
        <p class="text-xs text-text-secondary">
            <span class="font-medium text-text-main">{{ $rekap['jumlah_siap_dibandingkan'] }}</span>
            dari {{ $rekap['jumlah_peserta'] }} peserta punya baseline sekaligus pembanding
            (endline atau monitoring). Seluruh angka perubahan di bawah berasal dari peserta tersebut —
            peserta lain belum bisa dinyatakan berubah atau tidak.
        </p>

        @if (! empty($rekap['indikator']))
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                @foreach (array_slice($rekap['indikator'], 0, 4) as $ind)
                    <div class="rounded-xl bg-surface-bg p-3">
                        <p class="text-[11px] text-text-secondary">{{ $ind['label'] }}</p>
                        <p class="text-sm font-semibold text-text-main mt-0.5">
                            {{ $ind['format'] === 'rupiah'
                                ? rupiah((int) $ind['total_selisih'])
                                : number_format((int) $ind['total_selisih'], 0, ',', '.') . ' ' . $ind['satuan'] }}
                        </p>
                        <p class="text-[11px] text-text-secondary mt-0.5">
                            {{ $ind['jumlah_membaik'] }} dari {{ $ind['jumlah_peserta_terukur'] }} peserta membaik
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- FILTER --}}
    <form method="GET" action="{{ route('laporan.perkembangan') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <select name="pendamping_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected(($filters['pendamping_id'] ?? null) == $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            @foreach (\App\Models\Participant::statusYangSah() as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? null) === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Terapkan</button>
            <a href="{{ route('laporan.perkembangan') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
        </div>
    </form>

    {{-- TABEL --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[760px]">
                <thead>
                    <tr class="text-left text-xs text-text-secondary bg-surface-bg border-b border-border-soft">
                        <th class="px-4 py-3 font-medium">Peserta</th>
                        <th class="px-4 py-3 font-medium">Pendamping</th>
                        <th class="px-4 py-3 font-medium text-right">&Delta; Omzet/bln</th>
                        <th class="px-4 py-3 font-medium text-right">&Delta; Profit/bln</th>
                        <th class="px-4 py-3 font-medium text-right">&Delta; Tenaga Kerja</th>
                        <th class="px-4 py-3 font-medium">Legalitas</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-soft">
                    @forelse ($baris as $b)
                        @php
                            $omzet = $selisih($b['omzet']);
                            $profit = $selisih($b['profit']);
                            $tk = $selisih($b['tenaga_kerja'], false);
                        @endphp
                        <tr class="{{ $b['siap'] ? '' : 'bg-surface-bg/50' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('perkembangan.index', $b['peserta']) }}" class="text-text-main hover:text-cahaya-secondary">
                                    {{ $b['nama'] }}
                                </a>
                                <span class="block text-[11px] text-text-secondary">{{ $b['usaha'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-text-secondary">{{ $b['pendamping'] }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $omzet['kelas'] }}">{{ $omzet['teks'] }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $profit['kelas'] }}">{{ $profit['teks'] }}</td>
                            <td class="px-4 py-3 text-right {{ $tk['kelas'] }}">{{ $tk['teks'] }}</td>
                            <td class="px-4 py-3 text-text-secondary">
                                @if ($b['legalitas'] === null)
                                    —
                                @elseif ($b['legalitas']['membaik'])
                                    <span class="text-status-success">Tercapai</span>
                                @else
                                    {{ $b['legalitas']['sesudah'] ? 'Sudah' : 'Belum' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($b['siap'])
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-status-success/10 text-status-success">
                                        Terukur ({{ $b['sumber_akhir'] }})
                                    </span>
                                @else
                                    {{-- Peserta tanpa baseline SENGAJA tetap muncul.
                                         Menyembunyikannya akan membuat laporan ini hanya
                                         memuat peserta yang datanya bagus. --}}
                                    <span class="text-[11px] text-text-secondary">Belum bisa dibandingkan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-text-secondary">
                                Tidak ada peserta yang cocok dengan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
