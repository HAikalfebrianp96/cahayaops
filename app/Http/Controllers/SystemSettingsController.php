<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * PENGATURAN SISTEM — sekarang HANYA System Health, System Information,
 * dan Activity Log.
 *
 * Backup & Restore Database SUDAH DIPINDAHKAN seluruhnya ke
 * BackupDatabaseController (halaman terpisah, route backup-database.*).
 * Tidak ada sisa logic backup/restore di file ini — jangan ditambahkan
 * lagi ke sini supaya tidak ada dua sumber kebenaran.
 */
class SystemSettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $health = $this->gatherSystemHealth();
        $info = $this->gatherSystemInformation();

        $logs = ActivityLog::with('user')
            ->when($request->filled('model_type'), fn ($q) => $q->where('model_type', $request->string('model_type')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('tanggal_mulai'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('tanggal_mulai')))
            ->when($request->filled('tanggal_akhir'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('tanggal_akhir')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $modelTypeOptions = ActivityLog::query()->distinct()->orderBy('model_type')->pluck('model_type');
        $actionOptions = ActivityLog::query()->distinct()->orderBy('action')->pluck('action');
        $userOptions = User::orderBy('name')->get(['id', 'name']);

        return view('pengaturan-sistem.index', [
            'health' => $health,
            'info' => $info,
            'logs' => $logs,
            'modelTypeOptions' => $modelTypeOptions,
            'actionOptions' => $actionOptions,
            'userOptions' => $userOptions,
            'filters' => $request->only(['model_type', 'action', 'user_id', 'tanggal_mulai', 'tanggal_akhir']),
        ]);
    }

    /**
     * SYSTEM HEALTH — status TEKNIS yang bisa berubah (cache, koneksi DB,
     * storage, disk). SEMUA di sini READ-ONLY, tidak ada operasi tulis.
     *
     * Cache/Config/Route status pakai API RESMI Laravel (bukan cek manual
     * nama file cache, yang bisa berubah antar versi Laravel):
     * - app()->configurationIsCached()
     * - app()->routesAreCached()
     * - Cache facade dites LANGSUNG (put lalu get) supaya statusnya BENAR
     *   akurat, bukan cuma baca nama driver dari config.
     *
     * CATATAN JUJUR soal View Cache: Laravel TIDAK punya API resmi untuk
     * mendeteksi apakah `artisan view:cache` pernah dijalankan — file blade
     * terkompilasi terlihat SAMA baik dari view:cache maupun dari kompilasi
     * normal saat halaman pertama diakses. Jadi ditampilkan sebagai JUMLAH
     * file terkompilasi (informasi jujur), BUKAN klaim Aktif/Tidak Aktif
     * yang sebenarnya tidak bisa dipastikan oleh framework manapun.
     */
    private function gatherSystemHealth(): array
    {
        $dbOk = true;
        $dbError = null;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        $storagePath = storage_path();
        $freeBytes = @disk_free_space($storagePath);
        $totalBytes = @disk_total_space($storagePath);

        $cacheOk = true;
        try {
            $testKey = '__health_check_' . time();
            Cache::put($testKey, '1', 5);
            $cacheOk = Cache::get($testKey) === '1';
            Cache::forget($testKey);
        } catch (\Throwable $e) {
            $cacheOk = false;
        }

        $viewCacheCount = 0;
        $viewCachePath = storage_path('framework/views');
        if (is_dir($viewCachePath)) {
            $viewCacheCount = count(glob($viewCachePath . '/*.php') ?: []);
        }

        return [
            'db_connected' => $dbOk,
            'db_error' => $dbError,
            'storage_writable' => is_writable($storagePath),
            'disk_free_gb' => $freeBytes ? round($freeBytes / 1073741824, 1) : null,
            'disk_total_gb' => $totalBytes ? round($totalBytes / 1073741824, 1) : null,
            'config_cached' => app()->configurationIsCached(),
            'route_cached' => app()->routesAreCached(),
            'cache_ok' => $cacheOk,
            'cache_driver' => config('cache.default'),
            'view_cache_count' => $viewCacheCount,
            'server_time' => now()->format('d-m-Y H:i:s'),
        ];
    }

    /**
     * SYSTEM INFORMATION — info APLIKASI yang relatif tetap (versi, nama,
     * environment). TERPISAH dari System Health sesuai permintaan.
     * Sengaja TIDAK menampilkan: password DB, APP_KEY, secret/token apapun.
     */
    private function gatherSystemInformation(): array
    {
        $defaultConnection = config('database.default');

        return [
            'app_name' => config('app.name'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'environment' => config('app.env'),
            'debug_mode' => (bool) config('app.debug'),
            'db_driver' => $defaultConnection,
            'db_name' => config("database.connections.{$defaultConnection}.database"),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'server_os' => PHP_OS_FAMILY,
        ];
    }
}
