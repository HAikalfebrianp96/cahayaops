@extends('layouts.app')

@section('title', 'Edit Data Pelatihan')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Edit Data Pelatihan</h2>
    <p class="text-xs text-text-secondary mb-6">
        Peserta: <span class="font-medium text-text-main">{{ $participant?->nama_peserta ?? 'Peserta tidak ditemukan' }}</span>
        @if ($participant)&bull; {{ $participant->nama_usaha }}@endif
        @if ($participant?->trashed())<span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary">Peserta diarsipkan</span>@endif
    </p>

    <form method="POST" action="{{ route('training.update', $record) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tanggal</label>
                <input type="date" name="tanggal" required
                       value="{{ old('tanggal', $record->tanggal->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                @error('tanggal') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Status</label>
                <select name="status" required class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}" @selected(old('status', $record->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Nama Pelatihan</label>
                <input type="text" name="nama_pelatihan" required
                       value="{{ old('nama_pelatihan', $record->nama_pelatihan) }}"
                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                @error('nama_pelatihan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Penyelenggara <span class="text-text-secondary">(boleh kosong)</span></label>
                <input type="text" name="penyelenggara"
                       value="{{ old('penyelenggara', $record->penyelenggara) }}"
                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                @error('penyelenggara') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
            <textarea name="catatan" rows="3"
                      class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('catatan', $record->catatan) }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan Perubahan
            </button>
            <a href="{{ route('participants.show', $participant) }}"
               class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
