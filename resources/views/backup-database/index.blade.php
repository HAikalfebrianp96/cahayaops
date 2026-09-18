@extends('layouts.app')

@section('title', 'Backup Database')

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-[20px] font-bold text-text-main">Backup Database</h2>
        <p class="text-sm text-text-secondary mt-0.5">Buat, unduh, dan kelola file cadangan database — khusus Admin.</p>
    </div>

    {{-- SALINAN DI LUAR DISK INI

         Seluruh berkas di daftar bawah tersimpan di disk yang SAMA dengan
         databasenya. Kalau disk itu rusak atau komputernya hilang, database
         dan seluruh cadangannya lenyap bersamaan.

         Panel ini membuat keadaannya terlihat. Sebelumnya fitur yang mati
         dan fitur yang berjalan lancar sama-sama senyap: tidak muncul di
         halaman ini, tidak di jadwal, dan tidak di log. --}}
    @if (($salinanLuar['aktif'] ?? false) && ($salinanLuar['bisa_ditulis'] ?? false))
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-block w-2 h-2 rounded-full bg-status-success shrink-0"></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-text-main">Salinan di luar disk ini: aktif</p>
                    <p class="text-xs text-text-secondary mt-1 break-words">
                        {{ $salinanLuar['jumlah'] }} salinan tersimpan di <span class="font-medium">{{ $salinanLuar['folder'] }}</span>
                        (dipertahankan {{ $salinanLuar['retensi'] }}).
                        @if ($salinanLuar['terbaru'])
                            {{-- Timezone disebut eksplisit, sama seperti kolom "Dibuat" di bawah:
                                 sejak Carbon 3, createFromTimestamp() default-nya UTC,
                                 dan tanpa ini waktunya tampil mundur 7 jam. --}}
                            Terakhir disalin {{ \Carbon\Carbon::createFromTimestamp($salinanLuar['terbaru'], config('app.timezone'))->translatedFormat('d M Y H:i') }}.
                        @else
                            Belum ada satu pun salinan di sana.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @elseif ($salinanLuar['aktif'] ?? false)
        <div class="bg-red-50 border border-status-danger/40 rounded-2xl p-5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-block w-2 h-2 rounded-full bg-status-danger shrink-0"></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-status-danger">Salinan di luar disk ini: TIDAK BISA DITULIS</p>
                    <p class="text-xs text-text-secondary mt-1 break-words">
                        Folder tujuan <span class="font-medium">{{ $salinanLuar['folder'] }}</span>
                        {{ $salinanLuar['folder_ada'] ? 'ada tetapi tidak bisa ditulis' : 'tidak ditemukan' }}.
                        Kalau ini disk eksternal atau folder cloud, pastikan sudah terpasang dan tersinkron —
                        selama ini berlangsung, cadangan hanya ada di disk komputer ini.
                    </p>
                </div>
            </div>
        </div>
    @else
        <div class="bg-gold-light border border-status-warning/40 rounded-2xl p-5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-block w-2 h-2 rounded-full bg-status-warning shrink-0"></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-text-main">Salinan di luar disk ini: belum diatur</p>
                    <p class="text-xs text-text-secondary mt-1">
                        Semua cadangan di bawah tersimpan di disk yang sama dengan databasenya. Kalau disk itu
                        rusak atau komputernya hilang, database dan seluruh cadangannya hilang bersamaan.
                    </p>
                    <p class="text-xs text-text-secondary mt-2">
                        Menyalakan: jalankan <code class="bg-white/70 px-1 py-0.5 rounded text-[11px]">scripts\pasang-cadangan-luar.ps1</code>
                        dengan folder tujuan (hard disk eksternal atau folder yang tersinkron ke cloud).
                        Berkas cadangan memuat data pribadi peserta, jadi pemilihan tujuannya adalah keputusan
                        pengelola program.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- DAFTAR BACKUP --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-sm font-semibold text-text-main">Daftar File Backup</h3>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('backup-database.store') }}"
                      onsubmit="return confirm('Buat backup database sekarang? Proses ini cuma MEMBACA data (tidak mengubah apapun), tapi bisa makan waktu beberapa saat kalau data sudah banyak.');">
                    @csrf
                    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                        + Buat Backup Sekarang
                    </button>
                </form>
                <a href="{{ route('backup-database.restore.show') }}" class="border-2 border-status-danger text-status-danger text-sm font-medium px-4 py-2 rounded-lg hover:bg-status-danger/5 transition">
                    Restore Database
                </a>
            </div>
        </div>
        <p class="text-xs text-text-secondary mb-4">Backup cuma MEMBACA data (SELECT), tidak pernah mengubah database. Backup manual otomatis disimpan maksimal 10 file terbaru (retention) — backup otomatis sebelum restore TIDAK PERNAH ikut terhapus. Unduh &amp; simpan sendiri secara berkala di tempat terpisah (Google Drive/flashdisk) untuk keamanan penuh.</p>

        @if (empty($backups))
            <div class="text-center py-10">
                <p class="text-sm font-medium text-text-main mb-1">Belum Ada Backup</p>
                <p class="text-xs text-text-secondary">Klik "+ Buat Backup Sekarang" untuk membuat backup pertama.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Nama File</th>
                            <th class="px-4 py-3 font-medium">Ukuran</th>
                            <th class="px-4 py-3 font-medium">Dibuat</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $b)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-4 py-3 text-text-main">
                                    {{ $b['name'] }}
                                    @if ($b['is_pre_restore'])
                                        <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium bg-gold-light text-cahaya-accent">Otomatis (sebelum restore) — tidak ikut retention</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-text-secondary">{{ $b['size'] }}</td>
                                {{-- Timezone WAJIB disebut eksplisit: sejak Carbon 3,
                                     createFromTimestamp() default-nya UTC (dulu di Carbon 2
                                     mengikuti timezone aplikasi). Tanpa ini kolom "Dibuat"
                                     tampil mundur 7 jam dan bertentangan dengan jam pada
                                     nama filenya sendiri. --}}
                                <td class="px-4 py-3 text-text-secondary">{{ \Carbon\Carbon::createFromTimestamp($b['created_at'], config('app.timezone'))->format('d-m-Y H:i') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('backup-database.download', $b['name']) }}" class="text-status-info hover:underline text-xs font-medium">Download</a>
                                    <span class="text-border-soft mx-1">|</span>
                                    <form method="POST" action="{{ route('backup-database.destroy', $b['name']) }}" class="inline"
                                          onsubmit="return confirm('Hapus file backup ini? Pastikan sudah di-download dulu kalau masih diperlukan.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-status-danger hover:underline text-xs font-medium">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
