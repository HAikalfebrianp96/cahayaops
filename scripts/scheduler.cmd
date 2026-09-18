@echo off
rem ===================================================================
rem  CAHAYAOS — pemanggil Laravel Scheduler
rem  Dipanggil Windows Task Scheduler setiap 1 menit lewat scheduler.vbs
rem  (yang menjalankannya tanpa jendela hitam berkedip).
rem
rem  Tugasnya cuma satu: menjalankan "php artisan schedule:run".
rem  Jadwal sebenarnya — backup harian 02:00 dan pembersihan berkas
rem  sementara — didefinisikan di aplikasi, di routes/console.php, BUKAN
rem  di sini. Jadi menambah atau mengubah jadwal cukup di kode, tanpa
rem  menyentuh Task Scheduler lagi.
rem ===================================================================

setlocal enabledelayedexpansion

rem Path proyek diselesaikan jadi ABSOLUT supaya nama foldernya bisa
rem dipakai sebagai penanda unik di bawah.
for %%I in ("%~dp0..") do set "PROYEK=%%~fI"
for %%I in ("%PROYEK%") do set "NAMA_PROYEK=%%~nxI"
set "LOG=%PROYEK%\storage\logs\scheduler-task.log"

rem --- Mencari php.exe -------------------------------------------------
rem Path yang dipakai saat task ini dipasang didahulukan. Kalau PHP
rem di-upgrade dan folder itu hilang, versi terbaru di C:\laragon\bin\php
rem dipakai sebagai cadangan supaya backup tidak diam-diam mati.
set "PHP=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"

if not exist "%PHP%" (
    set "PHP="
    for /f "delims=" %%D in ('dir /b /ad /o-n "C:\laragon\bin\php" 2^>nul') do (
        if not defined PHP if exist "C:\laragon\bin\php\%%D\php.exe" set "PHP=C:\laragon\bin\php\%%D\php.exe"
    )
)

if not defined PHP (
    >>"%LOG%" echo [%date% %time%] GAGAL: php.exe tidak ditemukan di C:\laragon\bin\php
    exit /b 1
)

rem --- Menjalankan scheduler -------------------------------------------
rem Nama berkas sementara HARUS unik per aplikasi: ada beberapa aplikasi
rem di mesin ini yang menjalankan skrip serupa SETIAP MENIT. Dengan satu
rem nama yang sama, keluaran satu aplikasi bisa ditimpa atau dihapus
rem aplikasi lain di detik yang sama — catatan backup jadi salah atau
rem hilang, tanpa pesan error apa pun.
set "KELUARAN=%TEMP%\cahayaos-scheduler-%NAMA_PROYEK%.txt"

cd /d "%PROYEK%"
"%PHP%" artisan schedule:run >"%KELUARAN%" 2>&1
set "KODE=%errorlevel%"

rem Menit biasa menghasilkan "No scheduled commands are ready to run."
rem Kalau itu yang muncul, TIDAK dicatat — kalau tidak, log akan terisi
rem 1.440 baris tak berguna setiap hari dan kejadian penting tenggelam.
findstr /i /c:"No scheduled commands are ready to run" "%KELUARAN%" >nul
if errorlevel 1 (
    >>"%LOG%" echo === %date% %time% ===
    type "%KELUARAN%" >>"%LOG%"
)

if not "%KODE%"=="0" (
    >>"%LOG%" echo [%date% %time%] schedule:run berhenti dengan kode %KODE%
)

del "%KELUARAN%" >nul 2>&1
exit /b 0
