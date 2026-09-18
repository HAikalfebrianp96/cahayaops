<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\KoperasiMember;
use App\Models\LegalitasRecord;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pengeluaran;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\TrainingRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * HAPUS PERMANEN — tingkat ketiga, dan satu-satunya yang tidak bisa dibatalkan.
 *
 * ==========================================================================
 * KENAPA TERPUSAT DI SATU BERKAS
 * ==========================================================================
 *
 * Dua belas modul memakai Soft Delete. Menaruh "hapus permanen" di
 * masing-masing controller berarti dua belas salinan aturan pengaman yang
 * harus sama persis — dan pada penghapusan permanen, satu salinan yang
 * ketinggalan aturan bukan menghasilkan bug tampilan, melainkan data yang
 * hilang selamanya.
 *
 * ==========================================================================
 * TIGA PENGAMAN, BERURUTAN
 * ==========================================================================
 *
 * 1. HARUS SUDAH DIARSIPKAN LEBIH DULU.
 *    Tidak ada jalan dari "aktif" langsung ke "hilang selamanya". Data yang
 *    masih aktif harus diarsipkan dulu, dan pengarsipan itu sendiri sudah
 *    punya konfirmasinya. Artinya penghapusan permanen selalu merupakan
 *    keputusan KEDUA, diambil pada waktu yang berbeda dari yang pertama.
 *
 * 2. TIDAK BOLEH PUNYA TANGGUNGAN HISTORI.
 *    Inilah inti berkas ini. Sebuah produk yang pernah terjual, seorang
 *    peserta yang punya catatan pendampingan, seorang anggota yang punya
 *    simpanan — semuanya sudah menjadi bagian dari angka yang pernah
 *    dilaporkan. Menghapusnya membuat laporan lama tidak bisa dijelaskan
 *    lagi. Data semacam itu DITOLAK, dan alasannya disebutkan dengan
 *    bahasa yang bisa dipahami operator, bukan pesan error teknis.
 *
 * 3. HANYA ADMIN, DENGAN KALIMAT KONFIRMASI YANG DIKETIK ULANG.
 *    Diperiksa di controller, bukan di sini.
 *
 * ==========================================================================
 * KENAPA TIDAK ADA PENANDA "DATA TESTING"
 * ==========================================================================
 *
 * Pilihan yang disengaja. Menambah kolom `is_test` berarti setiap orang yang
 * membuat data harus ingat mencentangnya — dan yang jauh lebih berbahaya,
 * ingat MEMBERSIHKANNYA. Satu centang yang tertinggal akan mengeluarkan
 * penjualan sungguhan dari laporan keuangan secara diam-diam, dan kesalahan
 * seperti itu tidak menimbulkan error apa pun; ia hanya membuat angkanya
 * salah.
 *
 * Kebutuhan sebenarnya adalah "bersihkan data uji coba", dan itu sudah
 * terpenuhi: data uji coba tidak punya tanggungan histori, jadi ia justru
 * satu-satunya jenis data yang lolos pengaman nomor 2 di atas. Data
 * sungguhan tertahan dengan sendirinya — bukan karena ada yang menandainya,
 * melainkan karena ia memang punya jejak.
 */
class PenghapusanPermanenService
{
    /**
     * Kalimat yang WAJIB diketik ulang persis. Sama polanya dengan Restore
     * Database, supaya Admin tidak perlu menghafal dua kebiasaan berbeda
     * untuk dua tindakan yang sama-sama tidak bisa dibatalkan.
     */
    public const KONFIRMASI = 'HAPUS PERMANEN';

    /**
     * Modul yang boleh dihapus permanen, dipetakan dari kunci tab Arsip.
     *
     * Kuncinya SENGAJA sama dengan kunci tab di ArsipController: kalau suatu
     * hari ada tab baru yang lupa didaftarkan di sini, akibatnya adalah
     * tombolnya tidak muncul — bukan penghapusan yang lolos tanpa pengaman.
     * Kelalaian yang aman.
     *
     * @return array<string, class-string<Model>>
     */
    public static function modulTersedia(): array
    {
        return [
            'peserta' => Participant::class,
            'pendamping' => Pendamping::class,
            'pendampingan' => PendampinganRecord::class,
            'pelatihan' => TrainingRecord::class,
            'omzet' => OmzetReport::class,
            'hpp' => HppCalculation::class,
            'legalitas' => LegalitasRecord::class,
            'perkembangan' => ParticipantMeasurement::class,
            'anggota' => KoperasiMember::class,
            'unit-usaha' => BusinessUnit::class,
            'produk' => ProductService::class,
            'pelanggan' => Customer::class,
            'kategori' => PengeluaranCategory::class,

            // ==========================================================
            // CATATAN UANG YANG SUDAH DIBATALKAN
            // ==========================================================
            //
            // Ketiganya TIDAK memakai SoftDeletes — barisnya berpindah status
            // menjadi "Dibatalkan". Sebelumnya ketiganya sama sekali tidak
            // bisa dihapus, dengan alasan ini catatan keuangan.
            //
            // Alasan itu benar untuk transaksi yang SUNGGUHAN terjadi, dan
            // pembatalan memang koreksi yang tepat untuknya. Tapi CahayaOS
            // juga dipakai uji coba, dan transaksi percobaan yang sudah
            // dibatalkan tidak punya nilai histori apa pun — ia hanya
            // menumpuk di daftar dan mengaburkan angka yang sebenarnya.
            //
            // Pengamannya: HARUS dibatalkan lebih dulu (lihat
            // tahanCatatanUang), jadi menghapusnya selalu keputusan KEDUA
            // yang diambil pada waktu berbeda dari yang pertama.
            'penjualan-batal' => SalesTransaction::class,
            'pengeluaran-batal' => Pengeluaran::class,
            'simpanan-batal' => MemberSaving::class,
        ];
    }

    /**
     * Alasan kenapa data ini TIDAK boleh dihapus permanen, atau null kalau
     * memang boleh.
     *
     * Mengembalikan kalimat, bukan boolean, karena operator yang tombolnya
     * tidak berfungsi berhak tahu APA yang menahannya — dan biasanya itu
     * justru informasi yang berguna ("ternyata produk ini pernah terjual").
     */
    public function alasanTertahan(Model $model): ?string
    {
        // Pengaman 1a — catatan uang: harus sudah DIBATALKAN lebih dulu.
        if ($model instanceof SalesTransaction || $model instanceof Pengeluaran || $model instanceof MemberSaving) {
            $alasanUang = $this->tahanCatatanUang($model);

            if ($alasanUang !== null) {
                return $alasanUang;
            }

            return null;
        }

        // Pengaman 1b — data bermaster: harus sudah lewat tahap arsip.
        if (method_exists($model, 'trashed') && ! $model->trashed()) {
            return 'Data ini masih aktif. Arsipkan dulu, baru bisa dihapus permanen.';
        }

        // Pengaman 2 — tanggungan histori, per jenis data.
        return match (true) {
            $model instanceof Participant => $this->tahanPeserta($model),
            $model instanceof Pendamping => $this->tahanPendamping($model),
            $model instanceof KoperasiMember => $this->tahanAnggota($model),
            $model instanceof BusinessUnit => $this->tahanUnitUsaha($model),
            $model instanceof ProductService => $this->tahanProduk($model),
            $model instanceof Customer => $this->tahanPelanggan($model),
            $model instanceof PengeluaranCategory => $this->tahanKategori($model),
            default => null,
        };
    }

    public function bolehDihapus(Model $model): bool
    {
        return $this->alasanTertahan($model) === null;
    }

    /**
     * Hapus permanen. HANYA panggil setelah alasanTertahan() mengembalikan
     * null dan otorisasi sudah diperiksa — pemeriksaannya diulang di sini
     * supaya tidak ada jalur yang bisa melewatinya.
     *
     * @throws \RuntimeException
     */
    /**
     * Kolom yang menyimpan path berkas unggahan.
     *
     * Disebut di satu tempat supaya menambah jenis unggahan baru hanya
     * perlu menambah satu baris di sini, bukan menelusuri seluruh alur
     * penghapusan.
     */
    private const KOLOM_BERKAS = ['file_path', 'bukti_path'];

    public function hapus(Model $model): void
    {
        $alasan = $this->alasanTertahan($model);

        if ($alasan !== null) {
            throw new \RuntimeException($alasan);
        }

        // ============================================================
        // BERKAS UNGGAHAN DIBUANG HANYA DI SINI — bukan saat diarsipkan.
        //
        // Pengarsipan harus bisa dibatalkan, dan itu mustahil kalau
        // dokumennya sudah hilang dari penyimpanan.
        //
        // Disapu berdasarkan NAMA KOLOM, bukan jenis modelnya.
        //
        // Sebelumnya baris ini hanya mengurus LegalitasRecord. Padahal
        // Pengeluaran juga bisa dihapus permanen lewat alur yang sama dan
        // juga menyimpan berkas (bukti_path) — struk belanjanya akan
        // tertinggal di disk selamanya, tanpa satu pun baris yang
        // menunjuknya lagi.
        //
        // Menyapu per kolom membuat model yang menyusul ikut terurus
        // dengan sendirinya. Kalau dipilih per jenis model, setiap
        // penambahan menuntut orang mengingat tempat ini — dan yang
        // terlupa tidak akan menimbulkan error, hanya berkas yatim yang
        // menumpuk diam-diam.
        // ============================================================
        foreach (self::KOLOM_BERKAS as $kolom) {
            $path = $model->getAttribute($kolom);

            if (is_string($path) && $path !== '') {
                Storage::delete($path);
            }
        }

        $model->forceDelete();
    }

    /**
     * Ringkasan isi data untuk dicatat di Activity Log SEBELUM dihapus.
     *
     * Setelah forceDelete tidak ada apa pun yang tersisa untuk dirujuk, jadi
     * keterangan inilah satu-satunya jejak bahwa data ini pernah ada. Ia
     * sengaja memuat identitas yang bisa dikenali manusia, bukan hanya id.
     */
    public function ringkasan(Model $model): string
    {
        $nama = $model->nomor_transaksi
            ?? $model->nama_peserta
            ?? $model->nama_produk
            ?? $model->nama_pelatihan
            ?? $model->nama
            ?? $model->materi
            ?? $model->jenis_dokumen
            ?? ('#' . $model->getKey());

        return class_basename($model) . ' #' . $model->getKey() . ' — ' . $nama;
    }

    // ==================================================================
    // PENGAMAN PER JENIS DATA
    //
    // Semuanya memakai withTrashed()/hitungan mentah: catatan yang ikut
    // diarsipkan TETAP dihitung sebagai tanggungan. Kalau tidak, peserta
    // bisa dihapus permanen hanya dengan mengarsipkan dulu seluruh catatan
    // pendampingannya — pengamannya jadi sekadar formalitas.
    // ==================================================================

    /**
     * Catatan uang hanya boleh dihapus permanen kalau sudah dibatalkan.
     *
     * Pembatalan itulah batas antara "transaksi sungguhan yang dikoreksi"
     * dan "catatan percobaan yang memang tidak pernah berarti". Selama
     * barisnya masih aktif, ia sedang dihitung di laba rugi dan laporan —
     * menghapusnya diam-diam akan mengubah angka yang sudah dilaporkan
     * tanpa menyisakan jejak apa pun.
     */
    private function tahanCatatanUang($model): ?string
    {
        if ($model instanceof SalesTransaction) {
            return $model->status_transaksi === SalesTransaction::STATUS_DIBATALKAN
                ? null
                : 'Transaksi ini masih aktif. Batalkan dulu (beserta alasannya), baru bisa dihapus permanen.';
        }

        if ($model instanceof Pengeluaran) {
            return $model->dibatalkan()
                ? null
                : 'Pengeluaran ini masih aktif. Batalkan dulu (beserta alasannya), baru bisa dihapus permanen.';
        }

        if ($model instanceof MemberSaving) {
            if (! $model->dibatalkan()) {
                return 'Entri simpanan ini masih berlaku. Batalkan dulu (beserta alasannya), baru bisa dihapus permanen.';
            }

            // Entri asal yang sudah punya pengganti adalah mata rantai
            // koreksi: menghapusnya membuat entri pengganti menunjuk ke
            // sesuatu yang tidak ada, dan riwayat koreksinya jadi terputus.
            if ($model->penggantiKoreksi()->exists()) {
                return 'Entri ini sudah digantikan entri lain. Menghapusnya memutus rantai koreksi simpanan anggota.';
            }

            return null;
        }

        return null;
    }

    private function tahanPeserta(Participant $peserta): ?string
    {
        $jejak = [
            'laporan omzet' => $peserta->omzetReports()->withTrashed()->count(),
            'catatan pendampingan' => $peserta->pendampinganRecords()->withTrashed()->count(),
            'catatan pelatihan' => $peserta->trainingRecords()->withTrashed()->count(),
            'perhitungan HPP' => $peserta->hppCalculations()->withTrashed()->count(),
            'pengukuran perkembangan' => $peserta->measurements()->withTrashed()->count(),
            'dokumen legalitas' => $peserta->legalitasRecords()->withTrashed()->count(),
        ];

        if ($peserta->koperasiMember()->withTrashed()->exists()) {
            return 'Peserta ini terdaftar sebagai anggota koperasi. Hapus permanen keanggotaannya dulu, atau biarkan tetap diarsipkan.';
        }

        return $this->rangkumJejak($jejak, 'Peserta');
    }

    private function tahanPendamping(Pendamping $pendamping): ?string
    {
        $jumlah = $pendamping->participants()->withTrashed()->count();

        if ($jumlah > 0) {
            return "Pendamping ini masih tercatat membina {$jumlah} peserta. Pindahkan pesertanya lebih dulu, atau biarkan tetap diarsipkan.";
        }

        return null;
    }

    private function tahanAnggota(KoperasiMember $anggota): ?string
    {
        $jumlah = $anggota->savings()->count();

        if ($jumlah > 0) {
            return "Anggota ini punya {$jumlah} entri simpanan. Riwayat simpanan adalah catatan uang anggota dan tidak boleh hilang — biarkan tetap diarsipkan.";
        }

        return null;
    }

    private function tahanUnitUsaha(BusinessUnit $unit): ?string
    {
        $jejak = [
            'transaksi penjualan' => DB::table('sales_transactions')->where('unit_id', $unit->id)->count(),
            'pengeluaran' => DB::table('pengeluaran')->where('business_unit_id', $unit->id)->count(),
            'produk & jasa' => DB::table('products_services')->where('unit_id', $unit->id)->count(),
        ];

        return $this->rangkumJejak($jejak, 'Unit usaha');
    }

    private function tahanProduk(ProductService $produk): ?string
    {
        $jumlah = DB::table('sales_transaction_items')->where('product_service_id', $produk->id)->count();

        if ($jumlah > 0) {
            return "Produk ini sudah dipakai di {$jumlah} baris transaksi penjualan. Menghapusnya membuat transaksi lama tidak bisa ditelusuri — biarkan tetap diarsipkan.";
        }

        return null;
    }

    private function tahanPelanggan(Customer $pelanggan): ?string
    {
        $jumlah = $pelanggan->salesTransactions()->count();

        if ($jumlah > 0) {
            return "Pelanggan ini punya {$jumlah} transaksi penjualan. Menghapusnya membuat transaksi lama kehilangan pemiliknya — biarkan tetap diarsipkan.";
        }

        return null;
    }

    private function tahanKategori(PengeluaranCategory $kategori): ?string
    {
        $jumlah = $kategori->pengeluaran()->count();

        if ($jumlah > 0) {
            return "Kategori ini masih dipakai {$jumlah} pengeluaran. Menghapusnya membuat pengeluaran lama kehilangan kategorinya — biarkan tetap diarsipkan.";
        }

        return null;
    }

    /**
     * Susun satu kalimat dari daftar tanggungan yang tidak nol.
     *
     * @param  array<string, int>  $jejak
     */
    private function rangkumJejak(array $jejak, string $sebutan): ?string
    {
        $ada = [];
        foreach ($jejak as $label => $jumlah) {
            if ($jumlah > 0) {
                $ada[] = "{$jumlah} {$label}";
            }
        }

        if ($ada === []) {
            return null;
        }

        return "{$sebutan} ini punya " . implode(', ', $ada)
            . '. Data itu sudah masuk ke laporan yang pernah diterbitkan — biarkan tetap diarsipkan.';
    }
}
