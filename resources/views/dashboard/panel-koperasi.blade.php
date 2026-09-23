{{-- PANEL: KOPERASI CAHAYA (Admin) — Di-include oleh dashboard.blade.php
     saat modul aktif = 'koperasi'. Ringkasan kondisi usaha (filter, enam
     KPI, Produk Terlaris, Penjualan Terbesar, Transaksi Terbaru,
     Pengeluaran & Piutang, Performa Unit) ada di partial bersama yang juga
     dipakai Dashboard Tim Koperasi. Kartu di bawahnya (Posisi Uang,
     Simpanan, Dana Sosial & Hibah) dihitung SEPANJANG WAKTU, bukan per
     periode filter. --}}
    @include('dashboard._ringkasan-usaha')

    {{-- UANG TERSEDIA — posisi kas hari ini, BUKAN laba (KasBankService). --}}
    @isset($kasBank)
        @include('koperasi.kas-bank._kartu-dashboard')
    @endisset

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
