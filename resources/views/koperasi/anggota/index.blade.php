@extends('layouts.app')

@section('title', 'Keanggotaan Koperasi')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                Keanggotaan Koperasi
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                Daftar anggota Koperasi Cahaya
            </p>
        </div>
        <div class="flex items-center gap-2">
                                <a href="{{ route('koperasi.anggota.create') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    + Tambah Anggota
                </a>

        </div>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($members->isEmpty())
            <div class="text-center py-14">
                <p class="text-sm font-medium text-text-main mb-1">
                    Belum Ada Anggota
                </p>
                <p class="text-xs text-text-secondary mb-4">
                    Belum ada anggota koperasi yang terdaftar.
                </p>
                                    <a href="{{ route('koperasi.anggota.create') }}" class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">+ Tambah Anggota</a>

            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Nomor Anggota</th>
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Nomor HP</th>
                            <th class="px-4 py-3 font-medium">Tanggal Bergabung</th>
                            <th class="px-4 py-3 font-medium">Peserta Cahaya Preneur</th>
                            <th class="px-4 py-3 font-medium">Unit Usaha</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 text-text-secondary">{{ $m->nomor_anggota }}</td>
                                {{-- namaTampil() membaca dari peserta kalau anggota ini
                                     berasal dari database peserta — jadi memperbaiki nama
                                     di Data Peserta langsung benar juga di sini. --}}
                                <td class="px-4 py-3 font-medium text-text-main">{{ $m->namaTampil() }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $m->nomorHpTampil() ?: '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ optional($m->tanggal_bergabung)->format('d-m-Y') ?: '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $m->participant->nama_peserta ?? '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $m->unitUsaha->nama ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                        {{ $m->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                                        {{ $m->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex gap-1.5 items-center">
                                            <a href="{{ route('koperasi.anggota.show', $m) }}"
                                               class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-surface-bg text-status-info hover:bg-primary-light transition">
                                                Detail
                                            </a>
                                            <a href="{{ route('koperasi.anggota.edit', $m) }}"
                                               class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-primary-light text-cahaya-primary hover:opacity-80 transition">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('koperasi.anggota.destroy', $m) }}"
                                                  onsubmit="return confirm('Pindahkan anggota ini ke arsip? Riwayat simpanannya tetap tersimpan.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-surface-bg text-status-danger hover:bg-status-danger/10 transition">
                                                    Hapus
                                                </button>
                                            </form>

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($members->isNotEmpty())
        <div>{{ $members->links() }}</div>
    @endif
</div>
@endsection
