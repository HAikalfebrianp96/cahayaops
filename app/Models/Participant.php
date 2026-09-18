<?php

namespace App\Models;

use App\Models\LegalitasRecord;
use App\Models\Concerns\MencatatPenghapus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * MASTER PESERTA / ORANG / UMKM — Single Source of Truth CahayaOS.
 *
 * Satu pelaku UMKM = SATU baris di sini. Modul manapun yang membutuhkan
 * identitas orang tersebut menunjuk ke baris ini, tidak menyalinnya:
 *
 *   participants
 *      |-- pendampingan_records   (Cahaya Preneur — riwayat pendampingan)
 *      |-- omzet_reports          (Cahaya Preneur — laporan omzet)
 *      |-- training_records       (Cahaya Preneur — pelatihan)
 *      |-- legalitas_records      (Cahaya Preneur — legalitas)
 *      |-- hpp_calculations       (Cahaya Preneur — alat HPP)
 *      \-- koperasi_members        (Koperasi Cahaya — OPSIONAL)
 *
 * Menjadi peserta Cahaya Preneur TIDAK otomatis menjadikan seseorang anggota
 * Koperasi Cahaya, dan sebaliknya anggota koperasi TIDAK harus peserta
 * Cahaya Preneur (koperasi_members.participant_id boleh NULL).
 */
class Participant extends Model
{
    /**
     * SoftDeletes: peserta yang dihapus TIDAK hilang dari database, hanya
     * diberi tanda `deleted_at` sehingga bisa dipulihkan Admin.
     *
     * Ini penting justru karena peserta adalah sumber identitas utama —
     * menghapusnya permanen akan memutus omzet, pendampingan, pelatihan,
     * legalitas, HPP, dan keanggotaan koperasi sekaligus. Dengan soft delete,
     * seluruh relasi itu tetap utuh dan kembali lengkap begitu dipulihkan.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'nama_peserta',
        'nomor_identitas',
        'nomor_hp',
        'email',
        'alamat',
        'kecamatan',
        'kelurahan',
        'nama_usaha',
        'alamat_usaha',
        'kategori_usaha',
        'produk',
        'tahun_berdiri',
        'status_usaha',
        'tanggal_mulai_program',
        'status',
        'catatan_internal',
        'pendamping_id',
        // report_token SENGAJA tidak fillable — selalu digenerate otomatis (lihat booted()).
    ];

    protected $casts = [
        'tanggal_mulai_program' => 'date',
        'tahun_berdiri' => 'integer',
    ];

    public const KATEGORI_OPTIONS = [
        'Makanan & Minuman',
        'Kerajinan',
        'Jasa',
        'Pertanian',
        'Peternakan',
        'Perdagangan',
        'Lainnya',
    ];

    /**
     * STATUS PERJALANAN PESERTA — dari mendaftar sampai mandiri.
     *
     * Menggantikan tiga nilai lama sebagai PILIHAN di form, TAPI nilai
     * lamanya TIDAK dihapus dan TIDAK dimigrasi paksa: lihat STATUS_LEGACY
     * di bawah. Mengubah status 20 peserta secara diam-diam lewat migration
     * berarti menulis ulang fakta program yang sudah tercatat.
     */
    public const STATUS_OPTIONS = [
        'Terdaftar',
        'Aktif',
        'Berkembang',
        'Mandiri',
        'Alumni',
        'Nonaktif',
    ];

    /**
     * Nilai status LAMA yang masih dipakai baris existing.
     *
     * Tetap SAH untuk validasi dan tetap tampil apa adanya, sehingga:
     *   - peserta ber-status lama tidak mendadak tertolak saat form disimpan;
     *   - membuka form Edit peserta ber-status lama TIDAK diam-diam
     *     mengubah statusnya hanya karena nilainya tidak ada di dropdown.
     *
     * Padanannya dicantumkan sebagai keterangan, supaya Admin tahu harus
     * memilih apa kalau ingin memindahkannya ke status baru.
     */
    public const STATUS_LEGACY = [
        'Graduasi' => 'Alumni',
        'Tidak Aktif' => 'Nonaktif',
    ];

    /** Seluruh nilai status yang diterima — baru maupun lama. */
    public static function statusYangSah(): array
    {
        return array_merge(self::STATUS_OPTIONS, array_keys(self::STATUS_LEGACY));
    }

    /**
     * Pilihan yang dirender di form untuk SATU peserta.
     *
     * Kalau status peserta ini masih memakai nilai lama, nilai itu ikut
     * disertakan (diberi keterangan) supaya tidak hilang saat disimpan.
     */
    public function statusPilihan(): array
    {
        $pilihan = self::STATUS_OPTIONS;

        if ($this->status && ! in_array($this->status, $pilihan, true)) {
            $pilihan[] = $this->status;
        }

        return $pilihan;
    }

    /** Status yang dianggap masih berjalan — dipakai KPI dashboard. */
    public const STATUS_BERJALAN = ['Terdaftar', 'Aktif', 'Berkembang', 'Mandiri'];

    /**
     * Status USAHA — berbeda dari STATUS_OPTIONS di atas yang menyatakan
     * status keikutsertaan peserta di program. Boleh dikosongkan.
     */
    public const STATUS_USAHA_OPTIONS = ['Baru Merintis', 'Berkembang', 'Mapan', 'Vakum'];

    protected static function booted(): void
    {
        static::creating(function (Participant $participant) {
            if (empty($participant->report_token)) {
                $participant->report_token = Str::random(32);
            }
        });
    }

    public function pendamping(): BelongsTo
    {
        return $this->belongsTo(Pendamping::class);
    }

    /**
     * Keanggotaan Koperasi Cahaya milik peserta ini — OPSIONAL.
     *
     * NULL berarti peserta ini belum/tidak menjadi anggota koperasi, dan itu
     * kondisi yang sah. Relasi ini juga yang dipakai untuk MENCEGAH duplikasi:
     * satu peserta hanya boleh punya satu keanggotaan aktif.
     */
    public function koperasiMember(): HasOne
    {
        return $this->hasOne(KoperasiMember::class);
    }

    /** Apakah peserta ini sekaligus anggota Koperasi Cahaya? */
    public function isAnggotaKoperasi(): bool
    {
        return $this->koperasiMember()->exists();
    }

    /** Peserta masih berjalan di program (bukan Alumni/Nonaktif)? */
    public function masihBerjalan(): bool
    {
        return in_array($this->status, self::STATUS_BERJALAN, true);
    }

    public function omzetReports(): HasMany
    {
        return $this->hasMany(OmzetReport::class)->orderByDesc('periode_tahun')->orderByDesc('periode_bulan');
    }

    public function pendampinganRecords(): HasMany
    {
        return $this->hasMany(PendampinganRecord::class)->orderByDesc('tanggal');
    }

    public function trainingRecords(): HasMany
    {
        return $this->hasMany(TrainingRecord::class)->orderByDesc('tanggal');
    }

    public function legalitasRecords(): HasMany
    {
        return $this->hasMany(LegalitasRecord::class);
    }

    /**
     * Pengukuran perkembangan usaha — baseline, monitoring, endline.
     *
     * Diurutkan menaik berdasarkan tanggal, berbeda dari relasi catatan lain
     * di kelas ini yang menaruh yang terbaru di atas. Alasannya: yang dibaca
     * dari relasi ini SELALU sebuah perjalanan (dari mana ke mana), bukan
     * "apa yang terakhir terjadi", dan urutan maju itulah bentuk aslinya.
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(ParticipantMeasurement::class)->orderBy('tanggal');
    }

    /**
     * Histori seluruh perhitungan HPP (Alat Pendampingan UMKM) — banyak
     * produk, banyak histori per produk, TIDAK PERNAH ditimpa/dihapus.
     */
    public function hppCalculations(): HasMany
    {
        return $this->hasMany(HppCalculation::class)->orderByDesc('created_at');
    }

    /**
     * Nomor HP dalam format internasional tanpa tanda — 081234 -> 6281234.
     *
     * Dipakai tautan WhatsApp. Mengembalikan NULL kalau nomornya tidak masuk
     * akal sebagai nomor telepon (kurang dari 8 digit setelah dibersihkan),
     * supaya tombolnya tidak muncul dan mengarahkan orang ke percakapan
     * kosong dengan nomor yang tidak ada.
     */
    public function nomorHpInternasional(): ?string
    {
        $digit = preg_replace('/\D/', '', (string) $this->nomor_hp);

        if ($digit === '' || strlen($digit) < 8) {
            return null;
        }

        // 08xx -> 628xx. Nomor yang sudah diawali 62 dibiarkan apa adanya,
        // dan nomor tanpa awalan apa pun (8xx) diberi kode negara.
        if (str_starts_with($digit, '0')) {
            return '62' . substr($digit, 1);
        }

        return str_starts_with($digit, '62') ? $digit : '62' . $digit;
    }

    /**
     * Tautan WhatsApp untuk MENGHUBUNGI peserta, tanpa pesan bawaan.
     *
     * Dipakai tombol "Hubungi" di halaman Tugas Saya. Memakai pemeriksa
     * nomor yang SAMA dengan tautan laporan omzet, jadi nomor yang tidak
     * sah menghasilkan null di kedua tempat — bukan tautan rusak yang baru
     * ketahuan setelah pendamping menekannya di depan peserta.
     */
    public function tautanWhatsapp(): ?string
    {
        $nomor = $this->nomorHpInternasional();

        return $nomor === null ? null : 'https://wa.me/' . $nomor;
    }

    /**
     * Tautan WhatsApp berisi link laporan omzet yang sudah terisi pesannya.
     *
     * DEEP-LINK, bukan integrasi API. Tidak ada pesan yang benar-benar
     * dikirim sistem: tautan ini membuka WhatsApp milik petugas dengan pesan
     * yang sudah tertulis, dan petugaslah yang menekan kirim. Itu disengaja —
     * mengaku "mengirim WhatsApp" sementara yang terjadi hanya membuka
     * aplikasi akan membuat orang mengira pesannya sudah sampai.
     */
    public function tautanWhatsappLaporanOmzet(): ?string
    {
        $nomor = $this->nomorHpInternasional();

        if ($nomor === null || $this->report_token === null) {
            return null;
        }

        $pesan = 'Assalamualaikum ' . $this->nama_peserta . ', '
            . 'mohon bantuannya mengisi laporan omzet usaha ' . $this->nama_usaha . ' bulan ini '
            . 'melalui link berikut: ' . route('omzet.public.show', $this->report_token)
            . ' — terima kasih.';

        return 'https://wa.me/' . $nomor . '?text=' . rawurlencode($pesan);
    }

    /**
     * Laporan omzet untuk bulan berjalan (dipakai dashboard/monitoring), null kalau belum lapor.
     */
    public function omzetBulanIni(): ?OmzetReport
    {
        return $this->omzetReports()
            ->where('periode_bulan', now()->month)
            ->where('periode_tahun', now()->year)
            ->first();
    }

    /**
     * Timeline perkembangan peserta — gabungan sederhana dari data yang
     * SUDAH ADA (bukan engine timeline baru), diurutkan berdasarkan tanggal.
     * Pastikan relasi omzetReports/pendampinganRecords/trainingRecords/
     * legalitasRecords sudah di-load sebelum memanggil ini.
     */
    public function timeline(): array
    {
        $events = collect();

        if ($this->tanggal_mulai_program) {
            $events->push([
                'tanggal' => $this->tanggal_mulai_program,
                'judul' => 'Masuk Program Cahaya Preneur',
            ]);
        }

        foreach ($this->omzetReports as $report) {
            $events->push([
                'tanggal' => Carbon::createFromDate($report->periode_tahun, $report->periode_bulan, 1),
                'judul' => 'Laporan omzet ' . $report->periodeLabel() . ': Rp' . number_format($report->omzet, 0, ',', '.'),
            ]);
        }

        foreach ($this->pendampinganRecords as $record) {
            $events->push([
                'tanggal' => $record->tanggal,
                'judul' => $record->jenis_kegiatan . ' — ' . $record->materi,
            ]);
        }

        foreach ($this->trainingRecords as $training) {
            $events->push([
                'tanggal' => $training->tanggal,
                'judul' => 'Pelatihan: ' . $training->nama_pelatihan . ' (' . $training->status . ')',
            ]);
        }

        foreach ($this->legalitasRecords as $legalitas) {
            if ($legalitas->status === LegalitasRecord::STATUS_SUDAH_ADA && $legalitas->tanggal_terbit) {
                $events->push([
                    'tanggal' => $legalitas->tanggal_terbit,
                    'judul' => $legalitas->jenis_dokumen . ' terbit',
                ]);
            }
        }

        return $events->sortBy('tanggal')->values()->all();
    }
}
