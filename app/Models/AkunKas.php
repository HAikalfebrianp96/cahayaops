<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AKUN KAS & BANK — tempat uang koperasi berada.
 *
 * Saldo SENGAJA tidak disimpan di tabel ini. Yang disimpan hanya titik
 * awalnya (saldo_awal per tanggal_saldo_awal); saldo berjalan kelak
 * dijumlahkan dari catatan uang yang menunjuk ke akun ini — pola yang sama
 * dengan Dana Sosial & Hibah, supaya tidak ada dua sumber angka untuk uang
 * yang sama.
 *
 * Akun yang tidak dipakai lagi DINONAKTIFKAN, bukan dihapus: riwayat uang
 * yang pernah lewat akun itu tetap harus bisa ditelusuri.
 */
class AkunKas extends Model
{
    protected $table = 'akun_kas';

    protected $fillable = [
        'nama',
        'jenis',
        'nomor_rekening',
        'atas_nama',
        'saldo_awal',
        'tanggal_saldo_awal',
        'keterangan',
        'status',
        // created_by SENGAJA tidak fillable — nilainya hanya dari sesi berjalan.
    ];

    protected $casts = [
        'saldo_awal' => 'decimal:2',
        'tanggal_saldo_awal' => 'date',
    ];

    public const JENIS_KAS = 'Kas';
    public const JENIS_BANK = 'Bank';
    public const JENIS_OPTIONS = [self::JENIS_KAS, self::JENIS_BANK];

    public const STATUS_AKTIF = 'Aktif';
    public const STATUS_TIDAK_AKTIF = 'Tidak Aktif';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Riwayat hitung fisik akun ini. */
    public function rekonsiliasi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RekonsiliasiKas::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    public function isAktif(): bool
    {
        return $this->status === self::STATUS_AKTIF;
    }

    /**
     * Akun bawaan menurut metode pembayaran: Transfer/QRIS ke akun Bank aktif
     * pertama, selain itu (termasuk tanpa metode) ke akun Kas aktif pertama.
     * Null bila akun jenis itu tidak ada — lebih jujur daripada memasukkan
     * transferan ke laci.
     */
    public static function bawaanUntukMetode(?string $metode): ?self
    {
        $jenis = in_array($metode, ['Transfer', 'QRIS'], true) ? self::JENIS_BANK : self::JENIS_KAS;

        return static::aktif()->where('jenis', $jenis)->orderBy('id')->first();
    }

    /**
     * Aturan validasi isian akun_kas_id. Catatan BARU hanya boleh memakai akun
     * aktif; saat MEMPERBAIKI catatan lama, akun yang sudah dinonaktifkan tetap
     * sah supaya catatan itu bisa disimpan ulang tanpa dipaksa pindah akun.
     */
    public static function aturanValidasi(bool $bolehNonaktif = false): array
    {
        $ada = \Illuminate\Validation\Rule::exists('akun_kas', 'id');

        return ['nullable', $bolehNonaktif ? $ada : $ada->where('status', self::STATUS_AKTIF)];
    }

    /** Pilihan untuk dropdown: akun aktif, ditambah akun terpilih bila sudah nonaktif. */
    public static function pilihan(?int $terpilih = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->where(fn ($q) => $q->where('status', self::STATUS_AKTIF)->when($terpilih, fn ($q) => $q->orWhere('id', $terpilih)))
            ->orderBy('jenis')
            ->orderBy('nama')
            ->get();
    }

    /**
     * Saldo awal sudah ditetapkan pengurus? Saldo awal Rp0 yang belum
     * ditetapkan dan yang memang Rp0 adalah dua hal berbeda — hanya yang
     * kedua boleh dipakai sebagai titik awal perhitungan kas.
     */
    public function saldoAwalDitetapkan(): bool
    {
        return $this->tanggal_saldo_awal !== null;
    }
}
