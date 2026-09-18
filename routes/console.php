<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| TUGAS TERJADWAL
|--------------------------------------------------------------------------
|
| Sebelum ini TIDAK ADA satu pun tugas terjadwal. Selama aplikasi dijalankan
| di laptop pengembang hal itu tidak terasa; begitu berjalan di server tanpa
| penunggu, artinya tidak ada yang membuat cadangan dan tidak ada yang
| membereskan sisa berkas — sampai ada yang menyadarinya, biasanya terlambat.
|
| SELURUH JADWAL DI SINI TIDAK JALAN dengan sendirinya. Server WAJIB punya
| satu baris cron yang memanggil scheduler Laravel setiap menit:
|
|     * * * * * cd /path/ke/cahayaos && php artisan schedule:run >> /dev/null 2>&1
|
| Tanpa baris itu, jadwal di bawah hanyalah niat. Langkah pemasangannya ada
| di DEPLOYMENT.md, dan `php artisan schedule:list` memperlihatkan apakah
| jadwalnya benar-benar terbaca.
*/

// Backup harian dini hari — jam paling sepi, saat tidak ada Admin yang
// sedang mencatat simpanan atau peserta yang sedang melapor omzet.
// withoutOverlapping(): kalau database sudah besar dan backup kemarin belum
// selesai, jangan menumpuk proses kedua di atasnya.
Schedule::command('cahayaos:backup-otomatis')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        // Kegagalan sudah dicatat ke Activity Log oleh perintahnya sendiri;
        // baris ini memastikan gagalnya juga terlihat di log server.
        logger()->error('Jadwal backup harian CahayaOS gagal dijalankan.');
    });

/*
|--------------------------------------------------------------------------
| Salinan cadangan ke LUAR disk ini
|--------------------------------------------------------------------------
|
| Cadangan di atas tersimpan di disk yang SAMA dengan databasenya. Kalau
| komputer ini rusak, hilang, atau kena ransomware, database dan seluruh
| cadangannya lenyap bersamaan — praktis tidak ada cadangan sama sekali.
|
| Jadwal ini menyalin satu cadangan per hari ke folder di luar: folder yang
| disinkronkan ke cloud, atau hard disk eksternal. Jalannya 02:30, setengah
| jam setelah cadangan lokal, supaya keduanya tidak pernah berebut disk.
|
| HANYA AKTIF kalau cahayaos.backup.folder_luar diisi. Dibiarkan kosong =
| fitur mati tanpa error, dan cadangan lokal tetap berjalan seperti biasa.
| Lihat pertimbangan kerahasiaan datanya di config/cahayaos.php.
|
*/
if (filled(config('cahayaos.backup.folder_luar'))) {
    Schedule::command('cahayaos:salin-cadangan-luar')
        ->dailyAt('02:30')
        ->withoutOverlapping()
        ->onFailure(function () {
            logger()->error('Salinan cadangan luar CahayaOS gagal — periksa apakah folder tujuannya masih ada dan bisa ditulis.');
        });
}

// Sisa berkas ekspor yang unduhannya tidak selesai. Dijalankan setelah
// backup supaya keduanya tidak berebut disk pada saat yang sama, dan dengan
// batas 24 jam supaya unduhan yang sedang berjalan tidak pernah terganggu.
Schedule::command('cahayaos:bersihkan-berkas-sementara --umur=24')
    ->dailyAt('03:00');
