@extends('layouts.app')

@section('title', 'Buku Mutasi — ' . $akun->nama)

@section('content')
@php($m = $mutasi)
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('koperasi.kas-bank.index') }}" class="text-xs text-gray-500 hover:underline">&larr; Kas & Bank</a>
            <h2 class="text-lg font-semibold text-gray-800">{{ $akun->nama }}</h2>
            <p class="text-sm text-gray-500">
                {{ $akun->jenis }}{{ $akun->nomor_rekening ? ' · ' . $akun->nomor_rekening : '' }}{{ $akun->atas_nama ? ' a.n. ' . $akun->atas_nama : '' }}
                @unless ($akun->isAktif()) · <span class="text-gray-400">Tidak Aktif</span> @endunless
            </p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-500">Saldo sekarang</p>
            <p class="text-2xl font-bold {{ $saldoKini < 0 ? 'text-red-600' : 'text-gray-800' }}">{{ rupiah($saldoKini) }}</p>
            <p class="text-xs text-gray-500">
                @if ($akun->saldoAwalDitetapkan())
                    Saldo awal {{ rupiah($akun->saldo_awal) }} per {{ $akun->tanggal_saldo_awal->format('d-m-Y') }}
                @else
                    <span class="text-amber-700 font-semibold">Saldo awal belum ditetapkan</span>
                @endif
            </p>
        </div>
    </div>

    {{-- FILTER PERIODE --}}
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Dari</label>
            <input type="date" name="dari" value="{{ $m['dari'] }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Sampai</label>
            <input type="date" name="sampai" value="{{ $m['sampai'] }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Tampilkan</button>
        <a href="{{ route('koperasi.kas-bank.excel', ['akun' => $akun, 'dari' => $m['dari'], 'sampai' => $m['sampai']]) }}"
           class="text-sm text-koperasi-primary hover:underline py-2">Unduh Excel</a>
    </form>

    @if ($m['dari_efektif'] !== $m['dari'])
        <p class="text-xs text-amber-700">
            Catatan sebelum {{ \Carbon\Carbon::parse($m['dari_efektif'])->format('d-m-Y') }} (tanggal saldo awal) tidak ditampilkan —
            uangnya sudah termasuk di saldo awal.
        </p>
    @endif

    {{-- BUKU MUTASI --}}
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[720px]">
                <thead>
                    <tr class="text-left text-gray-500 border-b bg-gray-50">
                        <th class="py-2 px-4">Tanggal</th>
                        <th class="py-2 px-4">Uraian</th>
                        <th class="py-2 px-4 text-right">Masuk</th>
                        <th class="py-2 px-4 text-right">Keluar</th>
                        <th class="py-2 px-4 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b bg-gray-50/50 text-gray-600">
                        <td class="py-2 px-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($m['dari_efektif'])->format('d-m-Y') }}</td>
                        <td class="py-2 px-4 italic" colspan="3">Saldo awal periode</td>
                        <td class="py-2 px-4 text-right whitespace-nowrap font-medium">{{ rupiah($m['saldo_pembuka']) }}</td>
                    </tr>
                    @forelse ($m['baris'] as $b)
                        <tr class="border-b last:border-0 align-top">
                            <td class="py-2 px-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($b['tanggal'])->format('d-m-Y') }}</td>
                            <td class="py-2 px-4">
                                <a href="{{ $b['tautan'] }}" class="hover:underline">{{ $b['uraian'] }}</a>
                                <div class="text-xs text-gray-500">{{ $b['jenis'] }}{{ $b['catatan'] ? ' · ' . $b['catatan'] : '' }}</div>
                            </td>
                            <td class="py-2 px-4 text-right whitespace-nowrap text-green-700">{{ $b['masuk'] ? rupiah($b['masuk']) : '' }}</td>
                            <td class="py-2 px-4 text-right whitespace-nowrap text-red-600">{{ $b['keluar'] ? rupiah($b['keluar']) : '' }}</td>
                            <td class="py-2 px-4 text-right whitespace-nowrap {{ $b['saldo'] < 0 ? 'text-red-600' : '' }}">{{ rupiah($b['saldo']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-400 italic">Tidak ada mutasi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t bg-gray-50 font-semibold">
                        <td class="py-2 px-4" colspan="2">Total periode &amp; saldo akhir</td>
                        <td class="py-2 px-4 text-right whitespace-nowrap text-green-700">{{ rupiah($m['total_masuk']) }}</td>
                        <td class="py-2 px-4 text-right whitespace-nowrap text-red-600">{{ rupiah($m['total_keluar']) }}</td>
                        <td class="py-2 px-4 text-right whitespace-nowrap">{{ rupiah($m['saldo_penutup']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- HITUNG FISIK --}}
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Hitung Fisik (Rekonsiliasi)</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                Hitung isi laci atau lihat saldo di mutasi rekening, lalu catat di sini. Sistem membandingkannya dengan
                saldo menurut catatan pada akhir hari tersebut. Catatan ini tidak mengubah saldo — selisih dibereskan
                dengan mencari catatan uang yang terlewat atau salah.
            </p>
        </div>

        <form method="POST" action="{{ route('koperasi.kas-bank.rekonsiliasi', $akun) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-3xl">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal</label>
                <input type="date" name="tanggal" max="{{ now()->format('Y-m-d') }}" value="{{ old('tanggal', now()->format('Y-m-d')) }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('tanggal', 'rekonsiliasi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Uang yang ada (Rp)</label>
                <input type="number" step="1" name="saldo_fisik" value="{{ old('saldo_fisik') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('saldo_fisik', 'rekonsiliasi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-medium text-gray-700 mb-1">Catatan <span class="text-gray-500 font-normal">(wajib bila ada selisih)</span></label>
                <input type="text" name="catatan" value="{{ old('catatan') }}" placeholder="mis. dihitung bersama bendahara; selisih diduga pembelian ATK belum dicatat"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('catatan', 'rekonsiliasi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="bg-koperasi-primary text-white text-sm font-medium px-5 py-2 rounded-md hover:opacity-90 transition">Catat Hitung Fisik</button>
            </div>
        </form>

        @if ($rekonsiliasi->isNotEmpty())
            <div class="overflow-x-auto -mx-1 px-1">
                <table class="w-full text-sm min-w-[640px]">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="py-2 pr-3">Tanggal</th>
                            <th class="py-2 pr-3 text-right">Menurut Sistem</th>
                            <th class="py-2 pr-3 text-right">Uang yang Ada</th>
                            <th class="py-2 pr-3 text-right">Selisih</th>
                            <th class="py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rekonsiliasi as $rk)
                            <tr class="border-b last:border-0 align-top">
                                <td class="py-2 pr-3 whitespace-nowrap">{{ $rk->tanggal->format('d-m-Y') }}</td>
                                <td class="py-2 pr-3 text-right whitespace-nowrap">{{ rupiah($rk->saldo_sistem) }}</td>
                                <td class="py-2 pr-3 text-right whitespace-nowrap">{{ rupiah($rk->saldo_fisik) }}</td>
                                <td class="py-2 pr-3 text-right whitespace-nowrap font-semibold {{ $rk->cocok() ? 'text-green-700' : 'text-red-600' }}">
                                    {{ $rk->cocok() ? 'Cocok' : rupiah($rk->selisih) }}
                                </td>
                                <td class="py-2 text-xs text-gray-600">
                                    {{ $rk->catatan }}
                                    <div class="text-gray-400">{{ $rk->creator->name ?? '—' }} · {{ $rk->created_at->format('d-m-Y H:i') }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
