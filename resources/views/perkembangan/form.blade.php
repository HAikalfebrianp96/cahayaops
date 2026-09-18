@extends('layouts.app')

@section('title', $measurement ? 'Edit Pengukuran' : 'Catat Pengukuran')

@section('content')
@php
    $aksi = $measurement
        ? route('perkembangan.update', $measurement)
        : route('perkembangan.store', $participant);

    /** Nilai awal satu kolom: input lama (kalau validasi gagal) -> data -> kosong. */
    $nilai = fn (string $kolom) => old($kolom, $measurement->{$kolom} ?? null);

    /** Tri-state ya/tidak/belum terdata sebagai string untuk <select>. */
    $triState = function (string $kolom) use ($measurement) {
        $lama = old($kolom, $measurement?->{$kolom});

        return $lama === null ? '' : ($lama ? '1' : '0');
    };

    $kelasInput = 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';
@endphp

<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">
        {{ $measurement ? 'Edit Pengukuran Perkembangan' : 'Catat Pengukuran Perkembangan' }}
    </h2>
    <p class="text-xs text-text-secondary mb-5">
        Peserta: <span class="font-medium text-text-main">{{ $participant->nama_peserta }}</span>
        &bull; {{ $participant->nama_usaha }}
    </p>

    {{--
        Keterangan ini bukan basa-basi. Isi kolom yang tidak diketahui dengan
        angka asal adalah cara paling cepat merusak perhitungan dampak, dan
        satu-satunya penangkalnya adalah memberi tahu petugas — di tempat ia
        mengisi — bahwa mengosongkan kolom itu boleh dan benar.
    --}}
    <div class="bg-surface-bg border border-border-soft rounded-xl p-4 mb-6">
        <p class="text-xs text-text-secondary">
            <span class="font-medium text-text-main">Kosongkan kolom yang belum diketahui.</span>
            Kolom kosong dicatat sebagai &ldquo;belum terdata&rdquo; dan dilewati saat menghitung
            perubahan — berbeda dari angka 0, yang berarti benar-benar nol. Menebak angka
            justru membuat laporan dampak tidak bisa dipertanggungjawabkan.
        </p>
    </div>

    <form method="POST" action="{{ $aksi }}" class="space-y-6">
        @csrf
        @if ($measurement) @method('PUT') @endif

        {{-- IDENTITAS PENGUKURAN --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Jenis Pengukuran</label>
                <select name="jenis" required class="{{ $kelasInput }}">
                    @foreach (\App\Models\ParticipantMeasurement::JENIS_OPTIONS as $kode => $label)
                        <option value="{{ $kode }}" @selected(old('jenis', $measurement->jenis ?? $jenisDisarankan) === $kode)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('jenis') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tanggal Pengukuran</label>
                <input type="date" name="tanggal" required max="{{ now()->toDateString() }}"
                       value="{{ old('tanggal', $measurement?->tanggal?->toDateString() ?? now()->toDateString()) }}"
                       class="{{ $kelasInput }}">
                @error('tanggal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- KEUANGAN --}}
        <div>
            <h3 class="text-sm font-semibold text-text-main mb-1">Keuangan Usaha (rata-rata per bulan)</h3>
            <p class="text-[11px] text-text-secondary mb-3">
                Angka rata-rata bulanan pada saat pengukuran — bukan total sepanjang program.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Omzet per bulan (Rp)</label>
                    <input type="number" name="omzet_bulanan" min="0" step="1000" value="{{ $nilai('omzet_bulanan') }}" class="{{ $kelasInput }}">
                    @error('omzet_bulanan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">HPP per bulan (Rp)</label>
                    <input type="number" name="hpp_bulanan" min="0" step="1000" value="{{ $nilai('hpp_bulanan') }}" class="{{ $kelasInput }}">
                    @error('hpp_bulanan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Biaya operasional (Rp)</label>
                    <input type="number" name="biaya_operasional_bulanan" min="0" step="1000" value="{{ $nilai('biaya_operasional_bulanan') }}" class="{{ $kelasInput }}">
                    @error('biaya_operasional_bulanan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- KAPASITAS --}}
        <div>
            <h3 class="text-sm font-semibold text-text-main mb-3">Kapasitas Usaha</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Tenaga kerja (orang)</label>
                    <input type="number" name="jumlah_tenaga_kerja" min="0" value="{{ $nilai('jumlah_tenaga_kerja') }}" class="{{ $kelasInput }}">
                    @error('jumlah_tenaga_kerja') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Jenis produk</label>
                    <input type="number" name="jumlah_produk" min="0" value="{{ $nilai('jumlah_produk') }}" class="{{ $kelasInput }}">
                    @error('jumlah_produk') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Pelanggan rutin (orang)</label>
                    <input type="number" name="jumlah_pelanggan" min="0" value="{{ $nilai('jumlah_pelanggan') }}" class="{{ $kelasInput }}">
                    @error('jumlah_pelanggan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Kapasitas produksi</label>
                    <input type="number" name="kapasitas_produksi" min="0" value="{{ $nilai('kapasitas_produksi') }}" class="{{ $kelasInput }}">
                    @error('kapasitas_produksi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Satuan produksi</label>
                    <input type="text" name="satuan_produksi" maxlength="255" placeholder="pcs / kg / porsi per hari"
                           value="{{ $nilai('satuan_produksi') }}" class="{{ $kelasInput }}">
                    @error('satuan_produksi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-main mb-1">Jangkauan pasar</label>
                    <select name="jangkauan_pasar" class="{{ $kelasInput }}">
                        <option value="">Belum terdata</option>
                        @foreach (\App\Models\ParticipantMeasurement::PASAR_OPTIONS as $kode => $label)
                            <option value="{{ $kode }}" @selected($nilai('jangkauan_pasar') === $kode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jangkauan_pasar') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{--
            STATUS USAHA — tiga pilihan, bukan kotak centang.

            Kotak centang hanya bisa mengatakan "ya" dan "tidak", dan yang
            tidak dicentang selalu tersimpan sebagai "tidak". Itu berarti
            setiap peserta yang belum sempat ditanya soal legalitas akan
            tercatat TIDAK punya legalitas — lalu muncul sebagai "peningkatan
            legalitas" begitu pertanyaannya akhirnya diajukan. Pilihan ketiga
            "belum terdata" yang mencegahnya.
        --}}
        <div>
            <h3 class="text-sm font-semibold text-text-main mb-3">Status Usaha</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach (\App\Models\ParticipantMeasurement::INDIKATOR_BOOLEAN as $kolom => $label)
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">{{ $label }}</label>
                        <select name="{{ $kolom }}" class="{{ $kelasInput }}">
                            <option value="" @selected($triState($kolom) === '')>Belum terdata</option>
                            <option value="1" @selected($triState($kolom) === '1')>Sudah</option>
                            <option value="0" @selected($triState($kolom) === '0')>Belum</option>
                        </select>
                        @error($kolom) <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        {{-- CATATAN & SUMBER --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
                <textarea name="catatan" rows="3" class="{{ $kelasInput }}">{{ $nilai('catatan') }}</textarea>
                @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Sumber Data <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
                <textarea name="sumber_data" rows="3" placeholder="Mis. wawancara kunjungan 12 Mar, catatan penjualan peserta, nota pembelian"
                          class="{{ $kelasInput }}">{{ $nilai('sumber_data') }}</textarea>
                <p class="text-[11px] text-text-secondary mt-1">
                    Dipakai sebagai bukti saat angka ini dipakai di laporan dampak dan SROI.
                </p>
                @error('sumber_data') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('perkembangan.index', $participant) }}"
               class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">Batal</a>
        </div>
    </form>
</div>
@endsection
