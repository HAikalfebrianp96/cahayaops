@extends('layouts.app')

@section('title', 'Detail HPP')

@section('content')
@php
    $isPimpinan = auth()->user()->isPimpinan();
    $status = $calculation->statusHarga();
@endphp

<div class="max-w-2xl space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-text-main">HPP Produk</h2>
        <p class="text-sm text-text-secondary">
            {{ $participant?->nama_peserta ?? 'Peserta tidak ditemukan' }}@if ($participant) — {{ $participant->nama_usaha }}@endif
            @if ($participant?->trashed())<span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary">Peserta diarsipkan</span>@endif
        </p>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-lg font-bold text-text-main">{{ $calculation->nama_produk }}</h3>
            <span class="text-xs text-text-secondary">{{ $calculation->created_at->format('d F Y') }}</span>
        </div>
        <p class="text-sm text-text-secondary mb-6">{{ number_format($calculation->jumlah_produksi, 0, ',', '.') }} {{ $calculation->satuan_produksi }}</p>

        {{-- RINCIAN BIAYA --}}
        <div class="space-y-3 mb-6">
            @foreach (\App\Models\HppComponent::KATEGORI_OPTIONS as $kat)
                @if ($komponenPerKategori->has($kat))
                    <div>
                        <p class="text-xs font-semibold text-text-secondary mb-1">{{ $kat }}</p>
                        <div class="overflow-x-auto -mx-1 px-1">
                            <table class="w-full text-sm min-w-[460px]">
                            <tbody>
                                @foreach ($komponenPerKategori[$kat] as $k)
                                    <tr class="border-b border-border-soft last:border-0">
                                        <td class="py-1.5 text-text-main">{{ $k->nama_biaya }}</td>
                                        <td class="py-1.5 text-text-secondary text-xs">{{ number_format($k->qty, 2, ',', '.') }} {{ $k->satuan }} &times; {{ rupiah($k->harga_satuan) }}</td>
                                        <td class="py-1.5 text-right font-medium">{{ rupiah($k->total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- SUMMARY --}}
        <div class="border-t border-border-soft pt-4 space-y-2 text-sm">
            <div class="flex justify-between font-semibold"><span>Total Biaya Produksi</span><span>{{ rupiah($calculation->total_biaya_produksi) }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">HPP per {{ $calculation->satuan_produksi ?: 'Unit' }}</span><span class="font-bold text-cahaya-primary">{{ rupiah($calculation->hpp_per_unit) }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Harga Jual</span><span>{{ rupiah($calculation->harga_jual) }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Estimasi Laba per {{ $calculation->satuan_produksi ?: 'Unit' }}</span><span class="{{ $calculation->laba_per_unit >= 0 ? 'text-status-success' : 'text-status-danger' }} font-medium">{{ rupiah($calculation->laba_per_unit) }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Margin</span><span>{{ $calculation->margin_persen !== null ? number_format($calculation->margin_persen, 1, ',', '.') . '%' : '-' }}</span></div>
            <div class="flex justify-between border-t border-border-soft pt-2"><span class="text-text-secondary">Estimasi Laba per Batch</span><span class="font-bold text-koperasi-accent">{{ rupiah($calculation->estimasi_laba_batch) }}</span></div>
        </div>

        {{-- STATUS --}}
        <div class="mt-4 text-sm font-medium rounded-md px-3 py-2
            {{ match($status) {
                'di_bawah' => 'bg-red-50 text-status-danger',
                'sama' => 'bg-gold-light text-status-warning',
                default => 'bg-primary-light text-status-success',
            } }}">
            {{ match($status) {
                'di_bawah' => '⚠️ Harga jual di bawah HPP',
                'sama' => '⚠️ Belum ada margin keuntungan',
                default => '✓ Harga jual berada di atas HPP',
            } }}
        </div>

        <div class="mt-6 flex gap-2 flex-wrap">
            @unless ($isPimpinan)
                <a href="{{ route('hpp.create', $participant) }}" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
                    Buat Perhitungan Baru
                </a>
                {{-- Koreksi untuk salah input pada perhitungan INI. Kalau yang
                     berubah adalah biayanya di dunia nyata (harga bahan naik),
                     yang benar adalah Buat Perhitungan Baru supaya riwayatnya
                     tetap terbaca sebagai dua keadaan yang berbeda. --}}
                <a href="{{ route('hpp.edit', $calculation) }}" class="border-2 border-cahaya-primary text-cahaya-primary text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/5 transition">
                    Koreksi Perhitungan Ini
                </a>
            @endunless
            <a href="{{ route('hpp.index', $participant) }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Lihat Semua Histori
            </a>
        </div>
    </div>

    {{-- REKOMENDASI HARGA — simulasi matematis, BUKAN patokan harga pasar --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Rekomendasi Harga</h3>
        <p class="text-xs text-text-secondary mb-4">Angka berikut adalah contoh simulasi untuk membantu pendamping berdiskusi dengan pelaku usaha. Bukan patokan harga pasar.</p>

        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[420px]">
            <thead>
                <tr class="text-left text-text-secondary border-b">
                    <th class="py-2">Contoh Target Margin</th>
                    <th class="py-2">Contoh Harga Jual</th>
                    <th class="py-2">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ([25, 30, 40] as $targetMargin)
                    @php $hargaContoh = $calculation->hargaBerdasarkanMargin($targetMargin); @endphp
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-medium">{{ $targetMargin }}%</td>
                        <td class="py-2">±{{ rupiah($hargaContoh) }}</td>
                        <td class="py-2 text-xs text-text-secondary">Artinya laba sekitar {{ $targetMargin }}% dari harga jual — contoh target margin untuk simulasi.</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <p class="text-xs text-text-secondary mt-3">Ini simulasi matematis berdasarkan HPP. Pendamping tetap perlu membandingkan dengan harga pasar sebelum menetapkan harga jual.</p>
    </div>

    {{-- SIMULASI RESELLER — interaktif, TIDAK disimpan ke database --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Simulasi Reseller</h3>
        <p class="text-xs text-text-secondary mb-4">Contoh simulasi untuk bantu diskusi dengan pelaku usaha — bukan patokan harga pasar. Coba ubah angkanya untuk melihat dampaknya.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <div>
                <label class="block text-xs text-text-secondary mb-1">Harga Jual Retail (Rp)</label>
                <input type="number" min="0" id="simRetail" value="{{ (int) $calculation->harga_jual }}" oninput="recomputeReseller()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Harga Reseller (Rp)</label>
                <input type="number" min="0" id="simReseller" value="{{ max(0, (int) $calculation->harga_jual - 3000) }}" oninput="recomputeReseller()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Minimal Order</label>
                <input type="number" min="0" id="simMinOrder" value="20" oninput="recomputeReseller()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div class="border border-border-soft rounded-lg p-3">
                <p class="text-xs font-semibold text-text-secondary mb-2">Produsen (Anda)</p>
                <div class="flex justify-between"><span class="text-text-secondary">Laba / {{ $calculation->satuan_produksi ?: 'unit' }}</span><span id="simLabaProdusen" class="font-medium">Rp0</span></div>
                <div class="flex justify-between"><span class="text-text-secondary">Margin</span><span id="simMarginProdusen">-</span></div>
                <div class="flex justify-between border-t border-border-soft pt-1 mt-1"><span class="text-text-secondary">Laba Total (sesuai Min. Order)</span><span id="simLabaProdusenTotal" class="font-semibold text-cahaya-primary">Rp0</span></div>
            </div>
            <div class="border border-border-soft rounded-lg p-3">
                <p class="text-xs font-semibold text-text-secondary mb-2">Reseller</p>
                <div class="flex justify-between"><span class="text-text-secondary">Laba / {{ $calculation->satuan_produksi ?: 'unit' }}</span><span id="simLabaReseller" class="font-medium">Rp0</span></div>
                <div class="flex justify-between"><span class="text-text-secondary">Margin</span><span id="simMarginReseller">-</span></div>
                <div class="flex justify-between border-t border-border-soft pt-1 mt-1"><span class="text-text-secondary">Laba Total (sesuai Min. Order)</span><span id="simLabaResellerTotal" class="font-semibold text-koperasi-accent">Rp0</span></div>
            </div>
        </div>
    </div>

    {{-- CONTOH SKEMA RESELLER — beberapa skenario utk dibandingkan --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Contoh Skema Reseller</h3>
        <p class="text-xs text-text-secondary mb-4">Beberapa contoh simulasi untuk dibandingkan — bukan rekomendasi harga terbaik.</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @php
                $skenario = [
                    'A' => max(0, (float) $calculation->harga_jual - 3000),
                    'B' => max(0, (float) $calculation->harga_jual - 3500),
                    'C' => max(0, (float) $calculation->harga_jual - 4000),
                ];
            @endphp
            @foreach ($skenario as $label => $hargaResellerContoh)
                @php $sim = $calculation->simulasiReseller($hargaResellerContoh); @endphp
                <div class="border border-border-soft rounded-lg p-3 text-sm">
                    <p class="text-xs font-semibold text-text-secondary mb-2">Skenario {{ $label }}</p>
                    <div class="flex justify-between"><span class="text-text-secondary">Harga Reseller</span><span>{{ rupiah($hargaResellerContoh) }}</span></div>
                    <div class="flex justify-between"><span class="text-text-secondary">Margin Produsen</span><span>{{ $sim['margin_produsen'] !== null ? number_format($sim['margin_produsen'], 1, ',', '.') . '%' : '-' }}</span></div>
                    <div class="flex justify-between"><span class="text-text-secondary">Margin Reseller</span><span>{{ $sim['margin_reseller'] !== null ? number_format($sim['margin_reseller'], 1, ',', '.') . '%' : '-' }}</span></div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- CATATAN PENDAMPING --}}
    <div class="bg-status-info/10 border border-status-info/20 rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-cahaya-primary mb-2">💡 Catatan Pendamping</h3>
        <p class="text-sm text-text-main">{{ $calculation->catatanPendamping() }}</p>
    </div>

    <script>
        const HPP_UNIT_SIM = {{ (float) $calculation->hpp_per_unit }};

        function formatRpSim(n) {
            return 'Rp' + Math.round(n).toLocaleString('id-ID');
        }

        function recomputeReseller() {
            const retail = parseFloat(document.getElementById('simRetail').value || 0);
            const reseller = parseFloat(document.getElementById('simReseller').value || 0);
            const minOrder = parseFloat(document.getElementById('simMinOrder').value || 0);

            const labaProdusen = reseller - HPP_UNIT_SIM;
            const marginProdusen = reseller > 0 ? (labaProdusen / reseller) * 100 : null;
            const labaReseller = retail - reseller;
            const marginReseller = retail > 0 ? (labaReseller / retail) * 100 : null;

            document.getElementById('simLabaProdusen').textContent = formatRpSim(labaProdusen);
            document.getElementById('simMarginProdusen').textContent = marginProdusen !== null ? marginProdusen.toFixed(1).replace('.', ',') + '%' : '-';
            document.getElementById('simLabaProdusenTotal').textContent = formatRpSim(labaProdusen * minOrder);

            document.getElementById('simLabaReseller').textContent = formatRpSim(labaReseller);
            document.getElementById('simMarginReseller').textContent = marginReseller !== null ? marginReseller.toFixed(1).replace('.', ',') + '%' : '-';
            document.getElementById('simLabaResellerTotal').textContent = formatRpSim(labaReseller * minOrder);
        }

        recomputeReseller();
    </script>

    <a href="{{ route('participants.show', $participant) }}" class="text-sm text-status-info hover:underline">← Kembali ke Detail Peserta</a>
</div>
@endsection
