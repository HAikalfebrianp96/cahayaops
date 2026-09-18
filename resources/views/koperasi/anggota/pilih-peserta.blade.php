@extends('layouts.app')

@section('title', 'Pilih dari Database Peserta')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">Pilih dari Database Peserta</h2>
            <p class="text-sm text-text-secondary mt-0.5">
                Cari peserta Cahaya Preneur, lalu lanjutkan mengisi data khusus koperasi.
                Data dasarnya otomatis terpakai — tidak perlu diketik ulang.
            </p>
        </div>
        <a href="{{ route('koperasi.anggota.create') }}"
           class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">← Kembali</a>
    </div>

    <form method="GET" action="{{ route('koperasi.anggota.pilih-peserta') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari nama, nama usaha, atau nomor HP..."
               class="md:col-span-2 rounded-xl border border-border-soft px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">

        <label class="flex items-center gap-2 text-sm text-text-main">
            <input type="checkbox" name="belum_anggota" value="1" @checked($filters['belum_anggota'] ?? false)
                   class="rounded border-border-soft">
            Hanya yang belum jadi anggota
        </label>

        <div class="flex gap-2">
            <button type="submit"
                    class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Cari
            </button>
            <a href="{{ route('koperasi.anggota.pilih-peserta') }}"
               class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($participants->isEmpty())
            <div class="text-center py-14">
                <p class="text-sm font-medium text-text-main mb-1">Peserta Tidak Ditemukan</p>
                <p class="text-xs text-text-secondary">Coba kata kunci lain, atau daftarkan sebagai anggota baru.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Nama Peserta</th>
                            <th class="px-4 py-3 font-medium">Nomor HP</th>
                            <th class="px-4 py-3 font-medium">Nama Usaha</th>
                            <th class="px-4 py-3 font-medium">Jenis Usaha</th>
                            <th class="px-4 py-3 font-medium">Status Keanggotaan</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participants as $p)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 font-medium text-text-main">{{ $p->nama_peserta }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $p->nomor_hp }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $p->nama_usaha }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $p->kategori_usaha }}</td>
                                <td class="px-4 py-3">
                                    @if ($p->koperasiMember)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-primary-light text-status-success">
                                            Sudah anggota &bull; {{ $p->koperasiMember->nomor_anggota }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-text-secondary">
                                            Belum anggota
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($p->koperasiMember)
                                        {{-- Sengaja TIDAK bisa dipilih lagi: satu orang hanya
                                             boleh punya satu keanggotaan, tanpa identitas kedua. --}}
                                        <a href="{{ route('koperasi.anggota.show', $p->koperasiMember) }}"
                                           class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-surface-bg text-status-info hover:bg-primary-light transition">
                                            Lihat Anggota
                                        </a>
                                    @else
                                        <a href="{{ route('koperasi.anggota.create', ['participant_id' => $p->id]) }}"
                                           class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-cahaya-primary text-white hover:opacity-90 transition">
                                            Jadikan Anggota
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>{{ $participants->links() }}</div>
</div>
@endsection
