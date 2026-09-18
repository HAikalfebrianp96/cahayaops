@extends('layouts.app')

@section('title', 'Monitoring Omzet')

@section('content')
<div class="space-y-4">

    <h2 class="text-lg font-semibold text-gray-800">Monitoring Omzet Cahaya Preneur</h2>

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Total Omzet Periode</p>
            <p class="text-xl font-bold text-cahaya-primary mt-1">{{ rupiah($summary['total_omzet']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Jumlah Peserta Melapor</p>
            <p class="text-xl font-bold text-cahaya-primary mt-1">{{ $summary['jumlah_lapor'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Rata-rata Omzet</p>
            {{-- "—", bukan "Rp0". Rata-rata dari nol laporan tidak ada,
                 dan angka nol di sini terbaca sebagai fakta tentang
                 peserta. Pola yang sama dipakai halaman KPI. --}}
            <p class="text-xl font-bold text-cahaya-primary mt-1">{{ $summary['rata_rata'] !== null ? rupiah($summary['rata_rata']) : '—' }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Peserta Omzet Tertinggi</p>
            @if ($summary['tertinggi'])
                <p class="text-base font-bold text-cahaya-primary mt-1">{{ $summary['tertinggi']->participant->nama_peserta ?? '-' }}</p>
                <p class="text-xs text-gray-500">{{ rupiah($summary['tertinggi']->omzet) }}</p>
            @else
                <p class="text-base font-bold text-gray-400 mt-1">—</p>
            @endif
        </div>
    </div>

    {{--
        PROFIT — kartu terpisah, SENGAJA tidak digabung ke baris ringkasan di
        atas. Angkanya berasal dari kumpulan laporan yang berbeda (hanya yang
        HPP-nya dilaporkan), jadi menaruhnya sejajar dengan Total Omzet akan
        membuat orang mengurangkan dua angka yang tidak setara.
    --}}
    @if ($summary['jumlah_berlaba'] > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <p class="text-sm font-semibold text-gray-700">Profit Usaha Binaan</p>
                <p class="text-xs text-gray-500">
                    Dari {{ $summary['jumlah_berlaba'] }} dari {{ $summary['jumlah_lapor'] }} laporan yang sudah memuat HPP
                </p>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
                <div>
                    <p class="text-gray-500 text-xs">Omzet</p>
                    <p class="font-semibold text-gray-800">{{ rupiah($summary['omzet_berlaba']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">HPP</p>
                    <p class="font-semibold text-gray-800">{{ rupiah($summary['total_hpp']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Laba Kotor</p>
                    <p class="font-semibold text-gray-800">{{ rupiah($summary['total_laba_kotor']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Biaya Operasional</p>
                    <p class="font-semibold text-gray-800">{{ rupiah($summary['total_biaya_operasional']) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Profit Bersih</p>
                    <p class="font-bold {{ $summary['total_profit'] < 0 ? 'text-status-danger' : 'text-cahaya-primary' }}">
                        {{ rupiah($summary['total_profit']) }}
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-dashed border-gray-200 p-5">
            <p class="text-sm font-semibold text-gray-700 mb-1">Profit Usaha Binaan</p>
            <p class="text-xs text-gray-500">
                Belum ada laporan pada periode ini yang memuat HPP, jadi profit belum bisa dihitung.
                Lengkapi HPP pada laporan omzet agar angka laba muncul di sini.
            </p>
        </div>
    @endif

    {{-- FILTER --}}
    <form method="GET" action="{{ route('omzet.index') }}"
          class="bg-white border border-gray-100 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari nama peserta / usaha..."
               class="md:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">

        <select name="bulan" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Bulan</option>
            @foreach ($bulanOptions as $num => $nama)
                <option value="{{ $num }}" @selected((string) ($filters['bulan'] ?? '') === (string) $num)>{{ $nama }}</option>
            @endforeach
        </select>

        <select name="tahun" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Tahun</option>
            @for ($tahun = now()->year - 1; $tahun <= now()->year; $tahun++)
                <option value="{{ $tahun }}" @selected((string) ($filters['tahun'] ?? '') === (string) $tahun)>{{ $tahun }}</option>
            @endfor
        </select>

        <select name="pendamping_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>

        <div class="md:col-span-5 flex gap-2">
            <button type="submit"
                    class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-secondary/90 transition">
                Cari / Filter
            </button>
            <a href="{{ route('omzet.index') }}" class="text-sm text-gray-500 px-4 py-2 hover:text-gray-700">Reset</a>
        </div>
    </form>

    {{-- TABLE --}}
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="px-4 py-3">No</th>
                    <th class="px-4 py-3">Peserta</th>
                    <th class="px-4 py-3">Nama Usaha</th>
                    <th class="px-4 py-3">Pendamping</th>
                    <th class="px-4 py-3">Periode</th>
                    <th class="px-4 py-3">Omzet</th>
                    <th class="px-4 py-3">Tanggal Laporan</th>
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $index => $report)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3">{{ $reports->firstItem() + $index }}</td>
                        <td class="px-4 py-3">{{ $report->participant->nama_peserta ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $report->participant->nama_usaha ?? '-' }}</td>
                        <td class="px-4 py-3">{{ $report->participant->pendamping->nama ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{ $report->periodeLabel() }}
                            {{-- Lencana mode hanya muncul untuk harian & mingguan.
                                 Bulanan adalah mode bawaan dan sudah terbaca dari
                                 labelnya sendiri; melencanai semuanya hanya membuat
                                 kolomnya ramai tanpa menambah informasi. --}}
                            @unless ($report->bulanan())
                                <span class="block text-[11px] text-text-secondary">{{ $report->jenis_periode }}</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3">{{ rupiah($report->omzet) }}</td>
                        <td class="px-4 py-3">{{ $report->submitted_at?->format('d-m-Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                            <a href="{{ route('omzet.show', $report) }}" class="text-cahaya-secondary hover:underline">Lihat</a>
                            {{-- Edit & Hapus hanya untuk yang benar-benar berhak
                                 (OmzetReportPolicy). Pimpinan boleh MELIHAT
                                 halaman ini tapi read-only — kalau tombolnya
                                 tetap dirender, tautannya cuma berujung 403. --}}
                            @can('update', $report)
                                <a href="{{ route('omzet.edit', $report) }}" class="text-cahaya-primary hover:underline">Edit</a>
                            @endcan
                            @can('delete', $report)
                                <form method="POST" action="{{ route('omzet.destroy', $report) }}" class="inline"
                                      onsubmit="return confirm('Arsipkan laporan omzet ini? Periodenya bisa diisi ulang nanti.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Arsipkan</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400 italic">
                            Belum ada laporan omzet yang masuk.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $reports->links() }}</div>
</div>
@endsection
