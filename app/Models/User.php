<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /**
     * SoftDeletes: akun yang dihapus TIDAK hilang dari database, hanya diberi
     * tanda `deleted_at` sehingga bisa dipulihkan.
     *
     * Konsekuensinya berlaku otomatis di seluruh aplikasi karena global scope
     * Eloquent, dan semuanya memang yang diinginkan:
     *   - Auth::attempt() tidak menemukan akun terhapus  -> tidak bisa login
     *   - daftar & hitungan di Manajemen User mengecualikannya
     *   - jumlahAdminAktif() tidak lagi menghitungnya sebagai Admin aktif
     *   - route model binding mengembalikan 404 untuk akun terhapus
     *
     * Nonaktifkan (is_active) TETAP tindakan yang berbeda dan tetap jadi
     * tindakan baku: akses dicabut tapi akunnya masih terdaftar penuh.
     */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Nilai role yang SAH — sesuai audit Tahap 1, tersimpan sebagai string
     * biasa di kolom `role` (bukan enum database). Sebelumnya ketiga nilai ini
     * hanya ditulis sebagai literal di helper isAdmin()/isPimpinan()/
     * isPendamping(); dikumpulkan di sini supaya form & validasi punya satu
     * sumber kebenaran dan tidak ada nilai role karangan yang bisa masuk.
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PIMPINAN = 'pimpinan';
    public const ROLE_PENDAMPING = 'pendamping';
    public const ROLE_TIM_KOPERASI = 'tim_koperasi';

    public const ROLE_OPTIONS = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_PIMPINAN => 'Pimpinan',
        self::ROLE_PENDAMPING => 'Pendamping',
        self::ROLE_TIM_KOPERASI => 'Tim Koperasi',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'pendamping_id',
        // Status akun — dikelola Admin lewat modul Manajemen User.
        // CATATAN: last_login_at & last_login_ip SENGAJA tidak fillable,
        // keduanya hanya boleh diisi oleh alur login, bukan dari form.
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Nilai bawaan untuk instance baru.
     *
     * Kolom `is_active` punya default true di database, TAPI default itu baru
     * berlaku saat baris ditulis — instance PHP hasil User::create() tanpa
     * mengisi is_active tetap bernilai null sampai dimuat ulang dari DB.
     * Akibatnya isActive() mengembalikan false untuk akun yang sebenarnya
     * aktif, dan middleware EnsureUserIsActive akan salah memutus sesinya.
     *
     * Deklarasi ini menyamakan default model dengan default database, jadi
     * keduanya tidak lagi bisa berbeda.
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function pendamping(): BelongsTo
    {
        return $this->belongsTo(Pendamping::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPendamping(): bool
    {
        return $this->role === 'pendamping';
    }

    public function isPimpinan(): bool
    {
        return $this->role === 'pimpinan';
    }

    /**
     * Tim Koperasi - pengguna internal yang menangani operasional Koperasi
     * Cahaya. BUKAN Admin, dan tidak pernah menjadi Admin.
     */
    public function isTimKoperasi(): bool
    {
        return $this->role === self::ROLE_TIM_KOPERASI;
    }

    /**
     * Boleh mengelola modul Koperasi Cahaya?
     *
     * Dipakai sebagai SATU sumber kebenaran oleh seluruh guard modul Koperasi,
     * supaya aturannya tidak tersalin puluhan kali dan bisa diubah di satu
     * tempat kalau nanti berubah.
     *
     * PENTING: isAdmin() SENGAJA dibiarkan berarti "Admin saja". Kalau
     * isAdmin() diperluas, Manajemen User, Backup, Restore, dan Pengaturan
     * Sistem ikut terbuka - karena keempatnya memakai guard yang sama.
     * Jadi perluasan akses dilakukan lewat helper terpisah ini, bukan dengan
     * mengubah arti isAdmin().
     */
    public function bolehKelolaKoperasi(): bool
    {
        return $this->isAdmin() || $this->isTimKoperasi();
    }

    /**
     * Apakah akun ini boleh dipakai login?
     *
     * Akun yang dinonaktifkan TIDAK dihapus dan passwordnya TIDAK diubah —
     * hanya aksesnya yang dicabut, supaya seluruh jejaknya di Activity Log
     * dan kolom created_by di 7 tabel lain tetap utuh.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Apakah user ini boleh MENGELOLA (lihat + ubah) data peserta tertentu?
     * Admin: semua peserta. Pendamping: HANYA binaannya sendiri.
     * Pimpinan SENGAJA tidak termasuk di sini — Pimpinan read-only.
     */
    public function canAccessParticipant(?Participant $participant): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // null = pesertanya tidak bisa dimuat sama sekali. Bukan berarti
        // terbuka untuk semua orang: hanya Admin yang lolos di atas.
        return $participant !== null
            && $this->isPendamping()
            && $this->pendamping_id !== null
            && $participant->pendamping_id === $this->pendamping_id;
    }

    /**
     * Apakah user ini boleh MELIHAT SAJA (read-only) data peserta tertentu?
     * Sama seperti canAccessParticipant, TAPI Pimpinan juga diizinkan lihat
     * peserta manapun (read-only) — dipakai khusus di halaman Detail Peserta.
     */
    public function canViewParticipant(?Participant $participant): bool
    {
        return $this->isPimpinan() || $this->canAccessParticipant($participant);
    }

    /**
     * Apakah user ini boleh MENAMBAH peserta/binaan baru?
     *
     * Admin: ya, tanpa syarat. Pendamping: ya, TAPI hanya kalau akunnya
     * benar-benar tertaut ke satu data Pendamping — karena peserta yang
     * dibuatnya otomatis menjadi binaannya sendiri. Tanpa tautan itu, peserta
     * baru akan lahir tanpa pemilik dan langsung tidak bisa diakses
     * pembuatnya. Syaratnya dibuat identik dengan guard di
     * BinaanSayaController supaya menu dan otorisasi tidak pernah berbeda.
     *
     * Pimpinan SENGAJA tidak termasuk — Pimpinan read-only.
     */
    public function canCreateParticipant(): bool
    {
        return $this->isAdmin()
            || ($this->isPendamping() && $this->pendamping_id !== null);
    }

    /**
     * Apakah user ini boleh MENGHAPUS / MEMULIHKAN peserta?
     *
     * Admin saja. Menghapus peserta berdampak lintas modul (omzet,
     * pendampingan, pelatihan, legalitas, HPP, keanggotaan koperasi), jadi
     * tetap jadi kewenangan global. Pendamping memperbaiki data lewat Edit —
     * bukan dengan menghapus lalu membuat ulang.
     */
    public function canDeleteParticipant(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Apakah user ini boleh MENGUBAH SIAPA PENDAMPING dari sebuah peserta?
     *
     * Admin saja. Kalau Pendamping boleh, ia bisa memindahkan binaan orang
     * lain ke dirinya sendiri — atau melepas binaannya sendiri sehingga
     * hilang dari pengawasan.
     */
    public function canAssignPendamping(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Apakah user ini boleh mengelola (ubah/hapus) SATU catatan milik peserta?
     *
     * SATU aturan untuk SEMUA catatan yang menempel pada peserta — riwayat
     * pendampingan, riwayat pelatihan, laporan omzet, dan catatan sejenis
     * berikutnya. Modul yang bentuk datanya sama tidak boleh punya aturan izin
     * yang berbeda-beda; itu sumber celah dan kebingungan.
     *
     * Aturannya diturunkan dari PESERTANYA, bukan dari siapa yang dulu
     * menulis catatan: Admin boleh semua, Pendamping boleh selama catatan itu
     * milik binaannya sendiri. Ini disengaja — kalau Admin memindahkan binaan
     * ke Pendamping lain, pendamping barulah yang berhak merapikan catatannya.
     *
     * Peserta yang null (mis. barisnya menggantung setelah pesertanya
     * di-soft-delete) hanya bisa disentuh Admin.
     */
    public function canManageParticipantRecord(?Participant $participant): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $participant !== null && $this->canAccessParticipant($participant);
    }

    /**
     * Bentuk khusus untuk catatan pendampingan.
     *
     * Dipertahankan sebagai nama yang sudah dipakai controller, Blade, dan
     * test, TAPI aturannya kini diteruskan ke canManageParticipantRecord()
     * supaya tidak ada dua salinan aturan yang bisa berbeda.
     */
    public function canManagePendampinganRecord(PendampinganRecord $record): bool
    {
        // pesertaTermasukArsip(): tanpa ini, Pendamping kehilangan akses ke
        // catatannya sendiri begitu pesertanya diarsipkan — padahal catatan
        // itu justru yang masih perlu dibaca dan dirapikan. Pola yang sama
        // sudah dipakai OmzetReportPolicy.
        return $this->canManageParticipantRecord($record->pesertaTermasukArsip());
    }
}
