@extends('layouts.app')

@section('title', 'Binaan Saya')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Binaan Saya</h2>
            <p class="text-sm text-gray-500">
                Peserta Cahaya Preneur yang menjadi tanggung jawab pendampingan {{ $pendamping->nama }}.
            </p>
        </div>
        <a href="{{ route('participants.create') }}"
           class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-primary/90 transition">
            + Tambah Binaan
        </a>
    </div>

    {{-- SEARCH & FILTER — hanya menyaring binaan sendiri. Query-nya sudah
         ter-scope ke pendamping yang login di BinaanSayaController, jadi
         tidak ada cara menampilkan binaan Pendamping lain dari sini. --}}
    <form method="GET" action="{{ route('binaan.index') }}"
          class="bg-white border border-gray-100 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari nama peserta / nama usaha..."
               class="md:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">

        <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>

        <div class="flex gap-2">
            <button type="submit"
                    class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-secondary/90 transition">
                Cari
            </button>
            <a href="{{ route('binaan.index') }}" class="text-sm text-gray-500 px-4 py-2 hover:text-gray-700">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="px-4 py-3">Nama Peserta</th>
                    <th class="px-4 py-3">Nama Usaha</th>
                    <th class="px-4 py-3">Jenis Usaha</th>
                    <th class="px-4 py-3">Nomor HP</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($participants as $participant)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3">
                            {{ $participant->nama_peserta }}
                            @if ($participant->koperasiMember)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-cahaya-secondary/20 text-cahaya-primary">Anggota Koperasi</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $participant->nama_usaha }}</td>
                        <td class="px-4 py-3">{{ $participant->kategori_usaha }}</td>
                        <td class="px-4 py-3">{{ $participant->nomor_hp }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                {{ match($participant->status) {
                                    'Aktif' => 'bg-green-100 text-green-700',
                                    'Graduasi' => 'bg-cahaya-secondary/20 text-cahaya-primary',
                                    default => 'bg-gray-100 text-gray-600',
                                } }}">
                                {{ $participant->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                            <a href="{{ route('participants.show', $participant) }}" class="text-cahaya-secondary hover:underline">Lihat</a>
                            <a href="{{ route('participants.edit', $participant) }}" class="text-cahaya-primary hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400 italic">
                            Belum ada peserta yang ditugaskan kepada Anda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
