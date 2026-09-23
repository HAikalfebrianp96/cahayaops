<?php

namespace App\Services;

use App\Models\AkunKas;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pengeluaran;
use App\Models\SalesTransaction;
use Illuminate\Support\Collection;

/**
 * TUGAS KOPERASI — apa yang harus dikerjakan Tim Koperasi sekarang.
 *
 * ==========================================================================
 * SETIAP TUGAS DI SINI ADALAH FAKTA YANG HILANG, BUKAN KEBIJAKAN KARANGAN
 * ==========================================================================
 *
 * Sengaja TIDAK ada satu pun ambang waktu di berkas ini — tidak ada "sudah
 * 30 hari", tidak ada "lewat jatuh tempo". Alasannya sederhana: aturan
 * semacam itu belum ada di CahayaOS, dan mengarangnya di sini akan
 * menghasilkan daftar tugas yang terlihat resmi padahal tidak berdasar pada
 * kesepakatan siapa pun.
 *
 * Yang dipakai hanya keadaan yang bisa dibuktikan langsung dari data:
 *
 *   transaksi belum lunas TANPA invoice        -> pembeli belum menerima tagihan
 *   transaksi belum lunas yang SUDAH berinvoice -> uangnya memang belum masuk
 *   anggota aktif TANPA PIN                    -> tidak bisa Cek Simpanan
 *   anggota aktif TANPA satu pun simpanan      -> saldonya nol
 *   pengeluaran aktif TANPA berkas bukti       -> tidak bisa diaudit
 *
 * Kelimanya adalah "ada/tidak ada", bukan "sudah berapa lama". Tidak ada
 * yang perlu ditebak, dan tidak ada angka yang bisa diperdebatkan.
 *
 * SATU YANG SENGAJA TIDAK DIMASUKKAN: "belum setor simpanan wajib bulan
 * ini". Terdengar wajar, tapi CahayaOS TIDAK menyimpan kewajiban setoran di
 * mana pun — tabel koperasi_settings tidak punya nominal maupun jadwalnya.
 * Menambahkannya berarti mengarang kewajiban atas nama koperasi.
 *
 * ==========================================================================
 * KENAPA SERVICE, BUKAN QUERY DI CONTROLLER
 * ==========================================================================
 *
 * Angkanya muncul di DUA tempat: ringkasan di Dashboard dan daftar di
 * halaman Tugas Koperasi. Pada Phase 1 kesalahan yang persis seperti ini
 * sudah terjadi sekali — Dashboard menghitung sendiri dengan ambang yang
 * ditulis tetap, lalu memberi angka berbeda dari halamannya. Satu sumber
 * membuat perbedaan itu mustahil.
 */
class TugasKoperasiService
{
    /**
     * Berapa banyak baris yang ditampilkan per kelompok di halaman.
     *
     * Daftar tugas dikerjakan dari atas ke bawah, jadi menampilkan ribuan
     * kartu sekaligus tidak menolong siapa pun — dan pada volume beberapa
     * tahun pemakaian, memuat semuanya membuat halamannya sendiri lambat.
     *
     * JUMLAHNYA tetap dihitung utuh (lihat ringkas()), jadi yang dipotong
     * hanya tampilannya, bukan kabarnya. Ini pelajaran dari Tugas Saya:
     * pekerjaan yang hilang dari daftar jauh lebih berbahaya daripada
     * daftar yang panjang.
     */
    public const BATAS_TAMPIL = 50;

    /**
     * Transaksi BELUM LUNAS yang belum punya invoice — pembelinya harus
     * membayar, tapi belum menerima tagihan.
     *
     * Penjualan yang SUDAH LUNAS tanpa invoice bukan tugas: pembeli sudah
     * membayar dan membawa barangnya (mis. jual tunai, ambil di tempat), tidak
     * ada yang menunggu. Invoice untuknya tetap bisa dibuat kapan saja dari
     * halaman detail. Sebelumnya penjualan lunas ikut dihitung, sehingga
     * "Perlu Segera" berisi ratusan penjualan tunai yang sudah selesai.
     *
     * Draft & Dibatalkan dikecualikan lewat penyaring yang SAMA dengan yang
     * dipakai laporan dan laba rugi, supaya daftar tugas tidak pernah
     * menagih pekerjaan untuk transaksi yang memang sudah batal.
     */
    public function transaksiTanpaInvoice(): Collection
    {
        return $this->belumLunas()
            ->whereDoesntHave('invoice')
            ->with(['businessUnitTermasukArsip', 'customerTermasukArsip'])
            ->orderBy('tanggal')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    /**
     * Transaksi belum lunas yang SUDAH berinvoice — tinggal pembayarannya.
     *
     * Yang belum berinvoice ada di transaksiTanpaInvoice(); kedua kelompok
     * tidak beririsan, jadi satu transaksi tidak pernah terhitung dua kali.
     */
    public function transaksiBelumLunas(): Collection
    {
        return $this->belumLunas()
            ->whereHas('invoice')
            ->with(['businessUnitTermasukArsip', 'customerTermasukArsip'])
            ->orderBy('tanggal')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    /**
     * Anggota aktif yang belum punya PIN.
     *
     * Tanpa PIN, anggota TIDAK BISA membuka halaman Cek Simpanan sama
     * sekali — satu-satunya cara ia melihat saldonya sendiri tanpa akun.
     * Jadi ini bukan kelengkapan administratif, melainkan pintu yang masih
     * terkunci untuk orangnya.
     */
    public function anggotaTanpaPin(): Collection
    {
        return KoperasiMember::where('status', 'Aktif')
            ->whereNull('pin_hash')
            ->orderBy('nama')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    /** Anggota aktif yang belum punya satu pun entri simpanan. */
    public function anggotaTanpaSimpanan(): Collection
    {
        return KoperasiMember::where('status', 'Aktif')
            ->whereDoesntHave('savings')
            ->orderBy('nama')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    /**
     * Pengeluaran aktif tanpa berkas bukti.
     *
     * Pengeluaran mengurangi laba koperasi. Yang tidak punya bukti tetap
     * dihitung — jadi selisihnya nyata, hanya tidak bisa dipertanggung-
     * jawabkan saat diperiksa.
     */
    public function pengeluaranTanpaBukti(): Collection
    {
        return Pengeluaran::aktif()
            ->whereNull('bukti_path')
            ->with('businessUnitTermasukArsip')
            ->orderBy('tanggal')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    /**
     * Akun kas/bank aktif yang saldo awalnya belum ditetapkan.
     *
     * Tanpa titik awal, "uang tersedia" di Kas & Bank dihitung dari Rp0 —
     * angkanya tampak pasti padahal belum bisa dipercaya.
     */
    public function akunTanpaSaldoAwal(): Collection
    {
        return AkunKas::aktif()->whereNull('tanggal_saldo_awal')->orderBy('nama')->get();
    }

    /**
     * Peserta Cahaya Preneur yang belum menjadi anggota koperasi.
     *
     * Bukan pekerjaan yang mendesak, tapi inilah satu-satunya jembatan
     * antara dua modul CahayaOS — dan tanpa diingatkan, ia tidak pernah
     * terlihat oleh siapa pun.
     */
    public function calonAnggota(): Collection
    {
        return Participant::whereDoesntHave('koperasiMember')
            ->whereIn('status', Participant::STATUS_BERJALAN)
            ->with('pendamping')
            ->orderBy('nama_peserta')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    /**
     * JUMLAH tugas saja — tanpa memuat satu baris pun ke memori.
     *
     * ==================================================================
     * KENAPA TERPISAH DARI semua()
     * ==================================================================
     *
     * Ringkasan di Dashboard cuma butuh angka. Versi pertama service ini
     * memakai semua() untuk itu, yang berarti memuat SELURUH transaksi,
     * anggota, dan pengeluaran beserta relasinya hanya untuk dihitung.
     *
     * Terukur pada volume lima tahun pemakaian (3.000 transaksi): halaman
     * Tugas Koperasi memakan 18,5 MB memori dan 1,6 detik — hampir
     * seluruhnya untuk membangun objek yang langsung dibuang setelah
     * dihitung. Pada data hari ini hal itu sama sekali tidak terasa.
     *
     * @return array<string, int>
     */
    public function ringkas(): array
    {
        // = jumlah transaksi belum lunas: dua kelompok di bawah tidak
        // beririsan (belum berinvoice / sudah berinvoice).
        $segera = $this->belumLunas()->whereDoesntHave('invoice')->count()
            + $this->belumLunas()->whereHas('invoice')->count();

        $perhatian = KoperasiMember::where('status', 'Aktif')->whereNull('pin_hash')->count()
            + KoperasiMember::where('status', 'Aktif')->whereDoesntHave('savings')->count()
            + Pengeluaran::aktif()->whereNull('bukti_path')->count()
            + AkunKas::aktif()->whereNull('tanggal_saldo_awal')->count();

        return [
            'segera' => $segera,
            'perhatian' => $perhatian,
            'calon_anggota' => Participant::whereDoesntHave('koperasiMember')
                ->whereIn('status', Participant::STATUS_BERJALAN)
                ->count(),
            // Transaksi aktif yang sudah beres: LUNAS (invoice opsional).
            // Dihitung supaya "tidak ada yang tampil" terbaca sebagai selesai,
            // bukan sebagai terlewat.
            'beres' => $this->transaksiAktif()
                ->where('status_pembayaran', 'Lunas')
                ->count(),
        ];
    }

    /**
     * Seluruh tugas sekaligus, dikelompokkan menurut mendesaknya.
     *
     * @return array{segera: array, perhatian: array, info: array, ringkas: array}
     */
    public function semua(): array
    {
        $tanpaInvoice = $this->transaksiTanpaInvoice();
        $belumLunas = $this->transaksiBelumLunas();
        $tanpaPin = $this->anggotaTanpaPin();
        $tanpaSimpanan = $this->anggotaTanpaSimpanan();
        $tanpaBukti = $this->pengeluaranTanpaBukti();
        $calon = $this->calonAnggota();

        // SEGERA = ada pihak lain yang sedang menunggu: uang penjualan yang
        // belum masuk — dipisah menurut sudah/belum ada tagihannya.
        $segera = [
            'transaksi_tanpa_invoice' => $tanpaInvoice,
            'transaksi_belum_lunas' => $belumLunas,
        ];

        // PERHATIAN = catatan koperasi belum lengkap. Tidak ada yang
        // menunggu hari ini, tapi dibiarkan terus akan menyulitkan saat
        // penyusunan SHU dan pemeriksaan.
        $perhatian = [
            'anggota_tanpa_pin' => $tanpaPin,
            'anggota_tanpa_simpanan' => $tanpaSimpanan,
            'pengeluaran_tanpa_bukti' => $tanpaBukti,
            'akun_tanpa_saldo_awal' => $this->akunTanpaSaldoAwal(),
        ];

        $info = [
            'calon_anggota' => $calon,
        ];

        return [
            'segera' => $segera,
            'perhatian' => $perhatian,
            'info' => $info,
            // Angkanya dihitung ulang lewat ringkas() — query COUNT, bukan
            // hasil menghitung koleksi yang sudah dipotong BATAS_TAMPIL.
            // Kalau dihitung dari daftarnya, jumlahnya akan ikut terpotong
            // dan Tim Koperasi mengira pekerjaannya lebih sedikit.
            'ringkas' => $this->ringkas(),
        ];
    }

    /**
     * Penyaring transaksi yang DIHITUNG, sama persis dengan yang dipakai
     * laporan penjualan, laba rugi, dan SHU.
     */
    private function transaksiAktif()
    {
        return SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan']);
    }

    /**
     * Transaksi aktif yang belum lunas. status_pembayaran selalu diturunkan
     * dari riwayat pembayaran (PembayaranPenjualanService::hitungUlang), jadi
     * sama dengan "sisa tagihan > 0" yang dipakai Piutang Aktif.
     */
    private function belumLunas()
    {
        return $this->transaksiAktif()->whereIn('status_pembayaran', ['Belum Dibayar', 'DP']);
    }
}
