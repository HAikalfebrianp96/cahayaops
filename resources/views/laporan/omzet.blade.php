@extends('layouts.app')

@section('title', 'Laporan Omzet')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between no-print">
        <h2 class="text-[20px] font-bold text-text-main">Laporan Omzet</h2>
        <div class="flex gap-2">
            <a href="{{ route('laporan.omzet.export-pdf', request()->query()) }}" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                Export PDF
            </a>
            <a href="{{ route('laporan.omzet.export', request()->query()) }}" class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                Export Excel
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg">
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Omzet (sesuai filter)</p>
            <p class="text-xl font-bold text-cahaya-primary mt-1">{{ rupiah($totalOmzet) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Rata-rata Omzet</p>
            {{-- "—", bukan "Rp0". Rata-rata dari nol laporan tidak ada,
                 dan angka nol di sini terbaca sebagai fakta tentang
                 peserta. Pola yang sama dipakai halaman KPI. --}}
            <p class="text-xl font-bold text-cahaya-primary mt-1">{{ $rataRata !== null ? rupiah($rataRata) : '—' }}</p>
        </div>

        {{-- Profit dipisah dari dua kartu di atas dengan sengaja: angkanya
             berasal dari kumpulan laporan yang berbeda (hanya yang ber-HPP),
             jadi menaruhnya sejajar akan membuat orang mengurangkan dua angka
             yang tidak setara. --}}
        <div class="bg-white rounded-xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Profit Bersih</p>
            @if ($jumlahBerlaba > 0)
                <p class="text-xl font-bold mt-1 {{ $totalProfit < 0 ? 'text-status-danger' : 'text-cahaya-primary' }}">
                    {{ rupiah($totalProfit) }}
                </p>
                <p class="text-[11px] text-text-secondary mt-1">
                    HPP {{ rupiah($totalHpp) }} &bull; dari {{ $jumlahBerlaba }} dari {{ $jumlahLaporan }} laporan ber-HPP
                </p>
            @else
                <p class="text-xl font-bold text-text-secondary mt-1">—</p>
                <p class="text-[11px] text-text-secondary mt-1">belum ada laporan ber-HPP pada filter ini</p>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('laporan.omzet') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-5 gap-3 no-print">
        <select name="bulan" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Bulan</option>
            @foreach ($bulanOptions as $num => $nama)
                <option value="{{ $num }}" @selected((string) ($filters['bulan'] ?? '') === (string) $num)>{{ $nama }}</option>
            @endforeach
        </select>
        <select name="tahun" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Tahun</option>
            @for ($tahun = now()->year - 1; $tahun <= now()->year; $tahun++)
                <option value="{{ $tahun }}" @selected((string) ($filters['tahun'] ?? '') === (string) $tahun)>{{ $tahun }}</option>
            @endfor
        </select>
        <select name="pendamping_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>
        <select name="participant_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Peserta</option>
            @foreach ($participants as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['participant_id'] ?? '') === (string) $p->id)>{{ $p->nama_peserta }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Terapkan Filter</button>
            <a href="{{ route('laporan.omzet') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($reports->isEmpty())
            <p class="text-sm text-text-secondary italic text-center py-10">Tidak ada data.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Peserta</th>
                            <th class="px-4 py-3 font-medium">Usaha</th>
                            <th class="px-4 py-3 font-medium">Periode</th>
                            <th class="px-4 py-3 font-medium">Omzet</th>
                            <th class="px-4 py-3 font-medium">HPP</th>
                            <th class="px-4 py-3 font-medium">Profit Bersih</th>
                            <th class="px-4 py-3 font-medium">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $r)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 font-medium text-text-main">{{ $r->participantTermasukArsip?->nama_peserta ?? '-' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->participantTermasukArsip?->nama_usaha ?? '-' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->periodeLabel() }}</td>
                                <td class="px-4 py-3 font-semibold text-text-main">{{ rupiah($r->omzet) }}</td>
                                {{-- Strip, bukan Rp 0: HPP kosong berarti belum dilaporkan. --}}
                                <td class="px-4 py-3 text-text-secondary">{{ $r->hpp === null ? '—' : rupiah($r->hpp) }}</td>
                                <td class="px-4 py-3 {{ ($r->profitBersih() ?? 0) < 0 ? 'text-status-danger' : 'text-text-main' }}">
                                    {{ $r->profitBersih() === null ? '—' : rupiah($r->profitBersih()) }}
                                </td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->catatan ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="no-print">{{ $reports->links() }}</div>
</div>
@endsection
