<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\CatatanPeserta;
use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalitasRecord extends Model
{
    use CatatanPeserta;
    use HasFactory;
    use MencatatPenghapus;
    use SoftDeletes;

    protected $fillable = [
        'participant_id',
        'jenis_dokumen',
        'nomor_dokumen',
        'status',
        'tanggal_terbit',
        'tanggal_berlaku',
        'file_path',
        'file_asli',
        'catatan',
    ];

    protected $casts = [
        'tanggal_terbit' => 'date',
        'tanggal_berlaku' => 'date',
    ];

    public const JENIS_DOKUMEN = [
        'NIB',
        'PIRT',
        'Sertifikat Halal',
        'NPWP',
        'Merek',
        'Sertifikat Pelatihan',
        'Lainnya',
    ];

    /**
     * STATUS DOKUMEN — dinamai, karena ADA yang menyaring dengannya.
     *
     * "Sudah Ada" bukan sekadar pilihan di form. Enam tempat menyaring
     * dengannya: KPI legalitas (yang MENILAI pendamping), tiga hitungan di
     * dashboard, daftar tugas, dan monitoring perkembangan.
     *
     * Selama nilainya ditulis sebagai string di masing-masing tempat,
     * mengganti teksnya di sini akan membuat keenamnya menghitung NOL —
     * tanpa error, tanpa halaman rusak. KPI legalitas seluruh pendamping
     * menjadi nol dan tetap terlihat masuk akal di layar.
     */
    public const STATUS_BELUM_ADA = 'Belum Ada';
    public const STATUS_PROSES = 'Proses';
    public const STATUS_SUDAH_ADA = 'Sudah Ada';

    public const STATUS_OPTIONS = [
        self::STATUS_BELUM_ADA,
        self::STATUS_PROSES,
        self::STATUS_SUDAH_ADA,
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUDAH_ADA => 'bg-green-100 text-green-700',
            self::STATUS_PROSES => 'bg-amber-100 text-amber-700',
            default => 'bg-gray-100 text-gray-500',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this->status) {
            self::STATUS_SUDAH_ADA => '✅',
            self::STATUS_PROSES => '⏳',
            default => '❌',
        };
    }
}
