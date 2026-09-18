@extends('layouts.app')

@section('title', 'Tambah Anggota')

@section('content')
@php
    // Tiga tampilan dari satu halaman:
    //   $dari === null      -> pemilihan cara (langkah 1)
    //   $dari === 'peserta' -> form data khusus koperasi, identitas dari peserta
    //   $dari === 'baru'    -> form lengkap untuk orang yang bukan peserta
@endphp

<div class="max-w-2xl space-y-5">

    @if (session('error'))
        <div class="bg-status-danger/10 border border-status-danger/30 text-status-danger text-sm rounded-xl px-4 py-3">
            {{ session('error') }}
        </div>
    @endif

    {{-- ============================================================
         LANGKAH 1 — PILIH CARA MENAMBAH ANGGOTA
         ============================================================ --}}
    @if (! $dari)
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-text-main mb-1">Tambah Anggota Koperasi</h2>
            <p class="text-xs text-text-secondary mb-6">
                Pilih salah satu. Kalau orangnya sudah terdaftar sebagai peserta Cahaya Preneur,
                gunakan opsi pertama supaya datanya tidak diinput dua kali.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="{{ route('koperasi.anggota.pilih-peserta') }}"
                   class="block border border-border-soft rounded-xl p-5 hover:border-cahaya-primary hover:bg-surface-bg transition">
                    <p class="text-sm font-semibold text-text-main mb-1">Pilih dari Database Peserta</p>
                    <p class="text-xs text-text-secondary">
                        Cari peserta Cahaya Preneur yang sudah ada. Nama, HP, alamat, dan data usahanya
                        langsung terpakai — Anda tinggal mengisi data koperasinya.
                    </p>
                </a>

                <a href="{{ route('koperasi.anggota.create', ['dari' => 'baru']) }}"
                   class="block border border-border-soft rounded-xl p-5 hover:border-cahaya-primary hover:bg-surface-bg transition">
                    <p class="text-sm font-semibold text-text-main mb-1">Input Anggota Baru</p>
                    <p class="text-xs text-text-secondary">
                        Untuk calon anggota yang bukan peserta Cahaya Preneur. Identitasnya diisi di sini.
                    </p>
                </a>
            </div>

            <div class="pt-5 mt-5 border-t border-border-soft">
                <a href="{{ route('koperasi.anggota.index') }}" class="text-sm text-text-secondary hover:text-text-main">← Kembali ke Daftar Anggota</a>
            </div>
        </div>

    {{-- ============================================================
         LANGKAH 2 — FORM
         ============================================================ --}}
    @else
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-text-main mb-1">
                {{ $dari === 'peserta' ? 'Daftarkan Peserta sebagai Anggota' : 'Tambah Anggota Baru' }}
            </h2>
            <p class="text-xs text-text-secondary mb-6">Nomor anggota akan dibuat otomatis oleh sistem.</p>

            <form method="POST" action="{{ route('koperasi.anggota.store') }}" class="space-y-5">
                @csrf

                @if ($dari === 'peserta' && $participant)
                    {{-- Identitas TIDAK diketik ulang. Yang dikirim ke server hanya
                         participant_id; nama/HP/alamat diambil server dari
                         [Participant], jadi tidak mungkin lahir baris identitas kedua. --}}
                    <input type="hidden" name="participant_id" value="{{ $participant->id }}">

                    <div class="bg-surface-bg border border-border-soft rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-[13px] font-semibold text-text-main">Data dari Database Peserta</p>
                            <a href="{{ route('koperasi.anggota.pilih-peserta') }}" class="text-xs text-status-info hover:underline">Ganti peserta</a>
                        </div>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            @foreach ([
                                'Nama' => $participant->nama_peserta,
                                'Nomor HP' => $participant->nomor_hp,
                                'Email' => $participant->email,
                                'Alamat' => $participant->alamat,
                                'Nama Usaha' => $participant->nama_usaha,
                                'Jenis Usaha' => $participant->kategori_usaha,
                            ] as $label => $nilai)
                                <div>
                                    <dt class="text-[11px] text-text-secondary">{{ $label }}</dt>
                                    <dd class="text-text-main">{{ $nilai ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <p class="text-[11px] text-text-secondary mt-3">
                            Data di atas tetap milik modul Cahaya Preneur. Perubahannya di sana otomatis ikut di sini —
                            tidak ada salinan terpisah yang bisa berbeda.
                        </p>
                    </div>
                    @error('participant_id') <p class="text-xs text-status-danger">{{ $message }}</p> @enderror
                @else
                    <input type="hidden" name="dari" value="baru">

                    <div>
                        <h3 class="text-[13px] font-semibold text-text-main mb-3">Identitas Anggota</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-text-main mb-1">Nama Lengkap</label>
                                <input type="text" name="nama" value="{{ old('nama') }}"
                                       class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                                @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-text-main mb-1">Nomor HP</label>
                                <input type="text" name="nomor_hp" value="{{ old('nomor_hp') }}"
                                       class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                                @error('nomor_hp') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-text-main mb-1">Alamat <span class="text-text-secondary">(boleh kosong)</span></label>
                                <textarea name="alamat" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">{{ old('alamat') }}</textarea>
                                @error('alamat') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <p class="text-[11px] text-text-secondary mt-2">
                            Sudah pernah terdaftar sebagai peserta Cahaya Preneur?
                            <a href="{{ route('koperasi.anggota.pilih-peserta') }}" class="text-status-info hover:underline">Pilih dari database peserta</a>
                            supaya tidak ada data ganda.
                        </p>
                    </div>
                @endif

                {{-- DATA KHUSUS KOPERASI — satu-satunya bagian yang benar-benar
                     milik modul Koperasi Cahaya. --}}
                <div class="border-t border-border-soft pt-5">
                    <h3 class="text-[13px] font-semibold text-text-main mb-3">Data Keanggotaan Koperasi</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Tanggal Bergabung</label>
                            <input type="date" name="tanggal_bergabung" value="{{ old('tanggal_bergabung', now()->format('Y-m-d')) }}"
                                   class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                            @error('tanggal_bergabung') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Status Keanggotaan</label>
                            <select name="status" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                                @foreach ($statusOptions as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'Aktif') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Simpanan Pokok <span class="text-text-secondary">(boleh kosong)</span></label>
                            <input type="number" name="simpanan_pokok" min="0" step="1000" value="{{ old('simpanan_pokok') }}"
                                   class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                            @error('simpanan_pokok') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1">Simpanan Wajib <span class="text-text-secondary">(boleh kosong)</span></label>
                            <input type="number" name="simpanan_wajib" min="0" step="1000" value="{{ old('simpanan_wajib') }}"
                                   class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                            @error('simpanan_wajib') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-text-main mb-1">
                                Sumber Anggota <span class="text-text-secondary">(boleh kosong)</span>
                            </label>
                            <select name="sumber_anggota" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                                <option value="">— Belum ditentukan —</option>
                                @foreach ($sumberOptions as $sumber)
                                    <option value="{{ $sumber }}"
                                        @selected(old('sumber_anggota', $dari === 'peserta' ? \App\Models\KoperasiMember::SUMBER_PESERTA : '') === $sumber)>
                                        {{ $sumber }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-text-secondary mt-1">
                                Dipakai untuk mengukur berapa anggota koperasi yang lahir dari pembinaan Cahaya Preneur.
                            </p>
                            @error('sumber_anggota') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-text-main mb-1">Unit Usaha <span class="text-text-secondary">(boleh kosong)</span></label>
                            <select name="unit_usaha_id" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                                <option value="">— Belum ditentukan —</option>
                                @foreach ($unitUsahaOptions as $unit)
                                    <option value="{{ $unit->id }}" @selected((string) old('unit_usaha_id') === (string) $unit->id)>{{ $unit->nama }}</option>
                                @endforeach
                            </select>
                            @error('unit_usaha_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-text-main mb-1">Keterangan <span class="text-text-secondary">(boleh kosong)</span></label>
                            <textarea name="keterangan" rows="2" class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">{{ old('keterangan') }}</textarea>
                            @error('keterangan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p class="text-[11px] text-text-secondary mt-3">
                        Simpanan awal yang diisi di sini dicatat sebagai riwayat simpanan biasa, jadi saldonya
                        selalu sama dengan yang tampil di laporan simpanan.
                    </p>
                </div>

                <div class="pt-2 flex gap-2">
                    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                        Simpan
                    </button>
                    <a href="{{ route('koperasi.anggota.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
