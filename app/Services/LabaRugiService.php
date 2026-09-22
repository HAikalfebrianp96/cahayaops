<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Pengeluaran;
use App\Models\SalesTransaction;

class LabaRugiService
{
    /**
     * Hitung Laba Rugi untuk periode & unit tertentu.
     *
     * Prinsip:
     * - SETIAP item masuk Penjualan (subtotal) dan HPP (qty x
     *   harga_modal_snapshot); Laba Kotor = Penjualan - HPP. Tidak ada lagi
     *   item yang dikecualikan karena jenisnya ("Titip Jual" sudah dihapus).
     * - Hak Mitra dicatat BERDAMPINGAN, tidak mengurangi laba.
     * - Fee Koperasi hanya dari transaksi lama (fee_amount); transaksi baru
     *   selalu 0. Tetap ditambahkan ke laba supaya angka lama tidak hilang.
     * - Piutang = sisa tagihan transaksi yang sama — informasi, bukan rumus laba.
     * - Transaksi Draft & Dibatalkan TIDAK DIHITUNG.
     * - Pengeluaran Dibatalkan TIDAK DIHITUNG.
     * - Pengeluaran PEMBELIAN STOK (mis. "Re-Stok Produk") TIDAK mengurangi
     *   laba — biayanya sudah masuk lewat HPP saat barang terjual. Nilainya
     *   dilaporkan terpisah di total['pembelian_stok'].
     * - Pengeluaran Operasional Koperasi (business_unit_id null) TIDAK dibebankan
     *   otomatis ke unit manapun — cuma dikurangi di level total koperasi.
     */
    public function hitung(string $tahun = 'default', string $bulan = 'default', ?string $unitId = null): array
    {
        $transaksiQuery = SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])
            ->with('items');
        $this->applyPeriode($transaksiQuery, $tahun, $bulan, 'tanggal');

        if ($unitId && $unitId !== 'operasional') {
            $transaksiQuery->where('unit_id', $unitId);
        }

        $transaksi = $transaksiQuery->get();

        $units = BusinessUnit::orderBy('nama')->get();
        $perUnit = [];

        foreach ($units as $unit) {
            $perUnit[$unit->id] = $this->kosongkanUnit($unit->nama);
        }

        foreach ($transaksi as $trx) {
            if (! isset($perUnit[$trx->unit_id])) {
                continue; // unit sudah dihapus/tidak dikenal, lewati aman
            }

            // Piutang dari transaksi yang SAMA dengan Penjualan & HPP di bawah
            // — sisa tagihan (total - dibayar) yang belum lunas. Hanya
            // informasi: tidak ikut rumus laba.
            $perUnit[$trx->unit_id]['piutang'] += $trx->sisaPembayaran();

            foreach ($trx->items as $item) {
                // SETIAP item masuk Penjualan dan HPP — tidak ada lagi item
                // yang dikecualikan karena "jenis"-nya.
                $perUnit[$trx->unit_id]['penjualan'] += (float) $item->subtotal;
                $perUnit[$trx->unit_id]['hpp'] += (float) $item->qty * (float) $item->harga_modal_snapshot;

                // Hak Mitra dicatat BERDAMPINGAN, bukan dikurangkan: laba
                // kotor sudah lahir dari Penjualan - HPP.
                $perUnit[$trx->unit_id]['hak_mitra'] += (float) ($item->hak_mitra_amount ?? 0);

                // fee_koperasi hanya untuk transaksi LAMA yang sempat memakai
                // persentase fee. Transaksi baru selalu 0; kolomnya tidak
                // dihapus supaya angka lama tetap punya tempat.
                $perUnit[$trx->unit_id]['fee_koperasi'] += (float) ($item->fee_amount ?? 0);
            }
        }

        // Pengeluaran per unit — bebanUsaha(), BUKAN aktif(): pembelian stok
        // tidak dikurangkan di sini karena biayanya sudah masuk lewat HPP.
        $pengeluaranQuery = Pengeluaran::bebanUsaha();
        $this->applyPeriode($pengeluaranQuery, $tahun, $bulan, 'tanggal');
        $pengeluaranPeriode = (clone $pengeluaranQuery)->get();

        // Pembelian stok periode ini — hanya informasi, tidak masuk rumus.
        $pembelianStokQuery = Pengeluaran::pembelianStok();
        $this->applyPeriode($pembelianStokQuery, $tahun, $bulan, 'tanggal');
        if ($unitId === 'operasional') {
            $pembelianStokQuery->whereNull('business_unit_id');
        } elseif ($unitId) {
            $pembelianStokQuery->where('business_unit_id', $unitId);
        }
        $pembelianStok = (float) $pembelianStokQuery->sum('nominal');

        $pengeluaranOperasional = 0.0;
        foreach ($pengeluaranPeriode as $p) {
            if ($p->business_unit_id === null) {
                $pengeluaranOperasional += (float) $p->nominal;
            } elseif (isset($perUnit[$p->business_unit_id])) {
                $perUnit[$p->business_unit_id]['pengeluaran'] += (float) $p->nominal;
            }
        }

        // Hitung Laba Kotor, Kontribusi Usaha, Laba Bersih, Margin per unit
        foreach ($perUnit as &$u) {
            $u['laba_kotor'] = $u['penjualan'] - $u['hpp'];
            $u['kontribusi_usaha'] = $u['laba_kotor'] + $u['fee_koperasi'];
            $u['laba_bersih'] = $u['kontribusi_usaha'] - $u['pengeluaran'];
            $u['margin'] = $u['penjualan'] > 0 ? ($u['laba_bersih'] / $u['penjualan']) * 100 : null;
        }
        unset($u);

        // Kalau filter unit dipilih spesifik (bukan "semua"), sisakan cuma unit itu di tabel performa
        if ($unitId && $unitId !== 'operasional' && isset($perUnit[$unitId])) {
            $perUnit = [$unitId => $perUnit[$unitId]];
        }

        // Total keseluruhan koperasi
        $total = [
            'penjualan' => 0, 'hpp' => 0, 'laba_kotor' => 0, 'fee_koperasi' => 0,
            'hak_mitra' => 0, 'pengeluaran' => 0, 'kontribusi_usaha' => 0, 'piutang' => 0,
        ];
        foreach ($perUnit as $u) {
            foreach (['penjualan', 'hpp', 'laba_kotor', 'fee_koperasi', 'hak_mitra', 'pengeluaran', 'kontribusi_usaha', 'piutang'] as $key) {
                $total[$key] += $u[$key];
            }
        }
        $total['pengeluaran_operasional'] = $pengeluaranOperasional;
        $total['pengeluaran_total'] = $total['pengeluaran'] + $pengeluaranOperasional;
        $total['pembelian_stok'] = $pembelianStok;
        $total['laba_bersih'] = $total['kontribusi_usaha'] - $total['pengeluaran_total'];
        $total['margin'] = $total['penjualan'] > 0 ? ($total['laba_bersih'] / $total['penjualan']) * 100 : null;

        // Unit Usaha Teratas — berdasar Laba Bersih, kalau sama pakai Penjualan
        $unitTerbaik = collect($perUnit)
            ->filter(fn ($u) => $u['penjualan'] > 0 || $u['pengeluaran'] > 0 || $u['fee_koperasi'] > 0)
            ->sortByDesc(fn ($u) => [$u['laba_bersih'], $u['penjualan']])
            ->first();

        return [
            'total' => $total,
            'per_unit' => $perUnit,
            'unit_terbaik' => $unitTerbaik['nama'] ?? null,
            'ada_transaksi' => $transaksi->isNotEmpty(),
            'ada_pengeluaran' => $pengeluaranPeriode->isNotEmpty() || $pembelianStok > 0,
        ];
    }

    private function kosongkanUnit(string $nama): array
    {
        return [
            'nama' => $nama,
            'penjualan' => 0.0, 'hpp' => 0.0, 'laba_kotor' => 0.0,
            'fee_koperasi' => 0.0, 'hak_mitra' => 0.0, 'pengeluaran' => 0.0, 'piutang' => 0.0,
        ];
    }

    private function applyPeriode($query, string $tahun, string $bulan, string $kolom): void
    {
        if ($tahun === 'default') {
            $query->whereYear($kolom, now()->year);
        } elseif ($tahun !== 'semua') {
            $query->whereYear($kolom, (int) $tahun);
        }

        if ($bulan === 'default') {
            // Default K5: Semua Bulan (beda dari K4 yang default-nya Bulan Ini)
        } elseif ($bulan !== 'semua') {
            $query->whereMonth($kolom, (int) $bulan);
        }
    }
}
