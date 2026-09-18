@extends('layouts.app')

@section('title', 'Edit Anggota')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Edit Anggota — {{ $member->namaTampil() }}</h2>
    <p class="text-xs text-text-secondary mb-6">Nomor Anggota: {{ $member->nomor_anggota }}</p>

    <form method="POST" action="{{ route('koperasi.anggota.update', $member) }}" class="space-y-5">
        @csrf
        @method('PUT')

        {{-- ============================================================
             IDENTITAS.

             Anggota yang berasal dari peserta Cahaya Preneur TIDAK bisa
             diedit identitasnya di sini — sumbernya ada di Data Peserta.
             Kalau boleh diketik ulang di dua tempat, cepat atau lambat
             keduanya akan berbeda, dan itulah duplikasi yang dihindari.
             ============================================================ --}}
        @if ($member->dariPeserta())
            <div class="bg-surface-bg border border-border-soft rounded-xl p-4">
                <p class="text-[13px] font-semibold text-text-main mb-3">Identitas dari Database Peserta</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    @foreach ([
                        'Nama' => $member->namaTampil(),
                        'Nomor HP' => $member->nomorHpTampil(),
                        'Email' => $member->emailTampil(),
                        'Alamat' => $member->alamatTampil(),
                        'Nama Usaha' => $member->namaUsahaTampil(),
                        'Jenis Usaha' => $member->jenisUsahaTampil(),
                    ] as $label => $nilai)
                        <div>
                            <dt class="text-[11px] text-text-secondary">{{ $label }}</dt>
                            <dd class="text-text-main">{{ $nilai ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-[11px] text-text-secondary mt-3">
                    Identitas hanya bisa diperbaiki di modul Cahaya Preneur (Data Peserta),
                    supaya seluruh CahayaOS memakai angka dan ejaan yang sama.
                </p>

                {{-- Salinan cadangan identitas ikut dikirim supaya tautan ke peserta
                     tetap bisa DILEPAS (jadi anggota mandiri) tanpa barisnya kehilangan
                     nama & nomor HP — kolom keduanya NOT NULL di database. --}}
                <input type="hidden" name="nama" value="{{ $member->namaTampil() }}">
                <input type="hidden" name="nomor_hp" value="{{ $member->nomorHpTampil() }}">
                <input type="hidden" name="alamat" value="{{ $member->alamatTampil() }}">
            </div>
        @else
            <div>
                <h3 class="text-[13px] font-semibold text-text-main mb-3">Identitas Anggota</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Nama Lengkap</label>
                        <input type="text" name="nama" value="{{ old('nama', $member->nama) }}"
                               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                        @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1">Nomor HP</label>
                        <input type="text" name="nomor_hp" value="{{ old('nomor_hp', $member->nomor_hp) }}"
                               class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                        @error('nomor_hp') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-text-main mb-1">Alamat <span class="text-text-secondary">(boleh kosong)</span></label>
                        <textarea name="alamat" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">{{ old('alamat', $member->alamat) }}</textarea>
                        @error('alamat') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        {{-- DATA KHUSUS KOPERASI --}}
        <div class="border-t border-border-soft pt-5">
            <h3 class="text-[13px] font-semibold text-text-main mb-3">Data Keanggotaan Koperasi</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Tanggal Bergabung</label>
                    <input type="date" name="tanggal_bergabung" value="{{ old('tanggal_bergabung', $member->tanggal_bergabung->format('Y-m-d')) }}"
                           class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                    @error('tanggal_bergabung') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-main mb-1">Status Keanggotaan</label>
                    <select name="status" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status }}" @selected(old('status', $member->status) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-text-secondary mt-1">Data historis tidak akan hilang — gunakan "Tidak Aktif", jangan menghapus.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">
                        Sumber Anggota <span class="text-text-secondary">(boleh kosong)</span>
                    </label>
                    <select name="sumber_anggota" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        <option value="">— Belum ditentukan —</option>
                        @foreach ($sumberOptions as $sumber)
                            <option value="{{ $sumber }}" @selected(old('sumber_anggota', $member->sumber_anggota) === $sumber)>{{ $sumber }}</option>
                        @endforeach
                    </select>
                    @error('sumber_anggota') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Unit Usaha <span class="text-text-secondary">(boleh kosong)</span></label>
                    <select name="unit_usaha_id" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        <option value="">— Belum ditentukan —</option>
                        @foreach ($unitUsahaOptions as $unit)
                            <option value="{{ $unit->id }}" @selected((string) old('unit_usaha_id', $member->unit_usaha_id) === (string) $unit->id)>{{ $unit->nama }}</option>
                        @endforeach
                    </select>
                    @error('unit_usaha_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">
                        Hubungkan dengan Peserta Cahaya Preneur <span class="text-text-secondary">(opsional)</span>
                    </label>
                    <select name="participant_id" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                        <option value="">— Bukan peserta Cahaya Preneur —</option>
                        @foreach ($participants as $p)
                            <option value="{{ $p->id }}" @selected((string) old('participant_id', $member->participant_id) === (string) $p->id)>{{ $p->nama_peserta }} — {{ $p->nama_usaha }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-text-secondary mt-1">
                        Daftar ini hanya berisi peserta yang belum punya keanggotaan, supaya satu orang
                        tidak bisa terdaftar dua kali.
                    </p>
                    @error('participant_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary">(boleh kosong)</span></label>
                    <textarea name="keterangan" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">{{ old('keterangan', $member->keterangan) }}</textarea>
                    @error('keterangan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('koperasi.anggota.show', $member) }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
