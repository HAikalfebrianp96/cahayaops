@extends('layouts.app')

@section('title', 'Peserta Cahaya Preneur')

@section('content')
<div class="space-y-4">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">
                Peserta Cahaya Preneur
            </h2>
            <p class="text-sm text-gray-500">
                Data peserta Program Cahaya Preneur — YBM PLN UP3 Bengkulu
            </p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Export mengikuti FILTER yang sedang aktif, jadi isinya persis
                 sama dengan yang terlihat di tabel bawah. --}}
            <a href="{{ route('participants.export', request()->query()) }}"
               class="text-sm text-gray-600 border border-gray-300 px-4 py-2 rounded-md hover:bg-gray-50 transition">
                Export Excel
            </a>
                                <a href="{{ route('participants.create') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-primary/90 transition">
                    + Tambah Peserta
                </a>

        </div>
    </div>

    {{-- SEARCH & FILTER --}}
    <form method="GET" action="{{ route('participants.index') }}"
          class="bg-white border border-gray-100 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3">

        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari nama peserta / nama usaha / nomor HP..."
               class="md:col-span-2 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cahaya-secondary">

        <select name="status" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>

        <select name="pendamping_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>

        <select name="kategori_usaha" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Jenis Usaha</option>
            @foreach ($kategoriOptions as $kategori)
                <option value="{{ $kategori }}" @selected(($filters['kategori_usaha'] ?? '') === $kategori)>{{ $kategori }}</option>
            @endforeach
        </select>

        <select name="kecamatan" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Kecamatan</option>
            @foreach ($kecamatanOptions as $kecamatan)
                <option value="{{ $kecamatan }}" @selected(($filters['kecamatan'] ?? '') === $kecamatan)>{{ $kecamatan }}</option>
            @endforeach
        </select>

        <select name="tahun_masuk" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Tahun Masuk</option>
            @foreach ($tahunOptions as $tahun)
                <option value="{{ $tahun }}" @selected((string) ($filters['tahun_masuk'] ?? '') === (string) $tahun)>{{ $tahun }}</option>
            @endforeach
        </select>

        {{-- Status koperasi dijawab lewat RELASI, bukan kolom salinan —
             inilah gunanya satu identitas peserta. --}}
        <select name="status_koperasi" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua Status Koperasi</option>
            <option value="anggota" @selected(($filters['status_koperasi'] ?? '') === 'anggota')>Anggota Koperasi</option>
            <option value="bukan" @selected(($filters['status_koperasi'] ?? '') === 'bukan')>Bukan Anggota</option>
        </select>

        <div class="md:col-span-4 flex gap-2">
            <button type="submit"
                    class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-secondary/90 transition">
                Cari / Filter
            </button>
            <a href="{{ route('participants.index') }}"
               class="text-sm text-gray-500 px-4 py-2 hover:text-gray-700">
                Reset
            </a>
        </div>
    </form>

    {{-- TABLE --}}
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b bg-gray-50">
                    <th class="px-4 py-3">No</th>
                    <th class="px-4 py-3">Nama Peserta</th>
                    <th class="px-4 py-3">Nama Usaha</th>
                    <th class="px-4 py-3">Jenis Usaha</th>
                    <th class="px-4 py-3">Pendamping</th>
                        <th class="px-4 py-3">Tanggal Mulai</th>
                        <th class="px-4 py-3">Status</th>

                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($participants as $index => $participant)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3">{{ $participants->firstItem() + $index }}</td>
                        <td class="px-4 py-3">
                            {{ $participant->nama_peserta }}
                            @if ($participant->koperasiMember)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-cahaya-secondary/20 text-cahaya-primary">Koperasi</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $participant->nama_usaha }}</td>
                        <td class="px-4 py-3">{{ $participant->kategori_usaha }}</td>
                        <td class="px-4 py-3">{{ $participant->pendamping->nama ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $participant->tanggal_mulai_program->format('d-m-Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                    {{ $participant->masihBerjalan()
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-600' }}">
                                    {{ $participant->status }}
                                </span>
                            </td>

                        <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                                <a href="{{ route('participants.show', $participant) }}" class="text-cahaya-secondary hover:underline">Lihat</a>
                                <a href="{{ route('participants.edit', $participant) }}" class="text-cahaya-primary hover:underline">Edit</a>
                                <form method="POST" action="{{ route('participants.destroy', $participant) }}" class="inline"
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
                            Belum ada data peserta yang cocok dengan filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $participants->links() }}
    </div>
</div>
@endsection
