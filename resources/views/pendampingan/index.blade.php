@extends('layouts.app')

@section('title', 'Monitoring Pendampingan')

@section('content')
<div class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">Monitoring Pendampingan</h2>

    {{-- FILTER --}}
    <form method="GET" action="{{ route('pendampingan.index') }}"
          class="bg-white border border-gray-100 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        <select name="pendamping_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>

        <select name="participant_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Peserta</option>
            @foreach ($participants as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['participant_id'] ?? '') === (string) $p->id)>{{ $p->nama_peserta }}</option>
            @endforeach
        </select>

        <select name="jenis_kegiatan" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Jenis Kegiatan</option>
            @foreach ($jenisOptions as $jenis)
                <option value="{{ $jenis }}" @selected(($filters['jenis_kegiatan'] ?? '') === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>

        <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}"
               class="rounded-md border border-gray-300 px-3 py-2 text-sm" title="Dari tanggal">
        <input type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}"
               class="rounded-md border border-gray-300 px-3 py-2 text-sm" title="Sampai tanggal">

        <div class="md:col-span-5 flex gap-2">
            <button type="submit" class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-secondary/90 transition">
                Filter
            </button>
            <a href="{{ route('pendampingan.index') }}" class="text-sm text-gray-500 px-4 py-2 hover:text-gray-700">Reset</a>
        </div>
    </form>

    {{-- TABLE --}}
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Peserta</th>
                    <th class="px-4 py-3">Pendamping</th>
                    <th class="px-4 py-3">Jenis Kegiatan</th>
                    <th class="px-4 py-3">Materi</th>
                    <th class="px-4 py-3">Catatan</th>
                    <th class="px-4 py-3">Tindak Lanjut</th>
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-b last:border-0 align-top">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $record->tanggal->format('d-m-Y') }}</td>
                        <td class="px-4 py-3">
                            {{-- participantTermasukArsip: baris riwayat milik peserta
                                 yang sudah diarsipkan tetap menyebut nama pemiliknya.
                                 Tanpa ini halamannya bahkan tidak terbuka, karena
                                 route() menolak parameter null. --}}
                            @php($pesertaBaris = $record->participantTermasukArsip)
                            <a href="{{ $pesertaBaris ? route('participants.show', $pesertaBaris) : '#' }}" class="text-cahaya-secondary hover:underline">
                                {{ $pesertaBaris?->nama_peserta ?? 'Peserta tidak ditemukan' }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $record->pendamping->nama ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $record->jenis_kegiatan }}</td>
                        <td class="px-4 py-3 max-w-xs">{{ $record->materi }}</td>
                        <td class="px-4 py-3 max-w-xs text-gray-500">{{ $record->catatan ?: '—' }}</td>
                        <td class="px-4 py-3 max-w-xs text-gray-500">{{ $record->tindak_lanjut ?: '—' }}</td>
                        <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                            {{-- Catatan yang salah diperbaiki di tempat, bukan
                                 dengan menambah catatan tandingan. --}}
                            <a href="{{ route('pendampingan.edit', $record) }}" class="text-cahaya-primary hover:underline">Edit</a>
                            <form method="POST" action="{{ route('pendampingan.destroy', $record) }}" class="inline"
                                  onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Pastikan data ini memang sudah tidak diperlukan.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus Permanen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400 italic">
                            Belum ada catatan pendampingan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $records->links() }}</div>
</div>
@endsection
