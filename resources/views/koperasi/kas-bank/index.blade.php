@extends('layouts.app')

@section('title', 'Kas & Bank')

@section('content')
@php($r = $ringkasan)
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Kas & Bank</h2>
            <p class="text-sm text-gray-500">
                Uang yang benar-benar dipegang koperasi, per laci kas dan rekening bank.
            </p>
        </div>
        <a href="{{ route('koperasi.kas-bank.create') }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
            + Tambah Akun
        </a>
    </div>

    @if ($r['belum_ditetapkan'] > 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>{{ $r['belum_ditetapkan'] }} akun belum punya saldo awal</strong> — angka di bawah dihitung dari Rp0
            dan belum bisa dipercaya sebagai uang yang sungguh ada.
            Isi saldo awal dengan hasil hitung laci atau saldo rekening pada tanggal mulai pencatatan.
        </div>
    @endif

    @if ($r['tanpa_akun']['jumlah'] > 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <strong>{{ $r['tanpa_akun']['jumlah'] }} catatan uang belum menyebut akunnya</strong>
            (masuk {{ rupiah($r['tanpa_akun']['masuk']) }}, keluar {{ rupiah($r['tanpa_akun']['keluar']) }}).
            Nilainya tetap ikut di "Uang Tersedia", tapi tidak diketahui di laci atau rekening mana.
        </div>
    @endif

    {{-- TIGA ANGKA UTAMA --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-500 mb-1">Uang Tersedia</p>
            <p class="text-2xl font-bold text-gray-800">{{ rupiah($r['uang_tersedia']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Saldo semua akun kas &amp; bank.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-500 mb-1">Dana Anggota &amp; Titipan</p>
            <p class="text-2xl font-bold text-amber-700">{{ rupiah($r['titipan']['total']) }}</p>
            <dl class="text-xs text-gray-500 mt-1 space-y-0.5">
                <div class="flex justify-between"><dt>Simpanan anggota</dt><dd>{{ rupiah($r['titipan']['simpanan']) }}</dd></div>
                <div class="flex justify-between"><dt>Dana Sosial</dt><dd>{{ rupiah($r['titipan']['dana_sosial']) }}</dd></div>
                <div class="flex justify-between"><dt>Dana Hibah</dt><dd>{{ rupiah($r['titipan']['dana_hibah']) }}</dd></div>
            </dl>
        </div>
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-5">
            <p class="text-xs text-gray-500 mb-1">Uang Usaha</p>
            <p class="text-2xl font-bold {{ $r['uang_usaha'] < 0 ? 'text-red-600' : 'text-green-700' }}">{{ rupiah($r['uang_usaha']) }}</p>
            <p class="text-xs text-gray-500 mt-1">
                Uang tersedia dikurangi dana anggota &amp; titipan.
                <strong>Bukan laba</strong> — di dalamnya ada modal stok yang kembali jadi uang. Laba dibaca di Laporan Laba Rugi.
            </p>
            @if ($r['uang_usaha'] < 0)
                <p class="text-xs text-red-600 mt-1">
                    Negatif: uang yang dipegang lebih kecil dari dana milik anggota/titipan — sebagian dana itu sudah terpakai
                    untuk usaha, atau saldo awal belum diisi.
                </p>
            @endif
        </div>
    </div>

    {{-- DAFTAR AKUN --}}
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[720px]">
                <thead>
                    <tr class="text-left text-gray-500 border-b bg-gray-50">
                        <th class="py-2 px-4">Nama Akun</th>
                        <th class="py-2 px-4">Jenis</th>
                        <th class="py-2 px-4 text-right">Saldo Awal</th>
                        <th class="py-2 px-4 text-right">Saldo Sekarang</th>
                        <th class="py-2 px-4">Status</th>
                        <th class="py-2 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['akun'] as $baris)
                        @php($a = $baris['akun'])
                        <tr class="border-b last:border-0 {{ $a->isAktif() ? '' : 'text-gray-400' }}">
                            <td class="py-2 px-4">
                                <a href="{{ route('koperasi.kas-bank.show', $a) }}" class="font-medium text-koperasi-primary hover:underline">{{ $a->nama }}</a>
                                @if ($a->nomor_rekening)
                                    <div class="text-xs text-gray-500">{{ $a->nomor_rekening }}{{ $a->atas_nama ? ' a.n. ' . $a->atas_nama : '' }}</div>
                                @endif
                            </td>
                            <td class="py-2 px-4">{{ $a->jenis }}</td>
                            <td class="py-2 px-4 text-right whitespace-nowrap">
                                @if ($a->saldoAwalDitetapkan())
                                    {{ rupiah($a->saldo_awal) }}
                                    <div class="text-xs text-gray-500">per {{ $a->tanggal_saldo_awal->format('d-m-Y') }}</div>
                                @else
                                    <span class="text-xs font-semibold text-amber-700">Belum ditetapkan</span>
                                @endif
                            </td>
                            <td class="py-2 px-4 text-right whitespace-nowrap font-semibold {{ $baris['saldo'] < 0 ? 'text-red-600' : '' }}">
                                {{ rupiah($baris['saldo']) }}
                            </td>
                            <td class="py-2 px-4">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $a->isAktif() ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $a->status }}
                                </span>
                            </td>
                            <td class="py-2 px-4 space-x-2 whitespace-nowrap">
                                <a href="{{ route('koperasi.kas-bank.show', $a) }}" class="text-koperasi-primary hover:underline">Buku Mutasi</a>
                                <a href="{{ route('koperasi.kas-bank.edit', $a) }}" class="text-koperasi-primary hover:underline">Edit</a>
                                <form method="POST" action="{{ route('koperasi.kas-bank.toggle', $a) }}" class="inline"
                                      onsubmit="return confirm('{{ $a->isAktif() ? 'Nonaktifkan' : 'Aktifkan' }} akun ini?');">
                                    @csrf
                                    <button type="submit" class="text-xs {{ $a->isAktif() ? 'text-red-500' : 'text-green-600' }} hover:underline">
                                        {{ $a->isAktif() ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('koperasi.kas-bank.destroy', $a) }}" class="inline"
                                      onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Untuk akun yang tidak dipakai lagi, cukup nonaktifkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus Permanen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-400 italic">Belum ada akun kas atau bank.</td></tr>
                    @endforelse
                </tbody>
                @if ($r['akun']->isNotEmpty())
                    <tfoot>
                        <tr class="border-t bg-gray-50 font-semibold">
                            <td class="py-2 px-4" colspan="3">Total saldo semua akun</td>
                            <td class="py-2 px-4 text-right whitespace-nowrap">{{ rupiah($r['total_akun']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Saldo dihitung langsung dari catatan penjualan (pembayaran yang diterima), pengeluaran, simpanan, dan dana
        yang masih berlaku — tidak ada angka yang disimpan terpisah. Catatan bertanggal sebelum tanggal saldo awal
        dianggap sudah termasuk di saldo awal.
    </p>
</div>
@endsection
