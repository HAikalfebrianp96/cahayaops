@extends('layouts.app')

@section('title', $program ? 'Edit Program Dampak' : 'Program Dampak Baru')

@section('content')
@php
    $aksi = $program ? route('impact.update', $program) : route('impact.store');
    $nilai = fn (string $k, $bawaan = null) => old($k, $program->{$k} ?? $bawaan);
    $kelasInput = 'w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30';
@endphp

<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">
        {{ $program ? 'Edit Program Dampak' : 'Program Dampak Baru' }}
    </h2>
    <p class="text-xs text-text-secondary mb-5">
        Program membatasi pertanyaan &ldquo;dampaknya berapa&rdquo;: peserta yang mana, rentang waktu kapan,
        investasi yang mana.
    </p>

    <form method="POST" action="{{ $aksi }}" class="space-y-4">
        @csrf
        @if ($program) @method('PUT') @endif

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Nama Program</label>
            <input type="text" name="nama" required maxlength="255" value="{{ $nilai('nama') }}"
                   placeholder="Mis. Pemberdayaan UMKM Cahaya Preneur 2026"
                   class="{{ $kelasInput }}">
            @error('nama') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Periode Mulai</label>
                <input type="date" name="periode_mulai" required
                       value="{{ old('periode_mulai', $program?->periode_mulai?->toDateString()) }}"
                       class="{{ $kelasInput }}">
                @error('periode_mulai') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-text-main mb-1">Periode Selesai</label>
                <input type="date" name="periode_selesai" required
                       value="{{ old('periode_selesai', $program?->periode_selesai?->toDateString()) }}"
                       class="{{ $kelasInput }}">
                @error('periode_selesai') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">
                Batasi ke Pendamping <span class="text-text-secondary font-normal">(boleh kosong)</span>
            </label>
            <select name="pendamping_id" class="{{ $kelasInput }}">
                <option value="">Seluruh peserta pada periode ini</option>
                @foreach ($pendampings as $p)
                    <option value="{{ $p->id }}" @selected((int) $nilai('pendamping_id') === $p->id)>{{ $p->nama }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-text-secondary mt-1">
                Dipakai untuk program yang memang dijalankan per wilayah. Daftar pesertanya diturunkan
                otomatis dari periode &amp; pendamping — tidak perlu dipilih satu per satu, dan tidak akan
                pernah tidak sinkron dengan data peserta yang sebenarnya.
            </p>
            @error('pendamping_id') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Status</label>
            <select name="status" required class="{{ $kelasInput }}">
                @foreach (\App\Models\ImpactProgram::STATUS_OPTIONS as $kode => $label)
                    <option value="{{ $kode }}" @selected($nilai('status', \App\Models\ImpactProgram::STATUS_DRAFT) === $kode)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="text-[11px] text-text-secondary mt-1">
                Status Final ditetapkan lewat persetujuan hasil SROI, bukan dipilih di sini — dan setelah
                Final, program terkunci dari perubahan.
            </p>
            @error('status') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Deskripsi <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
            <textarea name="deskripsi" rows="3" class="{{ $kelasInput }}">{{ $nilai('deskripsi') }}</textarea>
            @error('deskripsi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary font-normal">(boleh kosong)</span></label>
            <textarea name="catatan" rows="2" class="{{ $kelasInput }}">{{ $nilai('catatan') }}</textarea>
            @error('catatan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ $program ? route('impact.show', $program) : route('impact.index') }}"
               class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">Batal</a>
        </div>
    </form>
</div>
@endsection
