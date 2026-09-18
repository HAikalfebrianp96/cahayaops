<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\BackupDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BACKUP & RESTORE DATABASE — halaman TERSENDIRI (dipisah dari
 * SystemSettingsController yang sekarang cuma mengurus System Health,
 * System Information, dan Activity Log).
 *
 * Seluruh logic di file ini DIPINDAHKAN dari SystemSettingsController —
 * bukan disalin/diduplikasi. Perilakunya tidak diubah sama sekali, hanya
 * berpindah tempat + nama route yang menyesuaikan (backup-database.*).
 */
class BackupDatabaseController extends Controller
{
    /**
     * Kalimat konfirmasi WAJIB diketik ulang persis sebelum Restore benar-benar
     * jalan. Bukan sekadar klik OK — supaya tidak ada yang ke-klik tidak sengaja.
     */
    private const RESTORE_KONFIRMASI = 'RESTORE DATABASE';

    /**
     * Pembuatan file backup, retention, dan pembacaan daftarnya DIPINDAHKAN ke
     * BackupDatabaseService supaya backup terjadwal memakai kode yang PERSIS
     * SAMA dengan tombol di halaman ini — bukan salinan kedua yang bisa
     * berbeda diam-diam. Perilaku halaman ini tidak berubah sama sekali.
     */
    public function __construct(private readonly BackupDatabaseService $backup)
    {
    }

    public function index()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return view('backup-database.index', [
            'backups' => $this->backup->daftar(),

            // Keadaan salinan ke luar disk ini. Seluruh berkas di daftar
            // atas berbagi nasib dengan satu disk yang sama; tanpa baris ini
            // tidak ada satu pun tempat yang memberi tahu Admin apakah ada
            // salinan di luar sana — dan fitur yang mati tidak bisa
            // dibedakan dari fitur yang berjalan lancar.
            'salinanLuar' => $this->backup->keadaanSalinanLuar(),
        ]);
    }

    // ==========================================================
    // BACKUP DATABASE — PHP MURNI, TIDAK PAKAI shell_exec/exec()
    // sama sekali. Cuma BACA data (SELECT), TIDAK PERNAH menulis
    // atau mengubah database manapun. Aman dijalankan berkali-kali.
    //
    // CATATAN: karena backup/restore di sini murni PHP (tidak shell-out
    // ke mysql.exe/mysqldump.exe), risiko soal "path executable tidak
    // ketemu di Windows/Laragon" otomatis TIDAK RELEVAN di implementasi
    // ini — tidak ada executable eksternal yang dipanggil sama sekali.
    // ==========================================================

    public function storeBackup()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $filename = $this->backup->buatFile('backup');

        ActivityLog::record(
            action: 'backup_created',
            modelType: 'SystemSetting',
            modelId: null,
            description: 'Backup database manual dibuat: ' . $filename,
        );

        return redirect()
            ->route('backup-database.index')
            ->with('success', 'Backup berhasil dibuat: ' . $filename);
    }

    public function downloadBackup(string $filename)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        // WAJIB — cegah path traversal. Terima kedua pola nama file
        // (backup manual & pre-restore-backup otomatis).
        abort_unless($this->backup->namaFileSah($filename), 404);

        $path = storage_path('app/backups/' . $filename);
        abort_unless(file_exists($path), 404);

        return response()->download($path);
    }

    public function destroyBackup(string $filename)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        abort_unless($this->backup->namaFileSah($filename), 404);

        $path = storage_path('app/backups/' . $filename);
        if (file_exists($path)) {
            unlink($path);
        }

        ActivityLog::record(
            action: 'backup_deleted',
            modelType: 'SystemSetting',
            modelId: null,
            description: 'File backup dihapus manual: ' . $filename,
        );

        return redirect()
            ->route('backup-database.index')
            ->with('success', 'File backup ' . $filename . ' berhasil dihapus.');
    }

    // ==========================================================
    // RESTORE DATABASE — PALING BERISIKO. Pengaman WAJIB, tidak ada
    // yang opsional:
    // 1. Cuma terima file yang DI-UPLOAD LANGSUNG saat itu juga.
    // 2. Validasi ekstensi .sql SAJA (bukan .txt lagi) + validasi isi.
    // 3. Kalimat konfirmasi harus diketik ULANG PERSIS (bukan cuma klik).
    // 4. Backup otomatis DULU sebelum restore apapun dijalankan.
    // 5. Validasi hasil setelah restore selesai.
    // 6. Kalau gagal, laporan JELAS + nama file backup utk jalan mundur.
    // 7. SEMUA (berhasil/gagal) dicatat ke Activity Log — TANPA isi SQL.
    //
    // CATATAN URUTAN: validasi konfirmasi teks dilakukan SEBELUM membuat
    // safety backup (bukan sesudah) — supaya salah ketik konfirmasi TIDAK
    // menghasilkan file backup sia-sia yang menumpuk di storage.
    //
    // CATATAN JUJUR: MySQL TIDAK mendukung DDL (CREATE/DROP TABLE) di
    // dalam transaction (DDL selalu auto-commit di MySQL) — jadi
    // DB::transaction() DI SINI TIDAK memberi proteksi "semua-atau-tidak
    // sama sekali" yang sesungguhnya kalau restore gagal di tengah jalan.
    // Satu-satunya jaring pengaman NYATA adalah backup otomatis di poin 4.
    // ==========================================================

    public function showRestore()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        return view('backup-database.restore', [
            'konfirmasiWajib' => self::RESTORE_KONFIRMASI,
            'targetDatabase' => DB::connection()->getDatabaseName(),
        ]);
    }

    public function restoreDatabase(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:sql', 'max:51200'], // maks 50MB, HANYA .sql
            'konfirmasi' => ['required', 'string'],
        ], [
            'file.extensions' => 'File harus berformat .sql.',
            'file.max' => 'Ukuran file maksimal 50MB.',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $fileSizeLabel = $this->backup->formatUkuran($uploadedFile->getSize());

        // Pertahanan tambahan — pastikan ekstensi ASLI file (bukan cuma lolos
        // rule Laravel) benar-benar .sql, bukan file lain yang disamarkan.
        if (strtolower($uploadedFile->getClientOriginalExtension()) !== 'sql') {
            return back()
                ->withErrors(['file' => 'Hanya file berekstensi .sql yang diperbolehkan.'])
                ->withInput();
        }

        // Kalimat konfirmasi dicek SEBELUM apapun lain dijalankan — supaya
        // salah ketik tidak memicu backup/proses apapun yang tidak perlu.
        if (trim($validated['konfirmasi']) !== self::RESTORE_KONFIRMASI) {
            return back()
                ->withErrors(['konfirmasi' => 'Kalimat konfirmasi tidak sesuai. Ketik persis: "' . self::RESTORE_KONFIRMASI . '"'])
                ->withInput();
        }

        $sqlContent = file_get_contents($uploadedFile->getRealPath());

        // Validasi dasar — pastikan ini benar-benar terlihat seperti dump SQL,
        // bukan file sembarangan yang kebetulan diberi nama .sql.
        if (! str_contains($sqlContent, 'CREATE TABLE') && ! str_contains($sqlContent, 'INSERT INTO')) {
            return back()
                ->withErrors(['file' => 'File ini tidak terlihat seperti backup database yang valid (tidak ditemukan CREATE TABLE / INSERT INTO di dalamnya).'])
                ->withInput();
        }

        // Pertahanan tambahan — tolak kalau ada tag PHP di dalamnya (indikasi
        // file executable yang disamarkan sebagai .sql).
        if (str_contains($sqlContent, '<?php') || str_contains($sqlContent, '<?=')) {
            return back()
                ->withErrors(['file' => 'File berisi kode yang tidak diperbolehkan (terdeteksi tag PHP). Ditolak demi keamanan.'])
                ->withInput();
        }

        set_time_limit(300);

        // WAJIB — backup otomatis SEBELUM restore. Ini jaring pengaman
        // NYATA satu-satunya (lihat catatan soal DDL & transaction di atas).
        $backupSebelumRestore = $this->backup->buatFile('pre-restore-backup');

        try {
            DB::unprepared($sqlContent);

            // Validasi minimum setelah restore — pastikan koneksi & tabel
            // penting masih bisa dibaca (bukan jaminan data 100% benar,
            // tapi minimal konfirmasi database tidak rusak total).
            DB::connection()->getPdo();
            foreach (['users', 'activity_logs'] as $tabelPenting) {
                if (! Schema::hasTable($tabelPenting)) {
                    throw new \RuntimeException("Tabel penting '{$tabelPenting}' tidak ditemukan setelah restore.");
                }
            }
        } catch (\Throwable $e) {
            // Restore GAGAL di tengah jalan — laporkan JELAS, jangan diam-diam.
            ActivityLog::record(
                action: 'restore_database_failed',
                modelType: 'SystemSetting',
                modelId: null,
                description: "Restore GAGAL. File: {$originalName} ({$fileSizeLabel}). Safety backup: {$backupSebelumRestore}. Error: " . $e->getMessage(),
            );

            return back()
                ->withErrors(['restore' => 'Restore GAGAL di tengah proses. Database mungkin dalam kondisi tidak lengkap. Error: ' . $e->getMessage()])
                ->with('backup_sebelum_restore', $backupSebelumRestore)
                ->withInput();
        }

        ActivityLog::record(
            action: 'restore_database_success',
            modelType: 'SystemSetting',
            modelId: null,
            description: "Restore BERHASIL. File: {$originalName} ({$fileSizeLabel}). Safety backup: {$backupSebelumRestore}.",
        );

        return redirect()
            ->route('backup-database.index')
            ->with('success', 'Restore database berhasil. Backup otomatis SEBELUM restore tersimpan sebagai "' . $backupSebelumRestore . '" — simpan baik-baik kalau nanti perlu mengembalikan data sebelum restore ini.');
    }
}
