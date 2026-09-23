{{--
    PILIHAN AKUN KAS/BANK — satu partial untuk semua form catatan uang
    (pengeluaran, simpanan, dana), supaya semuanya berperilaku sama.

    Parameter:
      $label       teks label, mis. "Dibayar dari" / "Diterima di"
      $terpilih    akun_kas_id tersimpan (edit) — null untuk catatan baru
      $petunjuk    teks opsi kosong, mis. "Otomatis sesuai metode"
      $kelasLabel  / $kelasInput  menyesuaikan gaya form induknya
--}}
@php
    $terpilih = $terpilih ?? null;
    $pilihanAkun = \App\Models\AkunKas::pilihan($terpilih);
    $nilai = (string) old('akun_kas_id', $terpilih);
@endphp
<div>
    <label class="{{ $kelasLabel ?? 'block text-sm font-medium text-text-main mb-1' }}">{{ $label }}</label>
    <select name="akun_kas_id" class="{{ $kelasInput ?? 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm' }}">
        <option value="">{{ $petunjuk ?? 'Otomatis (akun Kas)' }}</option>
        @foreach ($pilihanAkun as $akun)
            <option value="{{ $akun->id }}" @selected($nilai === (string) $akun->id)>
                {{ $akun->nama }} ({{ $akun->jenis }}){{ $akun->isAktif() ? '' : ' — nonaktif' }}
            </option>
        @endforeach
    </select>
    @error('akun_kas_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
</div>
