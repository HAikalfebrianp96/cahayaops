@extends('layouts.app')

@section('title', 'Tugas Koperasi')

@section('content')
{{--
    TUGAS KOPERASI — pasangan "Tugas Saya" milik Pendamping.

    ==========================================================================
    TIDAK ADA AMBANG WAKTU DI HALAMAN INI
    ==========================================================================

    Setiap baris di sini adalah keadaan yang bisa dibuktikan langsung dari
    data: ada invoice atau tidak, sudah lunas atau belum, punya PIN atau
    tidak. Tidak ada "sudah lewat sekian hari" — aturan seperti itu memang
    belum ada di CahayaOS, dan mengarangnya akan membuat daftar tugas yang
    terlihat resmi padahal tidak disepakati siapa pun.

    Akibatnya halaman ini tidak pernah keliru menagih pekerjaan: kalau sebuah
    baris muncul, sesuatu memang benar-benar belum ada.
--}}
<div class="space-y-5">

    <div>
        <h2 class="text-[20px] font-bold text-text-main">🎯 Tugas Koperasi</h2>
        <p class="text-sm text-text-secondary mt-0.5">
            Yang perlu dilengkapi, dihitung dari data yang tersimpan.
        </p>
    </div>

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['🔴', 'Perlu Segera', $ringkas['segera'], $ringkas['segera'] > 0 ? 'text-status-danger' : 'text-text-main'],
            ['🟠', 'Perlu Dilengkapi', $ringkas['perhatian'], $ringkas['perhatian'] > 0 ? 'text-status-warning' : 'text-text-main'],
            ['👥', 'Calon Anggota', $ringkas['calon_anggota'], 'text-status-info'],
            ['🟢', 'Transaksi Beres', $ringkas['beres'], 'text-status-success'],
        ] as [$tanda, $label, $nilai, $warna])
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1 leading-tight">
                    <span aria-hidden="true">{{ $tanda }}</span> {{ $label }}
                </p>
                <p class="text-2xl font-bold {{ $warna }}">{{ $nilai }}</p>
            </div>
        @endforeach
    </div>

    {{-- ================================================================
         🔴 PERLU SEGERA — ada pihak lain yang sedang menunggu.
         ================================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main">
            <span aria-hidden="true">🔴</span> Perlu Segera
            <span class="text-text-secondary font-normal">({{ $ringkas['segera'] }})</span>
        </h3>
        <p class="text-xs text-text-secondary mt-0.5 mb-4">
            Pembeli belum menerima tagihan, atau uangnya belum masuk.
        </p>

        @if ($ringkas['segera'] === 0)
            <p class="text-sm text-text-secondary italic">
                Tidak ada yang mendesak. Seluruh transaksi aktif sudah bertagihan dan lunas.
            </p>
        @else
            <div class="space-y-3">
                @foreach ($segera['transaksi_tanpa_invoice'] as $t)
                    <div class="border border-border-soft rounded-xl p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('koperasi.penjualan.show', $t) }}"
                                   class="text-[15px] font-semibold text-text-main hover:underline">
                                    {{ $t->nomor_transaksi }}
                                </a>
                                <p class="text-sm text-text-secondary">
                                    {{ $t->customerTermasukArsip?->nama ?? 'Tanpa pelanggan' }}
                                    &bull; {{ rupiah($t->total) }}
                                </p>
                                <p class="text-[11px] text-text-secondary mt-0.5">
                                    {{ $t->businessUnitTermasukArsip?->nama ?? 'Unit tidak diketahui' }}
                                    &bull; {{ $t->tanggal->translatedFormat('d F Y') }}
                                </p>
                            </div>
                            <span class="flex-none text-[11px] font-medium px-2 py-0.5 rounded-full bg-red-50 text-status-danger">
                                Belum ada invoice
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-text-secondary">
                            Alasan: Transaksi ini belum punya invoice, jadi pembeli belum menerima tagihan.
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('koperasi.invoice.store', $t) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                                    + Buat Invoice
                                </button>
                            </form>
                            <a href="{{ route('koperasi.penjualan.show', $t) }}"
                               class="inline-flex items-center justify-center rounded-lg border border-border-soft px-3.5 py-2.5 text-xs font-medium text-text-main hover:bg-surface-bg transition">
                                Lihat Transaksi
                            </a>
                        </div>
                    </div>
                @endforeach

                @foreach ($segera['transaksi_belum_lunas'] as $t)
                    <div class="border border-border-soft rounded-xl p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('koperasi.penjualan.show', $t) }}"
                                   class="text-[15px] font-semibold text-text-main hover:underline">
                                    {{ $t->nomor_transaksi }}
                                </a>
                                <p class="text-sm text-text-secondary">
                                    {{ $t->customerTermasukArsip?->nama ?? 'Tanpa pelanggan' }}
                                    &bull; {{ rupiah($t->total) }}
                                </p>
                                <p class="text-[11px] text-text-secondary mt-0.5">
                                    {{ $t->tanggal->translatedFormat('d F Y') }}
                                </p>
                            </div>
                            <span class="flex-none text-[11px] font-medium px-2 py-0.5 rounded-full bg-gold-light text-cahaya-accent">
                                {{ $t->status_pembayaran }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-text-secondary">
                            Alasan: Pembayaran tercatat "{{ $t->status_pembayaran }}" — uangnya belum diterima penuh.
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a href="{{ route('koperasi.penjualan.edit', $t) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                                Perbarui Pembayaran
                            </a>
                            <a href="{{ route('koperasi.penjualan.show', $t) }}"
                               class="inline-flex items-center justify-center rounded-lg border border-border-soft px-3.5 py-2.5 text-xs font-medium text-text-main hover:bg-surface-bg transition">
                                Lihat Transaksi
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ================================================================
         🟠 PERLU DILENGKAPI — catatan koperasi belum utuh.
         ================================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main">
            <span aria-hidden="true">🟠</span> Perlu Dilengkapi
            <span class="text-text-secondary font-normal">({{ $ringkas['perhatian'] }})</span>
        </h3>
        <p class="text-xs text-text-secondary mt-0.5 mb-4">
            Tidak ada yang menunggu hari ini, tapi dibiarkan akan menyulitkan saat penyusunan SHU dan pemeriksaan.
        </p>

        @if ($ringkas['perhatian'] === 0)
            <p class="text-sm text-text-secondary italic">Catatan koperasi sudah lengkap.</p>
        @else
            <div class="space-y-3">
                @foreach ($perhatian['anggota_tanpa_pin'] as $a)
                    <div class="border border-border-soft rounded-xl p-4">
                        <p class="text-[15px] font-semibold text-text-main">{{ $a->nama }}</p>
                        <p class="text-sm text-text-secondary">{{ $a->nomor_anggota }}</p>
                        <p class="mt-2 text-xs text-text-secondary">
                            Alasan: Belum punya PIN, jadi belum bisa membuka halaman Cek Simpanan sendiri.
                        </p>
                        <div class="mt-4">
                            <a href="{{ route('koperasi.anggota.show', $a) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                                Buatkan PIN
                            </a>
                        </div>
                    </div>
                @endforeach

                @foreach ($perhatian['anggota_tanpa_simpanan'] as $a)
                    <div class="border border-border-soft rounded-xl p-4">
                        <p class="text-[15px] font-semibold text-text-main">{{ $a->nama }}</p>
                        <p class="text-sm text-text-secondary">{{ $a->nomor_anggota }}</p>
                        <p class="mt-2 text-xs text-text-secondary">
                            Alasan: Belum ada satu pun entri simpanan, jadi saldonya masih nol.
                        </p>
                        <div class="mt-4">
                            <a href="{{ route('koperasi.anggota.show', $a) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                                Catat Simpanan
                            </a>
                        </div>
                    </div>
                @endforeach

                @foreach ($perhatian['pengeluaran_tanpa_bukti'] as $g)
                    <div class="border border-border-soft rounded-xl p-4">
                        <p class="text-[15px] font-semibold text-text-main">
                            {{ $g->kategori }} &bull; {{ rupiah($g->nominal) }}
                        </p>
                        <p class="text-sm text-text-secondary">
                            {{ $g->businessUnitTermasukArsip?->nama ?? 'Operasional' }}
                            &bull; {{ $g->tanggal->translatedFormat('d F Y') }}
                        </p>
                        <p class="mt-2 text-xs text-text-secondary">
                            Alasan: Belum ada berkas bukti, padahal nominalnya sudah mengurangi laba.
                        </p>
                        <div class="mt-4">
                            <a href="{{ route('koperasi.pengeluaran.edit', $g) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                                Unggah Bukti
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ================================================================
         👥 CALON ANGGOTA — jembatan ke Cahaya Preneur.

         Tertutup: jumlahnya besar dan ini bukan pekerjaan hari ini, tapi
         tanpa diingatkan ia tidak pernah terlihat oleh siapa pun.
         ================================================================ --}}
    @if ($ringkas['calon_anggota'] > 0)
        <details class="group bg-white border border-border-soft rounded-2xl shadow-sm">
            <summary class="flex items-center justify-between gap-3 p-6 cursor-pointer list-none">
                <div>
                    <h3 class="text-[15px] font-semibold text-text-main">
                        <span aria-hidden="true">👥</span> Calon Anggota
                    </h3>
                    <p class="text-xs text-text-secondary mt-0.5">
                        {{ $ringkas['calon_anggota'] }} peserta Cahaya Preneur belum menjadi anggota koperasi.
                    </p>
                </div>
                <span class="flex-none text-xs font-medium text-status-info whitespace-nowrap">
                    <span class="group-open:hidden">Lihat &rarr;</span>
                    <span class="hidden group-open:inline">Tutup</span>
                </span>
            </summary>

            <div class="px-6 pb-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                                <th class="px-4 py-2.5 font-medium">Peserta</th>
                                <th class="px-4 py-2.5 font-medium">Usaha</th>
                                <th class="px-4 py-2.5 font-medium">Pendamping</th>
                                <th class="px-4 py-2.5 font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($info['calon_anggota'] as $c)
                                <tr class="border-b border-border-soft last:border-0">
                                    <td class="px-4 py-2.5 text-text-main">{{ $c->nama_peserta }}</td>
                                    <td class="px-4 py-2.5 text-text-secondary">{{ $c->nama_usaha }}</td>
                                    <td class="px-4 py-2.5 text-text-secondary">{{ $c->pendamping->nama ?? '—' }}</td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <a href="{{ route('koperasi.anggota.pilih-peserta') }}"
                                           class="text-xs font-medium text-cahaya-primary hover:underline">
                                            Jadikan Anggota
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    @endif
</div>
@endsection
