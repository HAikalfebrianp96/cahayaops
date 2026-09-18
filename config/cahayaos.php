<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AMBANG BATAS FOLLOW UP PENDAMPINGAN
    |--------------------------------------------------------------------------
    |
    | Menentukan kapan sebuah binaan berpindah dari Hijau -> Kuning -> Merah,
    | dihitung dari TANGGAL AKTIVITAS PENDAMPINGAN TERAKHIR.
    |
    |   Hijau  : aktivitas terakhir < ambang kuning
    |   Kuning : ambang kuning s/d ambang merah
    |   Merah  : melewati ambang merah, atau belum pernah didampingi sama sekali
    |
    | Nilai di sini hanyalah BAWAAN. Angka yang benar-benar dipakai diambil
    | dari SystemSetting (kolom followup_kuning_hari & followup_merah_hari)
    | supaya Admin bisa mengubahnya lewat halaman Pengaturan tanpa menyentuh
    | kode. Lihat App\Services\FollowUpService::ambang().
    |
    */

    'followup' => [
        'kuning_hari' => env('CAHAYAOS_FOLLOWUP_KUNING', 30),
        'merah_hari' => env('CAHAYAOS_FOLLOWUP_MERAH', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | TARGET DEFAULT PENDAMPING
    |--------------------------------------------------------------------------
    |
    | Dipakai kalau seorang pendamping BELUM punya target khusus untuk periode
    | berjalan. Target individual SELALU menang; ini hanya jaring pengaman
    | supaya KPI tidak kosong di awal program.
    |
    | Angka yang benar-benar dipakai diambil dari SystemSetting kalau Admin
    | sudah mengisinya — lihat App\Services\KpiService::targetDefault().
    |
    */

    'target_default' => [
        'binaan' => env('CAHAYAOS_TARGET_BINAAN', 20),
        'kunjungan' => env('CAHAYAOS_TARGET_KUNJUNGAN', 10),
        'pendampingan' => env('CAHAYAOS_TARGET_PENDAMPINGAN', 15),
        'follow_up' => env('CAHAYAOS_TARGET_FOLLOW_UP', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | AMBANG KEAKTIFAN PESERTA (hari sejak aktivitas terakhir)
    |--------------------------------------------------------------------------
    |
    |   Aktif           : <= aktif_hari
    |   Perlu Follow Up : aktif_hari s/d perlu_follow_up_hari
    |   Tidak Aktif     : > perlu_follow_up_hari
    |
    | SENGAJA terpisah dari ambang follow up pendamping di atas: yang itu
    | mengukur kinerja PENDAMPING, yang ini mengukur kesehatan PESERTA.
    |
    */

    'keaktifan_peserta' => [
        'aktif_hari' => env('CAHAYAOS_PESERTA_AKTIF', 30),
        'perlu_follow_up_hari' => env('CAHAYAOS_PESERTA_FOLLOW_UP', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | AMBANG BEBAN PENDAMPING (jumlah binaan)
    |--------------------------------------------------------------------------
    |
    | Dipakai Admin untuk menimbang redistribusi peserta antar pendamping.
    |
    */

    'beban_pendamping' => [
        'ringan_maks' => env('CAHAYAOS_BEBAN_RINGAN', 10),
        'normal_maks' => env('CAHAYAOS_BEBAN_NORMAL', 20),
        'tinggi_maks' => env('CAHAYAOS_BEBAN_TINGGI', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | BACKUP DATABASE
    |--------------------------------------------------------------------------
    |
    | Dikumpulkan di sini supaya bisa diubah tanpa menyentuh kode, dan supaya
    | tidak ada angka backup yang tersebar di beberapa berkas sekaligus.
    |
    | retensi
    |   Berapa backup RUTIN terbaru yang dipertahankan. Backup pra-restore
    |   (pre-restore-backup-*) TIDAK PERNAH ikut dihapus — itu jaring pengaman
    |   sebelum tindakan berbahaya, bukan backup rutin.
    |
    |   Nilainya dipaksa minimal 1 di dalam kode. Retensi 0 berarti backup
    |   terbaru pun ikut dihapus begitu dibuat, dan itu meninggalkan sistem
    |   tanpa cadangan sama sekali — keadaan yang tidak boleh bisa dicapai
    |   hanya karena salah ketik di berkas konfigurasi.
    |
    | min_disk_mb
    |   Ruang bebas minimal sebelum backup dijalankan.
    |
    |   Angka 500 MB bukan tebakan: backup penuh basis data saat ini berukuran
    |   sekitar 0,4 MB, jadi sepuluh backup yang dipertahankan memakan ~4 MB.
    |   500 MB memberi ruang lebih dari seratus kali lipat kebutuhan itu,
    |   sekaligus menyisakan tempat kerja untuk MySQL — yang juga menulis
    |   berkas sementara saat membaca tabel besar.
    |
    |   Kalau ruang di bawah angka ini, backup TIDAK dijalankan dan keadaannya
    |   dilaporkan apa adanya. Menjalankannya tetap berisiko menghasilkan
    |   berkas separuh jadi, dan berkas backup separuh jadi lebih berbahaya
    |   daripada tidak ada backup: ia terlihat seperti cadangan.
    |
    */

    'backup' => [
        'retensi' => (int) env('CAHAYAOS_BACKUP_RETENSI', 10),
        'min_disk_mb' => (int) env('CAHAYAOS_BACKUP_MIN_DISK_MB', 500),

        /*
        | folder_luar
        |   Folder DI LUAR disk ini untuk salinan cadangan — folder yang
        |   disinkronkan ke cloud, atau hard disk eksternal.
        |
        |   Cadangan lokal tersimpan di disk yang SAMA dengan databasenya.
        |   Kalau komputer ini rusak, hilang, atau kena ransomware, database
        |   dan seluruh cadangannya lenyap bersamaan — praktis tidak ada
        |   cadangan sama sekali.
        |
        |   DIBIARKAN KOSONG = fitur mati tanpa error, cadangan lokal tetap
        |   berjalan seperti biasa. Diisi lewat scripts/pasang-cadangan-luar.ps1,
        |   yang memeriksa dulu foldernya benar-benar bisa ditulis.
        |
        |   PERTIMBANGAN YANG BUKAN URUSAN TEKNIS:
        |   berkas cadangan memuat data pribadi peserta — nama, alamat, nomor
        |   telepon, dan omzet usaha. Menyalinnya ke folder yang disinkronkan
        |   ke layanan pihak ketiga berarti mengirim data itu ke luar lembaga.
        |   Keputusan itu ada pada pengelola program, bukan pada kode ini, dan
        |   karena itulah nilainya sengaja dibiarkan kosong.
        |
        | retensi_luar
        |   Berapa salinan yang dipertahankan di folder luar. Dipisahkan dari
        |   retensi lokal karena media luar biasanya punya ruang berbeda.
        */
        'folder_luar' => env('CAHAYAOS_BACKUP_FOLDER_LUAR'),
        'retensi_luar' => (int) env('CAHAYAOS_BACKUP_RETENSI_LUAR', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | CATATAN: 'aktivitas_lapangan' SUDAH DIHAPUS
    |--------------------------------------------------------------------------
    |
    | Dulu ada daftar bernama 'aktivitas_lapangan' di sini, lengkap dengan
    | keterangan bahwa ia "memisahkan kunjungan yang benar-benar menyentuh
    | peserta dari catatan administratif".
    |
    | Setelah ditelusuri, TIDAK ADA satu baris kode pun yang membacanya —
    | nol pemakaian di seluruh app/, resources/, dan tests/. Keterangan itu
    | menjanjikan perilaku yang tidak pernah terjadi, dan keterangan semacam
    | itu lebih menyesatkan daripada tidak ada keterangan sama sekali: ia
    | membuat pembacanya yakin sudah ada penyaring, padahal tidak.
    |
    | Keadaan yang sebenarnya: KPI "Pendampingan" menghitung SELURUH catatan
    | apa pun jenisnya, termasuk 'Lainnya'. Itu perilaku yang sekarang
    | dikunci test, bukan lagi kebetulan yang tidak tertulis. Kalau nanti
    | 'Lainnya' memang perlu dikecualikan, itu keputusan yang mengubah angka
    | KPI dan harus diputuskan lebih dulu, bukan diselipkan lewat config.
    |
    */

];
