{{--
    KARTU "UANG TERSEDIA" untuk Dashboard — ringkas dari KasBankService::ringkasan(),
    sumber yang sama dengan halaman Kas & Bank, jadi angkanya tidak mungkin berbeda.
    Tidak ikut filter periode Dashboard: saldo selalu posisi hari ini.

    Uang Tersedia BUKAN Laba Bersih: dihitung dari saldo awal akun kas/bank
    ditambah seluruh uang masuk & keluar yang tercatat (termasuk pembelian
    stok, pembayaran piutang, transfer), bukan dari Penjualan - HPP - Pengeluaran.

    Kalau titik awalnya belum lengkap (ada akun tanpa saldo awal, atau catatan
    uang tanpa akun), angkanya TIDAK ditampilkan — "Belum tersedia" — supaya
    tidak ada angka yang diam-diam dihitung dari Rp0. Rinciannya tetap bisa
    dibuka di halaman Kas & Bank.
--}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
        <p class="text-[13px] font-semibold text-text-main">Posisi Uang Hari Ini</p>
        @if (auth()->user()?->bolehKelolaKoperasi())
            <a href="{{ route('koperasi.kas-bank.index') }}" class="text-xs font-medium text-status-info hover:underline">Buka Kas & Bank &rarr;</a>
        @endif
    </div>
    @if ($kasBank['bisa_dipercaya'])
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <p class="text-[11px] text-text-secondary">Uang Tersedia</p>
                <p class="text-xl font-bold text-text-main">{{ rupiah($kasBank['uang_tersedia']) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-text-secondary">Dana Anggota &amp; Titipan</p>
                <p class="text-xl font-bold text-cahaya-accent">{{ rupiah($kasBank['titipan']['total']) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-text-secondary">Uang Usaha <span class="text-text-secondary">(bukan laba)</span></p>
                <p class="text-xl font-bold {{ $kasBank['uang_usaha'] < 0 ? 'text-status-danger' : 'text-status-success' }}">{{ rupiah($kasBank['uang_usaha']) }}</p>
            </div>
        </div>
    @else
        <p class="text-[11px] text-text-secondary">Uang Tersedia</p>
        <p class="text-xl font-bold text-text-secondary">Belum tersedia</p>
        <p class="text-[11px] text-status-warning mt-2">
            @if ($kasBank['belum_ditetapkan'] > 0)
                {{ $kasBank['belum_ditetapkan'] }} akun belum punya saldo awal.
            @endif
            @if ($kasBank['tanpa_akun']['jumlah'] > 0)
                {{ $kasBank['tanpa_akun']['jumlah'] }} catatan uang belum menyebut akunnya.
            @endif
            Lengkapi di Kas &amp; Bank agar posisi uang bisa dihitung. Laba Bersih bukan uang tersedia.
        </p>
    @endif
</div>
