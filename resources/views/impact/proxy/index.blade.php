@extends('layouts.app')

@section('title', 'Proxy Finansial')

@section('content')
<div class="space-y-4">

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text-main">Proxy Finansial</h2>
                <p class="text-xs text-text-secondary mt-0.5 max-w-2xl">
                    Berapa rupiah nilai satu satuan outcome, dan dari mana angka itu berasal. Setiap rupiah
                    &ldquo;nilai sosial&rdquo; yang pernah dilaporkan lembaga ini berdiri di atas salah satu baris di bawah.
                </p>
            </div>
            <div class="flex gap-2">
                @if ($bisaKelola)
                    <a href="{{ route('impact.proxy.create') }}"
                       class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                        + Proxy Baru
                    </a>
                @endif
                <a href="{{ route('impact.index') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Kembali</a>
            </div>
        </div>
    </div>

    @include('impact.partials.notifikasi')

    @if ($proxies->isEmpty())
        {{--
            Daftar kosong adalah kondisi awal yang DISENGAJA, bukan kelalaian.
            CahayaOS lahir tanpa satu pun proxy terisi karena angka "contoh"
            akan dipakai apa adanya, dan rasio SROI yang berdiri di atas angka
            contoh adalah karangan yang terlihat resmi.
        --}}
        <div class="bg-white border border-dashed border-border-soft rounded-2xl p-8 text-center">
            <p class="text-sm font-semibold text-text-main mb-1">Belum ada proxy finansial</p>
            <p class="text-xs text-text-secondary max-w-xl mx-auto">
                CahayaOS sengaja tidak menyediakan angka bawaan. Proxy harus datang dari sumber yang bisa
                diperiksa — statistik resmi, studi, atau kebijakan lembaga — karena angka tanpa sumber
                akan tetap dipakai apa adanya dan berakhir di laporan sebagai fakta.
            </p>
            @if ($bisaKelola)
                <a href="{{ route('impact.proxy.create') }}" class="inline-block mt-4 text-sm text-cahaya-secondary hover:underline">
                    Tambah proxy pertama →
                </a>
            @endif
        </div>
    @else
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <div class="overflow-x-auto -mx-5 px-5">
                <table class="w-full text-sm min-w-[640px]">
                    <thead>
                        <tr class="text-left text-xs text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Nama</th>
                            <th class="py-2 font-medium">Untuk Outcome</th>
                            <th class="py-2 font-medium text-right">Nilai</th>
                            <th class="py-2 font-medium">Satuan</th>
                            <th class="py-2 font-medium">Sumber</th>
                            <th class="py-2 font-medium">Status</th>
                            @if ($bisaKelola) <th class="py-2 font-medium text-right">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @foreach ($proxies as $px)
                            <tr class="{{ $px->aktif ? '' : 'opacity-60' }}">
                                <td class="py-2.5 text-text-main">
                                    {{ $px->nama }}
                                    @if ($px->alasan)
                                        <p class="text-[11px] text-text-secondary">{{ \Illuminate\Support\Str::limit($px->alasan, 80) }}</p>
                                    @endif
                                </td>
                                <td class="py-2.5 text-text-secondary">
                                    {{ $px->untukOutcome() }}
                                    @if ($px->indikator)
                                        <span class="block text-[11px] text-text-secondary">{{ $px->indikator }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-right font-medium text-text-main">{{ rupiah($px->nilai) }}</td>
                                <td class="py-2.5 text-text-secondary">{{ $px->satuan }}</td>
                                <td class="py-2.5 text-text-secondary">
                                    {{ $px->sumber }} ({{ $px->tahun }})
                                    @if ($px->umurTahun() >= 5)
                                        {{-- Umur ditampilkan, bukan dipakai menghitung.
                                             Proxy lama belum tentu salah, tapi penyusun
                                             laporan berhak melihatnya tanpa menghitung sendiri. --}}
                                        <span class="block text-[11px] text-status-warning">{{ $px->umurTahun() }} tahun lalu</span>
                                    @endif
                                </td>
                                <td class="py-2.5">
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full
                                        {{ $px->aktif ? 'bg-status-success/10 text-status-success' : 'bg-surface-bg text-text-secondary' }}">
                                        {{ $px->aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                @if ($bisaKelola)
                                    <td class="py-2.5 text-right">
                                        <div class="inline-flex gap-3 text-xs">
                                            <a href="{{ route('impact.proxy.edit', $px) }}" class="text-cahaya-secondary hover:underline">Edit</a>
                                            <form method="POST" action="{{ route('impact.proxy.toggle', $px) }}">
                                                @csrf
                                                <button type="submit" class="text-text-secondary hover:underline">
                                                    {{ $px->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{ $proxies->links() }}
    @endif
</div>
@endsection
