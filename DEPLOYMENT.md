# CahayaOS — Panduan Deployment Production

Dokumen ini untuk orang yang memasang CahayaOS di server sungguhan, bukan
untuk pengembang. Urutannya sengaja dibuat bisa diikuti dari atas ke bawah
tanpa perlu tahu isi kodenya.

> **Satu perintah yang memeriksa hampir semua isi dokumen ini:**
> ```
> php artisan cahayaos:preflight
> ```
> Jalankan setiap kali selesai memasang atau memperbarui. Perintah ini keluar
> dengan kode 1 kalau ada MASALAH, jadi aman dipakai di skrip deploy.

---

## 0. Yang TIDAK boleh dilakukan di server

Ini bukan basa-basi. Database ini memuat buku simpanan anggota koperasi dan
laporan omzet peserta yang dikumpulkan satu per satu selama berbulan-bulan.
Sebagian besarnya tidak ada salinannya di tempat lain.

| Jangan | Alasan |
|---|---|
| `php artisan migrate:fresh` | Menghapus SELURUH tabel lalu membuatnya lagi kosong. |
| `php artisan migrate:refresh` | Sama saja — seluruh data hilang. |
| `php artisan db:wipe` | Menghapus semua tabel. |
| `php artisan db:seed --force` di server | **Paling berbahaya.** Seeder memakai email yang SAMA dengan akun sungguhan: kata sandi Admin & Pimpinan berubah menjadi kata sandi contoh yang tertulis di dalam repositori, dan akun Ahmad/Siti yang sudah dihapus permanen hidup kembali. Sejak audit terakhir perintah ini ditolak otomatis saat `APP_ENV=production` — tapi jangan mengandalkan itu, jangan menjalankannya sama sekali. |
| `TRUNCATE` tabel apa pun | Tidak bisa dibatalkan. |
| Menyalin `.env` dari laptop pengembang | Nilainya untuk Laragon: debug menyala, URL localhost, database root tanpa sandi. |

Untuk memperbarui struktur database, satu-satunya perintah yang dipakai
adalah `php artisan migrate` (tanpa akhiran apa pun). Perintah itu hanya
menambah, tidak pernah mengosongkan.

---

## 1. Kebutuhan server

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.2+ (diuji di 8.3) | |
| MySQL / MariaDB | 5.7+ / 10.3+ | Fitur Backup memakai `SHOW CREATE TABLE`, khas MySQL. |
| Composer | 2.x | |
| Node.js | 20+ (dengan npm) | Hanya dibutuhkan **saat memasang/memperbarui**, untuk membangun tampilan. Tidak perlu berjalan sebagai layanan. |
| Web server | Nginx atau Apache | Document root **wajib** ke `public/`, bukan ke folder proyek. |

Ekstensi PHP: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `json`, `fileinfo`, `zip` (untuk ekspor laporan), `gd` atau
`imagick` (untuk gambar di PDF).

**Document root.** Kalau document root diarahkan ke folder proyek dan bukan
ke `public/`, berkas `.env` bisa diunduh siapa saja lewat browser — beserta
kata sandi database di dalamnya. Ini kesalahan pemasangan Laravel yang paling
sering terjadi dan paling mahal akibatnya.

---

## 2. Pemasangan pertama kali

```bash
# 1. Ambil kode ke server
cd /var/www
git clone <repo> cahayaos
cd cahayaos

# 2. Dependency PHP — tanpa paket pengembangan, autoloader dioptimalkan
composer install --no-dev --optimize-autoloader

# 3. Bangun tampilan — WAJIB, bukan langkah opsional
npm ci
npm run build

# 4. Siapkan konfigurasi
cp .env.production.example .env
nano .env            # isi semua yang bertanda GANTI
php artisan key:generate

# 5. Buat struktur database (menambah tabel, tidak menghapus apa pun)
php artisan migrate --force

# 6. Tautan storage publik (logo, dokumen legalitas yang memang publik)
php artisan storage:link

# 7. Periksa sebelum dibuka ke pengguna
php artisan cahayaos:preflight
```

`--force` pada langkah 5 hanya berarti "jangan tanya konfirmasi karena ini
bukan terminal interaktif". Perintahnya tetap `migrate`, tetap aman.

### Kenapa `npm run build` tidak boleh dilewat

Folder hasilnya, `public/build/`, **tidak ikut masuk Git** (lihat
`.gitignore`), jadi `git clone` dan `git pull` tidak pernah membawanya.
Satu-satunya yang menghasilkannya adalah perintah itu, di server ini.

Akibat melewatkannya bukan tampilan yang polos, melainkan **seluruh halaman
gagal terbuka dengan error 500 — termasuk halaman login**, karena Laravel
mencari berkas manifest yang tidak ada. `php artisan cahayaos:preflight`
memeriksanya dan menolak dengan pesan yang menyebut perintahnya.

### Akun Admin pertama

Preflight akan menolak kalau belum ada Admin aktif. Buat lewat tinker:

```bash
php artisan tinker
>>> \App\Models\User::create([
...   'name' => 'Nama Admin',
...   'email' => 'admin@domain-anda.id',
...   'password' => bcrypt('KATA-SANDI-YANG-KUAT'),
...   'role' => 'admin',
...   'is_active' => true,
... ]);
```

Ganti kata sandinya lewat aplikasi setelah login pertama.

---

## 3. Hak akses folder

Hanya dua folder yang perlu bisa ditulis oleh web server:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Sisanya cukup dapat dibaca. **Jangan** `chmod -R 777` — itu membuat berkas
kode bisa ditulis ulang oleh proses mana pun di server.

---

## 4. Cron — WAJIB, bukan opsional

Tanpa satu baris ini, **tidak ada backup otomatis yang berjalan**:

```cron
* * * * * cd /var/www/cahayaos && php artisan schedule:run >> /dev/null 2>&1
```

Pasang dengan `crontab -e -u www-data`. Setelah itu pastikan jadwalnya
terbaca:

```bash
php artisan schedule:list
```

Harus muncul dua baris:

| Jadwal | Perintah | Gunanya |
|---|---|---|
| 02:00 tiap hari | `cahayaos:backup-otomatis` | Backup database rutin. |
| 03:00 tiap hari | `cahayaos:bersihkan-berkas-sementara` | Membuang sisa berkas ekspor. |

### Windows / Laragon

Cron tidak ada di Windows. Padanannya Task Scheduler, dan tanpa itu
**tidak ada backup otomatis yang berjalan** — persis seperti tanpa cron.

Pasang sekali, tidak perlu Administrator:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\pasang-penjadwal.ps1
```

Skrip itu mendaftarkan satu task bernama **`CahayaOS Scheduler`** yang
menjalankan `scripts\scheduler.vbs` setiap menit. Berkas .vbs memanggil
`scheduler.cmd`, yang menjalankan `php artisan schedule:run` tanpa
memunculkan jendela hitam berkedip tiap menit.

Jadwal sebenarnya tetap ditentukan aplikasi di `routes/console.php`, jadi
mengubah jam backup cukup di kode — Task Scheduler tidak perlu disentuh lagi.

| Perlu tahu | |
|---|---|
| Nama task | `CahayaOS Scheduler` |
| Dijalankan sebagai | pengguna Windows yang memasangnya |
| Mode | *Interactive only* — hanya berjalan selama pengguna itu login |
| Periksa | `schtasks /query /tn "CahayaOS Scheduler" /v /fo LIST` |
| Jalankan sekali | `schtasks /run /tn "CahayaOS Scheduler"` |
| Cabut | `schtasks /delete /tn "CahayaOS Scheduler" /f` |

**Keterbatasan yang perlu diketahui:** komputer yang terkunci tetap
terhitung login, jadi backup 02:00 tetap jalan. Komputer yang DIMATIKAN
pada malam hari akan melewatkan backup hari itu — dan `cahayaos:preflight`
akan melaporkan cadangan yang mulai basi. Menjalankannya sebagai SYSTEM
(supaya tidak bergantung login) memerlukan Administrator.

---

## 5. Backup dan pemulihan

### Di mana backup disimpan

`storage/app/backups/`. Sepuluh backup rutin terbaru disimpan; yang lebih
lama dibuang otomatis. Berkas `pre-restore-backup-*.sql` (dibuat otomatis
tepat sebelum setiap Restore) **tidak pernah** ikut dibuang — itu jaring
pengaman, bukan backup rutin.

### Yang masih harus dikerjakan manusia

Backup otomatis di atas tersimpan **di server yang sama dengan
databasenya**. Kalau yang gagal adalah servernya sendiri — disk rusak,
akun hosting hilang, ruangannya kebanjiran — backup itu ikut hilang bersama
data aslinya.

Jadwalkan penyalinan berkalanya ke luar server, minimal seminggu sekali:

```bash
rsync -avz /var/www/cahayaos/storage/app/backups/ user@server-lain:/cadangan/cahayaos/
```

Salinan itu berisi seluruh data anggota dan peserta. Simpan di tempat yang
akses dan enkripsinya setara dengan servernya sendiri.

### Cara memulihkan

Lewat aplikasi: **Pengaturan → Backup & Restore → Restore Database**. Unggah
berkas `.sql`, lalu ketik ulang persis kalimat `RESTORE DATABASE`. Sistem
membuat backup pengaman otomatis lebih dulu sebelum menjalankan apa pun.

**Catatan jujur soal Restore:** MySQL tidak mendukung `CREATE`/`DROP TABLE`
di dalam transaction. Artinya kalau restore gagal di tengah jalan, database
bisa tertinggal dalam keadaan setengah jadi, dan tidak ada rollback otomatis
yang bisa menyelamatkannya. Jaring pengaman satu-satunya adalah backup
otomatis sebelum restore itu. Jangan pernah menjalankan Restore tanpa
memastikan berkas backup pengamannya benar-benar tersimpan.

---

## 6. Memperbarui aplikasi yang sudah jalan

```bash
cd /var/www/cahayaos
php artisan down                              # halaman "sedang perbaikan"

php artisan cahayaos:backup-otomatis          # backup DULU, sebelum apa pun
git pull
composer install --no-dev --optimize-autoloader
npm ci                                        # WAJIB — lihat catatan di bagian 2
npm run build
php artisan migrate --force                   # menambah, tidak menghapus

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up
php artisan cahayaos:preflight
```

**Setiap kali `.env` diubah, `php artisan config:cache` WAJIB dijalankan
ulang.** Selama konfigurasi masih ter-cache, perubahan di `.env` tidak
dibaca sama sekali — termasuk perubahan yang menyangkut keamanan. Ini
sumber kebingungan yang sangat umum: berkasnya sudah benar, aplikasinya
tetap memakai nilai lama.

### Kalau pembaruan bermasalah

```bash
php artisan down
git reset --hard <commit-sebelumnya>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Mengembalikan kode **tidak** mengembalikan database. Kalau migration baru
sudah terlanjur jalan dan strukturnya perlu dikembalikan juga, pulihkan dari
backup yang dibuat di awal langkah pembaruan.

---

## 7. Pemeriksaan setelah go-live

Buka lewat browser sungguhan, bukan hanya lewat terminal:

- [ ] Halaman login terbuka dan **tampilannya benar** (kalau polos atau 500: `npm run build` belum dijalankan)
- [ ] Matikan internet kantor sebentar, muat ulang satu halaman → tampilannya tetap utuh
- [ ] Login sebagai Admin berhasil
- [ ] Dashboard memuat angka, bukan error
- [ ] Buka satu Peserta → detailnya tampil lengkap
- [ ] Unduh satu laporan PDF → **nama bulannya berbahasa Indonesia** ("Agustus", bukan "August")
- [ ] Halaman Cek Simpanan (tanpa login) → masukkan nomor anggota + PIN, total simpanan tampil
- [ ] Kirim satu tautan laporan omzet ke WhatsApp → tautannya memakai domain sungguhan, bukan `localhost`
- [ ] Unggah satu bukti omzet → berkasnya **tidak** bisa dibuka lewat URL langsung tanpa login
- [ ] Buka `/up` → menampilkan status sehat
- [ ] Coba buka `namadomain/.env` di browser → **harus 404**, bukan menampilkan isi berkas

---

## 8. Hal yang diketahui dan belum dibereskan

Bagian ini sengaja ada. Sistem yang menyembunyikan keterbatasannya akan
mengejutkan orang di saat yang paling tidak tepat.

### ~~Tailwind masih diambil dari CDN~~ — SUDAH DIBERESKAN

Sebelumnya kelima halaman utama memuat tampilannya lewat
`<script src="https://cdn.tailwindcss.com">`, yang oleh pembuat Tailwind
sendiri dinyatakan tidak untuk production.

Sekarang tampilan dibangun dengan Vite: satu berkas CSS berukuran ~64 KB
(~11 KB setelah gzip) menggantikan ~300 KB JavaScript yang sebelumnya harus
diunduh dari pihak ketiga LALU menyusun CSS-nya di dalam browser pengguna.
Aplikasi tidak lagi memerlukan sambungan ke server luar untuk bisa tampil.

Konsekuensinya satu, dan sudah dijelaskan di bagian 2: `npm run build`
menjadi langkah wajib setiap kali memasang atau memperbarui.

Catatan kecil yang sengaja dibiarkan: rangkaian huruf menyebut **Inter**
lebih dulu, tetapi Inter tidak pernah dimuat dari mana pun — jadi yang
sebenarnya dipakai selama ini adalah huruf bawaan sistem. Itu tidak diubah:
memuat Inter berarti menambah lagi ketergantungan ke server luar yang baru
saja dihilangkan. Kalau suatu saat Inter memang diinginkan, pasang berkas
hurufnya sendiri di dalam proyek, jangan tarik dari Google Fonts.

### Berkas ekspor yang tertinggal

Ekspor "Laporan Lengkap" membuat berkas `.zip` sementara dan menghapusnya
setelah terkirim. Kalau unduhan dibatalkan di tengah jalan, berkasnya
tertinggal. Perintah terjadwal pada pukul 03:00 menyapunya setiap hari
(hanya yang berumur lebih dari 24 jam, supaya unduhan yang sedang berjalan
tidak terganggu). Untuk membersihkan segera:

```bash
php artisan cahayaos:bersihkan-berkas-sementara --lihat-saja   # periksa dulu
php artisan cahayaos:bersihkan-berkas-sementara                # baru hapus
```

### Seeder: hanya untuk lingkungan pengembangan

Seluruh berkas di `database/seeders/` berisi akun dan data contoh, dan
beberapa memakai alamat email yang sama dengan akun sungguhan
(`admin@ybmpln.id`, `pimpinan@ybmpln.id`). Semuanya kini menolak berjalan
saat `APP_ENV=production`, dan tidak satu pun lagi menimpa kata sandi akun
yang sudah ada.

Pintu daruratnya ada, untuk server baru yang benar-benar masih kosong:

```bash
SEEDER_BOLEH_PRODUCTION=true php artisan db:seed --force
```

Hapus baris itu dari `.env` segera setelah dipakai — `cahayaos:preflight`
akan menolak kalau tertinggal menyala.

### Percobaan masuk dibatasi

Halaman login staf dibatasi **5 percobaan per menit**, dihitung per
kombinasi email + alamat IP. Pemilihan kunci itu disengaja: seluruh staf
bekerja dari satu kantor dengan satu IP, jadi pembatas per-IP saja akan
membuat satu orang yang lupa kata sandinya mengunci semua orang — termasuk
Admin yang seharusnya menolong. Halaman Cek Simpanan publik punya
pembatasnya sendiri (5 per menit).

### Email belum dipakai

`MAIL_MAILER=log`. CahayaOS saat ini tidak mengirim email apa pun — reset
kata sandi dilakukan Admin lewat Manajemen User. Biarkan begitu sampai ada
kebutuhan nyata; memasang kredensial SMTP yang tidak pernah dipakai justru
menambah rahasia yang harus dijaga tanpa manfaat apa pun.

---

## 9. Kalau ada yang tidak beres

| Gejala | Kemungkinan penyebab |
|---|---|
| Halaman putih kosong | `storage/` tidak bisa ditulis; cek `storage/logs/laravel.log`. |
| "500 Server Error" di semua halaman | `APP_KEY` kosong → `php artisan key:generate`. |
| Perubahan `.env` tidak terasa | Konfigurasi masih ter-cache → `php artisan config:cache`. |
| Semua halaman 500, termasuk login | `npm run build` belum dijalankan di server (lihat bagian 2). |
| Tampilan polos tanpa warna | Isi `public/build/assets/` hilang sebagian — bangun ulang. |
| Tautan WhatsApp mengarah ke localhost | `APP_URL` salah → perbaiki, lalu `config:cache`. |
| Nama bulan berbahasa Inggris di PDF | `APP_LOCALE` bukan `id` → perbaiki, lalu `config:cache`. |
| Backup tidak pernah muncul | Cron belum terpasang → cek bagian 4 dan `schedule:list`. |
| Halaman 404 di semua route kecuali beranda | Konfigurasi rewrite web server; document root harus ke `public/`. |

Log aplikasi ada di `storage/logs/`. Dengan `LOG_STACK=daily`, berkasnya
terpisah per hari dan yang lama dibuang otomatis.
