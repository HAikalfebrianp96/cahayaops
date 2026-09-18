cd C:\laragon\www\cahayaos
Write-Output "===== DAFTAR MODEL ====="
Get-ChildItem app\Models -Filter *.php | Select-Object Name