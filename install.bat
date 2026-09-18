@echo off
setlocal

set TARGET=C:\laragon\www\cahayaos

echo ============================================
echo   INSTALL - Pengaturan Sistem (Round 2)
echo   6 Temuan Audit: Health, Security Log,
echo   Restore Hardening, Filter, Info, Retention
echo   Target: %TARGET%
echo ============================================
echo.
echo   CATATAN: Round ini TIDAK ada perubahan route.
echo   routes\web.php TIDAK PERLU diedit lagi.
echo.

echo [1/9] Copy ActivityLog.php...
xcopy /Y "app\Models\ActivityLog.php" "%TARGET%\app\Models\"

echo [2/9] Copy AppServiceProvider.php...
xcopy /Y "app\Providers\AppServiceProvider.php" "%TARGET%\app\Providers\"

echo [3/9] Copy LoginController.php...
xcopy /Y "app\Http\Controllers\Auth\LoginController.php" "%TARGET%\app\Http\Controllers\Auth\"

echo [4/9] Copy AccountController.php...
xcopy /Y "app\Http\Controllers\AccountController.php" "%TARGET%\app\Http\Controllers\"

echo [5/9] Copy PendampingController.php...
xcopy /Y "app\Http\Controllers\PendampingController.php" "%TARGET%\app\Http\Controllers\"

echo [6/9] Copy SystemSettingsController.php...
xcopy /Y "app\Http\Controllers\SystemSettingsController.php" "%TARGET%\app\Http\Controllers\"

echo [7/9] Copy index.blade.php...
xcopy /Y "resources\views\pengaturan-sistem\index.blade.php" "%TARGET%\resources\views\pengaturan-sistem\"

echo [8/9] Copy restore.blade.php...
xcopy /Y "resources\views\pengaturan-sistem\restore.blade.php" "%TARGET%\resources\views\pengaturan-sistem\"

echo [9/9] Copy test PengaturanSistemTest.php...
xcopy /Y "tests\Feature\PengaturanSistemTest.php" "%TARGET%\tests\Feature\"

echo.
echo ============================================
echo   SELESAI COPY FILE.
echo.
echo   LANGKAH SELANJUTNYA:
echo   1. php artisan optimize:clear
echo   2. php artisan route:list
echo   3. php artisan test
echo   4. Buka halaman Pengaturan Sistem, cek:
echo      - System Health ^& System Information
echo        sudah jadi 2 card terpisah
echo      - Filter Log Aktivitas (User, Tanggal)
echo      - Halaman Restore: teks konfirmasi baru
echo        "RESTORE DATABASE", tombol disabled
echo        sampai teks diketik persis, ada info
echo        Database Target
echo   5. PENTING: kalimat konfirmasi restore GANTI
echo      jadi "RESTORE DATABASE" (bukan lagi
echo      "SAYA PAHAM RISIKO RESTORE")
echo ============================================
echo.
pause
