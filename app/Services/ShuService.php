<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\SalesTransaction;
use App\Models\ShuPeriod;
use Illuminate\Support\Facades\DB;

/**
 * PEMBAGIAN SHU.
 *
 * Dua hal yang membedakan kelas ini dari sekadar "kalikan persen":
 *
 * 1. SIMPANAN SUKARELA TIDAK IKUT MENJADI DASAR JASA MODAL.
 *
 *    Jasa modal adalah imbalan atas MODAL yang disertakan anggota ke
 *    koperasi — yaitu simpanan pokok dan simpanan wajib. Simpanan sukarela
 *    sifatnya titipan yang bisa ditarik sewaktu-waktu; memperlakukannya
 *    sebagai modal berarti anggota yang kebetulan menitipkan uang besar
 *    menjelang tutup buku mendapat bagian SHU yang tidak ia usahakan.
 *    Ini keputusan yang mudah terlewat karena hasilnya tetap berupa angka
 *    yang masuk akal.
 *
 * 2. DASARNYA DIBEKUKAN SAAT PENETAPAN.
 *
 *    Saldo simpanan dan nilai transaksi terus bergerak. Alokasi yang sudah
 *    disahkan Rapat Anggota tidak boleh berubah sendiri minggu depan, jadi
 *    angka dasarnya disimpan, bukan dihitung ulang saat ditampilkan.
 *
 * Anggota yang dasarnya nol TIDAK diberi baris alokasi kosong — daftarnya
 * memuat anggota yang benar-benar mendapat bagian.
 */
class ShuService
{
    public function __construct(private LabaRugiService $labaRugi)
    {
    }

    /**
     * Usulan total SHU dari Laba Rugi koperasi tahun tersebut.
     *
     * Hanya USULAN — angka final tetap keputusan Rapat Anggota, dan form
     * penetapan mengizinkannya diubah. LabaRugiService sudah memperlakukan
     * titip jual dengan benar (pendapatan koperasi hanya fee-nya, bukan
     * nilai penjualan penuh), jadi angka ini tidak menggelembung oleh
     * penjualan barang milik orang lain.
     */
    public function usulanDariLabaRugi(int $tahun): int
    {
        $hasil = $this->labaRugi->hitung((string) $tahun, 'semua');

        return (int) round(max(0, $hasil['total']['laba_bersih']));
    }

    /**
     * Dasar pembagian per anggota pada satu tahun buku.
     *
     * @return array<int, array{simpanan: int, transaksi: int}> dikunci member id
     */
    public function dasarPembagian(int $tahun): array
    {
        $dasar = [];

        foreach (KoperasiMember::pluck('id') as $id) {
            $dasar[$id] = ['simpanan' => 0, 'transaksi' => 0];
        }

        // --- Jasa modal: simpanan pokok + wajib, sampai akhir tahun buku. ---
        //
        // Memakai scope berlaku() supaya simpanan yang sudah DIBATALKAN tidak
        // ikut terhitung. Melewatkan scope ini di satu tempat saja sudah cukup
        // untuk membuat angkanya salah tanpa terlihat salah.
        $simpanan = MemberSaving::berlaku()
            ->whereIn('jenis_simpanan', ['Simpanan Pokok', 'Simpanan Wajib'])
            ->whereYear('tanggal', '<=', $tahun)
            ->select('koperasi_member_id', DB::raw('SUM(nominal) as jumlah'))
            ->groupBy('koperasi_member_id')
            ->pluck('jumlah', 'koperasi_member_id');

        foreach ($simpanan as $memberId => $jumlah) {
            if (isset($dasar[$memberId])) {
                $dasar[$memberId]['simpanan'] = (int) round((float) $jumlah);
            }
        }

        // --- Jasa usaha: nilai transaksi anggota pada tahun itu. ---
        //
        // Lewat tautan customers.koperasi_member_id. Pelanggan yang bukan
        // anggota (tautannya kosong) tidak ikut — dan itu benar: jasa usaha
        // adalah bagi hasil untuk anggota yang berbelanja di koperasinya
        // sendiri, bukan untuk setiap pembeli.
        $transaksi = SalesTransaction::query()
            ->whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])
            ->whereYear('tanggal', $tahun)
            ->whereIn('customer_id', Customer::whereNotNull('koperasi_member_id')->pluck('id'))
            ->join('customers', 'customers.id', '=', 'sales_transactions.customer_id')
            ->select('customers.koperasi_member_id', DB::raw('SUM(sales_transactions.total) as jumlah'))
            ->groupBy('customers.koperasi_member_id')
            ->pluck('jumlah', 'koperasi_member_id');

        foreach ($transaksi as $memberId => $jumlah) {
            if (isset($dasar[$memberId])) {
                $dasar[$memberId]['transaksi'] = (int) round((float) $jumlah);
            }
        }

        return $dasar;
    }

    /**
     * Susun ulang alokasi per anggota untuk satu penetapan.
     *
     * Alokasi lama DIHAPUS lalu ditulis ulang — bukan ditambahkan. Penetapan
     * yang masih Draft memang dimaksudkan untuk disusun berkali-kali sampai
     * angkanya benar; kalau barisnya menumpuk, totalnya akan berlipat tanpa
     * ada yang menyadarinya.
     *
     * Penetapan yang sudah Final tidak sampai ke sini — controller yang
     * menolaknya lebih dulu lewat ShuPeriod::terkunci().
     */
    public function susunAlokasi(ShuPeriod $periode): int
    {
        $dasar = $this->dasarPembagian($periode->tahun);

        $totalSimpanan = array_sum(array_column($dasar, 'simpanan'));
        $totalTransaksi = array_sum(array_column($dasar, 'transaksi'));

        $poolModal = $this->poolKomponen($periode, ShuPeriod::DASAR_SIMPANAN);
        $poolUsaha = $this->poolKomponen($periode, ShuPeriod::DASAR_TRANSAKSI);

        $periode->allocations()->delete();

        // ============================================================
        // DIBAGI UTUH, BUKAN DIBULATKAN SENDIRI-SENDIRI
        //
        // Sebelumnya bagian tiap anggota dihitung round(pool * bobot / total)
        // secara terpisah. Hasil bagi proporsional hampir tidak pernah bulat,
        // jadi jumlah seluruh bagian TIDAK sama dengan pool-nya — dan
        // selisihnya bisa ke dua arah:
        //
        //   3 anggota sama rata, pool 1.000.000  -> dibagikan   999.999
        //   simpanan ganjil,     pool 7.777.777  -> dibagikan 7.777.778
        //
        // Yang kelebihan lebih berat akibatnya: koperasi membagikan lebih
        // daripada yang disahkan Rapat Anggota.
        //
        // bagiProporsional() memakai metode sisa terbesar, yang menjamin
        // jumlahnya sama persis dengan pool.
        // ============================================================
        $bagianModal = $this->bagiProporsional(
            $poolModal,
            array_map(fn ($d) => $d['simpanan'], $dasar)
        );

        $bagianUsaha = $this->bagiProporsional(
            $poolUsaha,
            array_map(fn ($d) => $d['transaksi'], $dasar)
        );

        $dibuat = 0;

        foreach ($dasar as $memberId => $d) {
            $jasaModal = $bagianModal[$memberId] ?? 0;
            $jasaUsaha = $bagianUsaha[$memberId] ?? 0;

            if ($jasaModal === 0 && $jasaUsaha === 0) {
                continue;
            }

            $periode->allocations()->create([
                'koperasi_member_id' => $memberId,
                'dasar_simpanan' => $d['simpanan'],
                'dasar_transaksi' => $d['transaksi'],
                'jasa_modal' => $jasaModal,
                'jasa_usaha' => $jasaUsaha,
                'total' => $jasaModal + $jasaUsaha,
            ]);

            $dibuat++;
        }

        return $dibuat;
    }

    /**
     * BAGI SATU POOL RUPIAH SECARA PROPORSIONAL, TANPA ADA YANG TERCECER.
     *
     * =================================================================
     * KENAPA TIDAK CUKUP round() PER ORANG
     * =================================================================
     *
     * Membulatkan tiap bagian sendiri-sendiri membuat jumlahnya meleset dari
     * pool: kadang kurang, kadang lebih. Untuk uang anggota yang nominalnya
     * ditetapkan Rapat Anggota, "kurang lebih" bukan hasil yang sah — yang
     * dibagikan harus sama persis dengan yang disahkan.
     *
     * =================================================================
     * METODE SISA TERBESAR
     * =================================================================
     *
     * 1. Setiap bagian dibulatkan KE BAWAH lebih dulu. Dengan begitu jumlah
     *    seluruhnya pasti tidak pernah melebihi pool.
     * 2. Sisanya — selalu lebih kecil daripada jumlah penerima — dibagikan
     *    satu rupiah per orang, dimulai dari yang pecahannya paling besar.
     *
     * Hasilnya selalu berjumlah tepat sama dengan pool, tidak pernah negatif,
     * dan yang paling "dirugikan" pembulatan ke bawah adalah yang pertama
     * mendapat tambahannya.
     *
     * Urutan saat pecahannya sama persis mengikuti urutan anggota (arsort
     * mempertahankan urutan masukan untuk nilai yang sama), jadi hasilnya
     * selalu sama untuk data yang sama — bukan bergantung kebetulan.
     *
     * @param  array<int|string, int>  $bobot  simpanan/transaksi per anggota
     * @return array<int|string, int>  nominal rupiah per anggota
     */
    private function bagiProporsional(int $pool, array $bobot): array
    {
        $kosong = array_map(fn () => 0, $bobot);

        $totalBobot = array_sum($bobot);

        // Pool kosong, atau tidak ada satu pun simpanan/transaksi: komponen
        // itu TIDAK dibagikan sama sekali — bukan dibagi rata ke semua
        // anggota, karena dasarnya memang tidak ada.
        if ($pool <= 0 || $totalBobot <= 0) {
            return $kosong;
        }

        $hasil = [];
        $pecahan = [];
        $terbagi = 0;

        foreach ($bobot as $id => $b) {
            $tepat = $pool * $b / $totalBobot;
            $bulat = (int) floor($tepat);

            $hasil[$id] = $bulat;
            $pecahan[$id] = $tepat - $bulat;
            $terbagi += $bulat;
        }

        $sisa = $pool - $terbagi;

        arsort($pecahan);

        foreach (array_keys($pecahan) as $id) {
            if ($sisa <= 0) {
                break;
            }

            $hasil[$id]++;
            $sisa--;
        }

        return $hasil;
    }

    /** Nominal rupiah seluruh komponen yang dibagikan menurut satu dasar. */
    private function poolKomponen(ShuPeriod $periode, string $dasar): int
    {
        $nominal = $periode->nominalKomponenSemua();
        $total = 0;

        // komponenAnggota() mempertahankan kunci posisinya, jadi tiap komponen
        // mengambil nominal miliknya sendiri — bukan dihitung ulang di sini.
        foreach (array_keys($periode->komponenAnggota($dasar)) as $posisi) {
            $total += $nominal[$posisi] ?? 0;
        }

        return $total;
    }

    /**
     * Hal-hal yang perlu dilihat manusia sebelum penetapan disahkan.
     *
     * Tidak memblokir apa pun — penetapan SHU adalah keputusan Rapat Anggota,
     * dan sistem tidak berwenang menolaknya. Yang bisa dilakukan sistem
     * adalah memastikan yang mengesahkan melihat apa yang sedang ia sahkan.
     *
     * @return list<string>
     */
    public function peringatan(ShuPeriod $periode): array
    {
        $peringatan = [];
        $persen = $periode->totalPersen();

        if (abs($persen - 100) > 0.01) {
            $peringatan[] = 'Total persentase komponen ' . rtrim(rtrim(number_format($persen, 2, ',', '.'), '0'), ',')
                . '%, bukan 100%. '
                . ($persen < 100
                    ? 'Sisanya tidak dialokasikan ke mana pun.'
                    : 'Pembagian melebihi total SHU yang tersedia.');
        }

        $dasar = $this->dasarPembagian($periode->tahun);

        if (array_sum(array_column($dasar, 'simpanan')) === 0 && $this->poolKomponen($periode, ShuPeriod::DASAR_SIMPANAN) > 0) {
            // ============================================================
            // DUA SEBAB YANG SANGAT BERBEDA, JANGAN DISAMAKAN
            //
            // Dasar simpanan nol bisa berarti dua hal:
            //
            //   1. memang belum ada yang mencatat simpanan; atau
            //   2. simpanannya ADA, tapi seluruhnya milik anggota yang sudah
            //      diarsipkan — dan anggota terarsip memang tidak menerima
            //      pembagian SHU.
            //
            // Keduanya dulu memunculkan kalimat yang sama: "belum ada
            // simpanan tercatat". Pada keadaan kedua kalimat itu keliru, dan
            // kekeliruannya menyesatkan ke arah yang salah: pengurus akan
            // mencari-cari untuk mencatat simpanan yang sebenarnya sudah ada.
            //
            // Terjadi sungguhan pada data berjalan: Rp160.000 simpanan pokok
            // & wajib tercatat, seluruhnya milik dua anggota terarsip.
            // ============================================================
            $simpananTerarsip = MemberSaving::berlaku()
                ->whereIn('jenis_simpanan', ['Simpanan Pokok', 'Simpanan Wajib'])
                ->whereYear('tanggal', '<=', $periode->tahun)
                ->milikAnggotaTerarsip()
                ->sum('nominal');

            $peringatan[] = $simpananTerarsip > 0
                ? 'Simpanan pokok/wajib yang tercatat seluruhnya milik anggota yang sudah diarsipkan '
                    . '(total Rp' . number_format((float) $simpananTerarsip, 0, ',', '.') . '), '
                    . 'dan anggota terarsip tidak menerima pembagian SHU. '
                    . 'Komponen jasa modal karena itu tidak dapat dibagikan ke anggota mana pun.'
                : 'Belum ada simpanan pokok/wajib tercatat sampai tahun ini, '
                    . 'jadi komponen jasa modal tidak dapat dibagikan ke anggota mana pun.';
        }

        if (array_sum(array_column($dasar, 'transaksi')) === 0 && $this->poolKomponen($periode, ShuPeriod::DASAR_TRANSAKSI) > 0) {
            $peringatan[] = 'Belum ada transaksi penjualan yang tertaut ke anggota pada tahun ini, '
                . 'jadi komponen jasa usaha tidak dapat dibagikan. Tautkan pelanggan ke data anggota '
                . 'lewat menu Pelanggan agar transaksinya terhitung.';
        }

        return $peringatan;
    }
}
