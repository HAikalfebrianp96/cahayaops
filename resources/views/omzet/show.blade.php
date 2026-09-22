@extends('layouts.app')

@section('title', 'Detail Laporan Omzet')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-6">
        Laporan Omzet — {{ $report->periodeLabel() }}
    </h2>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-gray-500">Peserta</dt>
            <dd class="font-medium text-gray-800">{{ $report->participant->nama_peserta ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Nama Usaha</dt>
            <dd class="font-medium text-gray-800">{{ $report->participant->nama_usaha ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Pendamping</dt>
            <dd class="font-medium text-gray-800">{{ $report->participant->pendamping->nama ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Periode</dt>
            <dd class="font-medium text-gray-800">{{ $report->periodeLabel() }}</dd>
            {{-- Mode & rentang ikut ditulis supaya pembacanya tahu baris ini
                 mewakili satu hari, satu minggu, atau satu bulan penuh.
                 Tanpa itu, 30 baris harian terlihat seperti 30 bulan. --}}
            <dd class="text-xs text-gray-500">{{ $report->jenis_periode }} &middot; {{ $report->rentangLabel() }}</dd>
        </div>

        <div>
            <dt class="text-gray-500">Bukti / Dokumentasi</dt>
            <dd class="font-medium text-gray-800">
                @if ($report->punyaBukti())
                    <a href="{{ route('omzet.bukti', $report) }}" class="text-cahaya-secondary hover:underline">
                        {{ $report->namaBukti() }}
                    </a>
                @else
                    <span class="text-gray-400 font-normal">Belum ada bukti dilampirkan</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-gray-500">Omzet</dt>
            <dd class="font-medium text-gray-800">{{ rupiah($report->omzet) }}</dd>
        </div>
        <div>
            <dt class="text-gray-500">Tanggal Laporan</dt>
            <dd class="font-medium text-gray-800">{{ $report->submitted_at?->format('d-m-Y H:i') ?? '—' }}</dd>
        </div>
        <div class="sm:col-span-2">
            {{--
                LABA — hanya ditampilkan kalau komponen biayanya memang
                dilaporkan. Laporan lama (dan laporan yang pesertanya belum
                menghitung HPP) sengaja TIDAK ditebak labanya: menampilkan
                "laba = seluruh omzet" jauh lebih menyesatkan daripada
                mengakui angkanya belum ada.
            --}}
            @if ($report->punyaDataLaba())
                <div class="rounded-xl border border-border-soft bg-surface-bg p-4">
                    <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-2">Perhitungan Laba</p>
                    <dl class="grid grid-cols-2 gap-y-1">
                        <dt class="text-gray-500">Omzet</dt>
                        <dd class="text-right text-gray-800">{{ rupiah($report->omzet) }}</dd>
                        <dt class="text-gray-500">HPP</dt>
                        <dd class="text-right text-gray-800">− {{ rupiah($report->hpp) }}</dd>
                        <dt class="text-gray-700 font-medium border-t border-border-soft pt-1">Laba kotor</dt>
                        <dd class="text-right font-medium text-gray-800 border-t border-border-soft pt-1">{{ rupiah($report->labaKotor()) }}</dd>
                        <dt class="text-gray-500">Biaya operasional</dt>
                        <dd class="text-right text-gray-800">− {{ rupiah((int) ($report->biaya_operasional ?? 0)) }}</dd>
                        <dt class="text-gray-700 font-semibold border-t border-border-soft pt-1">Profit bersih</dt>
                        <dd class="text-right font-semibold border-t border-border-soft pt-1 {{ $report->profitBersih() < 0 ? 'text-status-danger' : 'text-gray-800' }}">
                            {{ rupiah($report->profitBersih()) }}
                        </dd>
                        @if ($report->marginPersen() !== null)
                            <dt class="text-gray-500">Margin</dt>
                            <dd class="text-right text-gray-800">{{ number_format($report->marginPersen(), 1, ',', '.') }}%</dd>
                        @endif
                    </dl>
                    @if ($report->profitBersih() < 0)
                        <p class="text-xs text-status-danger mt-2">Usaha tercatat rugi pada periode ini — perlu ditindaklanjuti pendamping.</p>
                    @endif
                </div>
            @else
                <div class="rounded-xl border border-dashed border-border-soft p-4">
                    <p class="text-xs text-text-secondary">
                        HPP belum dilaporkan untuk periode ini, jadi laba belum bisa dihitung.
                        Lengkapi lewat <span class="font-medium">Edit</span> agar periode ini ikut masuk ke perhitungan profit dan dampak.
                    </p>
                </div>
            @endif
        </div>

        <div class="sm:col-span-2">
            <dt class="text-gray-500">Catatan</dt>
            <dd class="font-medium text-gray-800">{{ $report->catatan ?: '—' }}</dd>
        </div>
    </dl>

    <div class="mt-6 flex gap-2">
        {{-- Penjaganya SAMA dengan yang dipakai daftar omzet (OmzetReportPolicy).
             Daftarnya sudah membungkus Edit dengan @can sejak awal; halaman
             detail ini tidak — jadi Pimpinan, yang boleh membuka halaman ini
             tapi read-only, melihat tombol Edit yang begitu diklik menolak
             dengan 403. --}}
        @can('update', $report)
            <a href="{{ route('omzet.edit', $report) }}"
               class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
                Edit
            </a>
        @endcan
        <a href="{{ route('participants.show', $report->participant) }}"
           class="text-sm text-cahaya-secondary px-5 py-2.5 hover:underline">
            Lihat Detail Peserta
        </a>
        <a href="{{ route('omzet.index') }}" class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
            Kembali
        </a>
    </div>
</div>
@endsection
