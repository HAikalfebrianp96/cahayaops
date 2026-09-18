@extends('layouts.app')

@section('title', 'Kategori Pengeluaran')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">
                Kategori Pengeluaran
            </h2>
            <p class="text-sm text-gray-500">
                Kelola daftar kategori. Nonaktifkan untuk kategori yang tak dipakai lagi, hapus untuk yang salah dibuat.
            </p>
        </div>
        <div class="flex items-center gap-2">
                                <a href="{{ route('koperasi.kategori-pengeluaran.create') }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
                    + Tambah Kategori
                </a>

        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[480px]">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="py-2 px-4">Nama Kategori</th>
                    <th class="py-2 px-4">Status</th>
                    <th class="py-2 px-4">Jumlah Dipakai</th>
                    <th class="py-2 px-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($kategori as $k)
                    <tr class="border-b last:border-0">
                        <td class="py-2 px-4 font-medium">{{ $k->nama }}</td>
                        <td class="py-2 px-4">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $k->status === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $k->status }}
                            </span>
                        </td>
                        <td class="py-2 px-4">{{ $k->pengeluaran_count }} transaksi</td>
                        <td class="py-2 px-4 space-x-2 whitespace-nowrap">
                            <a href="{{ route('koperasi.kategori-pengeluaran.edit', $k) }}" class="text-koperasi-primary hover:underline">Edit</a>
                                                        <form method="POST" action="{{ route('koperasi.kategori-pengeluaran.toggle', $k) }}" class="inline"
                                  onsubmit="return confirm('{{ $k->status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' }} kategori \'{{ $k->nama }}\'?');">
                                @csrf
                                <button type="submit" class="text-xs {{ $k->status === 'Aktif' ? 'text-red-500' : 'text-green-600' }} hover:underline">
                                    {{ $k->status === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>

                                {{-- Nonaktifkan (toggle) dan Hapus BERBEDA: nonaktifkan untuk
                                     kategori yang masih sah tapi tak dipakai lagi, hapus untuk
                                     kategori yang memang salah dibuat. Keduanya sama-sama tidak
                                     menghilangkan barisnya. --}}
                                <form method="POST" action="{{ route('koperasi.kategori-pengeluaran.destroy', $k) }}" class="inline"
                                      onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Pastikan data ini memang sudah tidak diperlukan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus Permanen</button>
                                </form>

                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-gray-400 italic">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>
</div>
@endsection
