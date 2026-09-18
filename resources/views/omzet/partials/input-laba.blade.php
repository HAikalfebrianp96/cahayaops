{{--
    KOMPONEN BIAYA — dipakai BERSAMA oleh tiga form omzet: catat internal,
    edit, dan form publik bertoken. Satu berkas supaya label, bantuan, dan
    ringkasan labanya tidak pernah berbeda antar jalur input.

    Parameter:
      $hpp, $biaya   nilai awal (boleh null)
      $publik        true  -> bahasa untuk peserta ("Modal / HPP")
                     false -> bahasa untuk petugas ("HPP")
--}}
@php
    $publik = $publik ?? false;
    $labelHpp = $publik ? 'Modal / HPP (Rp)' : 'HPP — Harga Pokok (Rp)';
@endphp

<div>
    <label class="block text-xs font-medium text-text-main mb-1">
        {{ $labelHpp }} <span class="text-text-secondary font-normal">(boleh kosong)</span>
    </label>
    <input type="number" name="hpp" min="0" step="1000" data-laba="hpp"
           value="{{ old('hpp', $hpp) }}"
           class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
    <p class="text-[11px] text-text-secondary mt-1">
        {{ $publik
            ? 'Total belanja bahan/barang untuk semua yang terjual di periode ini.'
            : 'Total harga pokok barang yang terjual pada periode ini.' }}
    </p>
    @error('hpp') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
</div>

<div>
    <label class="block text-xs font-medium text-text-main mb-1">
        Biaya Operasional (Rp) <span class="text-text-secondary font-normal">(boleh kosong)</span>
    </label>
    <input type="number" name="biaya_operasional" min="0" step="1000" data-laba="biaya"
           value="{{ old('biaya_operasional', $biaya) }}"
           class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
    <p class="text-[11px] text-text-secondary mt-1">
        {{ $publik
            ? 'Misalnya listrik, gas, transport, sewa, upah. Kosongkan kalau tidak ada.'
            : 'Listrik, transport, sewa, upah, dan biaya rutin lain di luar HPP.' }}
    </p>
    @error('biaya_operasional') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
</div>

{{--
    RINGKASAN LABA — bantuan hitung di layar, BUKAN sumber data.

    Tidak ada input tersembunyi di sini: yang dikirim ke server tetap hanya
    omzet, hpp, dan biaya operasional. Laba dihitung ulang di server dari
    ketiganya (lihat OmzetReport::profitBersih), jadi angka yang tampil di
    sini tidak bisa dipakai memasukkan laba karangan.
--}}
<div class="md:col-span-2 rounded-xl border border-border-soft bg-surface-bg p-4 hidden" data-laba="panel">
    <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-2">Perkiraan Laba</p>
    <dl class="grid grid-cols-2 gap-y-1 text-sm">
        <dt class="text-text-secondary">Laba kotor</dt>
        <dd class="text-right font-medium text-text-main" data-laba="kotor">-</dd>
        <dt class="text-text-secondary">Profit bersih</dt>
        <dd class="text-right font-semibold text-text-main" data-laba="bersih">-</dd>
        <dt class="text-text-secondary">Margin</dt>
        <dd class="text-right text-text-main" data-laba="margin">-</dd>
    </dl>
    <p class="text-[11px] text-text-secondary mt-2" data-laba="peringatan"></p>
</div>

@include('omzet.partials.skrip-laba')
