@extends('layouts.app')

@section('title', 'Monitoring & Keamanan')

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-[20px] font-bold text-text-main">Monitoring &amp; Keamanan</h2>
        <p class="text-sm text-text-secondary mt-0.5">Pantau kesehatan sistem dan riwayat aktivitas aplikasi.</p>
    </div>

    {{-- SYSTEM HEALTH --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">System Health</h3>
        <p class="text-xs text-text-secondary mb-4">Status teknis yang bisa berubah sewaktu-waktu (cache, koneksi, storage).</p>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Status Database</p>
                @if ($health['db_connected'])
                    <p class="text-sm font-bold text-status-success">🟢 Terhubung</p>
                @else
                    <p class="text-sm font-bold text-status-danger">🔴 Terputus</p>
                    <p class="text-[10px] text-status-danger mt-1 break-words">{{ $health['db_error'] }}</p>
                @endif
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Cache Aplikasi</p>
                <p class="text-sm font-bold {{ $health['cache_ok'] ? 'text-status-success' : 'text-status-danger' }}">
                    {{ $health['cache_ok'] ? '🟢 Aktif' : '🔴 Bermasalah' }}
                </p>
                <p class="text-[10px] text-text-secondary mt-1">Driver: {{ $health['cache_driver'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Config Cache</p>
                <p class="text-sm font-bold {{ $health['config_cached'] ? 'text-status-success' : 'text-status-warning' }}">
                    {{ $health['config_cached'] ? 'Tersimpan' : 'Tidak Tersimpan' }}
                </p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Route Cache</p>
                <p class="text-sm font-bold {{ $health['route_cached'] ? 'text-status-success' : 'text-status-warning' }}">
                    {{ $health['route_cached'] ? 'Tersimpan' : 'Tidak Tersimpan' }}
                </p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">View Terkompilasi</p>
                <p class="text-sm font-bold text-status-info">{{ $health['view_cache_count'] }} file</p>
                <p class="text-[10px] text-text-secondary mt-1">Laravel tidak punya penanda resmi "view:cache aktif"</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Storage Writable</p>
                <p class="text-sm font-bold {{ $health['storage_writable'] ? 'text-status-success' : 'text-status-danger' }}">
                    {{ $health['storage_writable'] ? '🟢 Ya' : '🔴 Tidak' }}
                </p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Ruang Disk Tersisa</p>
                <p class="text-sm font-bold text-text-main">
                    {{ $health['disk_free_gb'] !== null ? $health['disk_free_gb'] . ' GB' : '—' }}
                    @if ($health['disk_total_gb'])
                        <span class="text-text-secondary font-normal">/ {{ $health['disk_total_gb'] }} GB</span>
                    @endif
                </p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Waktu Server</p>
                <p class="text-sm font-bold text-text-main">{{ $health['server_time'] }}</p>
            </div>
        </div>
    </div>

    {{-- SYSTEM INFORMATION --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">System Information</h3>
        <p class="text-xs text-text-secondary mb-4">Informasi aplikasi yang relatif tetap. Tidak menampilkan password, APP_KEY, atau kredensial apapun.</p>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Aplikasi</p>
                <p class="text-sm font-bold text-text-main">{{ $info['app_name'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Laravel Version</p>
                <p class="text-sm font-bold text-text-main">{{ $info['laravel_version'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">PHP Version</p>
                <p class="text-sm font-bold text-text-main">{{ $info['php_version'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Environment</p>
                <p class="text-sm font-bold text-text-main">{{ $info['environment'] }} {{ $info['debug_mode'] ? '(debug ON)' : '' }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Database Driver</p>
                <p class="text-sm font-bold text-text-main">{{ $info['db_driver'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Database</p>
                <p class="text-sm font-bold text-text-main">{{ $info['db_name'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Timezone</p>
                <p class="text-sm font-bold text-text-main">{{ $info['timezone'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Locale</p>
                <p class="text-sm font-bold text-text-main">{{ $info['locale'] }}</p>
            </div>
            <div class="border border-border-soft rounded-xl p-4">
                <p class="text-xs text-text-secondary mb-1">Server OS</p>
                <p class="text-sm font-bold text-text-main">{{ $info['server_os'] }}</p>
            </div>
        </div>
    </div>

    {{-- ACTIVITY LOG --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-sm font-semibold text-text-main mb-1">Log Aktivitas</h3>
        <p class="text-xs text-text-secondary mb-4">Mulai tercatat sejak fitur ini dipasang — aktivitas sebelumnya tidak tersedia (tidak dibuat data palsu).</p>

        <form method="GET" class="flex flex-wrap gap-3 mb-4">
            <select name="model_type" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="">Semua Jenis Data</option>
                @foreach ($modelTypeOptions as $type)
                    <option value="{{ $type }}" @selected(($filters['model_type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="action" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="">Semua Aksi</option>
                @foreach ($actionOptions as $act)
                    <option value="{{ $act }}" @selected(($filters['action'] ?? '') === $act)>{{ ucfirst(str_replace('_', ' ', $act)) }}</option>
                @endforeach
            </select>
            <select name="user_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
                <option value="">Semua User</option>
                @foreach ($userOptions as $u)
                    <option value="{{ $u->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="tanggal_mulai" value="{{ $filters['tanggal_mulai'] ?? '' }}"
                   class="rounded-lg border border-border-soft px-3 py-2 text-sm" placeholder="Tanggal Mulai">
            <input type="date" name="tanggal_akhir" value="{{ $filters['tanggal_akhir'] ?? '' }}"
                   class="rounded-lg border border-border-soft px-3 py-2 text-sm" placeholder="Tanggal Akhir">
            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Filter</button>
            <a href="{{ route('pengaturan-sistem.index') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
        </form>

        @if ($logs->isEmpty())
            <div class="text-center py-10">
                <p class="text-sm font-medium text-text-main mb-1">Belum Ada Aktivitas Tercatat</p>
                <p class="text-xs text-text-secondary">Aktivitas akan muncul di sini begitu ada data yang dibuat/diubah/dihapus, atau aktivitas keamanan (login, ganti password, dst).</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Waktu</th>
                            <th class="px-4 py-3 font-medium">Pengguna</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                            <th class="px-4 py-3 font-medium">Keterangan</th>
                            <th class="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-4 py-3 text-text-secondary whitespace-nowrap">{{ $log->created_at->format('d-m-Y H:i') }}</td>
                                <td class="px-4 py-3 text-text-main">{{ $log->user->name ?? 'Sistem' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                        {{ match(true) {
                                            in_array($log->action, ['created', 'login', 'backup_created', 'restore_database_success', 'account_created', 'account_restored']) => 'bg-primary-light text-status-success',
                                            in_array($log->action, ['deleted', 'restore_database_failed', 'backup_deleted', 'account_unlinked', 'account_deactivated', 'login_blocked', 'session_blocked']) => 'bg-red-50 text-status-danger',
                                            default => 'bg-gold-light text-cahaya-accent',
                                        } }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-text-secondary">{{ $log->description }}</td>
                                <td class="px-4 py-3 text-text-secondary whitespace-nowrap">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
