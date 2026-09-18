@extends('layouts.app')

@section('title', 'Penetapan SHU ' . $periode->tahun)

@section('content')
@php $kartu = 'bg-white border border-border-soft rounded-2xl shadow-sm p-5'; @endphp

<div class="space-y-4">

    <div class="{{ $kartu }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-text-main">Penetapan SHU {{ $periode->tahun }}</h2>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full
                        {{ $periode->terkunci() ? 'bg-status-success/10 text-status-success' : 'bg-surface-bg text-text-secondary' }}">
                        {{ $periode->status }}
                    </span>
                </div>
                <p class="text-xs text-text-secondary mt-1">
                    Ditetapkan {{ $periode->tanggal_penetapan->format('d M Y') }}
                    oleh {{ $periode->penetap->name ?? 'Tidak tercatat' }}
                    &bull; Sumber: {{ \App\Models\ShuPeriod::SUMBER_OPTIONS[$periode->sumber] ?? $periode->sumber }}
                </p>
            </div>
            <div class="flex gap-2">
                {{-- Dokumen ini dibawa ke Rapat Anggota, jadi tombolnya ada
                     di tempat paling mudah ditemukan dan tersedia sejak status
                     masih Draft — penetapan perlu dicetak justru untuk dibahas
                     sebelum disahkan. --}}
                <a href="{{ route('koperasi.shu.export-pdf', $periode) }}"
                   class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                    Export PDF
                </a>
                @if ($bisaKelola)
                    <a href="{{ route('koperasi.shu.edit', $periode) }}"
                       class="text-sm text-text-secondary border border-border-soft px-4 py-2 rounded-lg hover:text-text-main">Edit</a>
                @endif
                <a href="{{ route('koperasi.shu.index') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Kembali</a>
            </div>
        </div>

        @if ($periode->terkunci())
            <div class="mt-4 rounded-xl bg-surface-bg border border-border-soft p-3">
                <p class="text-xs text-text-secondary">
                    <span class="font-medium text-text-main">Penetapan ini sudah disahkan dan terkunci.</span>
                    Alokasinya tidak lagi disusun ulang — pembagian yang sudah disahkan Rapat Anggota tidak
                    boleh bergeser ketika data simpanan atau transaksi diperbaiki kemudian.
                </p>
            </div>
        @endif
    </div>

    @include('impact.partials.notifikasi')

    {{-- PERINGATAN — tidak memblokir, tapi wajib terlihat sebelum disahkan --}}
    @if (! empty($peringatan))
        <div class="rounded-xl bg-gold-light border border-status-warning/40 px-4 py-3">
            <p class="text-xs font-semibold text-cahaya-primary mb-2">Perlu diperhatikan</p>
            <ul class="text-xs text-text-main space-y-1 list-disc list-inside">
                @foreach ($peringatan as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KOMPONEN PEMBAGIAN --}}
    <div class="{{ $kartu }}">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h3 class="text-sm font-semibold text-text-main">Komponen Pembagian</h3>
            <p class="text-sm font-semibold text-text-main">Total SHU {{ rupiah($periode->total_shu) }}</p>
        </div>

        <div class="overflow-x-auto -mx-5 px-5">
            <table class="w-full text-sm min-w-[480px]">
                <thead>
                    <tr class="text-left text-xs text-text-secondary border-b border-border-soft">
                        <th class="py-2 font-medium">Komponen</th>
                        <th class="py-2 font-medium text-right">Persen</th>
                        <th class="py-2 font-medium text-right">Nominal</th>
                        <th class="py-2 font-medium">Dibagikan ke</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-soft">
                    {{-- Indeksnya ikut dipakai: nominal tiap komponen diambil
                         menurut POSISI, supaya dua komponen yang kebetulan
                         isinya sama tidak saling tertukar nominalnya. --}}
                    @foreach ($periode->komponen ?? [] as $i => $k)
                        <tr>
                            <td class="py-2 text-text-main">{{ $k['nama'] }}</td>
                            <td class="py-2 text-right text-text-secondary">
                                {{ rtrim(rtrim(number_format($k['persen'], 2, ',', '.'), '0'), ',') }}%
                            </td>
                            <td class="py-2 text-right font-medium text-text-main">{{ rupiah($periode->nominalKomponenKe($i)) }}</td>
                            <td class="py-2 text-text-secondary">
                                @if (! empty($k['ke_anggota']))
                                    Anggota — menurut {{ $k['dasar'] === 'simpanan' ? 'simpanan pokok & wajib' : 'nilai transaksi' }}
                                @else
                                    Dana koperasi
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-border-soft">
                        <td class="py-2 font-medium text-text-main">Total</td>
                        <td class="py-2 text-right font-medium {{ abs($periode->totalPersen() - 100) > 0.01 ? 'text-status-danger' : 'text-text-main' }}">
                            {{ rtrim(rtrim(number_format($periode->totalPersen(), 2, ',', '.'), '0'), ',') }}%
                        </td>
                        <td class="py-2 text-right font-medium text-text-main">
                            {{ rupiah($periode->totalKeAnggota()) }} ke anggota
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($bisaKelola)
            <div class="flex flex-wrap gap-2 mt-4">
                <form method="POST" action="{{ route('koperasi.shu.susun-ulang', $periode) }}">
                    @csrf
                    <button type="submit" class="text-sm text-text-secondary border border-border-soft px-4 py-2 rounded-lg hover:text-text-main">
                        Susun Ulang Alokasi
                    </button>
                </form>
                @if ($bisaSahkan)
                    <form method="POST" action="{{ route('koperasi.shu.sahkan', $periode) }}"
                          onsubmit="return confirm('Sahkan penetapan SHU {{ $periode->tahun }}? Angka dan alokasinya akan terkunci.')">
                        @csrf
                        <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2 rounded-lg hover:opacity-90 transition">
                            Sahkan Penetapan
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    {{-- ALOKASI PER ANGGOTA --}}
    <div class="{{ $kartu }}">
        <h3 class="text-sm font-semibold text-text-main mb-1">
            Alokasi per Anggota
            <span class="font-normal text-text-secondary">({{ $periode->allocations->count() }} anggota)</span>
        </h3>
        <p class="text-[11px] text-text-secondary mb-3">
            Dasar simpanan dan transaksi dibekukan pada saat penetapan — bukan dihitung ulang saat
            halaman ini dibuka. Kalau dihitung ulang, pembagian yang sudah disahkan akan berubah sendiri
            begitu ada simpanan atau transaksi baru masuk.
        </p>

        @if ($periode->allocations->isEmpty())
            <p class="text-sm text-text-secondary">
                Belum ada anggota yang mendapat alokasi. Ini terjadi kalau belum ada simpanan pokok/wajib
                tercatat, dan belum ada transaksi penjualan yang tertaut ke anggota.
            </p>
        @else
            <div class="overflow-x-auto -mx-5 px-5">
                <table class="w-full text-sm min-w-[640px]">
                    <thead>
                        <tr class="text-left text-xs text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Anggota</th>
                            <th class="py-2 font-medium text-right">Dasar simpanan</th>
                            <th class="py-2 font-medium text-right">Dasar transaksi</th>
                            <th class="py-2 font-medium text-right">Jasa modal</th>
                            <th class="py-2 font-medium text-right">Jasa usaha</th>
                            <th class="py-2 font-medium text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @foreach ($periode->allocations as $a)
                            <tr>
                                <td class="py-2 text-text-main">
                                    {{ $a->member->nama ?? '—' }}
                                    <span class="block text-[11px] text-text-secondary">{{ $a->member->nomor_anggota ?? '' }}</span>
                                </td>
                                <td class="py-2 text-right text-text-secondary">{{ rupiah($a->dasar_simpanan) }}</td>
                                <td class="py-2 text-right text-text-secondary">{{ rupiah($a->dasar_transaksi) }}</td>
                                <td class="py-2 text-right text-text-secondary">{{ rupiah($a->jasa_modal) }}</td>
                                <td class="py-2 text-right text-text-secondary">{{ rupiah($a->jasa_usaha) }}</td>
                                <td class="py-2 text-right font-semibold text-text-main">{{ rupiah($a->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($periode->catatan)
        <div class="{{ $kartu }}">
            <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-1">Catatan</p>
            <p class="text-sm text-text-main">{{ $periode->catatan }}</p>
        </div>
    @endif
</div>
@endsection
