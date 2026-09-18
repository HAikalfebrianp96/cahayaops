@extends('layouts.app')

@section('title', 'Restore Database')

@section('content')
<div class="max-w-2xl space-y-5">

    <div class="bg-red-50 border-2 border-status-danger/40 rounded-2xl p-6">
        <h2 class="text-lg font-bold text-status-danger mb-2">⚠️ Restore Database — Tindakan Berbahaya</h2>
        <div class="text-sm text-status-danger space-y-2">
            <p>Restore akan <strong>MENIMPA SELURUH data yang sedang berjalan sekarang</strong> dengan isi file yang Anda upload. Tindakan ini <strong>TIDAK BISA DIBATALKAN</strong>.</p>
            <p>Kalau file yang di-upload salah, rusak, atau berasal dari database lain — data Koperasi Cahaya &amp; Cahaya Preneur yang sedang berjalan bisa <strong>hilang permanen</strong>.</p>
            <p>Sistem akan otomatis membuat 1 backup cadangan SEBELUM restore dijalankan — tapi ini bukan jaminan mutlak (baca catatan di bawah form).</p>
        </div>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <p class="text-xs text-text-secondary mb-1">Database Target Restore</p>
        <p class="text-sm font-bold text-text-main font-mono">{{ $targetDatabase }}</p>
    </div>

    @if (session('backup_sebelum_restore'))
        <div class="bg-gold-light border border-status-warning/40 rounded-2xl p-5">
            <p class="text-sm font-semibold text-text-main mb-1">Restore sebelumnya gagal di tengah proses.</p>
            <p class="text-sm text-text-main">Backup otomatis SEBELUM percobaan restore tadi tersimpan sebagai:</p>
            <p class="text-sm font-bold text-cahaya-primary mt-1">{{ session('backup_sebelum_restore') }}</p>
            <a href="{{ route('backup-database.index') }}" class="text-xs text-status-info hover:underline mt-2 inline-block">Buka halaman Backup Database untuk download backup itu &rarr;</a>
        </div>
    @endif

    @if ($errors->has('restore'))
        <div class="bg-red-50 border border-status-danger/40 rounded-2xl p-5">
            <p class="text-sm font-semibold text-status-danger mb-1">Restore Gagal</p>
            <p class="text-sm text-status-danger">{{ $errors->first('restore') }}</p>
        </div>
    @endif

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <form method="POST" action="{{ route('backup-database.restore.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-text-main mb-1">File Backup (.sql)</label>
                <p class="text-xs text-text-secondary mb-2">Hanya file yang di-upload langsung sekarang yang bisa diproses — tidak bisa memilih dari daftar backup lama.</p>
                <input type="file" name="file" accept=".sql" required
                       class="w-full rounded-xl border border-border-soft bg-white text-text-main px-3 py-2.5 text-sm">
                @error('file') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main mb-1">Ketik Ulang Kalimat Konfirmasi Ini Persis:</label>
                <p class="text-sm font-mono font-bold text-status-danger bg-red-50 rounded-lg px-3 py-2 mb-2 select-all">{{ $konfirmasiWajib }}</p>
                <input type="text" name="konfirmasi" id="restore-konfirmasi-input" required autocomplete="off"
                       placeholder="Ketik persis kalimat di atas"
                       oninput="document.getElementById('restore-submit-btn').disabled = (this.value !== @js($konfirmasiWajib));"
                       class="w-full rounded-xl border border-status-danger/40 bg-white text-text-main px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-danger/30">
                @error('konfirmasi') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-2 flex gap-2">
                <button type="submit" id="restore-submit-btn" disabled
                        onclick="return confirm('BENAR-BENAR YAKIN? Semua data yang sedang berjalan akan ditimpa. Ini tidak bisa dibatalkan.');"
                        class="bg-status-danger text-white text-sm font-bold px-5 py-2.5 rounded-lg hover:opacity-90 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Jalankan Restore Sekarang
                </button>
                <a href="{{ route('backup-database.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                    Batal, Kembali
                </a>
            </div>
        </form>
    </div>

    <div class="bg-surface-bg border border-border-soft rounded-2xl p-5 text-xs text-text-secondary space-y-1.5">
        <p class="font-semibold text-text-main">Catatan teknis jujur:</p>
        <p>MySQL tidak mendukung perintah struktur tabel (CREATE/DROP TABLE) di dalam transaksi database — artinya kalau proses restore terhenti DI TENGAH JALAN (misal listrik mati, koneksi putus), sebagian tabel bisa sudah ter-restore dan sebagian belum. Ini keterbatasan MySQL itu sendiri, bukan sesuatu yang bisa "diperbaiki" lewat kode.</p>
        <p>Satu-satunya jalan mundur yang benar-benar bisa diandalkan adalah backup otomatis yang dibuat SEBELUM restore ini dijalankan — pastikan Anda tahu di mana file itu tersimpan (akan diberi tahu setelah proses selesai/gagal).</p>
    </div>
</div>
@endsection
