# =====================================================================
#  CAHAYAOS - memasang salinan cadangan ke LUAR komputer ini
# =====================================================================
#
#  Cadangan CahayaOS tersimpan di disk yang SAMA dengan databasenya.
#  Kalau komputer ini rusak, hilang, atau kena ransomware, database dan
#  seluruh cadangannya lenyap bersamaan - praktis sama dengan tidak
#  pernah punya cadangan.
#
#  Skrip ini menghubungkan CahayaOS ke folder di luar: folder yang
#  disinkronkan ke cloud (OneDrive, Google Drive) atau hard disk
#  eksternal.
#
#  ---------------------------------------------------------------------
#  BACA INI SEBELUM MENJALANKAN
#  ---------------------------------------------------------------------
#
#  Berkas cadangan memuat DATA PRIBADI PESERTA: nama, alamat, nomor
#  telepon, dan omzet usaha mereka.
#
#  Menyalinnya ke folder yang disinkronkan ke layanan pihak ketiga
#  berarti mengirim data itu ke luar lembaga. Untuk hard disk eksternal,
#  datanya tetap di tangan Anda - tapi disk itu jadi perlu dijaga
#  seperti menjaga lemari arsip peserta.
#
#  Keputusan itu ada pada pengelola program. Skrip ini tidak memilihkan
#  tujuan apa pun untuk Anda.
#
#  ---------------------------------------------------------------------
#  CARA PAKAI
#  ---------------------------------------------------------------------
#
#      powershell -ExecutionPolicy Bypass -File scripts\pasang-cadangan-luar.ps1 -Folder "D:\Cadangan CahayaOS"
#
#  Yang dilakukan:
#    1. Memastikan folder tujuan ADA dan benar-benar bisa ditulis.
#    2. Mengisi CAHAYAOS_BACKUP_FOLDER_LUAR di .env.
#    3. Menjalankan satu penyalinan sungguhan sebagai bukti.
#
#  Tidak ada yang dihapus. Kalau ada langkah yang gagal, skrip berhenti
#  dan .env dikembalikan seperti semula.
#
#  Mematikan kembali: kosongkan CAHAYAOS_BACKUP_FOLDER_LUAR di .env.
# =====================================================================

param(
    [Parameter(Mandatory = $true)]
    [string]$Folder
)

$ErrorActionPreference = 'Stop'

$proyek = Split-Path -Parent $PSScriptRoot
$env_ = Join-Path $proyek '.env'

Write-Host ''
Write-Host "Proyek : $proyek"
Write-Host "Tujuan : $Folder"
Write-Host ''

# --- 1. Folder harus ada dan bisa ditulis -----------------------------
if (-not (Test-Path -LiteralPath $Folder)) {
    Write-Error "Folder tujuan tidak ditemukan: $Folder`n`nKalau ini disk eksternal atau folder cloud, pastikan sudah terpasang dan tersinkron."
}

$uji = Join-Path $Folder ('.cahayaos-uji-tulis-' + [guid]::NewGuid().ToString('N') + '.tmp')

try {
    'uji' | Set-Content -LiteralPath $uji -Encoding utf8
    Remove-Item -LiteralPath $uji -Force
} catch {
    Write-Error "Folder tujuan tidak bisa ditulis: $Folder`n$($_.Exception.Message)"
}

Write-Host '  [ok] folder ada dan bisa ditulis'

# --- 2. Menulis .env --------------------------------------------------
if (-not (Test-Path -LiteralPath $env_)) {
    Write-Error ".env tidak ditemukan di $env_"
}

$cadanganEnv = Get-Content -LiteralPath $env_ -Raw

try {
    # KUTIP TUNGGAL, bukan ganda.
    #
    # Di dalam kutip ganda, dotenv memperlakukan "\" sebagai awal escape
    # sequence - sehingga path Windows seperti C:\Users\... membuat SELURUH
    # berkas .env gagal diurai, bukan cuma baris ini. Aplikasinya lalu mati
    # total, bukan sekadar kehilangan satu pengaturan.
    #
    # Kutip tunggal dibaca apa adanya oleh dotenv.
    $baris = "CAHAYAOS_BACKUP_FOLDER_LUAR='$Folder'"

    if ($cadanganEnv -match '(?m)^CAHAYAOS_BACKUP_FOLDER_LUAR=.*$') {
        $baru = $cadanganEnv -replace '(?m)^CAHAYAOS_BACKUP_FOLDER_LUAR=.*$', $baris
    } else {
        $baru = $cadanganEnv.TrimEnd() + "`r`n`r`n# Salinan cadangan ke luar disk ini - lihat config/cahayaos.php`r`n" + $baris + "`r`n"
    }

    Set-Content -LiteralPath $env_ -Value $baru -Encoding utf8 -NoNewline
    Write-Host '  [ok] .env diperbarui'

    # --- 3. Bukti: satu penyalihan sungguhan --------------------------
    Push-Location $proyek
    try {
        & php artisan config:clear | Out-Null
        $keluaran = & php artisan cahayaos:salin-cadangan-luar 2>&1
        $kode = $LASTEXITCODE
    } finally {
        Pop-Location
    }

    Write-Host ''
    $keluaran | ForEach-Object { "  $_" }

    if ($kode -ne 0) {
        throw "Penyalinan percobaan gagal (kode $kode)."
    }
} catch {
    # .env dikembalikan apa adanya: setengah terkonfigurasi lebih buruk
    # daripada tidak terkonfigurasi sama sekali.
    Set-Content -LiteralPath $env_ -Value $cadanganEnv -Encoding utf8 -NoNewline
    Push-Location $proyek
    try { & php artisan config:clear | Out-Null } finally { Pop-Location }

    Write-Error "Gagal: $($_.Exception.Message)`n`n.env sudah dikembalikan seperti semula."
}

Write-Host ''
Write-Host 'Terpasang.' -ForegroundColor Green
Write-Host ''
Write-Host 'Mulai sekarang satu salinan dikirim ke folder itu setiap hari pukul 02:30,'
Write-Host 'setengah jam setelah cadangan lokal pukul 02:00.'
Write-Host ''
Write-Host 'Menyalin sekarang tanpa menunggu jadwal:'
Write-Host '  php artisan cahayaos:salin-cadangan-luar'
Write-Host ''
Write-Host 'Mematikan kembali: kosongkan CAHAYAOS_BACKUP_FOLDER_LUAR di .env.'

exit 0
