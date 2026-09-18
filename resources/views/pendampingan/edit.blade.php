@extends('layouts.app')

@section('title', 'Edit Riwayat Pendampingan')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Edit Riwayat Pendampingan</h2>
    <p class="text-xs text-text-secondary mb-6">
        Peserta: <span class="font-medium text-text-main">{{ $participant?->nama_peserta ?? 'Peserta tidak ditemukan' }}</span>
        @if ($participant)&bull; {{ $participant->nama_usaha }}@endif
        @if ($participant?->trashed())<span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary">Peserta diarsipkan</span>@endif
    </p>

    <form method="POST" action="{{ route('pendampingan.update', $record) }}" class="space-y-4">
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
                <label class="block text-xs font-medium text-text-main mb-1">Jenis Kegiatan</label>
                <select name="jenis_kegiatan" required class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                    @foreach ($jenisKegiatanOptions as $jenis)
                        <option value="{{ $jenis }}" @selected(old('jenis_kegiatan', $record->jenis_kegiatan) === $jenis)>{{ $jenis }}</option>
                    @endforeach
                </select>
                @error('jenis_kegiatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Field Pendamping hanya dirender untuk yang berhak (Admin).
                 Untuk Pendamping, nilainya dipaksa jadi dirinya sendiri di
                 controller — jadi request manual pun tidak bisa
                 mengatasnamakan pendamping lain. --}}
            @if ($bolehPilihPendamping)
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-text-main mb-1">Pendamping yang Melakukan</label>
                    <select name="pendamping_id" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        <option value="">— Tidak ditentukan —</option>
                        @foreach ($pendampings as $p)
                            <option value="{{ $p->id }}" @selected((string) old('pendamping_id', $record->pendamping_id) === (string) $p->id)>{{ $p->nama }}</option>
                        @endforeach
                    </select>
                    @error('pendamping_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Materi/Kegiatan</label>
            <textarea name="materi" required rows="3"
                      class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('materi', $record->materi) }}</textarea>
            @error('materi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2"
                      class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('catatan', $record->catatan) }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- SIKLUS TINDAK LANJUT.
             `kendala` dan `tindak_lanjut` adalah kolom LAMA yang dipakai
             ulang sebagai Permasalahan dan Rencana Tindak Lanjut — bukan
             kolom baru bernama lain, supaya 45 catatan yang sudah terisi
             tidak terpecah ke dua tempat. --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Permasalahan <span class="text-text-secondary">(boleh kosong)</span></label>
                <textarea name="kendala" rows="2" placeholder="Contoh: penjualan menurun"
                          class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('kendala', $record->kendala) }}</textarea>
                @error('kendala') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tindakan <span class="text-text-secondary">(boleh kosong)</span></label>
                <textarea name="tindakan" rows="2" placeholder="Contoh: konsultasi pemasaran digital"
                          class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('tindakan', $record->tindakan) }}</textarea>
                @error('tindakan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Hasil <span class="text-text-secondary">(boleh kosong)</span></label>
                <textarea name="hasil" rows="2" placeholder="Contoh: peserta mulai memakai WhatsApp Business"
                          class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('hasil', $record->hasil) }}</textarea>
                @error('hasil') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Rencana Tindak Lanjut <span class="text-text-secondary">(boleh kosong)</span></label>
                <textarea name="tindak_lanjut" rows="2"
                          class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('tindak_lanjut', $record->tindak_lanjut) }}</textarea>
                @error('tindak_lanjut') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Tanggal Follow Up <span class="text-text-secondary">(boleh kosong)</span></label>
                <input type="date" name="tanggal_follow_up"
                       value="{{ old('tanggal_follow_up', $record->tanggal_follow_up?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                <p class="text-[11px] text-text-secondary mt-1">Muncul di Dashboard Pendamping sebagai pengingat kunjungan berikutnya.</p>
                @error('tanggal_follow_up') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
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
