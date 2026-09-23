@extends('layouts.app')

@section('title', 'Perbaiki Catatan Dana')

{{--
    SATU HALAMAN UNTUK KEEMPAT ARUS DANA.

    Yang berbeda antar jenis hanya kolom keterangannya (sumber / keperluan /
    peruntukan), dan perbedaan itu datang dari controller lewat $kolom —
    bukan dari empat salinan blade yang lambat laun saling menyimpang.
--}}

@section('content')
<div class="space-y-4 max-w-2xl">

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-text-main mb-1">Perbaiki Catatan Dana</h2>
        <p class="text-xs text-text-secondary mb-5">{{ $judul }}</p>

        <div class="bg-gold-light border border-status-warning/30 rounded-xl px-4 py-3 mb-5">
            <p class="text-xs text-text-main">
                Dana ini titipan pihak ketiga. Setiap perubahan dicatat di Activity Log lengkap
                dengan nilai <span class="font-medium">sebelum &rarr; sesudah</span>. Kalau angkanya
                sudah terlanjur dilaporkan ke pihak yang menitipkan, gunakan
                <span class="font-medium">Batalkan</span> di bawah &mdash; bukan diedit diam-diam.
            </p>
        </div>

        @if ($errors->any())
            <div class="bg-status-danger/10 border border-status-danger/30 rounded-xl px-4 py-3 mb-5">
                <ul class="text-xs text-status-danger space-y-1">
                    @foreach ($errors->all() as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $aksiPerbarui }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ old('tanggal', $baris->tanggal->format('Y-m-d')) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" min="0" step="1000"
                           value="{{ old('nominal', (int) $baris->nominal) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                </div>

                <div class="md:col-span-2">
                    @include('koperasi.kas-bank._pilih-akun', [
                        'label' => $jenis === 'masuk' ? 'Diterima di' : 'Dibayar dari',
                        'terpilih' => $baris->akun_kas_id,
                        'kelasInput' => 'w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm',
                    ])
                </div>

                @foreach ($kolom as $nama => $spek)
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-text-main mb-1">
                            {{ $spek['label'] }}
                            @unless ($spek['wajib']) <span class="text-text-secondary">(boleh kosong)</span> @endunless
                        </label>
                        <input type="text" name="{{ $nama }}" value="{{ old($nama, $baris->{$nama}) }}" maxlength="255"
                               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    </div>
                @endforeach

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary">(boleh kosong)</span></label>
                    <textarea name="keterangan" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('keterangan', $baris->keterangan) }}</textarea>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="text-sm font-medium text-white bg-cahaya-primary px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                    Simpan Perubahan
                </button>
                <a href="{{ route('koperasi.keuangan.index') }}" class="text-sm text-text-secondary hover:underline">Batal</a>
            </div>
        </form>
    </div>

    {{-- BATALKAN — jalur koreksi yang meninggalkan barisnya di tempat. --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Batalkan Catatan Ini</h3>
        <p class="text-xs text-text-secondary mb-4">
            Barisnya tetap tersimpan sebagai jejak, berhenti dihitung ke saldo, dan alasannya ikut
            tercatat. Dipakai kalau angkanya sudah masuk laporan yang terbit.
        </p>
        <form method="POST" action="{{ $aksiBatalkan }}" class="space-y-3"
              onsubmit="return confirm('Batalkan catatan dana ini? Barisnya tetap tersimpan sebagai jejak.');">
            @csrf
            <input type="text" name="alasan_pembatalan" required maxlength="1000"
                   placeholder="Alasan pembatalan (wajib diisi)"
                   class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
            <button type="submit" class="text-sm font-medium text-status-danger border border-status-danger/40 px-5 py-2.5 rounded-lg hover:bg-status-danger/5 transition">
                Batalkan Catatan
            </button>
        </form>
    </div>
</div>
@endsection
