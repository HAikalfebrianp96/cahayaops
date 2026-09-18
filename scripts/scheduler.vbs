' ===================================================================
'  CAHAYAOS — peluncur senyap untuk Laravel Scheduler
'
'  Task Scheduler memanggil berkas ini, bukan php.exe langsung.
'  Alasannya: task berjalan SETIAP MENIT, dan memanggil php.exe langsung
'  memunculkan jendela hitam sekilas tiap menit sepanjang hari — sangat
'  mengganggu saat komputer dipakai bekerja.
'
'  Angka 0 pada .Run berarti "jalankan tanpa jendela".
'  False berarti "jangan tunggu selesai" — supaya Task Scheduler tidak
'  menganggap task-nya menggantung.
'
'  Pola ini disamakan dengan proyek lain di mesin ini (sailing-go,
'  sailing-supply, sailing-coffee-erp) supaya perilakunya seragam dan
'  tidak ada satu proyek pun yang perlu diingat secara khusus.
' ===================================================================

Dim sh, folder, perintah

Set sh = CreateObject("WScript.Shell")

folder = Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\"))
perintah = "cmd.exe /c """ & folder & "scheduler.cmd"""

sh.Run perintah, 0, False
