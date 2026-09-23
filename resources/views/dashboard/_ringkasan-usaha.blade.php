{{-- RINGKASAN KONDISI USAHA KOPERASI — dipakai BERSAMA oleh Dashboard
     Koperasi (Admin, dashboard/panel-koperasi) dan Dashboard Tim Koperasi
     (dashboard-koperasi). Data dari DashboardController::ringkasanUsaha()
     -> LabaRugiService, sumber yang sama dengan Keuangan & Laporan.

     Butuh: $usaha = [stats, perUnit, produkTerlaris, penjualanTerbesar,
     transaksiTerbaru, filters, units]. --}}
    @php
        $kpi = $usaha['stats'];
        $filters = $usaha['filters'];
        $units = $usaha['units'];
        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $labelTahun = $filters['tahun'] === 'default' ? 'Tahun ' . now()->year : ($filters['tahun'] === 'semua' ? 'Semua tahun' : 'Tahun ' . $filters['tahun']);
        $labelBulan = in_array($filters['bulan'], ['default', 'semua'], true) ? 'semua bulan' : $namaBulan[(int) $filters['bulan']];
        $labelUnit = $filters['unit_id'] === 'operasional'
            ? ', Operasional Koperasi'
            : ($filters['unit_id'] ? ', ' . ($units->firstWhere('id', (int) $filters['unit_id'])->nama ?? 'unit tidak dikenal') : '');
        $labelPeriode = $labelTahun . ', ' . $labelBulan . $labelUnit;

        // Tautan ke Pengeluaran & Penjualan membawa periode/unit yang SAMA,
        // supaya angka yang dibandingkan admin memang dari filter yang sama.
        // (Default bulan Pengeluaran = bulan ini, jadi "semua bulan" dikirim eksplisit.)
        $tahunNyata = $filters['tahun'] === 'default' ? (string) now()->year : $filters['tahun'];
        $bulanNyata = in_array($filters['bulan'], ['default', 'semua'], true) ? null : $filters['bulan'];
        $filterPengeluaran = array_filter([
            'tahun' => $tahunNyata, 'bulan' => $bulanNyata ?? 'semua', 'unit_id' => $filters['unit_id'],
        ]);
        $filterPenjualan = array_filter([
            'tahun' => $tahunNyata === 'semua' ? null : $tahunNyata, 'bulan' => $bulanNyata,
            'unit_id' => $filters['unit_id'] === 'operasional' ? null : $filters['unit_id'], 'keadaan' => 'aktif',
        ]);
    @endphp
    <div class="flex items-baseline justify-between flex-wrap gap-x-3 gap-y-1">
        <h3 class="text-[15px] font-semibold text-text-main">Ringkasan Laba-Rugi</h3>
        <p class="text-xs text-text-secondary">Periode: {{ $labelPeriode }}</p>
    </div>

    {{-- FILTER — nilai & artinya sama dengan halaman Keuangan, dan SATU
         filter ini berlaku untuk keenam KPI sekaligus. --}}
    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap gap-3">
        <input type="hidden" name="modul" value="koperasi">
        <select name="tahun" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="default" {{ $filters['tahun'] === 'default' ? 'selected' : '' }}>Tahun Ini</option>
            <option value="semua" {{ $filters['tahun'] === 'semua' ? 'selected' : '' }}>Semua Tahun</option>
            @for ($y = now()->year; $y >= now()->year - 3; $y--)
                <option value="{{ $y }}" {{ $filters['tahun'] == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
        <select name="bulan" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="default" {{ in_array($filters['bulan'], ['default', 'semua'], true) ? 'selected' : '' }}>Semua Bulan</option>
            @foreach (array_slice($namaBulan, 1) as $i => $nm)
                <option value="{{ $i + 1 }}" {{ $filters['bulan'] == ($i + 1) ? 'selected' : '' }}>{{ $nm }}</option>
            @endforeach
        </select>
        <select name="unit_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Unit</option>
            <option value="operasional" {{ $filters['unit_id'] === 'operasional' ? 'selected' : '' }}>Operasional Koperasi</option>
            @foreach ($units as $u)
                <option value="{{ $u->id }}" {{ $filters['unit_id'] == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Filter</button>
    </form>

    {{-- ENAM KPI UTAMA — satu hasil LabaRugiService, sama dengan Keuangan:
           Laba Kotor  = Total Penjualan - HPP Terjual
           Laba Bersih = Laba Kotor - Pengeluaran
         Piutang Aktif hanya informasi (sisa tagihan transaksi yang sama),
         tidak ikut rumus laba. Hak Mitra & Fee sengaja bukan KPI. --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Total Penjualan</p>
            <p class="text-xl font-bold text-text-main">{{ rupiah($kpi['penjualan_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">HPP Terjual</p>
            <p class="text-xl font-bold text-text-main">{{ rupiah($kpi['hpp_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Laba Kotor</p>
            <p class="text-xl font-bold text-status-info">{{ rupiah($kpi['laba_kotor_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Pengeluaran Operasional</p>
            <p class="text-xl font-bold text-status-danger">{{ rupiah($kpi['pengeluaran_tahun_ini']) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-2">Piutang Aktif</p>
            <p class="text-xl font-bold text-cahaya-accent">{{ rupiah($kpi['piutang_aktif']) }}</p>
        </div>
        {{-- LABA BERSIH — paling menonjol: hasil akhirnya --}}
        <div class="bg-cahaya-primary rounded-2xl shadow-sm p-5">
            <p class="text-xs font-medium text-white/70 mb-2">Laba Bersih</p>
            @if (! $kpi['ada_transaksi_tahun_ini'] && $kpi['pengeluaran_tahun_ini'] == 0)
                <p class="text-sm text-white/60 italic">Belum ada transaksi</p>
            @else
                <p class="text-2xl font-bold {{ $kpi['laba_bersih_tahun_ini'] >= 0 ? 'text-white' : 'text-red-300' }}">
                    {{ rupiah($kpi['laba_bersih_tahun_ini']) }}
                </p>
            @endif
        </div>
    </div>

    {{-- PRODUK TERLARIS & PENJUALAN TERBESAR — dua peringkat, satu sumber
         (LabaRugiService), filter periode & unit yang sama dengan KPI.
           Produk Terlaris   = JUMLAH UNIT terjual
           Penjualan Terbesar = NILAI penjualan (Rupiah) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">Produk Terlaris</h3>
                <p class="text-[11px] text-text-secondary mt-0.5">{{ $labelPeriode }} · berdasarkan jumlah terjual</p>
            </div>
            @if (empty($usaha['produkTerlaris']))
                <p class="text-sm text-text-secondary italic">Belum ada data penjualan.</p>
            @else
                <ol class="space-y-2.5 text-sm">
                    @foreach ($usaha['produkTerlaris'] as $i => $p)
                        <li class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0"><span class="font-semibold {{ $i === 0 ? 'text-cahaya-accent' : 'text-text-secondary' }} mr-1.5">{{ $i + 1 }}.</span><span class="font-medium text-text-main">{{ $p['nama'] }}</span></span>
                            <span class="font-bold text-text-main whitespace-nowrap">{{ number_format($p['qty'], 0, ',', '.') }} terjual</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">Penjualan Terbesar</h3>
                <p class="text-[11px] text-text-secondary mt-0.5">{{ $labelPeriode }} · berdasarkan nilai penjualan</p>
            </div>
            @if (empty($usaha['penjualanTerbesar']))
                <p class="text-sm text-text-secondary italic">Belum ada data penjualan.</p>
            @else
                <ol class="space-y-2.5 text-sm">
                    @foreach ($usaha['penjualanTerbesar'] as $i => $p)
                        <li class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0"><span class="font-semibold {{ $i === 0 ? 'text-cahaya-accent' : 'text-text-secondary' }} mr-1.5">{{ $i + 1 }}.</span><span class="font-medium text-text-main">{{ $p['nama'] }}</span></span>
                            <span class="font-bold text-text-main whitespace-nowrap">{{ rupiah($p['omzet']) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    {{-- TRANSAKSI TERBARU — beberapa transaksi terakhir yang ikut dihitung
         KPI (tanpa Draft/Dibatalkan). --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Transaksi Terbaru</h3>
            <a href="{{ route('koperasi.penjualan.index') }}" class="text-xs text-status-info font-medium hover:underline">Lihat Semua Penjualan</a>
        </div>
        @if ($usaha['transaksiTerbaru']->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada transaksi penjualan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 pr-3 font-medium">Tanggal</th>
                            <th class="py-2 pr-3 font-medium">Nomor</th>
                            <th class="py-2 pr-3 font-medium">Pelanggan</th>
                            <th class="py-2 pr-3 font-medium text-right">Total</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usaha['transaksiTerbaru'] as $t)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="py-2.5 pr-3 text-text-secondary whitespace-nowrap">{{ $t->tanggal->format('d-m-Y') }}</td>
                                <td class="py-2.5 pr-3 whitespace-nowrap"><a href="{{ route('koperasi.penjualan.show', $t) }}" class="text-text-main hover:underline">{{ $t->nomor_transaksi }}</a></td>
                                <td class="py-2.5 pr-3 text-text-secondary">{{ $t->customer->nama ?? '—' }}</td>
                                <td class="py-2.5 pr-3 font-medium text-text-main text-right whitespace-nowrap">{{ rupiah($t->total) }}</td>
                                <td class="py-2.5">
                                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full whitespace-nowrap
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

    {{-- PENGELUARAN & PIUTANG — periode & unit sama dengan KPI (satu hasil
         LabaRugiService). Pembelian stok bukan beban: uangnya keluar, tapi
         biayanya baru masuk laba lewat HPP Terjual. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">Pengeluaran</h3>
                <a href="{{ route('koperasi.pengeluaran.index', $filterPengeluaran) }}" class="text-xs text-status-info font-medium hover:underline">Lihat rincian</a>
            </div>
            <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-text-secondary">Pengeluaran Operasional <span class="text-[11px]">(mengurangi laba)</span></span>
                    <span class="font-semibold text-status-danger whitespace-nowrap">{{ rupiah($kpi['pengeluaran_tahun_ini']) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-text-secondary">Pembelian/Re-Stok Produk <span class="text-[11px]">(tidak mengurangi laba)</span></span>
                    <span class="font-semibold text-text-main whitespace-nowrap">{{ rupiah($kpi['pembelian_stok']) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-border-soft pt-2.5">
                    <span class="text-text-secondary">Total uang keluar</span>
                    <span class="font-bold text-text-main whitespace-nowrap">{{ rupiah($kpi['uang_keluar']) }}</span>
                </div>
            </div>
            <p class="text-[11px] text-text-secondary mt-3">Pembelian stok tidak mengurangi laba saat pembelian. Biayanya menjadi HPP ketika produk terjual.</p>
        </div>

        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">Piutang</h3>
                <a href="{{ route('koperasi.penjualan.index', $filterPenjualan) }}" class="text-xs text-status-info font-medium hover:underline">Lihat penjualan</a>
            </div>
            <div class="space-y-2.5 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-text-secondary">Transaksi belum lunas</span>
                    <span class="font-semibold text-text-main">{{ $kpi['transaksi_belum_lunas'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-border-soft pt-2.5">
                    <span class="text-text-secondary">Total piutang aktif</span>
                    <span class="font-bold text-cahaya-accent whitespace-nowrap">{{ rupiah($kpi['piutang_aktif']) }}</span>
                </div>
            </div>
            <p class="text-[11px] text-text-secondary mt-3">Sisa tagihan penjualan periode ini yang belum dibayar. Transaksi Draft dan Dibatalkan tidak dihitung.</p>
        </div>
    </div>

    {{-- PERFORMA UNIT USAHA --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Performa Unit Usaha</h3>
                <p class="text-[11px] text-text-secondary mt-0.5">{{ $labelPeriode }}</p>
            </div>
            @if ($kpi['unit_terbaik'])
                <span class="text-xs font-medium text-cahaya-accent bg-gold-light px-2.5 py-1 rounded-full">🏆 {{ $kpi['unit_terbaik'] }}</span>
            @endif
        </div>

        @if ($usaha['perUnit']->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada data unit usaha dengan transaksi.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Unit</th>
                            <th class="py-2 font-medium text-right">Penjualan</th>
                            <th class="py-2 font-medium text-right">HPP</th>
                            <th class="py-2 font-medium text-right">Laba Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usaha['perUnit'] as $u)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="py-2.5 font-medium text-text-main">{{ $u['nama'] }}</td>
                                <td class="py-2.5 text-right text-text-secondary whitespace-nowrap">{{ rupiah($u['penjualan']) }}</td>
                                <td class="py-2.5 text-right text-text-secondary whitespace-nowrap">{{ rupiah($u['hpp']) }}</td>
                                <td class="py-2.5 text-right font-semibold whitespace-nowrap {{ $u['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">{{ rupiah($u['laba_bersih']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
