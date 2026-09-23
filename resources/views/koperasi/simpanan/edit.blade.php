@extends('layouts.app')

@section('title', 'Perbaiki Data Simpanan')

@section('content')
<div class="space-y-4 max-w-2xl">

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-text-main mb-1">Perbaiki Data Simpanan</h2>
        <p class="text-xs text-text-secondary mb-5">
            Anggota: <span class="font-medium text-text-main">{{ $member->namaTampil() }}</span>
            &bull; {{ $member->nomor_anggota }}
        </p>

        <div class="bg-gold-light border border-status-warning/30 rounded-xl px-4 py-3 mb-5">
            <p class="text-xs text-text-main">
                Ini data keuangan anggota. Setiap perubahan dicatat di Activity Log lengkap dengan
                nilai <span class="font-medium">sebelum &rarr; sesudah</span>, jadi tetap bisa
                ditelusuri. Kalau entri ini sudah terlanjur dipakai di laporan,
                gunakan <span class="font-medium">Batalkan</span> di halaman anggota — bukan diedit
                diam-diam.
            </p>
        </div>

        <form method="POST" action="{{ route('koperasi.savings.update', $simpanan) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Jenis Simpanan</label>
                    <select name="jenis_simpanan" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        @foreach ($jenisOptions as $jenis)
                            <option value="{{ $jenis }}" @selected(old('jenis_simpanan', $simpanan->jenis_simpanan) === $jenis)>{{ $jenis }}</option>
                        @endforeach
                    </select>
                    @error('jenis_simpanan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ old('tanggal', $simpanan->tanggal->format('Y-m-d')) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('tanggal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" min="0" step="1000"
                           value="{{ old('nominal', (int) $simpanan->nominal) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('nominal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    @include('koperasi.kas-bank._pilih-akun', [
                        'label' => 'Diterima di',
                        'terpilih' => $simpanan->akun_kas_id,
                        'kelasInput' => 'w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm',
                    ])
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary">(boleh kosong)</span></label>
                    <textarea name="keterangan" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">{{ old('keterangan', $simpanan->keterangan) }}</textarea>
                    @error('keterangan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                    Simpan Perbaikan
                </button>
                <a href="{{ route('koperasi.anggota.show', $member) }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                    Batal
                </a>
            </div>
        </form>
    </div>

    {{-- MENGUNCI — menutup jalur edit langsung supaya koreksi berikutnya wajib berjejak. --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Kunci Entri Ini</h3>
        <p class="text-xs text-text-secondary mb-4">
            Setelah dikunci, entri ini tidak bisa diedit langsung lagi. Koreksinya harus lewat
            Batalkan, yang selalu meninggalkan alasan dan entri pengganti — dipakai kalau angkanya
            sudah masuk laporan atau sudah diserahkan ke anggota.
        </p>
        <form method="POST" action="{{ route('koperasi.savings.kunci', $simpanan) }}"
              onsubmit="return confirm('Kunci entri ini? Setelah dikunci hanya bisa dikoreksi lewat Batalkan.');">
            @csrf
            <button type="submit"
                    class="text-sm font-medium text-text-main border border-border-soft px-5 py-2.5 rounded-lg hover:bg-surface-bg transition">
                Kunci Entri
            </button>
        </form>
    </div>
</div>
@endsection
