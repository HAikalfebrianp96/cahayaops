<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * TARGET KINERJA PENDAMPING — ditetapkan Admin, per periode.
 *
 * Model ini menyimpan TARGET saja. REALISASI-nya tidak pernah disimpan di
 * sini: angka aktual dihitung saat dibutuhkan dari pendampingan_records,
 * participants, dan omzet_reports (lihat [KpiService]). Menyimpan realisasi
 * berarti ia harus diperbarui setiap kali satu catatan lapangan berubah —
 * dan pasti akan basi begitu satu jalur lupa memperbaruinya.
 *
 * SELURUH kolom target boleh kosong. Indikator yang tidak ditargetkan TIDAK
 * ikut dihitung ke KPI, sehingga program bebas menetapkan hanya sebagian
 * indikator tanpa membuat angka KPI-nya jadi timpang.
 */
class PendampingTarget extends Model
{
    /**
     * SoftDeletes: target yang dihapus tetap tersimpan — ia adalah bagian
     * dari riwayat kebijakan program, dan menghapusnya permanen membuat KPI
     * periode lalu tidak bisa dijelaskan lagi.
     *
     * Target terhapus TIDAK ikut dihitung KPI (global scope Eloquent yang
     * menanganinya), jadi mengarsipkan target = kembali memakai target
     * default.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'pendamping_id',
        'periode',
        'tahun',
        'bulan',
        'target_jumlah_binaan',
        'target_kunjungan',
        'target_pendampingan',
        'target_follow_up',
        'target_input_omzet',
        'target_pelatihan',
        'target_legalitas',
        'target_perkembangan_peserta',
        'catatan',
        'created_by',
        // deleted_by SENGAJA tidak fillable — hanya diisi trait MencatatPenghapus.
    ];

    protected $casts = [
        'tahun' => 'integer',
        'bulan' => 'integer',
    ];

    public const PERIODE_BULANAN = 'Bulanan';
    public const PERIODE_TRIWULAN = 'Triwulan';
    public const PERIODE_TAHUNAN = 'Tahunan';

    public const PERIODE_OPTIONS = [
        self::PERIODE_BULANAN,
        self::PERIODE_TRIWULAN,
        self::PERIODE_TAHUNAN,
    ];

    /**
     * Kolom target -> label yang dibaca manusia.
     *
     * Dipakai form, tabel KPI, dan export sekaligus. Menulis daftarnya di
     * tiga tempat berarti menambah indikator baru harus diingat di tiga
     * tempat pula.
     */
    public const INDIKATOR = [
        'target_jumlah_binaan' => 'Jumlah Binaan',
        'target_kunjungan' => 'Kunjungan Usaha',
        'target_pendampingan' => 'Pendampingan',
        'target_follow_up' => 'Follow Up',
        'target_input_omzet' => 'Input Omzet',
        'target_pelatihan' => 'Pelatihan',
        'target_legalitas' => 'Legalitas',
        // Dinamai ulang "Monitoring Peserta".
        //
        // Nama lamanya, "Perkembangan Peserta", menyesatkan: indikator ini
        // TIDAK membaca modul Perkembangan (baseline/monitoring/endline)
        // sama sekali. Yang dihitungnya adalah berapa binaan berjalan yang
        // SUDAH pernah didampingi — jangkauan pendampingan, bukan hasil
        // usahanya.
        //
        // Nama kolom di database sengaja TIDAK diubah: tidak ada migration,
        // tidak ada data yang bergerak, dan seluruh target yang pernah
        // disimpan tetap terbaca.
        'target_perkembangan_peserta' => 'Monitoring Peserta',
    ];

    /**
     * PENGELOMPOKAN INDIKATOR — untuk tampilan, bukan untuk perhitungan.
     *
     * ======================================================================
     * KENAPA DIKELOMPOKKAN
     * ======================================================================
     *
     * Delapan indikator yang berjajar sama rata membuat pendamping menduga
     * kedelapannya sama pentingnya, dan Admin menduga kedelapannya wajib
     * diisi. Padahal ketiganya berbeda jenis:
     *
     *   UTAMA     — pekerjaan yang MEMANG dilakukan pendamping, dan pantas
     *               dinilai: mendampingi, mengunjungi, menindaklanjuti,
     *               serta memastikan seluruh binaannya tersentuh.
     *
     *   PENDUKUNG — aktivitas pencatatan/hasil yang nilainya bergantung
     *               pembagian tugas di lapangan. Menargetkannya tanpa
     *               kesepakatan berarti menilai orang atas pekerjaan yang
     *               bukan tanggung jawabnya.
     *
     *   BEBAN     — jumlah binaan. Ini KAPASITAS, bukan prestasi. Pendamping
     *               dengan 40 binaan tidak "lebih baik" daripada yang punya
     *               15; ia lebih berat. Menjadikannya KPI mendorong hal yang
     *               keliru.
     *
     * Perhitungannya TIDAK berubah sedikit pun: indikator tanpa target tetap
     * tidak dihitung, apa pun kelompoknya. Yang berubah hanya cara
     * menampilkan dan menjelaskannya.
     */
    public const KELOMPOK_UTAMA = 'utama';
    public const KELOMPOK_PENDUKUNG = 'pendukung';
    public const KELOMPOK_BEBAN = 'beban';

    public const KELOMPOK = [
        self::KELOMPOK_UTAMA => [
            'target_pendampingan',
            'target_kunjungan',
            'target_follow_up',
            'target_perkembangan_peserta',
        ],
        self::KELOMPOK_PENDUKUNG => [
            'target_input_omzet',
            'target_pelatihan',
            'target_legalitas',
        ],
        self::KELOMPOK_BEBAN => [
            'target_jumlah_binaan',
        ],
    ];

    public const KELOMPOK_JUDUL = [
        self::KELOMPOK_UTAMA => 'KPI Utama',
        self::KELOMPOK_PENDUKUNG => 'Indikator Pendukung',
        self::KELOMPOK_BEBAN => 'Beban Kerja',
    ];

    public const KELOMPOK_KETERANGAN = [
        self::KELOMPOK_UTAMA => 'Pekerjaan inti pendamping. Isi target di sini kalau memang menjadi tanggung jawabnya pada periode ini.',
        self::KELOMPOK_PENDUKUNG => 'Aktivitas pencatatan dan hasil. Isi HANYA kalau memang ditugaskan — kosongkan kalau bukan tanggung jawabnya.',
        self::KELOMPOK_BEBAN => 'Kapasitas, bukan prestasi. Dipakai melihat berat tidaknya beban kerja, bukan menilai kinerja.',
    ];

    /** Kelompok sebuah indikator; 'pendukung' kalau belum terdaftar. */
    public static function kelompokIndikator(string $kolom): string
    {
        foreach (self::KELOMPOK as $kelompok => $kolomnya) {
            if (in_array($kolom, $kolomnya, true)) {
                return $kelompok;
            }
        }

        return self::KELOMPOK_PENDUKUNG;
    }

    public function pendamping(): BelongsTo
    {
        return $this->belongsTo(Pendamping::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==========================================================
    // PERIODE
    // ==========================================================

    /**
     * Rentang tanggal yang dicakup target ini.
     *
     * Satu-satunya tempat aturan periode diterjemahkan jadi tanggal. Kalau
     * tersebar, Triwulan akan berarti berbeda di halaman KPI dan di export.
     *
     * @return array{0:Carbon, 1:Carbon} [awal, akhir]
     */
    public function rentang(): array
    {
        return match ($this->periode) {
            self::PERIODE_TAHUNAN => [
                Carbon::create($this->tahun, 1, 1)->startOfDay(),
                Carbon::create($this->tahun, 12, 31)->endOfDay(),
            ],
            self::PERIODE_TRIWULAN => [
                // `bulan` menyimpan bulan AWAL triwulan (1, 4, 7, 10).
                Carbon::create($this->tahun, $this->bulan ?: 1, 1)->startOfDay(),
                Carbon::create($this->tahun, $this->bulan ?: 1, 1)->addMonths(2)->endOfMonth(),
            ],
            default => [
                Carbon::create($this->tahun, $this->bulan ?: 1, 1)->startOfDay(),
                Carbon::create($this->tahun, $this->bulan ?: 1, 1)->endOfMonth(),
            ],
        };
    }

    public function periodeLabel(): string
    {
        return match ($this->periode) {
            self::PERIODE_TAHUNAN => 'Tahun ' . $this->tahun,
            self::PERIODE_TRIWULAN => 'Triwulan ' . $this->triwulanKe() . ' ' . $this->tahun,
            default => OmzetReport::namaBulan((int) $this->bulan) . ' ' . $this->tahun,
        };
    }

    public function triwulanKe(): int
    {
        return (int) ceil(($this->bulan ?: 1) / 3);
    }

    /** Target ini mencakup tanggal tertentu? */
    public function mencakup(Carbon $tanggal): bool
    {
        [$awal, $akhir] = $this->rentang();

        return $tanggal->betweenIncluded($awal, $akhir);
    }

    /**
     * Target yang berlaku untuk satu pendamping pada satu tanggal.
     *
     * Urutan pemilihan SENGAJA: Bulanan menang atas Triwulan, Triwulan menang
     * atas Tahunan. Yang lebih spesifik selalu lebih dekat dengan niat Admin
     * saat menetapkannya.
     */
    public static function berlakuUntuk(int $pendampingId, Carbon $tanggal): ?self
    {
        $kandidat = static::where('pendamping_id', $pendampingId)
            ->where('tahun', $tanggal->year)
            ->get()
            ->filter(fn (self $t) => $t->mencakup($tanggal));

        foreach ([self::PERIODE_BULANAN, self::PERIODE_TRIWULAN, self::PERIODE_TAHUNAN] as $prioritas) {
            $cocok = $kandidat->firstWhere('periode', $prioritas);

            if ($cocok) {
                return $cocok;
            }
        }

        return null;
    }

    /** Ada minimal satu indikator yang benar-benar ditargetkan? */
    public function punyaTarget(): bool
    {
        foreach (array_keys(self::INDIKATOR) as $kolom) {
            if ((int) $this->{$kolom} > 0) {
                return true;
            }
        }

        return false;
    }
}
