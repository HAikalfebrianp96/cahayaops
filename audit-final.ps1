cd C:\laragon\www\cahayaos

Write-Output "===== ISI FOLDER VIEWS pengaturan-sistem ====="
Get-ChildItem -Recurse resources\views\pengaturan-sistem

Write-Output ""
Write-Output "===== ISI SETIAP FILE VIEW pengaturan-sistem ====="
Get-ChildItem -Recurse resources\views\pengaturan-sistem -Filter *.blade.php | ForEach-Object {
    Write-Output "--- $($_.FullName) ---"
    Get-Content $_.FullName
    Write-Output ""
}

Write-Output "===== STATUS MIGRATION (mana yang sudah/belum jalan) ====="
php artisan migrate:status

Write-Output "===== CEK ROUTE LIST SAAT INI UNTUK pengaturan-sistem ====="
php artisan route:list --name=pengaturan-sistem

Write-Output "===== CEK APAKAH TABEL activity_logs SUDAH ADA DI DATABASE ====="
php artisan tinker --execute="echo Schema::hasTable('activity_logs') ? 'ADA' : 'BELUM ADA';"