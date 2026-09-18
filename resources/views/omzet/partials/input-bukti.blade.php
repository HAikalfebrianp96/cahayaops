{{--
    UNGGAH BUKTI — dipakai bersama form internal, form edit, dan form publik.

    Parameter:
      $laporan  OmzetReport yang sedang diedit (null saat membuat baru)
      $publik   true -> gaya input besar untuk ponsel
--}}
@php
    $publik = $publik ?? false;
    $laporan = $laporan ?? null;

    $kelasLabel = $publik
        ? 'block text-sm font-semibold text-gray-700 mb-1'
        : 'block text-xs font-medium text-text-main mb-1';

    $kelasInput = $publik
        ? 'w-full rounded-xl border border-gray-300 px-3 py-3 text-base file:mr-3 file:rounded-lg file:border-0 file:bg-cahaya-primary file:px-3 file:py-1.5 file:text-sm file:text-white'
        : 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-cahaya-primary file:px-3 file:py-1.5 file:text-xs file:text-white';

    $kelasBantuan = $publik ? 'text-xs text-gray-400 mt-1' : 'text-[11px] text-text-secondary mt-1';
    $kelasError = $publik ? 'text-xs text-red-600 mt-1' : 'text-xs text-status-danger mt-1';
@endphp

<div class="{{ $publik ? '' : 'md:col-span-2' }}">
    <label class="{{ $kelasLabel }}">
        Bukti / Dokumentasi
        <span class="{{ $publik ? 'text-gray-400 font-normal' : 'text-text-secondary font-normal' }}">(boleh kosong)</span>
    </label>

    @if ($laporan?->punyaBukti())
        {{--
            Bukti yang sudah ada ditampilkan LEBIH DULU, beserta pernyataan
            bahwa mengosongkan kolom tidak menghapusnya. Tanpa itu, orang yang
            hanya ingin memperbaiki angka akan mengira buktinya ikut hilang
            karena kolom unggah terlihat kosong.
        --}}
        <div class="{{ $publik ? 'rounded-xl border border-gray-200 bg-gray-50 p-3 mb-2' : 'rounded-lg border border-border-soft bg-surface-bg p-3 mb-2' }}">
            <p class="text-xs {{ $publik ? 'text-gray-600' : 'text-text-secondary' }}">
                Bukti tersimpan:
                @if ($publik)
                    <span class="font-medium text-gray-800">{{ $laporan->namaBukti() }}</span>
                @else
                    <a href="{{ route('omzet.bukti', $laporan) }}" class="font-medium text-cahaya-secondary hover:underline">
                        {{ $laporan->namaBukti() }}
                    </a>
                @endif
            </p>
            <p class="{{ $kelasBantuan }}">
                Biarkan kolom di bawah kosong kalau bukti ini tidak perlu diganti.
            </p>
        </div>
    @endif

    <input type="file" name="bukti"
           accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
           class="{{ $kelasInput }}">
    <p class="{{ $kelasBantuan }}">
        Foto catatan penjualan, buku kas, atau tangkapan layar transaksi.
        Format JPG, PNG, WEBP, atau PDF — maksimal 5 MB.
    </p>
    @error('bukti') <p class="{{ $kelasError }}">{{ $message }}</p> @enderror
</div>
