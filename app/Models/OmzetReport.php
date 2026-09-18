<?php

namespace App\Models;

use App\Models\Concerns\CatatanPeserta;
use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OmzetReport extends Model
{
    /**
     * SoftDeletes. PERHATIAN: tabel ini punya unique index
     * (participant_id, periode_tahun, periode_bulan, jenis_periode,
     * periode_urut) yang TIDAK mempedulikan `deleted_at`, jadi baris terhapus
     * tetap memblokir periode yang sama. Itu disengaja — lihat helper
     * untukPeriode() di bawah, yang memulihkan baris lama alih-alih
     * menyisipkan baris baru.
     */
    use CatatanPeserta, HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'participant_id',
        'periode_bulan',
        'periode_tahun',
        'jenis_periode',
        'periode_urut',
        'omzet',
        'hpp',
        'biaya_operasional',
        // bukti_path & bukti_asli SENGAJA TIDAK fillable. Keduanya hanya
        // boleh diisi oleh alur unggah yang sudah memvalidasi tipe & ukuran
        // file (lihat MenyimpanBuktiOmzet). Membuatnya fillable berarti
        // sebuah request bisa menunjuk path apa pun di disk lewat mass
        // assignment, lalu route unduh yang ber-otorisasi akan menyajikannya.
        'catatan',
        'submitted_at',
    ];

    protected $casts = [
        'omzet' => 'integer',
        'hpp' => 'integer',
        'biaya_operasional' => 'integer',
        'periode_bulan' => 'integer',
        'periode_tahun' => 'integer',
        'periode_urut' => 'integer',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'submitted_at' => 'datetime',
    ];

    /**
     * Bawaan model disamakan dengan bawaan database.
     *
     * Tanpa ini, instance hasil create() yang tidak mengisi jenis_periode
     * bernilai NULL di PHP sampai dimuat ulang — dan seluruh pemeriksaan
     * mode akan salah membaca laporan bulanan sebagai mode tak dikenal.
     * Alasan dan polanya sama dengan $attributes di [User].
     */
    protected $attributes = [
        'jenis_periode' => self::BULANAN,
        'periode_urut' => 0,
    ];

    public const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    // ==========================================================
    // BUKTI LAPORAN
    //
    // File-nya ada di disk PRIVAT (storage/app/private) dan hanya bisa
    // dibuka lewat route ber-otorisasi — lihat OmzetMonitoringController::
    // bukti(). Tidak ada URL publik ke berkas ini, dan memang tidak boleh
    // ada: foto buku kas sebuah usaha adalah data keuangan milik peserta.
    // ==========================================================

    /** Ekstensi yang diterima — sengaja sempit: foto dan PDF saja. */
    public const BUKTI_MIME = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /** Batas ukuran dalam kilobyte. 5 MB, sama dengan lampiran legalitas. */
    public const BUKTI_MAKS_KB = 5120;

    public function punyaBukti(): bool
    {
        return filled($this->bukti_path);
    }

    /** Nama yang layak ditampilkan — nama asli kalau ada, kalau tidak nama file di disk. */
    public function namaBukti(): string
    {
        return $this->bukti_asli ?: basename((string) $this->bukti_path);
    }

    // ==========================================================
    // MODE PERIODE — Harian / Mingguan / Bulanan
    //
    // Ketiganya mencatat hal yang sama (omzet sebuah usaha pada suatu
    // rentang waktu), hanya berbeda panjang rentangnya. Yang membedakan
    // barisnya cuma dua kolom: jenis_periode dan periode_urut.
    //
    //   Bulanan   urut 0        -> "September 2026"
    //   Mingguan  urut 1..5     -> "Minggu ke-1 September 2026"  (hari 1-7)
    //   Harian    urut 1..31    -> "03 September 2026"
    //
    // periode_bulan & periode_tahun TETAP DIISI untuk ketiganya. Itulah yang
    // membuat seluruh kueri lama ("SUM omzet bulan ini", "siapa yang sudah
    // melapor bulan ini") terus benar tanpa disentuh sama sekali.
    // ==========================================================

    public const BULANAN = 'Bulanan';
    public const MINGGUAN = 'Mingguan';
    public const HARIAN = 'Harian';

    public const JENIS_OPTIONS = [
        self::BULANAN => 'Bulanan — satu laporan untuk seluruh bulan',
        self::MINGGUAN => 'Mingguan — satu laporan per minggu',
        self::HARIAN => 'Harian — satu laporan per hari',
    ];

    public static function jenisYangSah(): array
    {
        return array_keys(self::JENIS_OPTIONS);
    }

    /**
     * Berapa nilai `periode_urut` tertinggi yang MASUK AKAL untuk sebuah mode
     * pada bulan tertentu.
     *
     * Bergantung pada bulannya, dan itu bukan kerewelan: Februari 2027 tidak
     * punya tanggal 29, dan tidak punya minggu ke-5. Tanpa batas ini, laporan
     * "29 Februari" akan tersimpan dan rentang tanggalnya diam-diam meluber
     * ke Maret — omzet yang tercatat di bulan yang salah.
     */
    public static function urutMaksimal(string $jenis, int $bulan, int $tahun): int
    {
        $jumlahHari = Carbon::create($tahun, $bulan, 1)->daysInMonth;

        return match ($jenis) {
            self::HARIAN => $jumlahHari,
            self::MINGGUAN => (int) ceil($jumlahHari / 7),
            default => 0,
        };
    }

    /**
     * Rentang tanggal yang dicakup sebuah periode.
     *
     * Minggu didefinisikan sebagai blok 7 hari dari tanggal 1 — minggu ke-1
     * adalah tanggal 1-7, bukan "minggu kalender". Definisi kalender (Senin
     * sampai Minggu) akan membuat minggu pertama dan terakhir menggantung ke
     * bulan sebelah, dan omzet bulan September jadi memuat hari-hari Agustus.
     * Blok 7 hari selalu utuh di dalam satu bulan, dan itulah yang membuat
     * penjumlahan per bulan tetap benar.
     *
     * Minggu terakhir DIPOTONG di akhir bulan (mis. minggu ke-5 September =
     * tanggal 29-30), jadi gabungan seluruh minggu membagi habis bulan itu.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rentang(string $jenis, int $urut, int $bulan, int $tahun): array
    {
        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        if ($jenis === self::HARIAN) {
            // Tanggal di luar jangkauan bulan dijepit ke akhir bulan daripada
            // meluber ke bulan berikutnya. Validasi seharusnya sudah
            // menolaknya lebih dulu; ini jaring terakhir supaya rentang yang
            // tersimpan TIDAK PERNAH keluar dari bulannya.
            $hari = min(max($urut, 1), $akhirBulan->day);
            $tanggal = $awalBulan->copy()->day($hari);

            return [$tanggal, $tanggal->copy()];
        }

        if ($jenis === self::MINGGUAN) {
            $minggu = max($urut, 1);
            $mulai = $awalBulan->copy()->addDays(($minggu - 1) * 7);

            if ($mulai->greaterThan($akhirBulan)) {
                $mulai = $akhirBulan->copy();
            }

            $selesai = $mulai->copy()->addDays(6);

            return [$mulai, $selesai->greaterThan($akhirBulan) ? $akhirBulan->copy() : $selesai];
        }

        return [$awalBulan, $akhirBulan];
    }

    /**
     * MODE YANG SUDAH DIPAKAI peserta untuk sebuah bulan — null kalau bulan
     * itu masih kosong.
     *
     * INI PENJAGA UTAMA ANTI DOUBLE COUNTING.
     *
     * Kalau seorang peserta boleh mencatat harian SEKALIGUS bulanan untuk
     * September, maka "SUM omzet September" menjumlahkan bulan itu dua kali —
     * dan hasilnya tetap berupa angka yang masuk akal, jadi tidak akan ada
     * yang curiga. Aturannya karena itu tegas:
     *
     *     SATU PESERTA + SATU BULAN = SATU MODE PENCATATAN.
     *
     * Dengan aturan itu baris-baris dalam satu bulan membagi habis bulan
     * tersebut tanpa tumpang tindih, sehingga SUM biasa di dashboard,
     * monitoring, laporan, Impact, dan SROI tetap benar tanpa satu pun
     * perlu diubah.
     *
     * Ikut melihat baris TER-SOFT-DELETE, dengan alasan yang sama seperti
     * untukPeriode(): baris terhapus masih memblokir unique index, jadi
     * mengabaikannya di sini hanya memindahkan kegagalan ke level database.
     */
    public static function modeBulan(int $participantId, int $bulan, int $tahun): ?string
    {
        return static::withTrashed()
            ->where('participant_id', $participantId)
            ->where('periode_bulan', $bulan)
            ->where('periode_tahun', $tahun)
            ->value('jenis_periode');
    }

    /**
     * Apakah mode ini boleh dipakai peserta untuk bulan tersebut?
     *
     * Bulan yang masih kosong menerima mode apa pun. Bulan yang sudah terisi
     * hanya menerima mode yang sama.
     */
    public static function modeBolehDipakai(int $participantId, string $jenis, int $bulan, int $tahun): bool
    {
        $dipakai = static::modeBulan($participantId, $bulan, $tahun);

        return $dipakai === null || $dipakai === $jenis;
    }

    /** Kalimat penolakan yang menyebutkan jalan keluarnya, bukan sekadar "ditolak". */
    public static function pesanModeBentrok(string $modeTerpakai, int $bulan): string
    {
        return 'Omzet ' . self::namaBulan($bulan) . ' sudah dicatat secara '
            . strtolower($modeTerpakai) . '. Satu bulan hanya boleh memakai satu cara pencatatan '
            . 'supaya omzetnya tidak terhitung dua kali. Lanjutkan dengan mode '
            . strtolower($modeTerpakai) . ', atau hapus dulu laporan ' . self::namaBulan($bulan)
            . ' yang sudah ada.';
    }

    /**
     * Rentang tanggal diisi OTOMATIS dari empat kolom periode.
     *
     * Ditaruh di event model, bukan di controller, supaya baris yang lahir
     * dari jalur mana pun — form internal, form publik bertoken, seeder,
     * tinker, atau kode yang belum ada hari ini — selalu punya rentang yang
     * cocok dengan periodenya. Rentang yang diisi manual di controller cepat
     * atau lambat akan berbeda dari periodenya di salah satu jalur.
     */
    protected static function booted(): void
    {
        static::saving(function (self $laporan) {
            if ($laporan->periode_bulan === null || $laporan->periode_tahun === null) {
                return;
            }

            [$mulai, $selesai] = self::rentang(
                $laporan->jenis_periode ?: self::BULANAN,
                (int) $laporan->periode_urut,
                (int) $laporan->periode_bulan,
                (int) $laporan->periode_tahun,
            );

            $laporan->tanggal_mulai = $mulai;
            $laporan->tanggal_selesai = $selesai;
        });
    }

    /** Laporan dengan mode tertentu — dipakai filter & pemeriksaan konsistensi. */
    public function scopeMode($query, string $jenis)
    {
        return $query->where('jenis_periode', $jenis);
    }

    public function bulanan(): bool
    {
        return $this->jenis_periode === self::BULANAN;
    }

    // ==========================================================
    // LABA — DIHITUNG, TIDAK DISIMPAN.
    //
    // Laba kotor dan profit bersih sepenuhnya ditentukan oleh tiga kolom di
    // atas. Menyimpannya sebagai kolom keempat & kelima berarti ada dua
    // sumber kebenaran yang harus disamakan setiap kali salah satu angkanya
    // diperbaiki — dan cepat atau lambat akan berbeda. Pola yang sama sudah
    // dipakai PerkembanganService: yang bisa dihitung, dihitung.
    //
    // Semua mengembalikan NULL kalau komponennya belum dilaporkan. Itu
    // disengaja: "belum tahu" dan "nol" adalah dua hal berbeda, dan hanya
    // NULL yang jujur untuk laporan lama yang tidak pernah menanyakan HPP.
    // ==========================================================

    /** Omzet - HPP. NULL kalau HPP belum dilaporkan. */
    public function labaKotor(): ?int
    {
        if ($this->hpp === null) {
            return null;
        }

        return (int) $this->omzet - (int) $this->hpp;
    }

    /**
     * Laba kotor - biaya operasional.
     *
     * Biaya operasional yang kosong diperlakukan sebagai 0 — BUKAN sebagai
     * "tidak tahu". Berbeda dengan HPP, banyak usaha mikro memang tidak
     * punya biaya operasional terpisah (jualan dari rumah, tanpa karyawan,
     * tanpa sewa), jadi kosong di sana wajar berarti nihil. HPP yang kosong
     * tetap membuat seluruh perhitungan NULL, karena usaha yang menjual
     * barang selalu punya harga pokok.
     */
    public function profitBersih(): ?int
    {
        $kotor = $this->labaKotor();

        if ($kotor === null) {
            return null;
        }

        return $kotor - (int) ($this->biaya_operasional ?? 0);
    }

    /** Profit bersih sebagai persen dari omzet. NULL kalau omzet 0 / data kurang. */
    public function marginPersen(): ?float
    {
        $profit = $this->profitBersih();

        if ($profit === null || (int) $this->omzet === 0) {
            return null;
        }

        return round($profit / (int) $this->omzet * 100, 2);
    }

    /** Apakah laporan ini sudah memuat komponen biaya, bukan omzet saja? */
    public function punyaDataLaba(): bool
    {
        return $this->hpp !== null;
    }

    /** Laporan yang komponen biayanya lengkap — dipakai laporan & Impact. */
    public function scopeDenganLaba($query)
    {
        return $query->whereNotNull('hpp');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Laporan untuk satu peserta + periode, TERMASUK yang sudah dihapus.
     *
     * Dipakai seluruh alur penyimpanan (internal & form publik) sebelum
     * membuat baris baru. Tanpa withTrashed(), periode yang laporannya pernah
     * dihapus tidak akan pernah bisa diisi lagi: unique index menolaknya,
     * sementara query biasa tidak bisa melihat baris yang menghalangi.
     */
    public static function untukPeriode(
        int $participantId,
        int $bulan,
        int $tahun,
        string $jenis = self::BULANAN,
        int $urut = 0,
    ): ?self {
        return static::withTrashed()
            ->where('participant_id', $participantId)
            ->where('periode_bulan', $bulan)
            ->where('periode_tahun', $tahun)
            ->where('jenis_periode', $jenis)
            ->where('periode_urut', $urut)
            ->first();
    }

    /**
     * Label periode sesuai modenya — "September 2026", "Minggu ke-2
     * September 2026", atau "03 September 2026".
     *
     * Dipakai di seluruh tampilan dan export. Bentuknya SENGAJA berbeda per
     * mode: pembaca laporan harus bisa melihat sekali pandang bahwa sebuah
     * baris mewakili satu hari, bukan satu bulan. Label yang seragam akan
     * membuat 30 baris harian terlihat seperti 30 bulan.
     */
    public function periodeLabel(): string
    {
        $namaBulan = self::BULAN[$this->periode_bulan] ?? '-';

        return match ($this->jenis_periode) {
            self::HARIAN => str_pad((string) $this->periode_urut, 2, '0', STR_PAD_LEFT)
                . ' ' . $namaBulan . ' ' . $this->periode_tahun,
            self::MINGGUAN => 'Minggu ke-' . $this->periode_urut . ' ' . $namaBulan . ' ' . $this->periode_tahun,
            default => $namaBulan . ' ' . $this->periode_tahun,
        };
    }

    /** Rentang tanggal yang tercakup, mis. "01 - 07 Sep 2026". Satu hari ditulis tunggal. */
    public function rentangLabel(): string
    {
        if (! $this->tanggal_mulai || ! $this->tanggal_selesai) {
            return '-';
        }

        if ($this->tanggal_mulai->isSameDay($this->tanggal_selesai)) {
            return $this->tanggal_mulai->format('d M Y');
        }

        return $this->tanggal_mulai->format('d M') . ' - ' . $this->tanggal_selesai->format('d M Y');
    }

    public static function namaBulan(int $bulan): string
    {
        return self::BULAN[$bulan] ?? '-';
    }
}
