@extends('layouts.app')

@section('title', 'Catat Omzet Peserta')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Catat Perkembangan Omzet</h2>
    <p class="text-xs text-text-secondary mb-6">
        Peserta: <span class="font-medium text-text-main">{{ $participant->nama_peserta }}</span>
        &bull; {{ $participant->nama_usaha }}
    </p>

    <div class="bg-surface-bg border border-border-soft rounded-xl p-4 mb-5">
        <p class="text-xs text-text-secondary">
            Dipakai kalau peserta menyebutkan omzetnya saat kunjungan lapangan dan belum sempat mengisi
            sendiri lewat link laporan. Omzet bisa dicatat bulanan, mingguan, atau harian — tetapi
            <span class="font-medium text-text-main">satu bulan hanya boleh memakai satu cara pencatatan</span>,
            supaya omzet yang sama tidak terhitung dua kali. Kalau periodenya sudah terisi, perbaiki
            lewat Edit, jangan membuat laporan kedua.
        </p>
    </div>

    {{-- enctype WAJIB — tanpa ini berkas bukti tidak pernah sampai ke server,
         dan formnya tetap tersimpan seolah tidak ada yang salah. --}}
    <form method="POST" action="{{ route('omzet.store', $participant) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2 space-y-4">
                @include('omzet.partials.pilih-periode', ['publik' => false])
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-text-main mb-1">Omzet (Rp)</label>
                <input type="number" name="omzet" required min="0" step="1000"
                       value="{{ old('omzet') }}"
                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                @error('omzet') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            @include('omzet.partials.input-laba', ['hpp' => null, 'biaya' => null])
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
            <textarea name="catatan" rows="3"
                      class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('catatan') }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        @include('omzet.partials.input-bukti', ['laporan' => null, 'publik' => false])

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('participants.show', $participant) }}"
               class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
