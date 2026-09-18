<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PROXY FINANSIAL — berapa rupiah nilai satu satuan outcome.
 *
 * Ini titik paling rapuh dalam seluruh perhitungan SROI, dan seluruh
 * rancangan kelas ini berdiri untuk satu hal: memastikan tidak ada angka di
 * sini yang tidak bisa ditanyakan asalnya.
 *
 * TIDAK ADA PROXY BAWAAN YANG DIISI SISTEM.
 *
 * CahayaOS sengaja lahir tanpa satu pun proxy terisi. Menyediakan angka
 * "contoh" akan membuatnya dipakai apa adanya — dan sebuah rasio SROI yang
 * berdiri di atas angka contoh adalah angka karangan yang terlihat resmi.
 * Selama proxy belum diisi, modul SROI menyatakan datanya belum cukup dan
 * menolak mengeluarkan rasio. Itu perilaku yang benar, bukan kekurangan.
 *
 * `sumber` dan `tahun` wajib. Proxy tanpa keduanya tidak bisa diperiksa
 * siapa pun, dan yang tidak bisa diperiksa tidak layak dipakai melapor.
 */
class FinancialProxy extends Model
{
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'nama',
        // Untuk outcome & indikator APA angka ini dimaksudkan. Begitu daftar
        // proxy berisi sepuluh baris, tanpa keterangan ini penyusun laporan
        // harus menebak mana yang cocok — dan proxy yang dipasangkan ke
        // outcome yang salah menghasilkan nilai dampak keliru tanpa satu pun
        // tanda bahaya.
        'outcome',
        'indikator',
        'satuan',
        'nilai',
        'sumber',
        'tahun',
        'alasan',
        'catatan',
        'aktif',
        'dibuat_oleh',
    ];

    protected $casts = [
        'nilai' => 'integer',
        'tahun' => 'integer',
        'aktif' => 'boolean',
    ];

    protected $attributes = [
        'aktif' => true,
    ];

    public function outcomes(): HasMany
    {
        return $this->hasMany(ImpactOutcome::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    /** "Rp 12.000.000 per UMKM per tahun (BPS, 2024)" — satu baris siap tampil. */
    public function ringkas(): string
    {
        return rupiah($this->nilai) . ' ' . $this->satuan . ' (' . $this->sumber . ', ' . $this->tahun . ')';
    }

    /** Label outcome yang dilayani proxy ini — "-" kalau belum dinyatakan. */
    public function untukOutcome(): string
    {
        return $this->outcome ?: '(belum dinyatakan)';
    }

    /**
     * Apakah proxy ini cocok dipakai untuk sebuah indikator?
     *
     * Proxy yang TIDAK menyebut indikator dianggap cocok untuk semua — itu
     * keadaan bawaan dan tidak boleh menghalangi pemakaian. Yang ditandai
     * hanyalah ketidakcocokan yang NYATA: proxy yang secara tegas menyebut
     * indikator lain.
     */
    public function cocokUntuk(?string $indikator): bool
    {
        if (! $this->indikator || ! $indikator) {
            return true;
        }

        return $this->indikator === $indikator;
    }

    /**
     * Berapa tahun umur angka ini.
     *
     * Ditampilkan di daftar, bukan dipakai menghitung apa pun. Proxy berumur
     * tujuh tahun belum tentu salah, tapi orang yang menyusun laporan berhak
     * melihat umurnya tanpa harus menghitung sendiri.
     */
    public function umurTahun(): int
    {
        return max(0, now()->year - (int) $this->tahun);
    }
}
