@extends('layouts.app')

@section('title', 'Laporan Ringkasan Koperasi')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Ringkasan Koperasi</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.ringkasan.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
                Export PDF
            </a>
            <a href="{{ route('koperasi.laporan.ringkasan.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
                Export Excel
            </a>
        </div>
    </div>

    <form method="GET" class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-wrap gap-3">
        <select name="tahun" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="default" {{ $filters['tahun'] === 'default' ? 'selected' : '' }}>Tahun Ini</option>
            <option value="semua" {{ $filters['tahun'] === 'semua' ? 'selected' : '' }}>Semua Tahun</option>
            @for ($y = now()->year; $y >= now()->year - 3; $y--)
                <option value="{{ $y }}" {{ $filters['tahun'] == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
        <select name="bulan" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="default" {{ $filters['bulan'] === 'default' ? 'selected' : '' }}>Semua Bulan</option>
            @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nm)
                <option value="{{ $i + 1 }}" {{ $filters['bulan'] == ($i + 1) ? 'selected' : '' }}>{{ $nm }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Filter</button>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 space-y-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div><p class="text-xs text-text-secondary">Total Anggota</p><p class="text-lg font-bold text-koperasi-primary">{{ $totalAnggota }}</p></div>
            <div><p class="text-xs text-text-secondary">Anggota Aktif</p><p class="text-lg font-bold text-koperasi-primary">{{ $anggotaAktif }}</p></div>
            <div><p class="text-xs text-text-secondary">Unit Usaha</p><p class="text-lg font-bold text-koperasi-primary">{{ $totalUnit }}</p></div>
        </div>

        @if (! $lr['ada_transaksi'] && ! $lr['ada_pengeluaran'])
            <p class="text-sm text-text-secondary italic">Belum ada data pada periode ini.</p>
        @else
            <div>
                <h3 class="text-xs font-semibold text-text-secondary mb-2">Laba Rugi Koperasi</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Penjualan</p><p class="font-bold">{{ rupiah($lr['total']['penjualan']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">HPP</p><p class="font-bold">{{ rupiah($lr['total']['hpp']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Laba Kotor</p><p class="font-bold">{{ rupiah($lr['total']['laba_kotor']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Fee Koperasi</p><p class="font-bold">{{ rupiah($lr['total']['fee_koperasi']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Pengeluaran</p><p class="font-bold">{{ rupiah($lr['total']['pengeluaran_total']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3 bg-surface-bg col-span-2"><p class="text-xs text-text-secondary">Laba Bersih</p><p class="font-bold text-lg {{ $lr['total']['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">{{ rupiah($lr['total']['laba_bersih']) }}</p></div>
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold text-text-secondary mb-2">Performa Unit Usaha</h3>
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm min-w-[440px]">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1.5">Unit</th><th class="py-1.5">Penjualan</th><th class="py-1.5">Laba Bersih</th></tr></thead>
                    <tbody>
                        @foreach ($lr['per_unit'] as $u)
                            <tr class="border-b last:border-0">
                                <td class="py-1.5 font-medium">{{ $u['nama'] }}</td>
                                <td class="py-1.5">{{ rupiah($u['penjualan']) }}</td>
                                <td class="py-1.5 {{ $u['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }} font-semibold">{{ rupiah($u['laba_bersih']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif

        <div>
            <h3 class="text-xs font-semibold text-text-secondary mb-2">Dana Anggota &amp; Dana Sosial <span class="text-text-secondary font-normal">(bukan pendapatan usaha)</span></h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Simpanan Pokok</p><p class="font-bold">{{ rupiah($totalSimpananPokok) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Simpanan Wajib</p><p class="font-bold">{{ rupiah($totalSimpananWajib) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Saldo Dana Sukarela</p><p class="font-bold">{{ rupiah($saldoDanaSosial) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Saldo Dana Hibah</p><p class="font-bold">{{ rupiah($saldoDanaHibah) }}</p></div>            </div>

            @if (($simpananTerarsip ?? 0) > 0)
                {{-- Keterangan, bukan pengurangan angka.
                     Mengarsipkan anggota tidak mengembalikan uangnya, jadi
                     koperasi memang masih memegang dana ini. Yang keliru
                     adalah menampilkannya tanpa keterangan, seolah-olah itu
                     simpanan anggota yang masih ada. --}}
                <p class="mt-2 text-xs text-text-secondary">
                    Termasuk <span class="font-semibold text-text-main">{{ rupiah($simpananTerarsip) }}</span>
                    simpanan pokok/wajib milik anggota yang sudah diarsipkan. Dananya masih dipegang koperasi,
                    tetapi pemiliknya tidak lagi terhitung sebagai anggota dan tidak menerima pembagian SHU.
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
