{{-- PANEL: KOPERASI CAHAYA — dipindahkan apa adanya dari koperasi/dashboard.blade.php.
     Di-include oleh dashboard.blade.php saat modul aktif = 'koperasi'. --}}
    {{-- KETERANGAN PERIODE — dipindahkan dari subtitle header halaman.
         SENGAJA ditaruh di sini, menempel pada angka yang memang dibatasi
         periode ini (semuanya berasal dari LabaRugiService::hitung('default',
         'default') = tahun berjalan, semua bulan) — BUKAN di puncak panel.
         Alasannya: kartu Simpanan Anggota, Dana Sosial & Hibah, dan Status
         Pembayaran di bawah dihitung SEPANJANG WAKTU, jadi satu label periode
         di puncak panel justru akan menyesatkan. --}}
    <div class="flex items-baseline justify-between flex-wrap gap-x-3 gap-y-1">
        <h3 class="text-[15px] font-semibold text-text-main">Ringkasan Laba-Rugi</h3>
        <p class="text-xs text-text-secondary">Periode: Tahun {{ now()->year }}, semua bulan</p>
    </div>

    {{-- 4-5. KPI UTAMA (Penjualan, HPP, Laba Kotor, Fee Titip Jual, Pengeluaran, Laba Bersih) --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Total Penjualan</p>
            <p class="text-xl font-bold text-text-main">{{ rupiah($stats['penjualan_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">HPP</p>
            <p class="text-xl font-bold text-text-main">{{ rupiah($stats['hpp_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Laba Kotor</p>
            <p class="text-xl font-bold text-status-info">{{ rupiah($stats['laba_kotor_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Fee Titip Jual</p>
            <p class="text-xl font-bold text-cahaya-accent">{{ rupiah($stats['fee_koperasi_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Pengeluaran</p>
            <p class="text-xl font-bold text-status-danger">{{ rupiah($stats['pengeluaran_tahun_ini']) }}</p>
        </div>
        {{-- LABA BERSIH — paling menonjol, sesuai instruksi --}}
        <div class="bg-cahaya-primary rounded-2xl shadow-sm p-5">
            <p class="text-xs font-medium text-white/70 mb-2">Laba Bersih</p>
            @if (! $stats['ada_transaksi_tahun_ini'])
                <p class="text-sm text-white/60 italic">Belum ada transaksi</p>
            @else
                <p class="text-2xl font-bold {{ $stats['laba_bersih_tahun_ini'] >= 0 ? 'text-white' : 'text-red-300' }}">
                    {{ rupiah($stats['laba_bersih_tahun_ini']) }}
                </p>
            @endif
        </div>
    </div>

    {{-- 7. PERFORMA UNIT USAHA --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Performa Unit Usaha</h3>
                <p class="text-[11px] text-text-secondary mt-0.5">Tahun {{ now()->year }}, semua bulan</p>
            </div>
            @if ($stats['unit_terbaik'])
                <span class="text-xs font-medium text-cahaya-accent bg-gold-light px-2.5 py-1 rounded-full">🏆 {{ $stats['unit_terbaik'] }}</span>
            @endif
        </div>

        @if ($perUnit->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada data unit usaha dengan transaksi.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Unit</th>
                            <th class="py-2 font-medium">Penjualan</th>
                            <th class="py-2 font-medium">Laba Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($perUnit as $u)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="py-2.5 font-medium text-text-main">{{ $u['nama'] }}</td>
                                <td class="py-2.5 text-text-secondary">{{ rupiah($u['penjualan']) }}</td>
                                <td class="py-2.5 font-semibold {{ $u['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">{{ rupiah($u['laba_bersih']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- BARIS: PENJUALAN TERBARU + TITIP JUAL --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- 8. PENJUALAN TERBARU --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">Penjualan Terbaru</h3>
                <a href="{{ route('koperasi.penjualan.index') }}" class="text-xs text-status-info font-medium hover:underline">Lihat Semua Penjualan</a>
            </div>
            @if ($penjualanTerbaru->isEmpty())
                <p class="text-sm text-text-secondary italic">Belum ada transaksi penjualan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-text-secondary border-b border-border-soft">
                                <th class="py-2 font-medium">Nomor</th>
                                <th class="py-2 font-medium">Tanggal</th>
                                <th class="py-2 font-medium">Unit</th>
                                <th class="py-2 font-medium">Total</th>
                                <th class="py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($penjualanTerbaru as $t)
                                <tr class="border-b border-border-soft last:border-0">
                                    <td class="py-2.5 text-text-main">{{ $t->nomor_transaksi }}</td>
                                    <td class="py-2.5 text-text-secondary">{{ $t->tanggal->format('d-m-Y') }}</td>
                                    <td class="py-2.5 text-text-secondary">{{ $t->businessUnit->nama ?? '—' }}</td>
                                    <td class="py-2.5 font-medium text-text-main">{{ rupiah($t->total) }}</td>
                                    <td class="py-2.5">
                                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full
                                            {{ $t->status_pembayaran === 'Lunas' ? 'bg-primary-light text-status-success' : ($t->status_pembayaran === 'DP' ? 'bg-gold-light text-cahaya-accent' : 'bg-red-50 text-status-danger') }}">
                                            {{ $t->status_pembayaran }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- 11. TITIP JUAL + 9. STATUS PEMBAYARAN --}}
        <div class="space-y-5">
            <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
                <h3 class="text-[15px] font-semibold text-text-main mb-1">Titip Jual</h3>
                <p class="text-[11px] text-text-secondary mb-4">Nilai penjualan penuh BUKAN pendapatan koperasi — cuma Fee-nya. Tahun {{ now()->year }}, semua bulan.</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-text-secondary">Fee Koperasi</span><span class="font-semibold text-cahaya-accent">{{ rupiah($stats['fee_koperasi_tahun_ini']) }}</span></div>
                    <div class="flex justify-between"><span class="text-text-secondary">Hak Mitra</span><span class="font-semibold text-text-main">{{ rupiah($stats['hak_mitra_tahun_ini']) }}</span></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
                <h3 class="text-[15px] font-semibold text-text-main mb-4">Status Pembayaran</h3>
                <div class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-status-success"></span>Lunas</span>
                        <span class="font-semibold text-text-main">{{ $statusPembayaran['Lunas'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-cahaya-accent"></span>DP</span>
                        <span class="font-semibold text-text-main">{{ $statusPembayaran['DP'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-status-danger"></span>Belum Dibayar</span>
                        <span class="font-semibold text-text-main">{{ $statusPembayaran['Belum Dibayar'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 10. PENGELUARAN TERBARU --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Pengeluaran Terbaru</h3>
            <a href="{{ route('koperasi.pengeluaran.index') }}" class="text-xs text-status-info font-medium hover:underline">Lihat Semua</a>
        </div>
        @if ($pengeluaranTerbaru->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada data pengeluaran.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Tanggal</th>
                            <th class="py-2 font-medium">Kategori</th>
                            <th class="py-2 font-medium">Unit</th>
                            <th class="py-2 font-medium">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pengeluaranTerbaru as $p)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="py-2.5 text-text-secondary">{{ $p->tanggal->format('d-m-Y') }}</td>
                                <td class="py-2.5 text-text-main">{{ $p->kategori }}</td>
                                <td class="py-2.5 text-text-secondary">{{ $p->labelUnit() }}</td>
                                <td class="py-2.5 font-medium text-status-danger">{{ rupiah($p->nominal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- SIMPANAN & DANA (dipertahankan dari existing) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <h3 class="text-[15px] font-semibold text-text-main mb-4">Simpanan Anggota</h3>
            <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                <div><span class="text-text-secondary text-xs block mb-1">Total Anggota</span><span class="text-lg font-bold text-text-main">{{ $stats['total_anggota'] }}</span></div>
                <div><span class="text-text-secondary text-xs block mb-1">Anggota Aktif</span><span class="text-lg font-bold text-text-main">{{ $stats['anggota_aktif'] }}</span></div>
            </div>
            <div class="border-t border-border-soft pt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-text-secondary">Simpanan Pokok</span><span class="font-medium text-text-main">{{ rupiah($stats['total_pokok']) }}</span></div>
                <div class="flex justify-between"><span class="text-text-secondary">Simpanan Wajib</span><span class="font-medium text-text-main">{{ rupiah($stats['total_wajib']) }}</span></div>
                <div class="flex justify-between border-t border-border-soft pt-1.5"><span class="text-text-secondary">Total Simpanan</span><span class="font-bold text-cahaya-primary">{{ rupiah($stats['total_simpanan']) }}</span></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <h3 class="text-[15px] font-semibold text-text-main mb-4">Dana Sosial &amp; Hibah</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-center bg-surface-bg rounded-xl p-3">
                    <span class="text-text-secondary">Saldo Dana Sukarela</span>
                    <span class="font-bold text-cahaya-accent">{{ rupiah($stats['saldo_dana_sukarela']) }}</span>
                </div>
                <div class="flex justify-between items-center bg-surface-bg rounded-xl p-3">
                    <span class="text-text-secondary">Saldo Dana Hibah</span>
                    <span class="font-bold text-cahaya-accent">{{ rupiah($stats['saldo_dana_hibah']) }}</span>
                </div>
            </div>
            <a href="{{ route('koperasi.keuangan.index') }}" class="text-xs text-status-info hover:underline mt-4 inline-block">Lihat rincian di Keuangan &rarr;</a>
        </div>
    </div>
