<?php

namespace App\Services;

use App\Models\AkunKas;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\MemberSaving;
use App\Models\PembayaranPenjualan;
use App\Models\Pengeluaran;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * KAS & BANK — TAHAP 5: SALDO, BUKU MUTASI, DAN PEMISAHAN UANG.
 *
 * ==========================================================================
 * TIDAK ADA SALDO YANG DISIMPAN
 * ==========================================================================
 *
 * Saldo setiap akun selalu dijumlahkan langsung dari catatan aslinya:
 *
 *   saldo = saldo awal
 *         + pembayaran penjualan + setoran simpanan + dana masuk
 *         - pengeluaran + dana keluar
 *
 * Tidak ada buku mutasi salinan yang harus disinkronkan. Membatalkan,
 * memulihkan, atau memperbaiki catatan di modul mana pun langsung terlihat
 * di sini — karena memang tidak ada angka lain yang bisa tertinggal.
 *
 * Aturan "berlaku" di sini SAMA PERSIS dengan modul asalnya: scope
 * masukKas(), berlaku(), dan aktif() milik masing-masing model. Kalau aturan
 * pembatalan satu modul berubah, saldo kas ikut berubah tanpa disunting.
 *
 * ==========================================================================
 * SALDO AWAL = UANG PADA AWAL HARI tanggal_saldo_awal
 * ==========================================================================
 *
 * Catatan bertanggal SEBELUM tanggal saldo awal sudah tercermin di saldo
 * awal itu sendiri (uangnya sudah dihitung di laci), jadi tidak dihitung
 * lagi. Catatan pada tanggal itu dan sesudahnya dihitung.
 *
 * Akun yang saldo awalnya belum ditetapkan menghitung SEMUA catatannya
 * dari Rp0 — dan laporan menandainya sebagai belum bisa dipercaya.
 */
class KasBankService
{
    /**
     * Seluruh sumber uang, dengan aturan "berlaku" dari modul asalnya.
     *
     * @return array<string, array{label: string, arah: string, query: \Closure(): Builder}>
     */
    private function sumber(): array
    {
        return [
            'penjualan' => [
                'label' => 'Penerimaan penjualan',
                'arah' => 'masuk',
                'query' => fn () => PembayaranPenjualan::masukKas()->with('transaksi'),
            ],
            'simpanan' => [
                'label' => 'Setoran simpanan',
                'arah' => 'masuk',
                'query' => fn () => MemberSaving::berlaku()->with('koperasiMemberTermasukArsip'),
            ],
            'dana-sosial-masuk' => [
                'label' => 'Dana Sosial masuk',
                'arah' => 'masuk',
                'query' => fn () => DanaSukarelaMasuk::berlaku(),
            ],
            'dana-hibah-masuk' => [
                'label' => 'Dana Hibah masuk',
                'arah' => 'masuk',
                'query' => fn () => DanaHibahMasuk::berlaku(),
            ],
            'pengeluaran' => [
                'label' => 'Pengeluaran',
                'arah' => 'keluar',
                'query' => fn () => Pengeluaran::aktif()->with('businessUnitTermasukArsip'),
            ],
            'dana-sosial-keluar' => [
                'label' => 'Dana Sosial keluar',
                'arah' => 'keluar',
                'query' => fn () => DanaSukarelaKeluar::berlaku(),
            ],
            'dana-hibah-keluar' => [
                'label' => 'Dana Hibah keluar',
                'arah' => 'keluar',
                'query' => fn () => DanaHibahKeluar::berlaku(),
            ],
        ];
    }

    private function saring(Builder $query, ?int $akunId, ?string $dari, ?string $sampai): Builder
    {
        $akunId === null ? $query->whereNull('akun_kas_id') : $query->where('akun_kas_id', $akunId);

        if ($dari !== null) {
            $query->whereDate('tanggal', '>=', $dari);
        }
        if ($sampai !== null) {
            $query->whereDate('tanggal', '<=', $sampai);
        }

        return $query;
    }

    /** Catatan sebelum tanggal ini sudah tercermin di saldo awal. */
    private function batasBawah(AkunKas $akun): ?string
    {
        return $akun->tanggal_saldo_awal?->toDateString();
    }

    /**
     * Saldo akun pada AKHIR hari $sampai (null = semua catatan sampai kini).
     */
    public function saldo(AkunKas $akun, ?string $sampai = null): float
    {
        $bawah = $this->batasBawah($akun);

        if ($sampai !== null && $bawah !== null && $sampai < $bawah) {
            return (float) $akun->saldo_awal;
        }

        $saldo = (float) $akun->saldo_awal;

        foreach ($this->sumber() as $s) {
            $jumlah = (float) $this->saring(($s['query'])(), $akun->id, $bawah, $sampai)->sum('nominal');
            $saldo += $s['arah'] === 'masuk' ? $jumlah : -$jumlah;
        }

        return round($saldo, 2);
    }

    /**
     * Catatan uang yang belum menyebut akunnya. Uangnya ada, tempatnya tidak
     * diketahui — ditampilkan terpisah, tidak dimasukkan diam-diam ke Kas.
     *
     * @return array{jumlah: int, masuk: float, keluar: float, bersih: float}
     */
    public function tanpaAkun(): array
    {
        $hasil = ['jumlah' => 0, 'masuk' => 0.0, 'keluar' => 0.0];

        foreach ($this->sumber() as $s) {
            $query = $this->saring(($s['query'])(), null, null, null);
            $hasil['jumlah'] += (clone $query)->count();
            $hasil[$s['arah']] += (float) $query->sum('nominal');
        }

        $hasil['bersih'] = round($hasil['masuk'] - $hasil['keluar'], 2);

        return $hasil;
    }

    /**
     * Uang yang BUKAN milik usaha: simpanan anggota (kewajiban koperasi
     * kepada anggotanya) dan saldo dana titipan pihak ketiga.
     *
     * Dihitung dari seluruh catatan yang berlaku, tanpa batas tanggal saldo
     * awal: simpanan yang disetor sebelum pencatatan kas dimulai tetap uang
     * anggota.
     *
     * @return array{simpanan: float, dana_sosial: float, dana_hibah: float, total: float}
     */
    public function danaTitipan(): array
    {
        $simpanan = (float) MemberSaving::berlaku()->sum('nominal');
        $sosial = (float) DanaSukarelaMasuk::berlaku()->sum('nominal') - (float) DanaSukarelaKeluar::berlaku()->sum('nominal');
        $hibah = (float) DanaHibahMasuk::berlaku()->sum('nominal') - (float) DanaHibahKeluar::berlaku()->sum('nominal');

        return [
            'simpanan' => round($simpanan, 2),
            'dana_sosial' => round($sosial, 2),
            'dana_hibah' => round($hibah, 2),
            'total' => round($simpanan + $sosial + $hibah, 2),
        ];
    }

    /**
     * Ringkasan untuk halaman Kas & Bank dan Dashboard.
     *
     *   Uang tersedia = saldo semua akun (+ catatan tanpa akun)
     *   Uang usaha    = uang tersedia - dana titipan
     *
     * Uang usaha BUKAN laba: di dalamnya ada modal stok yang kembali dalam
     * bentuk tunai. Laba tetap dibaca dari Laporan Laba Rugi.
     */
    public function ringkasan(): array
    {
        $akun = AkunKas::orderByRaw("status = 'Aktif' DESC")->orderBy('jenis')->orderBy('nama')->get()
            ->map(fn (AkunKas $a) => ['akun' => $a, 'saldo' => $this->saldo($a)]);

        $tanpaAkun = $this->tanpaAkun();
        $titipan = $this->danaTitipan();
        $totalAkun = round((float) $akun->sum('saldo'), 2);
        $uangTersedia = round($totalAkun + $tanpaAkun['bersih'], 2);

        // Akun yang ikut menentukan angka tapi titik awalnya belum ditetapkan:
        // akun aktif, atau akun nonaktif yang masih menyimpan saldo.
        $belumDitetapkan = $akun->filter(
            fn ($r) => ! $r['akun']->saldoAwalDitetapkan() && ($r['akun']->isAktif() || $r['saldo'] != 0.0)
        )->count();

        return [
            'akun' => $akun,
            'total_akun' => $totalAkun,
            'tanpa_akun' => $tanpaAkun,
            'uang_tersedia' => $uangTersedia,
            'titipan' => $titipan,
            'uang_usaha' => round($uangTersedia - $titipan['total'], 2),
            'belum_ditetapkan' => $belumDitetapkan,
            'bisa_dipercaya' => $belumDitetapkan === 0 && $tanpaAkun['jumlah'] === 0,
        ];
    }

    /**
     * Buku mutasi satu akun untuk satu periode, dengan saldo berjalan.
     *
     * Periode yang dimulai sebelum tanggal saldo awal dipotong ke tanggal
     * saldo awal — catatan sebelumnya sudah tercermin di saldo awal.
     */
    public function bukuMutasi(AkunKas $akun, string $dari, string $sampai): array
    {
        $bawah = $this->batasBawah($akun);
        $dariEfektif = ($bawah !== null && $dari < $bawah) ? $bawah : $dari;

        $saldoPembuka = $this->saldo($akun, Carbon::parse($dariEfektif)->subDay()->toDateString());

        $baris = collect();
        $urutanSumber = 0;
        foreach ($this->sumber() as $kunci => $s) {
            $urutanSumber++;
            foreach ($this->saring(($s['query'])(), $akun->id, $dariEfektif, $sampai)->get() as $model) {
                $baris->push($this->baris($kunci, $s, $model, $urutanSumber));
            }
        }

        $saldo = $saldoPembuka;
        $baris = $baris
            ->sortBy([['tanggal', 'asc'], ['urutan', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $b) use (&$saldo) {
                $saldo = round($saldo + $b['masuk'] - $b['keluar'], 2);
                $b['saldo'] = $saldo;

                return $b;
            });

        return [
            'dari' => $dari,
            'dari_efektif' => $dariEfektif,
            'sampai' => $sampai,
            'saldo_pembuka' => $saldoPembuka,
            'baris' => $baris,
            'total_masuk' => round((float) $baris->sum('masuk'), 2),
            'total_keluar' => round((float) $baris->sum('keluar'), 2),
            'saldo_penutup' => $saldo,
        ];
    }

    private function baris(string $kunci, array $sumber, Model $m, int $urutan): array
    {
        [$uraian, $tautan] = match ($kunci) {
            'penjualan' => [
                'Penjualan ' . ($m->transaksi->nomor_transaksi ?? '#' . $m->sales_transaction_id),
                route('koperasi.penjualan.show', $m->sales_transaction_id),
            ],
            'simpanan' => [
                $m->jenis_simpanan . ' — ' . ($m->koperasiMemberTermasukArsip?->namaTampil() ?? 'Anggota #' . $m->koperasi_member_id),
                route('koperasi.anggota.show', $m->koperasi_member_id),
            ],
            'pengeluaran' => [
                $m->kategori . ' — ' . ($m->businessUnitTermasukArsip?->nama ?? 'Operasional Koperasi'),
                route('koperasi.pengeluaran.show', $m->id),
            ],
            'dana-sosial-masuk', 'dana-hibah-masuk' => [
                $sumber['label'] . ' — ' . $m->sumber,
                route('koperasi.keuangan.index'),
            ],
            default => [
                $sumber['label'] . ' — ' . $m->keperluan,
                route('koperasi.keuangan.index'),
            ],
        };

        $nominal = (float) $m->nominal;

        return [
            'tanggal' => $m->tanggal->toDateString(),
            'jenis' => $sumber['label'],
            'uraian' => $uraian,
            'catatan' => $m->keterangan ?? $m->catatan ?? null,
            'tautan' => $tautan,
            'masuk' => $sumber['arah'] === 'masuk' ? $nominal : 0.0,
            'keluar' => $sumber['arah'] === 'keluar' ? $nominal : 0.0,
            'urutan' => $urutan,
            'id' => $m->id,
        ];
    }
}
