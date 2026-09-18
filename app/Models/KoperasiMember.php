<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

/**
 * ANGGOTA KOPERASI CAHAYA — data KHUSUS keanggotaan, bukan identitas kedua.
 *
 * Ada dua asal-usul anggota, dan keduanya sah:
 *
 *   1. Berasal dari peserta Cahaya Preneur -> `participant_id` TERISI.
 *      Identitasnya (nama, HP, alamat, email, usaha) adalah milik
 *      [Participant]; baris ini hanya menyimpan nomor anggota, tanggal
 *      bergabung, status, unit usaha, dan keterangan.
 *   2. Anggota baru yang BUKAN peserta -> `participant_id` NULL, identitasnya
 *      diketik di baris ini.
 *
 * Kolom `nama`/`nomor_hp`/`alamat` TETAP ADA dan TIDAK dihapus (dipakai
 * kasus ke-2, dan dipakai laporan keanggotaan yang sudah berjalan). Untuk
 * anggota kasus ke-1, gunakan accessor nama()/nomorHp()/alamat() di bawah
 * supaya yang tampil selalu data terbaru dari peserta — satu sumber
 * kebenaran, tanpa perlu menyalin ulang saat data peserta berubah.
 */
class KoperasiMember extends Model
{
    /**
     * SoftDeletes: anggota yang dihapus keliru bisa dipulihkan lengkap dengan
     * riwayat simpanannya (member_savings tidak ikut terhapus).
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'nama',
        'nomor_hp',
        'alamat',
        'tanggal_bergabung',
        'status',
        'keterangan',
        'participant_id',
        'sumber_anggota',
        'unit_usaha_id',
        // nomor_anggota SENGAJA tidak fillable — selalu digenerate otomatis (lihat booted()).
        // pin_hash JUGA sengaja tidak fillable — cuma diisi lewat setPin(), tidak boleh mass-assignment.
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected $casts = [
        'tanggal_bergabung' => 'date',
    ];

    /**
     * STATUS KEANGGOTAAN.
     *
     * 'Aktif' & 'Tidak Aktif' DIPERTAHANKAN persis seperti sebelumnya —
     * keduanya dipakai baris existing, laporan keanggotaan, dan dashboard.
     * Dua nilai baru hanya menambah ketelitian: berhenti atas kemauan
     * sendiri berbeda dari diberhentikan koperasi, dan bedanya penting saat
     * anggota itu ingin mendaftar lagi.
     *
     * Berapa pun statusnya, riwayat simpanannya TIDAK pernah ikut dihapus.
     */
    public const STATUS_OPTIONS = [
        'Aktif',
        'Tidak Aktif',
        'Mengundurkan Diri',
        'Diberhentikan',
    ];

    /**
     * ASAL-USUL anggota — dipakai program untuk mengukur berapa anggota
     * koperasi yang lahir dari pembinaan Cahaya Preneur.
     */
    public const SUMBER_OPTIONS = [
        'Peserta Cahaya Preneur',
        'Pendaftaran Mandiri',
        'Rekomendasi',
        'Lainnya',
    ];

    public const SUMBER_PESERTA = 'Peserta Cahaya Preneur';

    protected static function booted(): void
    {
        static::creating(function (KoperasiMember $member) {
            if (empty($member->nomor_anggota)) {
                $member->nomor_anggota = static::nomorAnggotaBerikutnya();
            }
        });
    }

    /**
     * Nomor anggota berikutnya untuk tahun berjalan.
     *
     * withTrashed() WAJIB: sejak keanggotaan memakai Soft Delete, menghitung
     * tanpa itu akan MENGULANG nomor milik anggota yang sudah dihapus dan
     * melanggar unique index `nomor_anggota`. Perulangan while menutup sisa
     * celahnya (mis. nomor lama yang pernah diisi manual).
     */
    public static function nomorAnggotaBerikutnya(): string
    {
        $tahun = now()->format('Y');
        $urutan = static::withTrashed()->whereYear('created_at', now()->year)->count() + 1;

        do {
            $kandidat = 'KOP-' . $tahun . '-' . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (static::withTrashed()->where('nomor_anggota', $kandidat)->exists());

        return $kandidat;
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** Unit usaha koperasi tempat anggota ini terdaftar — boleh kosong. */
    public function unitUsaha(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'unit_usaha_id');
    }

    // ==========================================================
    // IDENTITAS — SATU SUMBER KEBENARAN
    //
    // Kalau anggota berasal dari peserta Cahaya Preneur, identitas yang
    // ditampilkan diambil dari [Participant]. Jadi memperbaiki nomor HP di
    // Data Peserta otomatis benar juga di modul Koperasi — tanpa input ulang
    // dan tanpa dua angka yang bisa berbeda.
    // ==========================================================

    public function namaTampil(): string
    {
        return $this->participant?->nama_peserta ?: (string) $this->nama;
    }

    public function nomorHpTampil(): ?string
    {
        return $this->participant?->nomor_hp ?: $this->nomor_hp;
    }

    public function alamatTampil(): ?string
    {
        return $this->participant?->alamat ?: $this->alamat;
    }

    public function emailTampil(): ?string
    {
        return $this->participant?->email;
    }

    public function namaUsahaTampil(): ?string
    {
        return $this->participant?->nama_usaha;
    }

    public function jenisUsahaTampil(): ?string
    {
        return $this->participant?->kategori_usaha;
    }

    /** Anggota ini berasal dari database peserta Cahaya Preneur? */
    public function dariPeserta(): bool
    {
        return $this->participant_id !== null;
    }

    /** Keanggotaannya masih berjalan? */
    public function aktif(): bool
    {
        return $this->status === 'Aktif';
    }

    public function sumberTampil(): string
    {
        return $this->sumber_anggota ?: ($this->dariPeserta() ? self::SUMBER_PESERTA : 'Tidak tercatat');
    }

    public function savings(): HasMany
    {
        return $this->hasMany(MemberSaving::class)->orderByDesc('tanggal');
    }

    /**
     * Saldo simpanan anggota.
     *
     * berlaku() WAJIB: entri yang sudah DIBATALKAN tetap tersimpan sebagai
     * jejak, tapi tidak boleh ikut dihitung — kalau ikut, saldonya salah dan
     * koreksi justru menggandakan angka.
     */
    public function totalSimpanan(): float
    {
        return (float) $this->savings()->berlaku()->sum('nominal');
    }

    public function totalSimpananPerJenis(string $jenis): float
    {
        return (float) $this->savings()->berlaku()->where('jenis_simpanan', $jenis)->sum('nominal');
    }

    /**
     * Ringkasan simpanan satu anggota — SATU sumber kebenaran untuk seluruh
     * layar yang menampilkannya.
     *
     * ==========================================================
     * KENAPA INI HARUS ADA DI SATU TEMPAT
     * ==========================================================
     *
     * Sebelumnya rumusnya ditulis dua kali: di halaman Admin (Detail Anggota)
     * dan di halaman publik (Cek Simpanan). Keduanya lalu berbeda — yang
     * Admin menjumlahkan Pokok + Wajib + Sukarela, yang publik hanya
     * Pokok + Wajib. Akibatnya satu anggota bisa melihat total yang LEBIH
     * KECIL daripada yang dilihat petugas untuk orang yang sama, tanpa ada
     * yang salah ketik di mana pun.
     *
     * Itu jenis perbedaan yang paling sulit ditemukan: kedua angkanya masuk
     * akal, keduanya konsisten dengan dirinya sendiri, dan tidak ada error
     * yang muncul. Yang menyelesaikannya bukan memperbaiki salah satu,
     * melainkan memastikan hanya ada satu rumus untuk diperbaiki.
     *
     * TOTAL = POKOK + WAJIB + SUKARELA, sesuai ketetapan koperasi.
     *
     * @return array{pokok: float, wajib: float, sukarela: float, total: float}
     */
    public function ringkasanSimpanan(): array
    {
        $ringkasan = [
            'pokok' => $this->totalSimpananPerJenis('Simpanan Pokok'),
            'wajib' => $this->totalSimpananPerJenis('Simpanan Wajib'),
            'sukarela' => $this->totalSimpananPerJenis('Simpanan Sukarela'),
        ];

        $ringkasan['total'] = $ringkasan['pokok'] + $ringkasan['wajib'] + $ringkasan['sukarela'];

        return $ringkasan;
    }

    // ==========================================================
    // AKSES "CEK SIMPANAN SAYA" — Nomor Anggota + PIN
    // ==========================================================

    public function hasPin(): bool
    {
        return ! empty($this->pin_hash);
    }

    /**
     * Set PIN baru (HASHED). Dipanggil dari KoperasiMemberController::generatePin().
     * PIN plaintext TIDAK PERNAH disimpan — cuma dikembalikan sekali ke Admin.
     */
    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
        $this->save();
    }

    public function verifyPin(string $pin): bool
    {
        return $this->hasPin() && Hash::check($pin, $this->pin_hash);
    }
}
