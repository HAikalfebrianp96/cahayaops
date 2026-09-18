@extends('layouts.app')

@section('title', $proxy ? 'Edit Proxy Finansial' : 'Proxy Finansial Baru')

@section('content')
@php
    $aksi = $proxy ? route('impact.proxy.update', $proxy) : route('impact.proxy.store');
    $nilai = fn (string $k, $bawaan = null) => old($k, $proxy->{$k} ?? $bawaan);
    $kelasInput = 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';
@endphp

<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">
        {{ $proxy ? 'Edit Proxy Finansial' : 'Proxy Finansial Baru' }}
    </h2>
    <p class="text-xs text-text-secondary mb-5">
        Berapa rupiah nilai satu satuan outcome, dan dari mana angka itu berasal.
    </p>

    @if ($proxy)
        {{-- Kekhawatiran yang wajar saat mengedit proxy adalah "apakah laporan
             lama ikut berubah". Dijawab di tempat kekhawatiran itu muncul. --}}
        <div class="bg-surface-bg border border-border-soft rounded-xl p-4 mb-6">
            <p class="text-xs text-text-secondary">
                <span class="font-medium text-text-main">Laporan yang sudah terbit tidak ikut berubah.</span>
                Nilai proxy yang dipakai setiap perhitungan SROI sudah dibekukan di hasilnya masing-masing —
                pola yang sama dengan snapshot harga pada transaksi penjualan. Yang berubah hanya
                perhitungan berikutnya.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ $aksi }}" class="space-y-4">
        @csrf
        @if ($proxy) @method('PUT') @endif

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Nama Proxy</label>
            <input type="text" name="nama" required maxlength="255" value="{{ $nilai('nama') }}"
                   placeholder="Mis. Nilai kenaikan pendapatan UMKM" class="{{ $kelasInput }}">
            @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{--
            Untuk outcome & indikator APA angka ini dimaksudkan.

            Keduanya boleh kosong — satu proxy memang bisa dipakai lintas
            outcome. Tapi begitu daftarnya berisi sepuluh baris, keterangan ini
            yang mencegah proxy "kenaikan pendapatan" dipasang ke outcome
            "penambahan tenaga kerja": salah pasang proxy menghasilkan nilai
            dampak yang keliru tanpa satu pun tanda bahaya.
        --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">
                    Untuk Outcome <span class="text-text-secondary font-normal">(disarankan)</span>
                </label>
                <input type="text" name="outcome" maxlength="255" value="{{ $nilai('outcome') }}"
                       placeholder="Mis. Peningkatan pendapatan usaha" class="{{ $kelasInput }}">
                @error('outcome') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">
                    Indikator <span class="text-text-secondary font-normal">(disarankan)</span>
                </label>
                <select name="indikator" class="{{ $kelasInput }}">
                    <option value="">— berlaku untuk semua indikator —</option>
                    @foreach ($indikatorPilihan as $kode => $label)
                        <option value="{{ $kode }}" @selected($nilai('indikator') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-text-secondary mt-1">
                    Kalau diisi, sistem memperingatkan bila proxy ini dipasang ke outcome berindikator lain.
                </p>
                @error('indikator') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Nilai (Rp)</label>
                <input type="number" name="nilai" required min="1" step="1000" value="{{ $nilai('nilai') }}"
                       class="{{ $kelasInput }}">
                @error('nilai') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Satuan</label>
                <input type="text" name="satuan" required maxlength="255" value="{{ $nilai('satuan') }}"
                       placeholder="per UMKM per tahun" class="{{ $kelasInput }}">
                @error('satuan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Sumber</label>
                <input type="text" name="sumber" required maxlength="255" value="{{ $nilai('sumber') }}"
                       placeholder="Mis. BPS Statistik UMKM" class="{{ $kelasInput }}">
                <p class="text-[11px] text-text-secondary mt-1">
                    Wajib. Proxy tanpa sumber tidak bisa diperiksa siapa pun, dan yang tidak bisa diperiksa
                    tidak layak dipakai melapor ke pemberi dana.
                </p>
                @error('sumber') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tahun Sumber</label>
                <input type="number" name="tahun" required min="2000" max="{{ now()->year }}"
                       value="{{ $nilai('tahun', now()->year) }}" class="{{ $kelasInput }}">
                @error('tahun') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">
                Alasan Pemakaian <span class="text-text-secondary font-normal">(sangat disarankan)</span>
            </label>
            <textarea name="alasan" rows="3" class="{{ $kelasInput }}"
                      placeholder="Kenapa angka ini yang paling mendekati konteks peserta program?">{{ $nilai('alasan') }}</textarea>
            @error('alasan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2" class="{{ $kelasInput }}">{{ $nilai('catatan') }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-text-main">
            <input type="hidden" name="aktif" value="0">
            <input type="checkbox" name="aktif" value="1" @checked((bool) $nilai('aktif', true))
                   class="rounded border-border-soft">
            Aktif — ditawarkan saat menyusun outcome
        </label>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('impact.proxy.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">Batal</a>
        </div>
    </form>
</div>
@endsection
