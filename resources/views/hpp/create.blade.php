@extends('layouts.app')

{{--
    SATU FORM UNTUK BUAT DAN KOREKSI.

    Formulir ini punya pembangun baris komponen dan kalkulator pratinjau
    sendiri. Menyalinnya menjadi berkas edit tersendiri berarti dua salinan
    rumus HPP di sisi tampilan — dan yang satu pasti tertinggal saat yang
    lain diperbaiki.

    $calculation TIDAK ADA saat membuat baru, dan berisi perhitungan yang
    sedang dikoreksi saat mengedit. Itu satu-satunya pembeda.
--}}
@php
    $mode = isset($calculation) ? 'koreksi' : 'buat';

    // Disiapkan di sini, BUKAN di dalam <script> di bawah. Blade memasangkan
    // pembuka dan penutup blok PHP dengan pencocokan teks biasa, jadi satu
    // berkas sebaiknya memakai satu bentuk saja dan tidak menyebut nama
    // direktifnya di dalam komentar.
    $komponenAwal = isset($calculation)
        ? $calculation->components->map(fn ($k) => [
            'kategori' => $k->kategori,
            'nama_biaya' => $k->nama_biaya,
            'qty' => (float) $k->qty,
            'satuan' => $k->satuan,
            'harga_satuan' => (float) $k->harga_satuan,
        ])->values()
        : [];
@endphp

@section('title', $mode === 'koreksi' ? 'Koreksi Perhitungan HPP' : 'Buat Perhitungan HPP')

@section('content')
<div class="max-w-6xl">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-text-main">
            {{ $mode === 'koreksi' ? 'Koreksi Perhitungan HPP' : 'Buat Perhitungan HPP' }}
        </h2>
        <p class="text-sm text-text-secondary">{{ $participant->nama_peserta }} — {{ $participant->nama_usaha }}</p>
        @if ($mode === 'koreksi')
            <p class="text-xs text-text-secondary mt-1">
                Perubahan angka HPP ikut tercatat di Activity Log, lengkap dengan nilai sebelum dan sesudahnya.
                Transaksi penjualan yang sudah terjadi TIDAK ikut berubah — masing-masing menyimpan HPP-nya sendiri.
            </p>
        @endif
    </div>

    @if ($errors->any())
        <div class="max-w-6xl mb-4 rounded-md bg-red-50 border border-status-danger/20 px-4 py-3 text-sm text-status-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST"
          action="{{ $mode === 'koreksi' ? route('hpp.update', $calculation) : route('hpp.store', $participant) }}"
          id="formHpp">
        @csrf
        @if ($mode === 'koreksi') @method('PUT') @endif
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            {{-- KIRI: FORM --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- INFORMASI PRODUK --}}
                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                    <h3 class="text-sm font-semibold text-text-main mb-4">Informasi Produk</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-text-main mb-1">Nama Produk</label>
                            <input type="text" name="nama_produk" value="{{ old('nama_produk', $calculation->nama_produk ?? '') }}" placeholder="Contoh: Keripik Pisang Original"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('nama_produk') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Jumlah Produksi</label>
                            <input type="number" min="1" step="1" name="jumlah_produksi" id="inputJumlahProduksi" value="{{ old('jumlah_produksi', $calculation->jumlah_produksi ?? 1) }}"
                                   oninput="recomputeSummary()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('jumlah_produksi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Satuan Produksi</label>
                            <input type="text" name="satuan_produksi" value="{{ old('satuan_produksi', $calculation->satuan_produksi ?? '') }}" placeholder="Contoh: bungkus, pcs, kg"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('satuan_produksi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-text-main mb-1">Harga Jual Saat Ini (Rp)</label>
                            <input type="number" min="0" step="1" name="harga_jual" id="inputHargaJual" value="{{ old('harga_jual', isset($calculation) ? (int) $calculation->harga_jual : 0) }}"
                                   oninput="recomputeSummary()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('harga_jual') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- KOMPONEN BIAYA --}}
                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-text-main">Komponen Biaya</h3>
                        <button type="button" onclick="addRow()" class="text-xs bg-cahaya-primary text-white px-3 py-1.5 rounded-md hover:opacity-90">+ Tambah Biaya</button>
                    </div>
                    @error('komponen') <p class="text-xs text-status-danger mb-2">{{ $message }}</p> @enderror

                    <div id="komponenContainer" class="space-y-2"></div>
                    <p id="noKomponenHint" class="text-sm text-text-secondary italic">Belum ada komponen biaya. Klik "+ Tambah Biaya" untuk mulai (contoh: bahan baku, kemasan, tenaga kerja, dll).</p>
                </div>
            </div>

            {{-- KANAN: RINGKASAN LIVE --}}
            <div class="lg:col-span-1">
                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 lg:sticky lg:top-4">
                    <h3 class="text-sm font-semibold text-text-main mb-4">Ringkasan HPP</h3>
                    <p class="text-xs text-text-secondary mb-3">Pratinjau — dihitung ulang & disimpan resmi saat Anda klik Simpan.</p>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-text-secondary">Total Biaya</span><span id="sumTotalBiaya" class="font-medium">Rp0</span></div>
                        <div class="flex justify-between border-t border-border-soft pt-2"><span class="text-text-secondary">HPP / Produk</span><span id="sumHpp" class="font-bold text-cahaya-primary">Rp0</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Harga Jual</span><span id="sumHargaJual" class="font-medium">Rp0</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Estimasi Laba / Produk</span><span id="sumLaba" class="font-medium">Rp0</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Margin</span><span id="sumMargin" class="font-medium">-</span></div>
                        <div class="flex justify-between border-t border-border-soft pt-2"><span class="text-text-secondary">Estimasi Laba / Batch</span><span id="sumEstimasiBatch" class="font-bold text-koperasi-accent">Rp0</span></div>
                    </div>

                    <div id="statusHarga" class="mt-4 text-xs font-medium rounded-md px-3 py-2"></div>

                    <button type="submit" class="w-full mt-6 bg-cahaya-primary text-white text-sm font-medium py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
                        {{ $mode === 'koreksi' ? 'Simpan Koreksi' : 'Simpan Perhitungan' }}
                    </button>
                    <a href="{{ $mode === 'koreksi' ? route('hpp.show', $calculation) : route('hpp.index', $participant) }}"
                       class="block text-center text-sm text-text-secondary mt-2 hover:text-text-main">Batal</a>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="templateKomponenRow">
    <div class="komponen-row grid grid-cols-12 gap-2 items-end border-b border-border-soft pb-2">
        <div class="col-span-2">
            <select class="input-kategori w-full rounded-md border border-gray-300 px-2 py-1.5 text-xs">
                @foreach ($kategoriOptions as $kat)
                    <option value="{{ $kat }}">{{ $kat }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-span-3"><input type="text" placeholder="Nama Biaya" class="input-nama w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
        <div class="col-span-2"><input type="number" min="0" step="0.01" placeholder="Qty" class="input-qty w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
        <div class="col-span-2"><input type="text" placeholder="Satuan" class="input-satuan w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm"></div>
        <div class="col-span-2"><input type="number" min="0" step="1" placeholder="Harga Satuan" class="input-harga w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" oninput="recomputeSummary()"></div>
        <div class="col-span-1 flex items-center justify-between gap-1">
            <span class="total-label text-xs font-medium text-text-secondary">Rp0</span>
            <button type="button" onclick="removeRow(this)" class="text-status-danger text-xs hover:underline">✕</button>
        </div>
    </div>
</template>

<script>
let komponenCounter = 0;

function formatRp(n) {
    return 'Rp' + Math.round(n).toLocaleString('id-ID');
}

function addRow() {
    const tpl = document.getElementById('templateKomponenRow').content.cloneNode(true);
    const row = tpl.querySelector('.komponen-row');
    const idx = komponenCounter++;

    row.querySelector('.input-kategori').name = `komponen[${idx}][kategori]`;
    row.querySelector('.input-nama').name = `komponen[${idx}][nama_biaya]`;
    row.querySelector('.input-qty').name = `komponen[${idx}][qty]`;
    row.querySelector('.input-satuan').name = `komponen[${idx}][satuan]`;
    row.querySelector('.input-harga').name = `komponen[${idx}][harga_satuan]`;

    document.getElementById('komponenContainer').appendChild(row);
    toggleHint();
    recomputeSummary();
}

function removeRow(btn) {
    btn.closest('.komponen-row').remove();
    toggleHint();
    recomputeSummary();
}

function toggleHint() {
    const total = document.querySelectorAll('#komponenContainer .komponen-row').length;
    document.getElementById('noKomponenHint').style.display = total === 0 ? 'block' : 'none';
}

function recomputeSummary() {
    let totalBiaya = 0;

    document.querySelectorAll('#komponenContainer .komponen-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.input-qty')?.value || 0);
        const harga = parseFloat(row.querySelector('.input-harga')?.value || 0);
        const total = qty * harga;
        totalBiaya += total;
        const label = row.querySelector('.total-label');
        if (label) label.textContent = formatRp(total);
    });

    const jumlahProduksi = parseInt(document.getElementById('inputJumlahProduksi')?.value || 0);
    const hargaJual = parseFloat(document.getElementById('inputHargaJual')?.value || 0);

    const hpp = jumlahProduksi > 0 ? totalBiaya / jumlahProduksi : 0;
    const laba = hargaJual - hpp;
    const margin = hargaJual > 0 ? (laba / hargaJual) * 100 : null;
    const estimasiBatch = laba * jumlahProduksi;

    document.getElementById('sumTotalBiaya').textContent = formatRp(totalBiaya);
    document.getElementById('sumHpp').textContent = formatRp(hpp);
    document.getElementById('sumHargaJual').textContent = formatRp(hargaJual);
    document.getElementById('sumLaba').textContent = formatRp(laba);
    document.getElementById('sumMargin').textContent = margin !== null ? margin.toFixed(1).replace('.', ',') + '%' : '-';
    document.getElementById('sumEstimasiBatch').textContent = formatRp(estimasiBatch);

    const statusEl = document.getElementById('statusHarga');
    if (hargaJual < hpp) {
        statusEl.textContent = '⚠️ Harga jual di bawah HPP';
        statusEl.className = 'mt-4 text-xs font-medium rounded-md px-3 py-2 bg-red-50 text-status-danger';
    } else if (hargaJual === hpp) {
        statusEl.textContent = '⚠️ Belum ada margin keuntungan';
        statusEl.className = 'mt-4 text-xs font-medium rounded-md px-3 py-2 bg-gold-light text-status-warning';
    } else {
        statusEl.textContent = '✓ Harga jual berada di atas HPP';
        statusEl.className = 'mt-4 text-xs font-medium rounded-md px-3 py-2 bg-primary-light text-status-success';
    }
}

// Saat KOREKSI, baris diisi dari komponen yang sudah tersimpan. Saat BUAT,
// cukup satu baris kosong biar formnya tidak terlihat kosong-melompong.
const komponenAwal = @json($komponenAwal);

if (komponenAwal.length > 0) {
    komponenAwal.forEach(function (k) {
        addRow();
        const baris = document.querySelectorAll('#komponenContainer .komponen-row');
        const row = baris[baris.length - 1];
        row.querySelector('.input-kategori').value = k.kategori ?? '';
        row.querySelector('.input-nama').value = k.nama_biaya ?? '';
        row.querySelector('.input-qty').value = k.qty ?? 0;
        row.querySelector('.input-satuan').value = k.satuan ?? '';
        row.querySelector('.input-harga').value = k.harga_satuan ?? 0;
    });
} else {
    addRow();
}

recomputeSummary();
</script>
@endsection
