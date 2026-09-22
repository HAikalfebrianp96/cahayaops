@extends('layouts.app')

@section('title', 'Keuangan')

@section('content')
<div class="space-y-6 w-full max-w-none">
    <div>
        <h2 class="text-[20px] font-bold text-text-main">Keuangan</h2>
        <p class="text-sm text-text-secondary mt-0.5">3 buku terpisah — Keuangan Usaha, Dana Anggota, dan Dana Sosial tidak pernah dicampur.</p>
    </div>

    {{-- ================= BUKU 1 — KEUANGAN USAHA (LABA RUGI) ================= --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Buku 1 — Keuangan Usaha</h3>
        <p class="text-xs text-text-secondary mb-4">Ringkasan laba rugi dari transaksi Penjualan dan Pengeluaran. Harga modal/HPP menggunakan nilai yang tersimpan pada setiap item transaksi.</p>

        <form method="GET" class="flex flex-wrap gap-3 mb-5">
            <select name="tahun" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="default" {{ ($filters['tahun'] ?? 'default') === 'default' ? 'selected' : '' }}>Tahun Ini</option>
                <option value="semua" {{ ($filters['tahun'] ?? '') === 'semua' ? 'selected' : '' }}>Semua Tahun</option>
                @for ($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ ($filters['tahun'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <select name="bulan" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="default" {{ ($filters['bulan'] ?? 'default') === 'default' ? 'selected' : '' }}>Semua Bulan</option>
                @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nm)
                    <option value="{{ $i + 1 }}" {{ ($filters['bulan'] ?? '') == ($i + 1) ? 'selected' : '' }}>{{ $nm }}</option>
                @endforeach
            </select>
            <select name="unit_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="">Semua Unit</option>
                <option value="operasional" {{ ($filters['unit_id'] ?? '') === 'operasional' ? 'selected' : '' }}>Operasional Koperasi</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" {{ ($filters['unit_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Filter</button>
        </form>

        @if (! $lr['ada_transaksi'] && ! $lr['ada_pengeluaran'])
            <div class="text-center py-10">
                <p class="text-sm font-medium text-text-main mb-1">Belum Ada Transaksi</p>
                <p class="text-xs text-text-secondary">Belum ada transaksi pada periode ini.</p>
            </div>
        @else
            {{-- KEUANGAN USAHA — enam angka yang sama dengan Dashboard Koperasi
                 (satu hasil LabaRugiService, filter yang sama):
                   Laba Kotor  = Total Penjualan - HPP Terjual
                   Laba Bersih = Laba Kotor - Pengeluaran Operasional
                 Pembelian stok TIDAK termasuk Pengeluaran Operasional — lihat
                 bagian Arus Kas Pembelian Stok di bawah. Fee Koperasi & Hak
                 Mitra tetap ada di Rekap Penjualan. --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-2">
                <div class="border border-border-soft rounded-xl p-4">
                    <p class="text-xs text-text-secondary mb-1">Total Penjualan</p>
                    <p class="text-base font-bold text-text-main">{{ rupiah($lr['total']['penjualan']) }}</p>
                </div>
                <div class="border border-border-soft rounded-xl p-4">
                    <p class="text-xs text-text-secondary mb-1">HPP Terjual</p>
                    <p class="text-base font-bold text-text-main">{{ rupiah($lr['total']['hpp']) }}</p>
                </div>
                <div class="border border-border-soft rounded-xl p-4">
                    <p class="text-xs text-text-secondary mb-1">Laba Kotor</p>
                    <p class="text-base font-bold text-status-info">{{ rupiah($lr['total']['laba_kotor']) }}</p>
                </div>
                <div class="border border-border-soft rounded-xl p-4">
                    <p class="text-xs text-text-secondary mb-1">Pengeluaran Operasional</p>
                    <p class="text-base font-bold text-status-danger">{{ rupiah($lr['total']['pengeluaran_total']) }}</p>
                </div>
                <div class="border border-border-soft rounded-xl p-4">
                    <p class="text-xs text-text-secondary mb-1">Piutang Aktif</p>
                    <p class="text-base font-bold text-cahaya-accent">{{ rupiah($lr['total']['piutang']) }}</p>
                </div>
                <div class="border border-border-soft rounded-xl p-4 bg-cahaya-primary">
                    <p class="text-xs text-white/70 mb-1">Laba Bersih</p>
                    <p class="text-xl font-bold {{ $lr['total']['laba_bersih'] >= 0 ? 'text-white' : 'text-red-300' }}">
                        {{ rupiah($lr['total']['laba_bersih']) }}
                        @if ($lr['total']['margin'] !== null)
                            <span class="text-xs font-normal text-white/60">(margin {{ number_format($lr['total']['margin'], 1) }}%)</span>
                        @endif
                    </p>
                </div>
            </div>
            <p class="text-xs text-text-secondary mb-5">Pengeluaran tanpa unit usaha / Operasional Koperasi ({{ rupiah($lr['total']['pengeluaran_operasional']) }}) sudah termasuk di Pengeluaran Operasional di atas, tapi tidak dibebankan ke unit manapun.</p>

            {{-- ARUS KAS PEMBELIAN STOK — terpisah dari Keuangan Usaha supaya
                 tidak terbaca sebagai angka yang tidak sinkron. Uangnya
                 memang keluar, tapi biayanya masuk laba-rugi lewat HPP Terjual
                 saat barang terjual — dihitung sekali, bukan dua kali. --}}
            <div class="border border-dashed border-border-soft rounded-xl p-4 mb-6">
                <h4 class="text-xs font-semibold text-text-secondary mb-2">Arus Kas Pembelian Stok</h4>
                <div class="flex justify-between text-sm">
                    <span class="text-text-secondary">Pembelian/Re-Stok Produk</span>
                    <span class="font-semibold text-text-main">{{ rupiah($lr['total']['pembelian_stok']) }}</span>
                </div>
                <p class="text-xs text-text-secondary mt-2">Pembelian stok tercatat sebagai pengeluaran kas, namun tidak dibebankan langsung ke laba-rugi. Biaya masuk HPP saat barang terjual.</p>
            </div>

            {{-- PERFORMA / LABA RUGI PER UNIT --}}
            <h4 class="text-xs font-semibold text-text-secondary mb-2">Laba Rugi per Unit</h4>
            <div class="overflow-x-auto mb-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-3 py-2.5 font-medium">Unit</th>
                            <th class="px-3 py-2.5 font-medium">Penjualan</th>
                            <th class="px-3 py-2.5 font-medium">HPP</th>
                            <th class="px-3 py-2.5 font-medium">Fee</th>
                            <th class="px-3 py-2.5 font-medium">Pengeluaran</th>
                            <th class="px-3 py-2.5 font-medium">Laba Bersih</th>
                            <th class="px-3 py-2.5 font-medium">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lr['per_unit'] as $u)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-3 py-2.5 font-medium text-text-main">{{ $u['nama'] }}</td>
                                <td class="px-3 py-2.5 text-text-secondary">{{ rupiah($u['penjualan']) }}</td>
                                <td class="px-3 py-2.5 text-text-secondary">{{ rupiah($u['hpp']) }}</td>
                                <td class="px-3 py-2.5 text-text-secondary">{{ $u['fee_koperasi'] > 0 ? 'Rp' . number_format($u['fee_koperasi'], 0, ',', '.') : '-' }}</td>
                                <td class="px-3 py-2.5 text-text-secondary">{{ rupiah($u['pengeluaran']) }}</td>
                                <td class="px-3 py-2.5 font-semibold {{ $u['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">{{ rupiah($u['laba_bersih']) }}</td>
                                <td class="px-3 py-2.5 text-text-secondary">{{ $u['margin'] !== null ? number_format($u['margin'], 1) . '%' : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-text-secondary italic">Belum ada unit usaha.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- REKAP PENJUALAN --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border border-border-soft rounded-xl p-4">
                    <h4 class="text-xs font-semibold text-text-secondary mb-2">Rekap Penjualan</h4>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-text-secondary">Jumlah Transaksi</span><span class="font-semibold text-text-main">{{ $jumlahTransaksi }}</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Total Penjualan</span><span class="font-semibold text-text-main">{{ rupiah($lr['total']['penjualan']) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Total HPP</span><span class="font-semibold text-text-main">{{ rupiah($lr['total']['hpp']) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Total Fee</span><span class="font-semibold text-text-main">{{ rupiah($lr['total']['fee_koperasi']) }}</span></div>
                        <div class="flex justify-between"><span class="text-text-secondary">Total Hak Mitra</span><span class="font-semibold text-text-main">{{ rupiah($lr['total']['hak_mitra']) }}</span></div>
                    </div>
                </div>

                {{-- REKAP PENGELUARAN PER KATEGORI --}}
                <div class="border border-border-soft rounded-xl p-4">
                    <h4 class="text-xs font-semibold text-text-secondary mb-2">Rekap Pengeluaran per Kategori</h4>
                    @forelse ($rekapKategori as $k)
                        <div class="flex justify-between text-sm py-0.5">
                            <span class="text-text-secondary">
                                {{ $k->kategori }}
                                @if (in_array($k->kategori, \App\Models\Pengeluaran::kategoriPembelianStok(), true))
                                    <span class="text-[11px] italic">(pembelian stok — tidak mengurangi laba)</span>
                                @endif
                            </span>
                            <span class="font-semibold text-text-main">{{ rupiah($k->total) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-text-secondary italic">Belum ada pengeluaran.</p>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    {{-- ================= BUKU 2 — DANA ANGGOTA ================= --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Buku 2 — Dana Anggota</h3>
        <p class="text-xs text-text-secondary mb-4">Simpanan Pokok & Wajib — bukan omzet, bukan laba, bukan pendapatan usaha.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Setiap angka ringkasan menunjuk ke transaksi yang
                 membentuknya. Angka ini dijumlahkan dari entri simpanan
                 masing-masing anggota, bukan disimpan di kolom mana pun —
                 jadi "Lihat Riwayat" bukan sekadar navigasi, melainkan cara
                 memeriksa angkanya sampai ke sumbernya. --}}
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Simpanan Pokok</p>
                <p class="text-xl font-bold text-cahaya-primary">{{ rupiah($totalSimpananPokok) }}</p>
                <a href="{{ route('koperasi.laporan.simpanan') }}" class="text-xs text-status-info hover:underline mt-1 inline-block">Lihat Riwayat</a>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Simpanan Wajib</p>
                <p class="text-xl font-bold text-cahaya-primary">{{ rupiah($totalSimpananWajib) }}</p>
                <a href="{{ route('koperasi.laporan.simpanan') }}" class="text-xs text-status-info hover:underline mt-1 inline-block">Lihat Riwayat</a>
            </div>
            @if (($simpananAnggotaTidakAktif ?? 0) > 0)
                {{-- Keterangan, bukan pengurangan angka: menonaktifkan anggota
                     tidak mengembalikan uangnya, jadi koperasi memang masih
                     memegang dana ini. Yang keliru adalah menampilkannya
                     tanpa keterangan. --}}
                <div class="sm:col-span-2 lg:col-span-3 text-xs text-text-secondary">
                    Termasuk <span class="font-semibold text-text-main">{{ rupiah($simpananAnggotaTidakAktif) }}</span>
                    milik anggota yang statusnya tidak lagi aktif. Dananya masih dipegang koperasi, tetapi pemiliknya
                    tidak lagi terhitung sebagai anggota dan tidak menerima pembagian SHU.
                </div>
            @endif
        </div>
    </div>

    {{-- ================= BUKU 3 — DANA SOSIAL ================= --}}
    <div class="space-y-4">
        <div>
            <h3 class="text-base font-bold text-text-main">Buku 3 — Dana Sosial</h3>
            <p class="text-xs text-text-secondary mt-0.5">Dana Sukarela & Dana Hibah — tidak pernah dihitung sebagai omzet atau laba usaha.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
                <p class="text-xs text-text-secondary mb-1">Saldo Dana Sukarela</p>
                <p class="text-xl font-bold text-cahaya-accent">{{ rupiah($saldo) }}</p>
                <p class="text-[11px] text-text-secondary mt-1">
                    {{ rupiah($totalMasuk) }} masuk &minus; {{ rupiah($totalKeluar) }} keluar.
                    <a href="#riwayat-dana-sukarela" class="text-status-info hover:underline">Lihat Riwayat</a>
                </p>
            </div>
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
                <p class="text-xs text-text-secondary mb-1">Saldo Dana Hibah</p>
                <p class="text-xl font-bold text-cahaya-accent">{{ rupiah($saldoHibah) }}</p>
                <p class="text-[11px] text-text-secondary mt-1">
                    {{ rupiah($totalHibahMasuk) }} masuk &minus; {{ rupiah($totalHibahKeluar) }} keluar.
                    <a href="#riwayat-dana-hibah" class="text-status-info hover:underline">Lihat Riwayat</a>
                </p>
            </div>
        </div>

        <div>
            <h4 id="riwayat-dana-sukarela" class="text-sm font-semibold text-text-main mb-2 scroll-mt-24">Dana Sukarela Sosial</h4>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                    <h5 class="text-sm font-semibold text-text-main mb-4">Catat Pemasukan</h5>
                    <form method="POST" action="{{ route('koperasi.dana-sukarela.masuk') }}" class="space-y-3 mb-6">
                        @csrf
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Tanggal</label>
                            <input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Nominal (Rp)</label>
                            <input type="number" min="0" step="1" name="nominal" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Sumber</label>
                            <input type="text" name="sumber" placeholder="Contoh: Celengan Sukarela" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Keterangan</label>
                            <input type="text" name="keterangan" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Simpan Pemasukan</button>
                    </form>

                    <h6 class="text-xs font-semibold text-text-secondary mb-2">Riwayat Terbaru</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="text-left text-text-secondary border-b border-border-soft"><th class="py-1.5">Tanggal</th><th class="py-1.5">Sumber</th><th class="py-1.5">Nominal</th><th class="py-1.5">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($riwayatMasuk as $r)
                                    <tr class="border-b border-border-soft last:border-0 {{ $r->dibatalkan() ? 'opacity-60' : '' }}">
                                        <td class="py-1.5 text-text-secondary">{{ $r->tanggal->format('d-m-Y') }}</td>
                                        <td class="py-1.5 text-text-main">{{ $r->sumber }}</td>
                                        <td class="py-1.5 font-medium text-text-main">{{ rupiah($r->nominal) }}</td>
                                        <td class="py-1.5 whitespace-nowrap">
                                            @if (! auth()->user()->isAdmin())
                                                {{-- Dana titipan pihak ketiga: hanya Admin yang mengubahnya. --}}
                                                @if ($r->dibatalkan())
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                @else
                                                    <span class="text-text-secondary">&mdash;</span>
                                                @endif
                                            @elseif ($r->dibatalkan())
                                                {{-- Sudah dibatalkan: alasannya SUDAH tercatat di Activity
                                                     Log, dan barisnya sudah berhenti dihitung ke saldo. Baru
                                                     di titik inilah Hapus Permanen terbuka — jadi membuang
                                                     baris selalu keputusan KEDUA, diambil pada waktu yang
                                                     berbeda dari pembatalannya. --}}
                                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                <form method="POST" action="{{ route('koperasi.dana-sukarela.destroy', ['jenis' => 'masuk', 'id' => $r->id]) }}" class="inline ml-2"
                                                      onsubmit="return confirm('Hapus Permanen?

Data yang dihapus tidak dapat dipulihkan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-status-danger hover:underline">Hapus Permanen</button>
                                                </form>
                                            @else
                                                {{-- Menuju halaman perbaikan, yang juga memuat Batalkan
                                                     beserta isian alasannya. Tombol Batalkan sekali-klik yang
                                                     dulu ada di sini mengirim alasan kalengan "Dibatalkan
                                                     lewat halaman Keuangan" — kalimat yang tidak menjelaskan
                                                     apa pun kepada pihak yang menitipkan dananya. --}}
                                                <a href="{{ route('koperasi.dana-sukarela.edit', ['jenis' => 'masuk', 'id' => $r->id]) }}" class="text-status-info hover:underline">Perbaiki</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-text-secondary italic">Belum ada pemasukan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                    <h5 class="text-sm font-semibold text-text-main mb-4">Catat Penggunaan</h5>
                    <form method="POST" action="{{ route('koperasi.dana-sukarela.keluar') }}" class="space-y-3 mb-6">
                        @csrf
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Tanggal</label>
                            <input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Nominal (Rp)</label>
                            <input type="number" min="0" step="1" name="nominal" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Keperluan</label>
                            <input type="text" name="keperluan" placeholder="Contoh: Bantuan Musibah Anggota" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Keterangan</label>
                            <input type="text" name="keterangan" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Simpan Penggunaan</button>
                    </form>

                    <h6 class="text-xs font-semibold text-text-secondary mb-2">Riwayat Terbaru</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="text-left text-text-secondary border-b border-border-soft"><th class="py-1.5">Tanggal</th><th class="py-1.5">Keperluan</th><th class="py-1.5">Nominal</th><th class="py-1.5">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($riwayatKeluar as $r)
                                    <tr class="border-b border-border-soft last:border-0 {{ $r->dibatalkan() ? 'opacity-60' : '' }}">
                                        <td class="py-1.5 text-text-secondary">{{ $r->tanggal->format('d-m-Y') }}</td>
                                        <td class="py-1.5 text-text-main">{{ $r->keperluan }}</td>
                                        <td class="py-1.5 font-medium text-status-danger">{{ rupiah($r->nominal) }}</td>
                                        <td class="py-1.5 whitespace-nowrap">
                                            @if (! auth()->user()->isAdmin())
                                                {{-- Dana titipan pihak ketiga: hanya Admin yang mengubahnya. --}}
                                                @if ($r->dibatalkan())
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                @else
                                                    <span class="text-text-secondary">&mdash;</span>
                                                @endif
                                            @elseif ($r->dibatalkan())
                                                {{-- Sudah dibatalkan: alasannya SUDAH tercatat di Activity
                                                     Log, dan barisnya sudah berhenti dihitung ke saldo. Baru
                                                     di titik inilah Hapus Permanen terbuka — jadi membuang
                                                     baris selalu keputusan KEDUA, diambil pada waktu yang
                                                     berbeda dari pembatalannya. --}}
                                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                <form method="POST" action="{{ route('koperasi.dana-sukarela.destroy', ['jenis' => 'keluar', 'id' => $r->id]) }}" class="inline ml-2"
                                                      onsubmit="return confirm('Hapus Permanen?

Data yang dihapus tidak dapat dipulihkan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-status-danger hover:underline">Hapus Permanen</button>
                                                </form>
                                            @else
                                                {{-- Menuju halaman perbaikan, yang juga memuat Batalkan
                                                     beserta isian alasannya. Tombol Batalkan sekali-klik yang
                                                     dulu ada di sini mengirim alasan kalengan "Dibatalkan
                                                     lewat halaman Keuangan" — kalimat yang tidak menjelaskan
                                                     apa pun kepada pihak yang menitipkan dananya. --}}
                                                <a href="{{ route('koperasi.dana-sukarela.edit', ['jenis' => 'keluar', 'id' => $r->id]) }}" class="text-status-info hover:underline">Perbaiki</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-text-secondary italic">Belum ada penggunaan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <h4 id="riwayat-dana-hibah" class="text-sm font-semibold text-text-main mb-2 scroll-mt-24">Dana Hibah / Bantuan Modal Usaha</h4>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                    <h5 class="text-sm font-semibold text-text-main mb-4">Catat Pemasukan Hibah</h5>
                    <form method="POST" action="{{ route('koperasi.dana-hibah.masuk') }}" class="space-y-3 mb-6">
                        @csrf
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Tanggal</label>
                            <input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Nominal (Rp)</label>
                            <input type="number" min="0" step="1" name="nominal" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Sumber / Pemberi Hibah</label>
                            <input type="text" name="sumber" placeholder="Contoh: YBM PLN Pusat" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Peruntukan <span class="text-text-secondary">(boleh kosong)</span></label>
                            <input type="text" name="peruntukan" placeholder="Contoh: Modal Usaha Cahaya Mart" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Keterangan</label>
                            <input type="text" name="keterangan" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <button type="submit" class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Simpan Pemasukan Hibah</button>
                    </form>

                    <h6 class="text-xs font-semibold text-text-secondary mb-2">Riwayat Terbaru</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="text-left text-text-secondary border-b border-border-soft"><th class="py-1.5">Tanggal</th><th class="py-1.5">Sumber</th><th class="py-1.5">Peruntukan</th><th class="py-1.5">Nominal</th><th class="py-1.5">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($riwayatHibahMasuk as $r)
                                    <tr class="border-b border-border-soft last:border-0 {{ $r->dibatalkan() ? 'opacity-60' : '' }}">
                                        <td class="py-1.5 text-text-secondary">{{ $r->tanggal->format('d-m-Y') }}</td>
                                        <td class="py-1.5 text-text-main">{{ $r->sumber }}</td>
                                        <td class="py-1.5 text-text-secondary">{{ $r->peruntukan ?: '—' }}</td>
                                        <td class="py-1.5 font-medium text-text-main">{{ rupiah($r->nominal) }}</td>
                                        <td class="py-1.5 whitespace-nowrap">
                                            @if (! auth()->user()->isAdmin())
                                                {{-- Dana titipan pihak ketiga: hanya Admin yang mengubahnya. --}}
                                                @if ($r->dibatalkan())
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                @else
                                                    <span class="text-text-secondary">&mdash;</span>
                                                @endif
                                            @elseif ($r->dibatalkan())
                                                {{-- Sudah dibatalkan: alasannya SUDAH tercatat di Activity
                                                     Log, dan barisnya sudah berhenti dihitung ke saldo. Baru
                                                     di titik inilah Hapus Permanen terbuka — jadi membuang
                                                     baris selalu keputusan KEDUA, diambil pada waktu yang
                                                     berbeda dari pembatalannya. --}}
                                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                <form method="POST" action="{{ route('koperasi.dana-hibah.destroy', ['jenis' => 'masuk', 'id' => $r->id]) }}" class="inline ml-2"
                                                      onsubmit="return confirm('Hapus Permanen?

Data yang dihapus tidak dapat dipulihkan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-status-danger hover:underline">Hapus Permanen</button>
                                                </form>
                                            @else
                                                {{-- Menuju halaman perbaikan, yang juga memuat Batalkan
                                                     beserta isian alasannya. Tombol Batalkan sekali-klik yang
                                                     dulu ada di sini mengirim alasan kalengan "Dibatalkan
                                                     lewat halaman Keuangan" — kalimat yang tidak menjelaskan
                                                     apa pun kepada pihak yang menitipkan dananya. --}}
                                                <a href="{{ route('koperasi.dana-hibah.edit', ['jenis' => 'masuk', 'id' => $r->id]) }}" class="text-status-info hover:underline">Perbaiki</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-4 text-center text-text-secondary italic">Belum ada pemasukan hibah.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
                    <h5 class="text-sm font-semibold text-text-main mb-4">Catat Penggunaan Hibah</h5>
                    <form method="POST" action="{{ route('koperasi.dana-hibah.keluar') }}" class="space-y-3 mb-6">
                        @csrf
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Tanggal</label>
                            <input type="date" name="tanggal" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Nominal (Rp)</label>
                            <input type="number" min="0" step="1" name="nominal" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Keperluan</label>
                            <input type="text" name="keperluan" placeholder="Contoh: Pembelian etalase Cahaya Mart" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Keterangan</label>
                            <input type="text" name="keterangan" class="w-full rounded-xl border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <button type="submit" class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Simpan Penggunaan Hibah</button>
                    </form>

                    <h6 class="text-xs font-semibold text-text-secondary mb-2">Riwayat Terbaru</h6>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="text-left text-text-secondary border-b border-border-soft"><th class="py-1.5">Tanggal</th><th class="py-1.5">Keperluan</th><th class="py-1.5">Nominal</th><th class="py-1.5">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($riwayatHibahKeluar as $r)
                                    <tr class="border-b border-border-soft last:border-0 {{ $r->dibatalkan() ? 'opacity-60' : '' }}">
                                        <td class="py-1.5 text-text-secondary">{{ $r->tanggal->format('d-m-Y') }}</td>
                                        <td class="py-1.5 text-text-main">{{ $r->keperluan }}</td>
                                        <td class="py-1.5 font-medium text-status-danger">{{ rupiah($r->nominal) }}</td>
                                        <td class="py-1.5 whitespace-nowrap">
                                            @if (! auth()->user()->isAdmin())
                                                {{-- Dana titipan pihak ketiga: hanya Admin yang mengubahnya. --}}
                                                @if ($r->dibatalkan())
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                @else
                                                    <span class="text-text-secondary">&mdash;</span>
                                                @endif
                                            @elseif ($r->dibatalkan())
                                                {{-- Sudah dibatalkan: alasannya SUDAH tercatat di Activity
                                                     Log, dan barisnya sudah berhenti dihitung ke saldo. Baru
                                                     di titik inilah Hapus Permanen terbuka — jadi membuang
                                                     baris selalu keputusan KEDUA, diambil pada waktu yang
                                                     berbeda dari pembatalannya. --}}
                                                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-text-secondary">Dibatalkan</span>
                                                <form method="POST" action="{{ route('koperasi.dana-hibah.destroy', ['jenis' => 'keluar', 'id' => $r->id]) }}" class="inline ml-2"
                                                      onsubmit="return confirm('Hapus Permanen?

Data yang dihapus tidak dapat dipulihkan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-status-danger hover:underline">Hapus Permanen</button>
                                                </form>
                                            @else
                                                {{-- Menuju halaman perbaikan, yang juga memuat Batalkan
                                                     beserta isian alasannya. Tombol Batalkan sekali-klik yang
                                                     dulu ada di sini mengirim alasan kalengan "Dibatalkan
                                                     lewat halaman Keuangan" — kalimat yang tidak menjelaskan
                                                     apa pun kepada pihak yang menitipkan dananya. --}}
                                                <a href="{{ route('koperasi.dana-hibah.edit', ['jenis' => 'keluar', 'id' => $r->id]) }}" class="text-status-info hover:underline">Perbaiki</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-text-secondary italic">Belum ada penggunaan hibah.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
