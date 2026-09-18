# =====================================================================
#  CAHAYAOS - memasang Laravel Scheduler ke Windows Task Scheduler
#
#  =====================================================================
#  KENAPA SKRIP INI PERLU ADA
#  =====================================================================
#
#  routes/console.php sudah menjadwalkan backup harian pukul 02:00. Tapi
#  jadwal Laravel TIDAK berjalan sendiri: ia hanya bergerak kalau ada
#  yang memanggil "php artisan schedule:run" setiap menit.
#
#  Tanpa task ini, backup harian tidak pernah benar-benar jalan - dan itu
#  tidak menimbulkan error apa pun, karena memang tidak ada yang
#  dijalankan untuk gagal.
#
#  =====================================================================
#  ADMINISTRATOR TIDAK DIPERLUKAN
#  =====================================================================
#
#  Versi pertama skrip ini menolak berjalan tanpa Administrator. Ternyata
#  keliru: Windows mengizinkan pengguna biasa mendaftarkan task yang
#  berjalan atas namanya sendiri, dan itu justru yang kita inginkan -
#  task-nya memakai akun yang sama dengan yang memakai aplikasinya.
#
#  Elevasi hanya diperlukan kalau task ingin berjalan sebagai SYSTEM atau
#  saat tidak ada yang login. Lihat catatan "KETERBATASAN" di bawah.
#
#      powershell -ExecutionPolicy Bypass -File scripts\pasang-penjadwal.ps1
#
#  Membatalkan:
#
#      schtasks /delete /tn "CahayaOS Scheduler" /f
#
#  =====================================================================
#  KETERBATASAN YANG PERLU DIKETAHUI
#  =====================================================================
#
#  Task ini berjalan dengan mode "Interactive only": ia hanya bergerak
#  selama penggunanya masih login. Komputer yang terkunci tetap terhitung
#  login, jadi backup 02:00 tetap jalan - tapi komputer yang DIMATIKAN
#  pada malam hari akan melewatkan backup hari itu.
#
#  Kalau itu terjadi, "cahayaos:preflight" akan melaporkan cadangan yang
#  mulai basi. Menjalankannya sebagai SYSTEM (supaya tidak bergantung
#  login) memerlukan Administrator dan kredensial tersimpan.
# =====================================================================

$ErrorActionPreference = 'Stop'

$namaTask = 'CahayaOS Scheduler'
$vbs      = Join-Path $PSScriptRoot 'scheduler.vbs'

if (-not (Test-Path $vbs)) {
    Write-Error "scheduler.vbs tidak ditemukan di $vbs"
}

Write-Host "Task   : $namaTask"
Write-Host "Perintah: wscript.exe `"$vbs`""
Write-Host "Pengguna: $env:USERDOMAIN\$env:USERNAME"
Write-Host ''

if (schtasks /query /tn "$namaTask" 2>$null) {
    Write-Host 'Task dengan nama itu sudah ada - dihapus dulu supaya tidak dobel.'
    schtasks /delete /tn "$namaTask" /f | Out-Null
}

# /sc minute /mo 1 : setiap menit, sama seperti proyek lain di mesin ini.
# Jadwal SEBENARNYA tetap ditentukan aplikasi di routes/console.php, jadi
# mengubah jam backup cukup di kode - tanpa menyentuh Task Scheduler lagi.
schtasks /create `
    /tn "$namaTask" `
    /tr "wscript.exe `"$vbs`"" `
    /sc minute `
    /mo 1 `
    /f | Out-Null

if ($LASTEXITCODE -ne 0) {
    Write-Error @"
Pendaftaran task gagal (kode $LASTEXITCODE).

Kalau pesannya soal hak akses, jalankan ulang PowerShell sebagai
Administrator lalu ulangi perintah yang sama.
"@
}

Write-Host 'Terpasang.' -ForegroundColor Green
Write-Host ''
Write-Host 'Periksa:'
Write-Host "  schtasks /query /tn `"$namaTask`" /v /fo LIST"
Write-Host ''
Write-Host 'Jalankan sekali sekarang (tanpa menunggu jadwal):'
Write-Host "  schtasks /run /tn `"$namaTask`""
Write-Host ''
Write-Host 'Backup manual, memakai perintah yang sama dengan jadwalnya:'
Write-Host '  php artisan cahayaos:backup-otomatis'
Write-Host ''
Write-Host 'Memastikan cadangan benar-benar bisa dipulihkan:'
Write-Host '  php artisan cahayaos:verifikasi-cadangan'

# Status keluar ditegaskan. Tanpa baris ini PowerShell mewarisi kode dari
# perintah terakhir yang kebetulan dijalankan, dan skrip yang jelas-jelas
# berhasil bisa melapor gagal kepada pemanggilnya.
exit 0
