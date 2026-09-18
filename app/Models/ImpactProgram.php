<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SATU PROGRAM YANG DINILAI DAMPAKNYA.
 *
 * Program bukan entitas baru di dunia nyata — ia adalah CARA MEMBATASI
 * pertanyaan "dampaknya berapa": peserta yang mana, rentang waktu kapan,
 * investasi yang mana. Tanpa batas itu, setiap angka dampak akan bergeser
 * diam-diam setiap kali ada peserta baru masuk.
 *
 * Program TIDAK menyimpan daftar pesertanya sebagai tabel pivot. Pesertanya
 * DITURUNKAN dari periode dan (opsional) pendampingnya — lihat peserta().
 * Alasannya: daftar pivot harus dirawat manual dan akan segera tidak sinkron
 * dengan data peserta yang sebenarnya, sementara turunannya selalu benar.
 */
class ImpactProgram extends Model
{
    use HasFactory, MencatatPenghapus, SoftDeletes;

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_REVIEW = 'Review';
    public const STATUS_FINAL = 'Final';

    public const STATUS_OPTIONS = [
        self::STATUS_DRAFT => 'Draft — masih disusun',
        self::STATUS_REVIEW => 'Review — menunggu persetujuan',
        self::STATUS_FINAL => 'Final — sudah dilaporkan',
    ];

    protected $fillable = [
        'nama',
        'periode_mulai',
        'periode_selesai',
        'deskripsi',
        'pendamping_id',
        'status',
        'catatan',
        'dibuat_oleh',
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    public function pendamping(): BelongsTo
    {
        return $this->belongsTo(Pendamping::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function investments(): HasMany
    {
        return $this->hasMany(ProgramInvestment::class)->orderBy('tanggal');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(ImpactOutcome::class)->orderBy('id');
    }

    /** Seluruh perhitungan yang pernah dijalankan — yang terbaru di atas. */
    public function calculations(): HasMany
    {
        return $this->hasMany(SroiCalculation::class)->orderByDesc('dihitung_at');
    }

    /** Hasil perhitungan terakhir, atau null kalau belum pernah dihitung. */
    public function perhitunganTerakhir(): ?SroiCalculation
    {
        return $this->calculations()->first();
    }

    /**
     * Peserta yang tercakup program ini — DITURUNKAN, bukan didaftar.
     *
     * Aturannya: peserta yang tanggal mulai programnya jatuh pada atau
     * sebelum akhir periode. Peserta yang baru masuk setelah periode berakhir
     * jelas bukan bagian dari dampak periode itu.
     *
     * Sengaja TIDAK menyaring dengan tanggal_mulai_program >= periode_mulai:
     * peserta lama yang masih didampingi sepanjang periode ini tetap bagian
     * dari programnya. Yang menentukan ia ikut terhitung atau tidak adalah
     * ada-tidaknya baseline & endline — bukan tanggal masuknya.
     */
    public function peserta()
    {
        return Participant::query()
            ->where('tanggal_mulai_program', '<=', $this->periode_selesai)
            ->when($this->pendamping_id, fn ($q) => $q->where('pendamping_id', $this->pendamping_id));
    }

    public function totalInvestasi(): int
    {
        return (int) ($this->relationLoaded('investments')
            ? $this->investments->sum('nominal')
            : $this->investments()->sum('nominal'));
    }

    public function statusLabel(): string
    {
        return self::STATUS_OPTIONS[$this->status] ?? $this->status;
    }

    public function periodeLabel(): string
    {
        return $this->periode_mulai->format('d M Y') . ' – ' . $this->periode_selesai->format('d M Y');
    }

    /**
     * Program yang sudah Final TIDAK boleh diubah lagi.
     *
     * Angkanya sudah dipakai melapor keluar; mengubah outcome atau investasi
     * setelah itu berarti laporan yang sudah terkirim tidak lagi cocok dengan
     * sistem, dan tidak ada yang bisa menjelaskan selisihnya. Untuk revisi,
     * buat program baru — riwayat keduanya tetap utuh.
     */
    public function terkunci(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public static function statusYangSah(): array
    {
        return array_keys(self::STATUS_OPTIONS);
    }
}
