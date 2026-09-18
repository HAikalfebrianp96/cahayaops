@extends('layouts.app')

@section('title', $investasi ? 'Edit Investasi Program' : 'Catat Investasi Program')

@section('content')
@php
    $aksi = $investasi
        ? route('impact.investasi.update', $investasi)
        : route('impact.investasi.store', $program);
    $nilai = fn (string $k, $bawaan = null) => old($k, $investasi->{$k} ?? $bawaan);
    $kelasInput = 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';
@endphp

<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">
        {{ $investasi ? 'Edit Investasi Program' : 'Catat Investasi Program' }}
    </h2>
    <p class="text-xs text-text-secondary mb-5">Program: {{ $program->nama }}</p>

    {{--
        Peringatan ini menyasar satu kesalahan yang paling sering terjadi dan
        paling sulit terlihat: mencatat hanya biaya tunai. In-kind yang tidak
        dicatat membuat penyebut rasio lebih kecil daripada seharusnya, dan
        rasio SROI ikut membesar — tanpa satu pun angka yang dipalsukan.
    --}}
    <div class="bg-surface-bg border border-border-soft rounded-xl p-4 mb-6">
        <p class="text-xs text-text-secondary">
            <span class="font-medium text-text-main">Catat juga yang bukan uang tunai.</span>
            Tenaga relawan, tempat yang dipinjamkan, barang sumbangan — semuanya investasi nyata.
            Mengabaikannya membuat rasio SROI terlihat lebih baik daripada sebenarnya.
        </p>
    </div>

    {{-- enctype WAJIB — tanpa ini kuitansi tidak pernah sampai ke server,
         dan formnya tetap tersimpan seolah tidak ada yang salah. --}}
    <form method="POST" action="{{ $aksi }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @if ($investasi) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Kategori</label>
                {{-- Daftar terbuka: list hanya sebagai saran, kolomnya string
                     biasa. Daftar tertutup memaksa biaya nyata masuk kategori
                     yang salah supaya formnya mau tersimpan. --}}
                <input type="text" name="kategori" required maxlength="255" list="kategoriInvestasi"
                       value="{{ $nilai('kategori') }}" class="{{ $kelasInput }}">
                <datalist id="kategoriInvestasi">
                    @foreach (\App\Models\ProgramInvestment::KATEGORI_UMUM as $k)
                        <option value="{{ $k }}">
                    @endforeach
                </datalist>
                @error('kategori') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Jenis</label>
                <select name="jenis" required class="{{ $kelasInput }}">
                    @foreach (\App\Models\ProgramInvestment::JENIS_OPTIONS as $kode => $label)
                        <option value="{{ $kode }}" @selected($nilai('jenis', \App\Models\ProgramInvestment::JENIS_TUNAI) === $kode)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('jenis') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Uraian</label>
            <input type="text" name="uraian" required maxlength="255" value="{{ $nilai('uraian') }}"
                   placeholder="Mis. Pelatihan pembukuan 2 angkatan" class="{{ $kelasInput }}">
            @error('uraian') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tanggal</label>
                <input type="date" name="tanggal" required
                       value="{{ old('tanggal', $investasi?->tanggal?->toDateString() ?? now()->toDateString()) }}"
                       class="{{ $kelasInput }}">
                @error('tanggal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Nominal (Rp)</label>
                <input type="number" name="nominal" required min="1" step="1000" value="{{ $nilai('nominal') }}"
                       class="{{ $kelasInput }}">
                @error('nominal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Sumber Dana <span class="text-text-secondary font-normal">(opsional)</span></label>
                <input type="text" name="sumber_dana" maxlength="255" value="{{ $nilai('sumber_dana') }}"
                       placeholder="Mis. YBM PLN" class="{{ $kelasInput }}">
                @error('sumber_dana') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2" class="{{ $kelasInput }}">{{ $nilai('catatan') }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">
                Bukti <span class="text-text-secondary font-normal">(boleh kosong)</span>
            </label>

            @if ($investasi?->punyaBukti())
                {{-- Bukti yang sudah ada ditampilkan LEBIH DULU, beserta
                     pernyataan bahwa mengosongkan kolom tidak menghapusnya. --}}
                <div class="rounded-lg border border-border-soft bg-surface-bg p-3 mb-2">
                    <p class="text-xs text-text-secondary">
                        Bukti tersimpan:
                        <a href="{{ route('impact.investasi.bukti', $investasi) }}"
                           class="font-medium text-cahaya-secondary hover:underline">{{ $investasi->namaBukti() }}</a>
                    </p>
                    <p class="text-[11px] text-text-secondary mt-1">
                        Biarkan kolom di bawah kosong kalau bukti ini tidak perlu diganti.
                    </p>
                </div>
            @endif

            <input type="file" name="bukti"
                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                   class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-cahaya-primary file:px-3 file:py-1.5 file:text-xs file:text-white">
            <p class="text-[11px] text-text-secondary mt-1">
                Kuitansi, nota, atau bukti transfer. JPG, PNG, WEBP, atau PDF — maksimal 5 MB.
                Pos in-kind yang memang tidak berkuitansi boleh dikosongkan.
            </p>
            @error('bukti') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('impact.show', $program) }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">Batal</a>
        </div>
    </form>
</div>
@endsection
