@extends('layouts.app')

@section('title', 'Tambah Legalitas / Sertifikasi')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-1">+ Tambah Legalitas / Sertifikasi</h2>
    <p class="text-sm text-gray-500 mb-6">{{ $participant->nama_peserta }} — {{ $participant->nama_usaha }}</p>

    <form method="POST" action="{{ route('legalitas.store', $participant) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Legalitas / Sertifikasi</label>
            <input type="text" name="jenis_dokumen" list="saranNamaLegalitas" value="{{ old('jenis_dokumen') }}"
                   placeholder="Contoh: NIB, Sertifikat Organik, Merek, dll — bebas sesuai usaha peserta"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">
            <datalist id="saranNamaLegalitas">
                @foreach ($saranNama as $saran)
                    <option value="{{ $saran }}"></option>
                @endforeach
            </datalist>
            <p class="text-xs text-gray-400 mt-1">Boleh ketik nama apa saja sesuai kebutuhan usaha peserta, tidak terbatas pada daftar saran.</p>
            @error('jenis_dokumen') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status }}" @selected(old('status', 'Belum Ada') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Dokumen <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="text" name="nomor_dokumen" value="{{ old('nomor_dokumen') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('nomor_dokumen') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Terbit <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="date" name="tanggal_terbit" value="{{ old('tanggal_terbit') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('tanggal_terbit') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berlaku <span class="text-gray-400">(boleh kosong)</span></label>
                <input type="date" name="tanggal_berlaku" value="{{ old('tanggal_berlaku') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('tanggal_berlaku') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan <span class="text-gray-400">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">{{ old('catatan') }}</textarea>
            @error('catatan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Upload Dokumen <span class="text-gray-400">(PDF/JPG/PNG, maks 5MB — boleh kosong)</span></label>
            <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @error('file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:bg-cahaya-primary/90 transition">
                Simpan
            </button>
            <a href="{{ route('participants.show', $participant) }}" class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
