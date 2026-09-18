<?php

namespace App\Models;

use App\Models\Concerns\CatatanPeserta;
use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingRecord extends Model
{
    /**
     * SoftDeletes: data pelatihan yang dihapus karena salah input tetap ada
     * di database, jadi rekam jejak peserta tidak berlubang.
     */
    use CatatanPeserta, HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'participant_id',
        'tanggal',
        'nama_pelatihan',
        'penyelenggara',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    /** Status pelatihan; "Selesai" dipakai menyaring di monitoring perkembangan. */
    public const STATUS_TERDAFTAR = 'Terdaftar';
    public const STATUS_MENGIKUTI = 'Mengikuti';
    public const STATUS_SELESAI = 'Selesai';
    public const STATUS_TIDAK_MENGIKUTI = 'Tidak Mengikuti';

    public const STATUS_OPTIONS = [
        self::STATUS_TERDAFTAR,
        self::STATUS_MENGIKUTI,
        self::STATUS_SELESAI,
        self::STATUS_TIDAK_MENGIKUTI,
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
