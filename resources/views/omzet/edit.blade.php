@extends('layouts.app')

@section('title', 'Edit Laporan Omzet')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-2">
        Edit Laporan Omzet — {{ $report->participant->nama_peserta ?? '-' }}
    </h2>
    <p class="text-xs text-gray-500 mb-6">
        Periode {{ $report->periodeLabel() }} ({{ strtolower($report->jenis_periode) }}, {{ $report->rentangLabel() }})
        — periode tidak dapat diubah, hanya nilai omzet, komponen biaya, bukti, dan catatan.
    </p>

    {{-- enctype WAJIB — tanpa ini berkas bukti tidak pernah sampai ke server. --}}
    <form method="POST" action="{{ route('omzet.update', $report) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Omzet (Rp)</label>
                <input type="number" min="0" step="1" name="omzet" value="{{ old('omzet', $report->omzet) }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
                @error('omzet') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @include('omzet.partials.input-laba', [
                'hpp' => $report->hpp,
                'biaya' => $report->biaya_operasional,
            ])
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
            <textarea name="catatan" rows="3"
                      class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">{{ old('catatan', $report->catatan) }}</textarea>
            @error('catatan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mt-4">
            @include('omzet.partials.input-bukti', ['laporan' => $report, 'publik' => false])
        </div>

        <div class="mt-6 flex gap-2">
            <button type="submit"
                    class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
                Simpan
            </button>
            <a href="{{ route('omzet.index') }}" class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
