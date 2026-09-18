<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        // Identitas pelaku dibekukan di barisnya sendiri supaya jejak audit
        // tetap terbaca setelah akunnya dihapus permanen.
        'actor_nama',
        'actor_email',
        'action',
        'model_type',
        'model_id',
        'description',
        'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==========================================================
    // PENYAJIAN — kalimat yang bisa dibaca manusia.
    //
    // Yang TERSIMPAN di kolom `description` TIDAK diubah sama sekali. Itu
    // catatan audit; mengubah formatnya berarti membuat baris lama dan baru
    // tidak lagi setara, dan menghapus konteks teknis yang justru berguna
    // saat menelusuri masalah.
    //
    // Yang ditambahkan di sini adalah LAPISAN TAMPILAN: dari `action` yang
    // sudah terstruktur (mis. 'participant_updated'), sistem menyusun kalimat
    // Indonesia yang wajar untuk ditampilkan di halaman Riwayat Perubahan.
    // Tidak ada tabel log kedua, tidak ada kolom baru.
    // ==========================================================

    /** Objek yang dikenai tindakan, diturunkan dari awalan `action`. */
    private const OBJEK = [
        'participant' => 'data peserta',
        'mentoring' => 'riwayat pendampingan',
        'training' => 'data pelatihan',
        'omzet' => 'laporan omzet',
        'measurement' => 'pengukuran perkembangan usaha',
        'koperasi_member' => 'keanggotaan koperasi',
        'koperasi_saving' => 'data simpanan',
        'business_unit' => 'unit usaha',
        'sales_transaction' => 'transaksi penjualan',
        'dana_sukarela' => 'catatan dana sosial',
        'dana_hibah' => 'catatan dana hibah',
        'account' => 'akun pengguna',
    ];

    /** Kata kerja, diturunkan dari akhiran `action`. */
    private const TINDAKAN = [
        'created' => 'menambahkan',
        'updated' => 'mengubah',
        'deleted' => 'menghapus',
        'restored' => 'memulihkan',
        'corrected' => 'mengoreksi',
        'cancelled' => 'membatalkan',
    ];

    /**
     * Kalimat siap tampil, mis. "Rosa Linda (Pendamping) menambahkan riwayat
     * pendampingan."
     *
     * Kalau polanya tidak dikenali, deskripsi aslinya dikembalikan apa adanya
     * — lebih baik menampilkan catatan teknis yang benar daripada kalimat
     * rapi yang mengarang.
     */
    public function kalimat(): string
    {
        $pelaku = $this->namaPelaku();
        $inti = $this->intiKalimat();

        return $inti === null
            ? $this->description
            : $pelaku . ' ' . $inti . '.';
    }

    /** "Rosa Linda (Pendamping)" — atau "Sistem" untuk aksi tanpa pelaku. */
    public function namaPelaku(): string
    {
        // Akun yang sudah dihapus permanen: user_id-nya NULL, tapi identitas
        // pelakunya masih tersimpan di barisnya. Disebut apa adanya, lengkap
        // dengan keterangan bahwa akunnya sudah tidak ada — jauh lebih jujur
        // daripada menampilkannya sebagai "Sistem".
        if (! $this->user && $this->actor_nama) {
            return $this->actor_nama . ' (akun sudah dihapus)';
        }

        if (! $this->user) {
            return 'Sistem';
        }

        $peran = User::ROLE_OPTIONS[$this->user->role] ?? null;

        return $peran ? $this->user->name . ' (' . $peran . ')' : $this->user->name;
    }

    /** Bagian "menambahkan riwayat pendampingan", tanpa pelaku. Null = tak dikenali. */
    private function intiKalimat(): ?string
    {
        foreach (self::TINDAKAN as $akhiran => $kata) {
            if (! str_ends_with($this->action, '_' . $akhiran) && $this->action !== $akhiran) {
                continue;
            }

            // Aksi generik lama ('created'/'updated'/'deleted') tidak punya
            // awalan objek — objeknya diambil dari model_type.
            $awalan = $this->action === $akhiran
                ? null
                : substr($this->action, 0, -strlen('_' . $akhiran));

            $objek = $awalan === null
                ? $this->objekDariModel()
                : (self::OBJEK[$awalan] ?? $this->objekDariModel());

            return $kata . ' ' . $objek;
        }

        return null;
    }

    /** Nama objek yang bisa dibaca, dari model_type. */
    private function objekDariModel(): string
    {
        return match ($this->model_type) {
            'Participant' => 'data peserta',
            'PendampinganRecord' => 'riwayat pendampingan',
            'TrainingRecord' => 'data pelatihan',
            'OmzetReport' => 'laporan omzet',
            'ParticipantMeasurement' => 'pengukuran perkembangan usaha',
            'KoperasiMember' => 'keanggotaan koperasi',
            'MemberSaving' => 'data simpanan',
            'BusinessUnit' => 'unit usaha',
            'ProductService' => 'produk/jasa',
            'Customer' => 'data pelanggan',
            'Pendamping' => 'data pendamping',
            'HppCalculation' => 'perhitungan HPP',
            'SalesTransaction' => 'transaksi penjualan',
            'Pengeluaran' => 'catatan pengeluaran',
            'User' => 'akun pengguna',
            default => 'data',
        };
    }

    /**
     * Rincian teknis di balik kalimat — nama data yang disentuh, nominal,
     * nilai sebelum/sesudah. Ditampilkan sebagai baris kecil di bawahnya
     * supaya kalimatnya tetap ringkas tapi jejaknya tidak hilang.
     */
    public function rincian(): string
    {
        return $this->description;
    }

    /**
     * Helper terpusat untuk mencatat aktivitas KEAMANAN (login, logout, ganti
     * password, reset password, dst) dari controller manapun — TETAP pakai
     * tabel & model activity_logs yang sama (BUKAN sistem log kedua).
     *
     * Dibungkus try/catch: gagal mencatat log TIDAK BOLEH menggagalkan aksi
     * aslinya (login/ganti password tetap harus berhasil walau log gagal).
     */
    public static function record(
        string $action,
        string $modelType,
        ?int $modelId,
        string $description,
        ?int $userId = null,
    ): void {
        try {
            $pelaku = $userId ? User::find($userId) : Auth::user();

            static::create([
                'user_id' => $userId ?? Auth::id(),
                // Identitas pelaku ikut dibekukan di barisnya sendiri. Kalau
                // akunnya kelak dihapus permanen, user_id menjadi NULL tapi
                // jejak auditnya tetap menyebut siapa yang melakukannya.
                'actor_nama' => $pelaku?->name,
                'actor_email' => $pelaku?->email,
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'description' => $description,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            // Sengaja diam — logging tidak boleh menjatuhkan aksi utama.
        }
    }
}
