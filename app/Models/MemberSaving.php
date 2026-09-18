<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SIMPANAN ANGGOTA — transaksi keuangan, jadi koreksinya BERJEJAK.
 *
 * Tidak ada baris simpanan yang pernah dihapus. Yang bisa berubah hanyalah
 * apakah ia masih dihitung ke saldo:
 *
 *   Tercatat   -> sah, dihitung ke saldo. Selama BELUM dikunci, nilainya
 *                 masih boleh diperbaiki di tempat (setiap perubahan masuk
 *                 Activity Log lengkap dengan nilai sebelum -> sesudah).
 *   Dibatalkan -> TIDAK dihitung ke saldo, tapi barisnya tetap ada dan tetap
 *                 terlihat di riwayat beserta alasan pembatalannya.
 *
 * Entri yang sudah dikunci (`dikunci_at`) tidak bisa diedit lagi. Koreksinya
 * lewat pembatalan + entri PENGGANTI yang menunjuk balik ke entri asal
 * (`dikoreksi_dari_id`), sehingga rantai koreksinya bisa ditelusuri utuh.
 */
class MemberSaving extends Model
{
    use HasFactory;

    protected $fillable = [
        'koperasi_member_id',
        'jenis_simpanan',
        'tanggal',
        'nominal',
        'keterangan',
        'status',
        'dikoreksi_dari_id',
        'alasan_koreksi',
        // dibatalkan_at, dibatalkan_oleh & dikunci_at SENGAJA tidak fillable —
        // ketiganya hanya boleh diisi alur batalkan()/kunci(), bukan dari form.
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'dibatalkan_at' => 'datetime',
        'dikunci_at' => 'datetime',
    ];

    public const JENIS_OPTIONS = ['Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela'];

    public const STATUS_TERCATAT = 'Tercatat';
    public const STATUS_DIBATALKAN = 'Dibatalkan';

    public function koperasiMember(): BelongsTo
    {
        return $this->belongsTo(KoperasiMember::class);
    }

    /** Entri asal yang dikoreksi oleh entri ini (kalau ini entri pengganti). */
    public function dikoreksiDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dikoreksi_dari_id');
    }

    /** Entri pengganti yang dibuat untuk mengoreksi entri ini. */
    public function penggantiKoreksi(): HasMany
    {
        return $this->hasMany(self::class, 'dikoreksi_dari_id');
    }

    /**
     * Apakah entri ini sudah digantikan entri lain yang MASIH BERLAKU.
     *
     * Dipakai untuk mencegah penghitungan ganda saat pemulihan: entri asal
     * dan penggantinya tidak boleh sama-sama menghitung ke saldo anggota.
     * Pengganti yang ikut dibatalkan tidak menghalangi apa pun, karena ia
     * sendiri sudah berhenti dihitung.
     */
    public function punyaPenggantiBerlaku(): bool
    {
        return $this->penggantiKoreksi()->berlaku()->exists();
    }

    // ==========================================================
    // SALDO — hanya entri yang BERLAKU yang dihitung
    // ==========================================================

    /**
     * Entri yang sah dan ikut menentukan saldo.
     *
     * WAJIB dipakai di SEMUA perhitungan simpanan (saldo anggota, dashboard,
     * laba rugi, laporan simpanan). Tanpa ini, entri yang sudah dibatalkan
     * akan tetap terhitung dan pembukuannya salah.
     *
     * Ditulis sebagai "bukan Dibatalkan", bukan "sama dengan Tercatat", supaya
     * status lain yang mungkin ditambahkan kelak (mis. 'Final') tetap dihitung
     * secara bawaan — kelalaian yang aman.
     */
    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DIBATALKAN);
    }

    /**
     * Simpanan yang pemiliknya SUDAH TIDAK LAGI menjadi anggota.
     *
     * Mengarsipkan anggota tidak mengembalikan uangnya: barisnya tetap
     * berlaku, dan koperasi tetap memegang dananya. Jadi angkanya memang
     * benar ikut dilaporkan — yang keliru adalah menampilkannya tanpa
     * keterangan, seolah-olah itu simpanan anggota yang masih ada.
     *
     * Akibatnya bisa terbaca aneh di satu halaman yang sama: "Total Anggota 1"
     * berdampingan dengan simpanan yang tak satu pun miliknya.
     *
     * ShuService sudah lama memakai penyaringan ini untuk menolak membagi
     * jasa modal kepada anggota terarsip. Definisinya dipindahkan ke sini
     * supaya laporan dan SHU tidak mungkin berbeda pendapat tentang uang
     * yang sama.
     */
    public function scopeMilikAnggotaTerarsip(Builder $query): Builder
    {
        return $query->whereHas('koperasiMember', fn ($q) => $q->onlyTrashed());
    }

    public function dibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    public function terkunci(): bool
    {
        return $this->dikunci_at !== null;
    }

    /**
     * Siapa yang membatalkan entri ini. Namanya disamakan dengan relasi
     * serupa di SalesTransaction dan Pengeluaran supaya halaman Arsip bisa
     * merender ketiganya lewat satu jalur yang sama.
     */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    /**
     * Anggota pemilik entri, TERMASUK yang keanggotaannya sudah diarsipkan.
     *
     * Simpanan adalah catatan uang milik orang. Kalau keanggotaannya
     * diarsipkan, riwayat setorannya TETAP harus terbaca dan bisa dikoreksi —
     * justru pada saat keanggotaan berakhir itulah saldonya paling sering
     * diperiksa ulang.
     */
    public function koperasiMemberTermasukArsip(): BelongsTo
    {
        return $this->belongsTo(KoperasiMember::class, 'koperasi_member_id')->withTrashed();
    }

    /**
     * Masih boleh diperbaiki di tempat?
     *
     * Tidak, kalau sudah dibatalkan (tidak ada gunanya) atau sudah dikunci
     * (harus lewat pembatalan + entri pengganti supaya berjejak).
     */
    public function bolehDiubahLangsung(): bool
    {
        return ! $this->dibatalkan() && ! $this->terkunci();
    }
}
