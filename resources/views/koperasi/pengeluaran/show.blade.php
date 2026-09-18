@extends('layouts.app')

@section('title', 'Detail Pengeluaran')

@section('content')
<div class="max-w-xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Detail Pengeluaran</h2>

    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $pengeluaran->status === 'Aktif' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                {{ $pengeluaran->status }}
            </span>
            <p class="text-2xl font-bold text-koperasi-primary">{{ rupiah($pengeluaran->nominal) }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Tanggal</p>
                <p class="font-medium text-gray-800">{{ $pengeluaran->tanggal->format('d-m-Y') }}</p>
            </div>
            <div>
                <p class="text-gray-500">Unit</p>
                <p class="font-medium text-gray-800">{{ $pengeluaran->labelUnit() }}</p>
            </div>
            <div>
                <p class="text-gray-500">Kategori</p>
                <p class="font-medium text-gray-800">{{ $pengeluaran->kategori }}</p>
            </div>
            <div>
                <p class="text-gray-500">Metode Pembayaran</p>
                <p class="font-medium text-gray-800">{{ $pengeluaran->metode_pembayaran }}</p>
            </div>
        </div>

        @if ($pengeluaran->keterangan)
            <div>
                <p class="text-gray-500 text-sm">Keterangan</p>
                <p class="text-sm text-gray-800">{{ $pengeluaran->keterangan }}</p>
            </div>
        @endif

        @if ($pengeluaran->bukti_path)
            <div>
                <p class="text-gray-500 text-sm mb-1">Bukti Pengeluaran</p>
                <a href="{{ route('koperasi.pengeluaran.bukti', $pengeluaran) }}" target="_blank" class="text-koperasi-primary text-sm hover:underline">
                    Lihat {{ $pengeluaran->bukti_asli }}
                </a>
            </div>
        @endif

        <div class="flex gap-2 pt-2 border-t border-gray-100">
            {{-- Pengeluaran yang sudah dibatalkan tidak menawarkan Edit:
                 mengubah angka yang sudah berhenti dihitung hanya membuat
                 jejaknya membingungkan. Pulihkan dulu, baru koreksi. --}}
            @if (! $pengeluaran->dibatalkan())
                <a href="{{ route('koperasi.pengeluaran.edit', $pengeluaran) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
                    Edit
                </a>
            @endif
            <a href="{{ route('koperasi.pengeluaran.index') }}" class="text-sm text-gray-500 px-4 py-2 hover:text-gray-700">
                Kembali
            </a>
        </div>

        @if ($pengeluaran->dibatalkan())
            <div class="pt-4 border-t border-gray-100">
                <p class="text-sm font-semibold text-gray-800 mb-1">Pengeluaran Dibatalkan</p>
                <p class="text-xs text-gray-500">
                    Dibatalkan {{ $pengeluaran->dibatalkan_at?->format('d-m-Y H:i') ?: '—' }}
                    @if ($pengeluaran->pembatal) oleh {{ $pengeluaran->pembatal->name }} @endif
                </p>
                @if ($pengeluaran->alasan_pembatalan)
                    <p class="text-xs text-amber-600 mt-1">Alasan: {{ $pengeluaran->alasan_pembatalan }}</p>
                @endif
                <p class="text-[11px] text-gray-500 mt-2">
                    Datanya tetap tersimpan, tapi sudah tidak dihitung di laba rugi maupun laporan pengeluaran.
                </p>

                <form method="POST" action="{{ route('koperasi.pengeluaran.pulihkan', $pengeluaran) }}" class="mt-3"
                      onsubmit="return confirm('Pulihkan pengeluaran ini?

Pengeluaran akan kembali dihitung di laba rugi.');">
                    @csrf
                    <button type="submit" class="border-2 border-koperasi-primary text-koperasi-primary text-sm font-medium px-4 py-2 rounded-md hover:bg-gray-50 transition">
                        Pulihkan Pengeluaran
                    </button>
                </form>
            </div>
        @else
            {{-- Alasan pembatalan WAJIB, jadi formnya butuh ruang menulis —
                 tidak bisa cuma sebuah tombol di deretan aksi. --}}
            <details class="pt-4 border-t border-gray-100">
                <summary class="text-sm font-medium text-red-600 cursor-pointer">Batalkan Pengeluaran</summary>

                <form method="POST" action="{{ route('koperasi.pengeluaran.cancel', $pengeluaran) }}" class="mt-3 space-y-3 max-w-xl">
                    @csrf
                    <p class="text-xs text-gray-500">
                        Pengeluaran TIDAK dihapus — barisnya tetap ada, hanya berhenti dihitung di laba rugi
                        dan laporan pengeluaran. Alasannya ikut tersimpan.
                    </p>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Alasan Pembatalan</label>
                        <input type="text" name="alasan_pembatalan" required
                               placeholder="Contoh: salah input nominal, pembelian batal"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        @error('alasan_pembatalan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="bg-red-50 text-red-600 text-sm font-medium px-4 py-2 rounded-md hover:bg-red-100 transition"
                            onclick="return confirm('Batalkan pengeluaran ini? Datanya tetap tersimpan sebagai jejak.');">
                        Batalkan Pengeluaran
                    </button>
                </form>
            </details>
        @endif
    </div>
</div>
@endsection
