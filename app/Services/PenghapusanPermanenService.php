<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\Concerns\BisaDibatalkan;
use App\Models\Customer;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\HppCalculation;
use App\Models\ImpactProgram;
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
use App\Models\ShuPeriod;
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
 * DUA PENGAMAN
 * ==========================================================================
 *
 * Konsep Arsip sudah dilepas dari CahayaOS (keputusan pemilik 18 Sep 2026),
 * jadi tidak ada lagi tahap antara "aktif" dan "hilang". Yang menahan
 * penghapusan sekarang HANYA tanggungan histori di bawah ini — dan justru
 * karena itu ia tidak boleh dilonggarkan.
 *
 * 1. TIDAK BOLEH PUNYA TANGGUNGAN HISTORI.
 *    Inilah inti berkas ini. Sebuah produk yang pernah terjual, seorang
 *    peserta yang punya catatan pendampingan, seorang anggota yang punya
 *    simpanan — semuanya sudah menjadi bagian dari angka yang pernah
 *    dilaporkan. Menghapusnya membuat laporan lama tidak bisa dijelaskan
 *    lagi. Data semacam itu DITOLAK, dan alasannya disebutkan dengan
 *    bahasa yang bisa dipahami operator, bukan pesan error teknis.
 *
 * 2. OTORISASI + KONFIRMASI DI APLIKASI.
 *    Diperiksa di controller/policy modul masing-masing, bukan di sini.
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
     * Daftar modul yang mengenal hapus permanen, dengan kunci yang stabil.
     *
     * Halaman Arsip yang dulu memakai daftar ini sudah dihapus, tapi daftarnya
     * tetap berguna sebagai satu tempat untuk melihat modul mana saja yang
     * ikut aturan penghapusan di berkas ini — dan sebagai tempat mendaftarkan
     * modul baru. Penjagaan sebenarnya ada di alasanTertahan(), bukan di sini.
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
            'dana-sukarela-masuk-batal' => DanaSukarelaMasuk::class,
            'dana-sukarela-keluar-batal' => DanaSukarelaKeluar::class,
            'dana-hibah-masuk-batal' => DanaHibahMasuk::class,
            'dana-hibah-keluar-batal' => DanaHibahKeluar::class,
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
        // Catatan uang: harus sudah DIBATALKAN lebih dulu. Ini BUKAN sisa
        // konsep arsip — pembatalan adalah koreksi akuntansi yang berdiri
        // sendiri, dan tetap jadi syarat supaya angka laporan yang sudah
        // terbit tidak berubah diam-diam.
        if ($this->catatanUang($model)) {
            return $this->tahanCatatanUang($model);
        }

        // Tanggungan histori, per jenis data. Data aktif kini boleh langsung
        // dihapus permanen — yang menahannya hanya jejak di bawah ini.
        return match (true) {
            $model instanceof Participant => $this->tahanPeserta($model),
            $model instanceof Pendamping => $this->tahanPendamping($model),
            $model instanceof KoperasiMember => $this->tahanAnggota($model),
            $model instanceof BusinessUnit => $this->tahanUnitUsaha($model),
            $model instanceof ProductService => $this->tahanProduk($model),
            $model instanceof Customer => $this->tahanPelanggan($model),
            $model instanceof PengeluaranCategory => $this->tahanKategori($model),
            $model instanceof ImpactProgram => $this->tahanProgramDampak($model),
            $model instanceof ShuPeriod => $this->tahanPeriodeShu($model),
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
        // BERKAS UNGGAHAN DIBUANG DI SINI, bersama barisnya.
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
     * Satu langkah lengkap: periksa pengaman, catat ringkasan, hapus, dan
     * tulis Activity Log. Mengembalikan ringkasan untuk pesan ke pengguna.
     *
     * Dipakai SELURUH controller supaya urutan ini tidak disalin dua puluh
     * kali. Pada penghapusan permanen, satu salinan yang lupa mencatat
     * Activity Log berarti data hilang tanpa jejak siapa pun yang
     * menghapusnya — dan itu tidak menimbulkan error apa pun.
     *
     * @throws \RuntimeException bila data tertahan; panggil alasanTertahan()
     *                           lebih dulu supaya pesannya bisa ditampilkan
     *                           sebagai penjelasan, bukan sebagai kegagalan.
     */
    public function hapusDenganCatatan(Model $model): string
    {
        $alasan = $this->alasanTertahan($model);

        if ($alasan !== null) {
            throw new \RuntimeException($alasan);
        }

        // Id dan ringkasan diambil SEBELUM dihapus. Setelah forceDelete tidak
        // ada apa pun yang tersisa untuk dirujuk, dan baris Activity Log
        // inilah satu-satunya bukti bahwa data ini pernah ada.
        $id = $model->getKey();
        $jenis = class_basename($model);
        $ringkasan = $this->ringkasan($model);

        $this->hapus($model);

        ActivityLog::record(
            action: 'permanently_deleted',
            modelType: $jenis,
            modelId: $id,
            description: 'DIHAPUS PERMANEN: ' . $ringkasan . '. Tindakan ini tidak dapat dibatalkan.',
        );

        return $ringkasan;
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
    /**
     * Catatan uang tidak memakai SoftDeletes: barisnya berpindah status
     * menjadi "Dibatalkan". Keempat arus dana dikenali lewat trait
     * [BisaDibatalkan] — bukan lewat daftar nama kelas — supaya jenis dana
     * yang ditambahkan kelak ikut terjaga tanpa ada yang perlu ingat
     * menyunting daftar di sini.
     */
    private function catatanUang(Model $model): bool
    {
        return $model instanceof SalesTransaction
            || $model instanceof Pengeluaran
            || $model instanceof MemberSaving
            || in_array(BisaDibatalkan::class, class_uses_recursive($model), true);
    }

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

        // Dana Sosial & Dana Hibah — titipan pihak ketiga, syaratnya sama:
        // dibatalkan dulu beserta alasannya. Menghapus baris yang masih
        // berlaku membuat saldo berubah tanpa ada yang bisa menjelaskan
        // sebabnya kepada pihak yang menitipkan dananya.
        if (in_array(BisaDibatalkan::class, class_uses_recursive($model), true)) {
            return $model->dibatalkan()
                ? null
                : 'Catatan dana ini masih berlaku. Batalkan dulu (beserta alasannya), baru bisa dihapus permanen.';
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
            return 'Peserta ini terdaftar sebagai anggota koperasi. Hapus permanen keanggotaannya lebih dulu.';
        }

        return $this->rangkumJejak($jejak, 'Peserta');
    }

    private function tahanPendamping(Pendamping $pendamping): ?string
    {
        $jumlah = $pendamping->participants()->withTrashed()->count();

        if ($jumlah > 0) {
            return "Pendamping ini masih tercatat membina {$jumlah} peserta. Pindahkan pesertanya ke pendamping lain lebih dulu.";
        }

        return null;
    }

    private function tahanAnggota(KoperasiMember $anggota): ?string
    {
        $jumlah = $anggota->savings()->count();

        if ($jumlah > 0) {
            return "Anggota ini punya {$jumlah} entri simpanan. Riwayat simpanan adalah catatan uang anggota dan tidak boleh hilang, jadi anggotanya tidak bisa dihapus.";
        }

        // shu_allocations.koperasi_member_id memakai ON DELETE CASCADE:
        // tanpa pemeriksaan ini, menghapus anggota ikut menghapus catatan
        // pembagian SHU-nya secara diam-diam, dan total SHU periode itu
        // tidak lagi cocok dengan yang pernah dibagikan.
        $alokasi = DB::table('shu_allocations')->where('koperasi_member_id', $anggota->id)->count();

        if ($alokasi > 0) {
            return "Anggota ini punya {$alokasi} catatan pembagian SHU. Menghapusnya membuat total SHU periode itu tidak lagi cocok dengan yang pernah dibagikan.";
        }

        return null;
    }

    private function tahanUnitUsaha(BusinessUnit $unit): ?string
    {
        $jejak = [
            'transaksi penjualan' => DB::table('sales_transactions')->where('unit_id', $unit->id)->count(),
            'pengeluaran' => DB::table('pengeluaran')->where('business_unit_id', $unit->id)->count(),
            'produk & jasa' => DB::table('products_services')->where('unit_id', $unit->id)->count(),
            // koperasi_members.unit_usaha_id memakai ON DELETE SET NULL:
            // tanpa baris ini, menghapus unit akan melepas anggotanya dari
            // unitnya tanpa satu pun peringatan.
            'anggota terdaftar' => DB::table('koperasi_members')->where('unit_usaha_id', $unit->id)->count(),
        ];

        return $this->rangkumJejak($jejak, 'Unit usaha');
    }

    private function tahanProduk(ProductService $produk): ?string
    {
        $jumlah = DB::table('sales_transaction_items')->where('product_service_id', $produk->id)->count();

        if ($jumlah > 0) {
            return "Produk ini sudah dipakai di {$jumlah} baris transaksi penjualan. Menghapusnya membuat transaksi lama tidak bisa ditelusuri.";
        }

        return null;
    }

    private function tahanPelanggan(Customer $pelanggan): ?string
    {
        $jumlah = $pelanggan->salesTransactions()->count();

        if ($jumlah > 0) {
            return "Pelanggan ini punya {$jumlah} transaksi penjualan. Menghapusnya membuat transaksi lama kehilangan pemiliknya.";
        }

        return null;
    }

    /**
     * Program dampak: seluruh anaknya memakai ON DELETE CASCADE.
     *
     * Artinya menghapus programnya akan ikut menghapus outcome, investasi,
     * dan perhitungan SROI-nya tanpa satu pun peringatan dari database.
     * Justru karena cascade-nya diam, penolakannya harus eksplisit di sini.
     */
    private function tahanProgramDampak(ImpactProgram $program): ?string
    {
        $jejak = [
            'outcome' => DB::table('impact_outcomes')->where('impact_program_id', $program->id)->count(),
            'catatan investasi' => DB::table('program_investments')->where('impact_program_id', $program->id)->count(),
            'perhitungan SROI' => DB::table('sroi_calculations')->where('impact_program_id', $program->id)->count(),
        ];

        return $this->rangkumJejak($jejak, 'Program dampak');
    }

    /**
     * Periode SHU: shu_allocations memakai ON DELETE CASCADE.
     *
     * Pembagian SHU adalah catatan uang anggota. Menghapus periodenya akan
     * menghapus seluruh alokasi di dalamnya sekaligus.
     */
    private function tahanPeriodeShu(ShuPeriod $periode): ?string
    {
        $jumlah = DB::table('shu_allocations')->where('shu_period_id', $periode->id)->count();

        if ($jumlah > 0) {
            return "Periode SHU ini punya {$jumlah} catatan pembagian ke anggota. Menghapusnya menghapus seluruh pembagian SHU periode tersebut.";
        }

        return null;
    }

    private function tahanKategori(PengeluaranCategory $kategori): ?string
    {
        $jumlah = $kategori->pengeluaran()->count();

        if ($jumlah > 0) {
            return "Kategori ini masih dipakai {$jumlah} pengeluaran. Menghapusnya membuat pengeluaran lama kehilangan kategorinya.";
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
            . '. Data itu sudah masuk ke laporan yang pernah diterbitkan, jadi tidak bisa dihapus.';
    }
}
