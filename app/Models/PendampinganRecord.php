<?php

namespace App\Models;

use App\Models\Concerns\CatatanPeserta;
use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PendampinganRecord extends Model
{
    /**
     * SoftDeletes: catatan pendampingan yang dihapus karena salah input tetap
     * tersimpan di database (hanya diberi tanda `deleted_at`), sehingga
     * jejaknya tidak lenyap dan tetap bisa diaudit.
     */
    use CatatanPeserta, HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'participant_id',
        'pendamping_id',
        'tanggal',
        'jenis_kegiatan',
        'materi',
        'catatan',
        // Siklus tindak lanjut: kendala (= permasalahan) -> tindakan ->
        // hasil -> tindak_lanjut (= rencana) + tanggal_follow_up.
        // `kendala` & `tindak_lanjut` SUDAH ADA sejak awal dan sengaja
        // dipakai ulang, bukan dibuatkan kolom kembar.
        'kendala',
        'tindakan',
        'hasil',
        'tindak_lanjut',
        'tanggal_follow_up',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_follow_up' => 'date',
    ];

    /**
     * JENIS KEGIATAN — dinamai supaya tidak bisa diam-diam menyimpang.
     *
     * =================================================================
     * KENAPA PAKAI KONSTANTA, BUKAN STRING LANGSUNG
     * =================================================================
     *
     * Lima tempat di aplikasi ini MENYARING catatan berdasarkan jenisnya:
     * KPI (kunjungan usaha), monitoring perkembangan, dua kolom di laporan
     * kinerja pendamping, dan satu kartu di dashboard.
     *
     * Selama nilainya ditulis sebagai string di masing-masing tempat,
     * mengganti satu nama di daftar ini — misalnya "Kunjungan Usaha" jadi
     * "Kunjungan" — akan membuat kelimanya mengembalikan NOL. Tidak ada
     * error, tidak ada test yang gagal; angkanya sekadar menjadi nol dan
     * tetap terlihat masuk akal di layar.
     *
     * Dengan konstanta, penyaring dan daftarnya selalu menunjuk nilai yang
     * sama persis.
     */
    public const ASESMEN = 'Asesmen';
    public const KUNJUNGAN_USAHA = 'Kunjungan Usaha';
    public const KONSULTASI = 'Konsultasi';
    public const PENDAMPINGAN = 'Pendampingan';
    public const MONITORING = 'Monitoring';
    public const EVALUASI = 'Evaluasi';
    public const LAINNYA = 'Lainnya';

    public const JENIS_KEGIATAN = [
        self::ASESMEN,
        self::KUNJUNGAN_USAHA,
        self::KONSULTASI,
        self::PENDAMPINGAN,
        self::MONITORING,
        self::EVALUASI,
        self::LAINNYA,
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Catatan yang punya rencana tindak lanjut dan tanggalnya sudah lewat
     * atau jatuh dalam beberapa hari ke depan — inilah yang muncul sebagai
     * "perlu follow up" di Dashboard Pendamping.
     */
    public function scopePerluFollowUp($query, int $dalamHari = 7)
    {
        return $query->whereNotNull('tanggal_follow_up')
            ->whereDate('tanggal_follow_up', '<=', now()->addDays($dalamHari)->toDateString());
    }

    public function followUpTerlambat(): bool
    {
        return $this->tanggal_follow_up !== null
            && $this->tanggal_follow_up->isBefore(now()->startOfDay());
    }

    public function pendamping(): BelongsTo
    {
        return $this->belongsTo(Pendamping::class);
    }

    /**
     * Judul singkat untuk Activity Log & daftar — memakai data yang sudah ada,
     * bukan kolom baru.
     */
    public function judul(): string
    {
        return $this->jenis_kegiatan . ' — ' . Str::limit((string) $this->materi, 60);
    }
}
